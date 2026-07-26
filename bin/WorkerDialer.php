<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */
namespace Modules\ModuleAutoDialerManage\bin;
require_once 'Globals.php';

use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\System\Processes;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerBase;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleAutoDialerManage\Lib\AutoDialerConf;
use Modules\ModuleAutoDialerManage\Lib\AutoDialerMain;
use Modules\ModuleAutoDialerManage\Lib\Logger;
use Modules\ModuleAutoDialerManage\Models\Blacklist;
use Modules\ModuleAutoDialerManage\Models\TaskResults;
use Modules\ModuleAutoDialerManage\Models\Tasks;

class WorkerDialer extends WorkerBase
{
    private Logger $logger;
    // ID обработанных TaskResults для предотвращения дублирования call-файлов.
    // Очищается когда getSliceTask() возвращает пустой массив (все звонки обработаны).
    private array $processedIds = [];

    /**
     * Handles the received signal.
     *
     * @param int $signal The signal to handle.
     *
     * @return void
     */
    public function signalHandler(int $signal): void
    {
        parent::signalHandler($signal);
        $title = cli_get_process_title();
        if (strncmp($title, 'SHUTDOWN_', 9) !== 0) {
            cli_set_process_title('SHUTDOWN_' . $title);
        }
    }

    /**
     * Старт работы.
     *
     * @param $argv
     */
    public function start($argv):void
    {
        $this->logger   = new Logger('WorkerDialer', 'ModuleAutoDialerManage');
        $this->logger->writeInfo('Starting...');
        $beanstalk      = new BeanstalkClient(self::class);
        $beanstalk->subscribe(self::class, [$this, 'onEvents']);
        $beanstalk->subscribe($this->makePingTubeName(self::class), [$this, 'pingCallBack']);
        // Bit Dream IT extension: track campaign states to detect transitions
        // and fire webhooks. Maps task_id => last_known_state (int).
        // Two event types fire:
        //   - 'campaign.completed'    : state transitions to closed (1)
        //   - 'campaign.state_changed': state transitions between open/paused/closed
        $this->lastKnownStates = [];
        $this->webhookFired    = [];
        while ($this->needRestart === false){
            // Ожидаем таймаут, выполняем внешние команды.
            $beanstalk->wait(1);
            $slice    = ConnectorDB::invoke('getSliceTask');
            if(empty($slice)){
                $this->processedIds = [];
                continue;
            }
            $statuses = AutoDialerMain::getCacheData('statuses');
            $queues   = AutoDialerMain::getCacheData('queues');
            foreach ($slice as $taskData){
                $trId = (int)($taskData['id'] ?? 0);
                if(isset($this->processedIds[$trId])){
                    // Call-файл для этой строки уже создан, ждём обновления состояния в БД.
                    continue;
                }
                if(empty($taskData['phone'])){
                    $this->logger->writeInfo(['action' => 'dialer', 'task' => $taskData['taskId'], 'message' => 'No next phone']);
                    // По задаче пока все номера отложены. Звонить нелья.
                    continue;
                }
                // Bit Dream IT extension: DNC (Do-Not-Call) blacklist filter.
                // Skip numbers present in m_ModuleAutoDialerManage_Blacklist.
                $normalizedPhone = preg_replace('/\D/', '', (string)$taskData['phone']);
                try {
                    $blacklisted = Blacklist::findFirst([
                        'number = :number:',
                        'bind' => ['number' => $normalizedPhone]
                    ]);
                    if ($blacklisted !== null) {
                        $this->logger->writeInfo([
                            'action' => 'dialer',
                            'task' => $taskData['taskId'],
                            'message' => "Skipping blacklisted number: {$normalizedPhone} (reason: {$blacklisted->reason})"
                        ]);
                        // Mark the row as dialed-but-skipped so the worker
                        // doesn't keep picking it up.
                        ConnectorDB::invoke('saveStateData', [
                            ConnectorDB::EVENT_END_CALL,
                            $normalizedPhone,
                            $taskData['taskId'],
                            ['filename' => '', 'skip_reason' => 'dnc']
                        ], false);
                        $this->processedIds[$trId] = true;
                        continue;
                    }
                } catch (\Throwable $e) {
                    // Blacklist table might not exist yet (pre-migration).
                    // Fail open: dial anyway.
                }

                // Bit Dream IT extension: schedule (business hours) check.
                // Verify today's weekday is in scheduleDays. Empty = all days.
                $scheduleDays = (string)($taskData['scheduleDays'] ?? '');
                if ($scheduleDays !== '') {
                    $todayIso = (int)date('N'); // 1=Mon ... 7=Sun
                    $allowed = array_map('intval', explode(',', $scheduleDays));
                    if (!in_array($todayIso, $allowed, true)) {
                        $this->logger->writeInfo([
                            'action' => 'dialer',
                            'task' => $taskData['taskId'],
                            'message' => "Skipping: today (ISO weekday {$todayIso}) not in scheduleDays ({$scheduleDays})"
                        ]);
                        continue;
                    }
                }

                if((int)$taskData['maxCountChannels'] <= (int)$taskData['in_progress']){
                    // Превышено максимально число каналов для задачи.
                    $this->logger->writeInfo(['action' => 'dialer', 'task' => $taskData['taskId'], 'message' => "maxCountChannels({$taskData['maxCountChannels']}) <= in_progress({$taskData['in_progress']})"]);
                    continue;
                }
                if($taskData['innerNumType'] === Tasks::TYPE_INNER_NUM_EXTENSION && $statuses[$taskData['innerNum']] !== WorkerAMI::STATE_IDLE){
                    // Внутренний номер занят.
                    $this->logger->writeInfo(['action' => 'dialer', 'task' => $taskData['taskId'], 'message' => "Number: $taskData[innerNum], State: ({$statuses[$taskData['innerNum']]}) is BUSY"]);
                    continue;
                }
                if(empty(trim($taskData['innerNum'] ?? ''))){
                    $this->logger->writeInfo(['action' => 'dialer', 'task' => $taskData['taskId'], 'message' => "Skipping: innerNum is empty"]);
                    continue;
                }
                $this->logger->writeInfo(['action' => 'dialer', 'task' => $taskData['taskId'], 'message' => "Create callfile. Phone ({$taskData['phone']}), InnerNum ({$taskData['innerNum']})"]);
                $this->createCallFile($taskData, $queues);
                $this->processedIds[$trId] = true;
                usleep(200000);
            }
            // Bit Dream IT extension: fire webhooks for state transitions
            // (both 'campaign.state_changed' and 'campaign.completed').
            $this->fireWebhooks();
            $this->logger->rotate();
        }
    }

    /**
     * Bit Dream IT extension: fires callbackUrl webhooks for state transitions.
     *
     * Two event types:
     *
     *   1. 'campaign.state_changed' — fires whenever a campaign's state
     *      transitions between open(0) / paused(2) / closed(1). Triggered
     *      by both worker state changes AND user-driven changes via the UI/API.
     *      Payload: {event, task_id, name, crm_id, old_state, new_state,
     *                old_state_label, new_state_label, changed_at}
     *
     *   2. 'campaign.completed' — fires exactly once when a campaign
     *      transitions to closed(1). Includes summary stats (total, answered,
     *      failed). Tracked in $webhookFired to ensure exactly-once delivery.
     *      Payload: {event, task_id, name, crm_id, total, answered, failed,
     *                completed_at}
     *
     * Both events go to the same callbackUrl configured on the campaign.
     */
    private function fireWebhooks(): void
    {
        try {
            // Find all campaigns with a non-empty callbackUrl.
            $tasks = Tasks::find([
                'conditions' => 'callbackUrl <> :empty:',
                'bind' => ['empty' => '']
            ]);
            foreach ($tasks as $task) {
                $taskId = (int)$task->id;
                $currentState = (int)$task->state;
                $lastState = $this->lastKnownStates[$taskId] ?? null;

                // First time we see this campaign — seed state, no event.
                if ($lastState === null) {
                    $this->lastKnownStates[$taskId] = $currentState;
                    // Fall through to completion-webhook check below
                    // (in case the campaign was already closed when worker started).
                } elseif ($lastState !== $currentState) {
                    // State transition detected → fire state_changed event.
                    $this->postWebhook($task->callbackUrl, [
                        'event'             => 'campaign.state_changed',
                        'task_id'           => $taskId,
                        'name'              => $task->name,
                        'crm_id'            => $task->crmId,
                        'old_state'         => $lastState,
                        'new_state'         => $currentState,
                        'old_state_label'   => $this->stateLabel($lastState),
                        'new_state_label'   => $this->stateLabel($currentState),
                        'changed_at'        => date('c'),
                    ]);
                    $this->lastKnownStates[$taskId] = $currentState;
                    $this->logger->writeInfo([
                        'action'  => 'webhook',
                        'task'    => $taskId,
                        'message' => "Fired state_changed webhook ({$this->stateLabel($lastState)} -> {$this->stateLabel($currentState)}) to {$task->callbackUrl}"
                    ]);
                }

                // Fire completion webhook exactly once per campaign
                // (when state just became closed).
                if ($currentState === Tasks::STATE_CLOSE && !isset($this->webhookFired[$taskId])) {
                    $total = TaskResults::count(['task_id = :tid:', 'bind' => ['tid' => $taskId]]);
                    $answered = TaskResults::count([
                        'task_id = :tid: AND outDialState = :state:',
                        'bind' => ['tid' => $taskId, 'state' => 'ANSWER']
                    ]);
                    $this->postWebhook($task->callbackUrl, [
                        'event'        => 'campaign.completed',
                        'task_id'      => $taskId,
                        'name'         => $task->name,
                        'crm_id'       => $task->crmId,
                        'total'        => (int)$total,
                        'answered'     => (int)$answered,
                        'failed'       => max(0, (int)$total - (int)$answered),
                        'completed_at' => date('c'),
                    ]);
                    $this->webhookFired[$taskId] = true;
                    $this->logger->writeInfo([
                        'action'  => 'webhook',
                        'task'    => $taskId,
                        'message' => "Fired completion webhook (total={$total}, answered={$answered}) to {$task->callbackUrl}"
                    ]);
                }

                // If a campaign is re-opened (state back to 0 or 2), allow
                // the completion webhook to fire again on next close.
                if ($currentState !== Tasks::STATE_CLOSE && isset($this->webhookFired[$taskId])) {
                    unset($this->webhookFired[$taskId]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->writeInfo('Webhook fire error: ' . $e->getMessage());
        }
    }

    /**
     * Bit Dream IT extension: maps task state int to human-readable label.
     */
    private function stateLabel(int $state): string
    {
        switch ($state) {
            case Tasks::STATE_OPEN:  return 'open';
            case Tasks::STATE_CLOSE: return 'closed';
            case Tasks::STATE_PAUSE: return 'paused';
            default: return 'unknown';
        }
    }

    /**
     * Bit Dream IT extension: HTTP POST a JSON payload to a webhook URL.
     * Uses curl with a 5-second timeout. Failures are logged but not fatal.
     */
    private function postWebhook(string $url, array $payload): void
    {
        if (!function_exists('curl_init')) {
            return;
        }
        $ch = curl_init($url);
        if (!is_resource($ch) && !$ch) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: ModuleAutoDialerManage/1.35 (Bit Dream IT)',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Генерация задачи на callback.
     * @param array $taskData
     * @param array $queues
     * @return string
     */
    public function createCallFile(array $taskData, array $queues): string {
        $phone    = preg_replace('/\D/', '', $taskData['phone'] ?? '');
        $innerNum = preg_replace('/\D/', '', $taskData['innerNum'] ?? '');
        $innerNumType = $taskData['innerNumType'] ?? '';
        $taskId = $taskData['taskId'] ?? '';
        $defDialPrefix = $taskData['dialPrefix'] ?? '';
        $params = $taskData['params'] ?? '';
        if(!file_exists($params)){
            $params = base64_encode($params);
        }
        $maxAttempt = $taskData['maxAttempt'] ?? '';
        $tryInterval = $taskData['tryInterval'] ?? '';
        $attemptUntilSignal = $taskData['attemptUntilSignal'] ?? '';
        $isCallback = (int)($taskData['isCallback']??0);
        // Bit Dream IT extension: AMD toggle passed through to dialplan as
        // channel variable M_AMD_ENABLED. The dialplan branches on this.
        $amdEnabled = (int)($taskData['amdEnabled'] ?? 0);

        if($isCallback){
            $queueId = $queues[$innerNum]??'';
            $srcNum = $innerNum;
            $dstNum = $defDialPrefix.$phone;
            $srcContext = 'internal-originate';
            $dstContext = 'outgoing';
            $additionalVars = "Setvar: __SRC_QUEUE=".$queueId.PHP_EOL;
            $additionalVars.= "Setvar: __pt1c_cid=$phone".PHP_EOL;
            $additionalVars.= "Setvar: __M_IS_CALLBACK=1".PHP_EOL;
        }else{
            $srcNum = $defDialPrefix.$phone;
            $dstNum = $innerNum;
            $srcContext = 'outgoing';
            $dstContext = 'internal';
            $additionalVars = '';
        }

        $conf = "Channel: Local/$srcNum@dialer-manage-out-originate-outgoing".PHP_EOL.
            "Callerid: dialer <$taskId>".PHP_EOL.
            "MaxRetries: 0".PHP_EOL.
            "RetryTime: 3".PHP_EOL.
            "Context: ".AutoDialerConf::CONTEXT_NAME.PHP_EOL.
            "Extension: $dstNum".PHP_EOL.
            "Priority: 1".PHP_EOL.
            "Archive: no".PHP_EOL.
            $additionalVars.
            "Setvar: __DISABLE_ANNONCE=1".PHP_EOL.
            "Setvar: _QUEUE_SRC_CHAN=1".PHP_EOL.
            "Setvar: __SRC_CONTEXT=$srcContext".PHP_EOL.
            "Setvar: __DST_CONTEXT=$dstContext".PHP_EOL.
            "Setvar: OFF_ANSWER_SUB=1".PHP_EOL.
            "Setvar: __M_INNER_NUMBER=$innerNum".PHP_EOL.
            "Setvar: __M_TASK_ID=$taskId".PHP_EOL.
            "Setvar: __M_MAX_ATTEMPT=$maxAttempt".PHP_EOL.
            "Setvar: __M_MAX_RETRY=1".PHP_EOL.
            "Setvar: __M_TRY_INTERVAL=$tryInterval".PHP_EOL.
            "Setvar: __M_OUT_NUMBER=$phone".PHP_EOL.
            "Setvar: __M_ATTEMPT_UTIL_SIGNAL=$attemptUntilSignal".PHP_EOL.
            "Setvar: __M_EXTEN_TYPE=$innerNumType".PHP_EOL.
            "Setvar: __M_PARAMS=$params".PHP_EOL.
            "Setvar: __M_AMD_ENABLED=$amdEnabled";

        $outgoingDir = AutoDialerMain::getDiSetting('asterisk.astspooldir').'/outgoing';
        $tmpDir      = AutoDialerMain::getDiSetting('core.tempDir');

        $tmpFileName = tempnam($tmpDir, 'call');
        $newFilename = "$outgoingDir/dialer-manage-$taskId-$srcNum-$dstNum.call";

        file_put_contents($tmpFileName, $conf);
        $data = ['filename' => basename($newFilename)];
        ConnectorDB::invoke('saveStateData', [ConnectorDB::EVENT_CREATE_CALL_FILE, $phone, $taskId, $data], false);
        $mvPath = Util::which('mv');
        Processes::mwExec("$mvPath $tmpFileName $newFilename");
        return $newFilename;
    }

    /**
     * Получение запросов на идентификацию номера телефона.
     * @param $tube
     * @return void
     */
    public function onEvents($tube): void
    {
        try {
            $data = json_decode($tube->getBody(), true, 512, JSON_THROW_ON_ERROR);
        }catch (\Throwable $e){
            return;
        }
        if($data['action'] === 'invoke'){
            $res_data = [];
            $funcName = $data['function']??'';
            if(method_exists($this, $funcName)){
                if(count($data['args']) === 0){
                    $res_data = $this->$funcName();
                }else{
                    $res_data = $this->$funcName(...$data['args']??[]);
                }
                $res_data = serialize($res_data);
            }
            if(isset($data['need-ret'])){
                $tube->reply($res_data);
            }
        }
    }

    /**
     * Выполнение методов worker, запущенного в другом процессе.
     * @param string $function
     * @param array $args
     * @param bool $retVal
     * @return array|bool|mixed
     */
    public static function invoke(string $function, array $args = [], bool $retVal = true){
        $req = [
            'action'   => 'invoke',
            'function' => $function,
            'args'     => $args
        ];
        $client = new BeanstalkClient(self::class);
        try {
            if($retVal){
                $req['need-ret'] = true;
                $result = $client->request(json_encode($req, JSON_THROW_ON_ERROR), 20);
            }else{
                $client->publish(json_encode($req, JSON_THROW_ON_ERROR));
                return true;
            }
            $object = unserialize($result, ['allowed_classes' => [PBXApiResult::class]]);
        } catch (\Throwable $e) {
            $object = [];
        }
        return $object;
    }
}

if(isset($argv) && count($argv) !== 1
    && Util::getFilePathByClassName(WorkerDialer::class) === $argv[0]){
    // Start worker process
    WorkerDialer::startWorker($argv??[]);
}