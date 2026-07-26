<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 4 2020
 *
 */

namespace Modules\ModuleAutoDialerManage\Lib\RestAPI\Controllers;

use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\Modules\ModulesControllerBase;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleAutoDialerManage\bin\ConnectorDB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ApiController extends ModulesControllerBase
{
    /**
     * Декодирует JSON из тела запроса, убирая BOM если есть.
     * @return array|null массив данных или null при ошибке парсинга
     */
    private function getJsonBody(): ?array
    {
        $rawBody = $this->request->getRawBody();
        // Удаляем UTF-8 BOM (часто приходит из 1С)
        if (strncmp($rawBody, "\xEF\xBB\xBF", 3) === 0) {
            $rawBody = substr($rawBody, 3);
        }
        $data = json_decode($rawBody, true);
        return is_array($data) ? $data : null;
    }
    /**
     * curl -X POST -d '{"crmId":80001,"name":"New task","state":0,"innerNum":"2001","maxCountChannels":1,"dialPrefix": "999","numbers":["77952223344","77952223341"]}' http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/task
     * curl -X POST -d '{"crmId":90072,"name":"New pollingtask","state":0,"innerNum":"2","innerNumType": "polling","maxCountChannels":1,"dialPrefix": "999","numbers":["77952223344","77952223341"]}' http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/task
     * // Task with params:
     * curl -X POST -d '{"crmId":90072,"name":"New pollingtask","state":0,"innerNum":"2","innerNumType":"polling","maxCountChannels":1,"dialPrefix":"999","numbers":[{"number":"77952223344","params":{"speach":"Выша задолженность 1000 рублей"}}]}' http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/task
     */
    public function postTaskAction():void
    {
        $data = $this->getJsonBody();
        if ($data === null) {
            $result = new PBXApiResult();
            $result->messages[] = 'Invalid JSON request body';
            $this->echoResponse($result->getResult());
            $this->response->sendRaw();
            return;
        }
        // Валидация: innerNum обязателен для задач обзвона
        $innerNum = trim($data['innerNum'] ?? '');
        if ($innerNum === '') {
            $result = new PBXApiResult();
            $result->messages[] = 'Field "innerNum" is required and cannot be empty';
            $this->echoResponse($result->getResult());
            $this->response->sendRaw();
            return;
        }
        $result = ConnectorDB::invoke('addTask', [$data], true, 120);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }


    /** curl -X POST http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/upload-xls
     * @return void
     */
    public function uploadXlsAction():void
    {
        require_once(dirname(__DIR__,3).'/vendor/autoload.php');
        $phones = [];
        foreach ($this->request->getUploadedFiles() as $file) {
            $fileExtension = strtolower($file->getExtension());
            if ($fileExtension === 'xls' || $fileExtension === 'xlsx') {
                $filePath = '/tmp/' . $file->getName();
                $file->moveTo($filePath);
                file_put_contents($filePath, base64_decode(file_get_contents($filePath)));
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $rowIndex = 1;
                while (true) {
                    $rowData = $sheet->rangeToArray("A$rowIndex:Z$rowIndex", null, true, false)[0];
                    $clientPhones = array_filter($rowData, fn($value) => !empty($value));
                    if (empty($clientPhones)) {
                        break;
                    }
                    foreach ($clientPhones as $phone) {
                        $phones[] = [
                            'number' => $phone,
                            'clientId' => "$rowIndex"
                        ];
                    }
                    $rowIndex++;
                }
                unlink($filePath);
            }
        }

        $this->echoResponse($phones);
        $this->response->sendRaw();
    }

    /**
     * curl -X POST -d '{"phone":"77952223344","taskId":""}' http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/task-signal-close
     * @return void
     */
    public function postTaskSignalAction():void
    {
        $data = $this->getJsonBody();
        if ($data === null) {
            $result = new PBXApiResult();
            $result->messages[] = 'Invalid JSON request body';
            $this->echoResponse($result->getResult());
            $this->response->sendRaw();
            return;
        }
        $result = ConnectorDB::invoke('taskSignalClose', [$data]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * curl -X POST -d '[{"id":"","name":"Петров Иван Степанович","crmId":"000000000001","properties":[{"key":"ADDRES","value":"Москва, Георгиевский пр-кт д. 1701"},{"key":"ACCOUNT_1","value":"10000123"}],"phones":["74952293042","79052232222"]}]' http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/client
     * @return void
     */
    public function postClientAction():void
    {
        $data = $this->getJsonBody();
        if ($data === null) {
            $result = new PBXApiResult();
            $result->messages[] = 'Invalid JSON request body';
            $this->echoResponse($result->getResult());
            $this->response->sendRaw();
            return;
        }
        $result = ConnectorDB::invoke('addClient', [$data]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * curl -X DELETE 'http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/client/1'
     * @param $id
     * @return void
     */
    public function deleteClientAction($id):void
    {
        $result = ConnectorDB::invoke('deleteClient', [$id]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * curl -X GET 'http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/client-by-phone/74952293042'
     * @param $phone
     * @return void
     */
    public function getClientByPhoneAction($phone):void
    {
        $result = ConnectorDB::invoke('findClientByPhone', [$phone]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }



    /**
     *  curl -X POST -d '{"crmId":"100000","name":"New polling","questions":[{"questionId":"1","questionText":"Готовы ли Вы принять груз? Нажмите 1, если согласны. Нажмите 0, если отказываетесь, нажмите 3 для связи с оператором. Нажмите 4 для заказа такси","press":[{"key":"1","action":"answer","value":"1","nextQuestion":"2"},{"key":"2","action":"answer","value":"0","nextQuestion":""},{"key":"3","action":"dial","value":"201","nextQuestion":""},{"key":"4","action":"","value":"","nextQuestion":"2"}]},{"questionId":"2","questionText":"Заказать Вам такси?","press":[{"key":"1","action":"answer","value":"1","nextQuestion":""},{"key":"2","action":"answer","value":"0","nextQuestion":""}]}]}' http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/polling
     *  curl -X POST -d '{"crmId":"100002","name":"New polling","questions":[{"questionId":"test-2","questionText":"На свзи компания МиКОО. Готовы ли Вы принять груз? Нажмите 1, если согласны. Нажмите 0, если отказываетесь, нажмите 3 для связи с оператором. Нажмите 4 для заказа такси","press":[{"key":"1","action":"answer","value":"1","nextQuestion":""},{"key":"2","action":"answer","value":"0","nextQuestion":""}]}]}' http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/polling
     * @return void
     */
    public function  postPollingAction():void
    {
        $data = $this->getJsonBody();
        if ($data === null) {
            $result = new PBXApiResult();
            $result->messages[] = 'Invalid JSON request body';
            $this->echoResponse($result->getResult());
            $this->response->sendRaw();
            return;
        }
        $result = ConnectorDB::invoke('addPolling', [$data]);
        if (!is_array($result)) {
            $result = (new PBXApiResult())->getResult();
        }
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * curl 'http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/polling'
     * @return void
     */
    public function getPollingAction():void
    {
        $result = ConnectorDB::invoke('getPolling', []);
        $this->echoResponse($result, true);
        $this->response->sendRaw();
    }

    /**
     * curl 'http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/polling/1'
     * @param $id
     * @return void
     */
    public function getPollingByIdAction($id):void
    {
        $result = ConnectorDB::invoke('getPollingById', [$id]);
        $result['data'] = $result['data']['results'];
        $this->decodeData($result['data']);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * curl -X DELETE 'http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/polling/1'
     * @param $id
     * @return void
     */
    public function deletePollingByIdAction($id):void
    {
        $result = ConnectorDB::invoke('deletePolling', [$id]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * Deletes a task.
     * curl -X DELETE http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/task/600011
     * @param string $taskId
     * @return void
     */
    public function deleteTaskAction(string $taskId):void
    {
        $result = ConnectorDB::invoke('deleteTask', [$taskId]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * Returns task data by ID.
     * curl -X GET http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/task/5002
     * @param string $taskId
     * @return void
     */
    public function getTaskAction(string $taskId):void
    {
        $result = ConnectorDB::invoke('getTask', [$taskId]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * Returns list of tasks.
     * curl -X GET http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/task
     * @return void
     */
    public function getTasksAction():void
    {
        $state  = $this->request->get('state');
        $limit  = $this->request->get('limit');
        $offset = $this->request->get('offset');
        $result = ConnectorDB::invoke('getTasks', [$state, $limit, $offset]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * curl -H 'Content-Type: application/json' -X PUT -d '{"state":0}' http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/task/997
     * @param string $taskId
     * @return void
     */
    public function putTaskAction(string $taskId):void
    {
        $data = $this->getJsonBody();
        if ($data === null) {
            $result = new PBXApiResult();
            $result->messages[] = 'Invalid JSON request body';
            $this->echoResponse($result->getResult());
            $this->response->sendRaw();
            return;
        }
        $result = ConnectorDB::invoke('changeTask', [$taskId, $data]);
        $responseData = ($result instanceof PBXApiResult) ? $result->getResult() : $result;
        $this->echoResponse($responseData);
        $this->response->sendRaw();
    }

    /**
     * curl -F "file=@/home/serber/1.mp3" 'http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/audio'
     * @return void
     */
    public function uploadAudio():void
    {
        $result = new PBXApiResult();
        if ($this->request->isPost()) {
            $file = $this->request->getUploadedFiles();
            if (isset($file[0])) {
                $uploadedFile = $file[0];
                $extension = Util::getExtensionOfFile($uploadedFile->getName());
                $path = '/tmp/' . md5($uploadedFile->getTempName()).'.'.$extension;
                if ($uploadedFile->moveTo($path)) {
                    $result = ConnectorDB::invoke('saveAudioFile', [$path, basename($uploadedFile->getName())]);
                } else {
                    $result->messages[] = 'error upload file: fail mv fail to /tmp';
                }
                unlink($path);
            } else {
                $result->messages[] = 'error upload file: file is empty';
            }
        }
        $responseData = ($result instanceof PBXApiResult) ? $result->getResult() : $result;
        $this->echoResponse($responseData);
        $this->response->sendRaw();
    }

    /**
     * curl 'http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/audio'
     * @return void
     */
    public function listAudioFiles():void
    {
        $result = ConnectorDB::invoke('listAudioFiles', []);
        $responseData = ($result instanceof PBXApiResult) ? $result->getResult() : $result;
        $this->echoResponse($responseData);
        $this->response->sendRaw();
    }

    /**
     *  curl -X DELETE 'http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/audio/1.mp3'
     * @return void
     */
    public function deleteAudioFile($name):void
    {
        $result = ConnectorDB::invoke('deleteAudioFile', [$name]);
        $responseData = ($result instanceof PBXApiResult) ? $result->getResult() : $result;
        $this->echoResponse($responseData);
        $this->response->sendRaw();
    }

    /**
     * curl -X GET http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/results/{changeTime}
     * curl -X GET http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/results/1690194629
     * @param string $changeTime
     * @return void
     */
    public function getResultsAction(string $changeTime):void
    {
        $result = ConnectorDB::invoke('getResults', [$changeTime]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * curl -X GET http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/polling-results/{changeTime}
     * curl -X GET http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/polling-results/1690194629
     * @param string $changeTime
     * @return void
     */
    public function getResultsPollingAction(string $changeTime):void
    {
        $result = ConnectorDB::invoke('getResultsPolling', [$changeTime]);
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * Тестовый эндпоинт CRM — возвращает timestamp.
     * curl -X POST http://127.0.0.1/pbxcore/api/module-dialer-manage/v1/crm-test
     * @return void
     */
    public function postCrmTestAction():void
    {
        $result = ['result' => true, 'data' => (string)time()];
        $this->echoResponse($result);
        $this->response->sendRaw();
    }

    /**
     * Outputs the server response as JSON.
     * @param array $result
     * @param bool $forDataTables
     * @return void
     */
    private function echoResponse(array $result, bool $forDataTables = false):void
    {
        if(isset($result['data']['results'])){
            $this->decodeData($result['data']['results']);
        }

        if($forDataTables===true){
            $result['data'] = $result['data']['results'];
            $result['draw'] = $this->request->get('draw');
            $result['recordsTotal'] = count($result['data']??[]);
            $result['recordsFiltered'] = 0;
        }
        try {
            echo json_encode($result, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        }catch (\Exception $e){
            echo json_encode(['result' => false, 'messages' => ['JSON encoding error']]);
        }
    }

    /**
     * If data is a file path, attempts to decode its contents as JSON.
     * @param $data
     * @return void
     */
    private function decodeData(& $data):void
    {
        if(is_string($data) && is_file($data) && file_exists($data)){
            $filePath = $data;
            try {
                $data = json_decode(file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR);
            }catch ( \JsonException $e){
            }
            unlink($filePath);
        }
    }

    // =========================================================================
    // Bit Dream IT extensions - dashboard, management, DNC, AMD support
    // =========================================================================

    /**
     * Live campaign status: in-progress count, max channels, recent activity.
     * Lightweight GET suitable for 3-5 second polling.
     * GET /pbxcore/api/module-dialer-manage/v1/task/{id}/status
     */
    public function getTaskStatusAction(string $id): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            $task = \Modules\ModuleAutoDialerManage\Models\Tasks::findFirstById($id);
            if ($task === null) {
                $result->messages[] = "Task {$id} not found";
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $inProgress = \Modules\ModuleAutoDialerManage\Models\TaskResults::count([
                'task_id = :tid: AND outDialState = :state:',
                'bind' => ['tid' => $id, 'state' => 'DIAL']
            ]);
            $totalDialed = \Modules\ModuleAutoDialerManage\Models\TaskResults::count([
                'task_id = :tid:',
                'bind' => ['tid' => $id]
            ]);
            $result->success = true;
            $result->data = [
                'task_id'         => (int)$task->id,
                'name'            => $task->name,
                'state'           => (int)$task->state,
                'state_label'     => $this->taskStateLabel((int)$task->state),
                'in_progress'     => (int)$inProgress,
                'max_channels'    => (int)$task->maxCountChannels,
                'total_dialed'    => (int)$totalDialed,
                'amd_enabled'     => (int)$task->amdEnabled,
                'updated_at'      => date('c'),
            ];
        } catch (\Throwable $e) {
            $result->messages[] = 'Error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * Live agent/extension status panel for dashboard.
     * Returns array of all DialerExtensions with their current state
     * (idle, busy, unavailable) from the worker cache.
     * GET /pbxcore/api/module-dialer-manage/v1/agents-status
     */
    public function getAgentsStatusAction(): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            $statuses = \Modules\ModuleAutoDialerManage\Lib\AutoDialerMain::getCacheData('statuses');
            $extensions = \Modules\ModuleAutoDialerManage\Models\DialerExtensions::find()->toArray();
            $agents = [];
            foreach ($extensions as $ext) {
                $number = $ext['extension'] ?? '';
                $state = $statuses[$number] ?? 'Unknown';
                $agents[] = [
                    'number'    => $number,
                    'name'      => $ext['description'] ?? $number,
                    'state'     => $state,
                    'state_label' => $this->agentStateLabel($state),
                    'is_idle'   => ($state === \Modules\ModuleAutoDialerManage\bin\WorkerAMI::STATE_IDLE),
                ];
            }
            $result->success = true;
            $result->data = ['agents' => $agents, 'count' => count($agents)];
        } catch (\Throwable $e) {
            $result->messages[] = 'Error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * Campaign summary report: totals, answer rate, average duration, etc.
     * GET /pbxcore/api/module-dialer-manage/v1/task/{id}/summary
     */
    public function getTaskSummaryAction(string $id): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            $task = \Modules\ModuleAutoDialerManage\Models\Tasks::findFirstById($id);
            if ($task === null) {
                $result->messages[] = "Task {$id} not found";
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $results = \Modules\ModuleAutoDialerManage\Models\TaskResults::find([
                'task_id = :tid:',
                'bind' => ['tid' => $id]
            ])->toArray();
            $total = count($results);
            $answered = 0;
            $failed = 0;
            $totalDuration = 0;
            $durationCount = 0;
            foreach ($results as $row) {
                if (($row['outDialState'] ?? '') === 'ANSWER') {
                    $answered++;
                    if (!empty($row['duration'])) {
                        $totalDuration += (int)$row['duration'];
                        $durationCount++;
                    }
                } else {
                    $failed++;
                }
            }
            $result->success = true;
            $result->data = [
                'task_id'          => (int)$task->id,
                'name'             => $task->name,
                'total_dialed'     => $total,
                'answered'         => $answered,
                'failed'           => $failed,
                'answer_rate'      => $total > 0 ? round($answered / $total * 100, 2) : 0,
                'avg_duration_sec' => $durationCount > 0 ? (int)round($totalDuration / $durationCount) : 0,
                'total_duration_sec' => $totalDuration,
                'state'            => (int)$task->state,
                'state_label'      => $this->taskStateLabel((int)$task->state),
                'amd_enabled'      => (int)$task->amdEnabled,
                'created_at'       => $task->timeStart ?? null,
                'closed_at'        => $task->timeEnd ?? null,
            ];
        } catch (\Throwable $e) {
            $result->messages[] = 'Error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * CSV export of campaign results.
     * GET /pbxcore/api/module-dialer-manage/v1/task/{id}/export
     * Streams a CSV file with columns: number, state, duration, attempt, time
     */
    public function exportTaskCsvAction(string $id): void
    {
        try {
            $results = \Modules\ModuleAutoDialerManage\Models\TaskResults::find([
                'task_id = :tid:',
                'bind' => ['tid' => $id]
            ])->toArray();
            $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8');
            $this->response->setHeader('Content-Disposition', 'attachment; filename="task_' . $id . '_results.csv"');
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 auto-detection
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['number', 'state', 'duration_sec', 'attempt', 'time', 'cause']);
            foreach ($results as $row) {
                fputcsv($out, [
                    $row['number'] ?? '',
                    $row['outDialState'] ?? '',
                    (int)($row['duration'] ?? 0),
                    (int)($row['attempt'] ?? 0),
                    $row['time'] ?? '',
                    $row['cause'] ?? '',
                ]);
            }
            fclose($out);
            $this->response->sendRaw();
        } catch (\Throwable $e) {
            $result = new PBXApiResult();
            $result->messages[] = 'Export error: ' . $e->getMessage();
            $this->echoResponse($result->getResult());
            $this->response->sendRaw();
        }
    }

    /**
     * CSV import of phone numbers into an existing campaign.
     * POST /pbxcore/api/module-dialer-manage/v1/task/{id}/import-csv
     * Multipart form-data with field 'file' = CSV file.
     * Accepted CSV columns (any order, header row required):
     *   number, name, params
     * 'params' may be JSON string or key=value pairs separated by ';'.
     */
    public function importTaskCsvAction(string $id): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            $task = \Modules\ModuleAutoDialerManage\Models\Tasks::findFirstById($id);
            if ($task === null) {
                $result->messages[] = "Task {$id} not found";
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $uploaded = $this->request->getUploadedFiles();
            if (empty($uploaded)) {
                $result->messages[] = 'No file uploaded (field name must be "file")';
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $file = $uploaded[0];
            $tmpPath = '/tmp/import_csv_' . uniqid() . '.csv';
            $file->moveTo($tmpPath);

            $handle = fopen($tmpPath, 'r');
            if ($handle === false) {
                $result->messages[] = 'Cannot open uploaded file';
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $header = fgetcsv($handle);
            if ($header === false || !in_array('number', array_map('strtolower', $header), true)) {
                $result->messages[] = 'CSV must have a header row with at least a "number" column';
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $header = array_map('strtolower', $header);
            $numberIdx = array_search('number', $header, true);
            $nameIdx = array_search('name', $header, true);
            $paramsIdx = array_search('params', $header, true);

            $numbers = [];
            $rowCount = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $number = preg_replace('/\D/', '', (string)($row[$numberIdx] ?? ''));
                if ($number === '') continue;
                $entry = ['number' => $number];
                if ($nameIdx !== false && !empty($row[$nameIdx])) {
                    $entry['name'] = $row[$nameIdx];
                }
                if ($paramsIdx !== false && !empty($row[$paramsIdx])) {
                    $raw = $row[$paramsIdx];
                    $decoded = json_decode($raw, true);
                    $entry['params'] = is_array($decoded) ? $decoded : ['text' => $raw];
                }
                $numbers[] = $entry;
                $rowCount++;
            }
            fclose($handle);
            unlink($tmpPath);

            // Add numbers to the task via the existing ConnectorDB::addTask path.
            // We invoke addClients to bulk-insert.
            $addResult = ConnectorDB::invoke('addClients', [$id, $numbers], true, 120);
            $result->success = true;
            $result->data = [
                'task_id'      => (int)$id,
                'rows_read'    => $rowCount,
                'rows_added'   => count($numbers),
                'detail'       => $addResult,
            ];
        } catch (\Throwable $e) {
            $result->messages[] = 'Import error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * Test-call: dials a single test number to preview the campaign/poll.
     * POST /pbxcore/api/module-dialer-manage/v1/task/{id}/test-call
     * Body: {"number": "77951112233", "extension": "201"} (extension optional)
     */
    public function testCallAction(string $id): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            $task = \Modules\ModuleAutoDialerManage\Models\Tasks::findFirstById($id);
            if ($task === null) {
                $result->messages[] = "Task {$id} not found";
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $data = $this->getJsonBody() ?? [];
            $testNumber = preg_replace('/\D/', '', (string)($data['number'] ?? ''));
            if ($testNumber === '') {
                $result->messages[] = 'Field "number" is required';
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            // Build a single-call task payload and submit
            $testPayload = [
                'crmId'           => $task->crmId . '_TEST_' . time(),
                'name'            => 'TEST: ' . $task->name,
                'state'           => 0,
                'innerNum'        => $data['extension'] ?? $task->innerNum,
                'innerNumType'    => $task->innerNumType,
                'maxCountChannels'=> 1,
                'dialPrefix'      => $task->dialPrefix,
                'numbers'         => [['number' => $testNumber, 'params' => $data['params'] ?? []]],
            ];
            $testResult = ConnectorDB::invoke('addTask', [$testPayload], true, 120);
            $result->success = true;
            $result->data = [
                'test_task' => $testResult,
                'dialed_number' => $testNumber,
            ];
        } catch (\Throwable $e) {
            $result->messages[] = 'Test-call error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * Add number(s) to the DNC blacklist.
     * POST /pbxcore/api/module-dialer-manage/v1/blacklist
     * Body: {"numbers":["77951112233"], "reason":"customer complaint", "source":"manual"}
     * OR single: {"number":"...", "reason":"..."}
     */
    public function postBlacklistAction(): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            $data = $this->getJsonBody();
            if ($data === null) {
                $result->messages[] = 'Invalid JSON body';
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $numbers = [];
            if (isset($data['number'])) {
                $numbers[] = $data['number'];
            }
            if (isset($data['numbers']) && is_array($data['numbers'])) {
                $numbers = array_merge($numbers, $data['numbers']);
            }
            if (empty($numbers)) {
                $result->messages[] = 'No "number" or "numbers" field provided';
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $reason = (string)($data['reason'] ?? '');
            $source = (string)($data['source'] ?? 'manual');
            $added = 0;
            $skipped = 0;
            foreach ($numbers as $num) {
                $num = preg_replace('/\D/', '', (string)$num);
                if ($num === '') {
                    $skipped++;
                    continue;
                }
                $existing = \Modules\ModuleAutoDialerManage\Models\Blacklist::findFirst([
                    'number = :n:',
                    'bind' => ['n' => $num]
                ]);
                if ($existing !== null) {
                    $skipped++;
                    continue;
                }
                $entry = new \Modules\ModuleAutoDialerManage\Models\Blacklist();
                $entry->number = $num;
                $entry->reason = $reason;
                $entry->source = $source;
                $entry->createdAt = time();
                if ($entry->save()) {
                    $added++;
                } else {
                    $skipped++;
                }
            }
            $result->success = true;
            $result->data = ['added' => $added, 'skipped_duplicates' => $skipped];
        } catch (\Throwable $e) {
            $result->messages[] = 'Blacklist add error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * List DNC blacklist entries (paginated).
     * GET /pbxcore/api/module-dialer-manage/v1/blacklist?limit=100&offset=0&q=7795
     */
    public function getBlacklistAction(): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            $limit = max(1, min(1000, (int)$this->request->get('limit', 'int', 100)));
            $offset = max(0, (int)$this->request->get('offset', 'int', 0));
            $q = (string)$this->request->get('q', 'string', '');
            $conditions = '';
            $bind = [];
            if ($q !== '') {
                $conditions = 'number LIKE :q:';
                $bind = ['q' => '%' . preg_replace('/\D/', '', $q) . '%'];
            }
            $entries = \Modules\ModuleAutoDialerManage\Models\Blacklist::find([
                $conditions === '' ? null : $conditions,
                'bind' => $bind,
                'limit' => $limit,
                'offset' => $offset,
                'order' => 'createdAt DESC',
            ])->toArray();
            $total = \Modules\ModuleAutoDialerManage\Models\Blacklist::count();
            $result->success = true;
            $result->data = [
                'entries' => $entries,
                'total'   => (int)$total,
                'limit'   => $limit,
                'offset'  => $offset,
            ];
        } catch (\Throwable $e) {
            $result->messages[] = 'Blacklist list error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * Delete a single number from the DNC blacklist.
     * DELETE /pbxcore/api/module-dialer-manage/v1/blacklist/{number}
     */
    public function deleteBlacklistAction(string $number): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            $number = preg_replace('/\D/', '', $number);
            $entry = \Modules\ModuleAutoDialerManage\Models\Blacklist::findFirst([
                'number = :n:',
                'bind' => ['n' => $number]
            ]);
            if ($entry === null) {
                $result->messages[] = "Number {$number} not in blacklist";
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            if ($entry->delete()) {
                $result->success = true;
                $result->data = ['deleted' => $number];
            } else {
                $result->messages[] = 'Failed to delete entry';
            }
        } catch (\Throwable $e) {
            $result->messages[] = 'Blacklist delete error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * Recording lookup by linkedId (joins to MikoPBX core CDR).
     * GET /pbxcore/api/module-dialer-manage/v1/recording/{linkedId}
     * Returns: {linked_id, recording_path, duration, dialstatus}
     */
    public function getRecordingAction(string $linkedId): void
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        try {
            // Look up the CDR record by linkedid
            $cdr = \MikoPBX\Common\Models\CallDetailRecords::findFirst([
                'linkedid = :lid:',
                'bind' => ['lid' => $linkedId],
                'order' => 'id DESC',
            ]);
            if ($cdr === null) {
                $result->messages[] = "No CDR record found for linkedId {$linkedId}";
                $this->echoResponse($result->getResult());
                $this->response->sendRaw();
                return;
            }
            $recordingFile = $cdr->recordingfile ?? '';
            $result->success = true;
            $result->data = [
                'linked_id'      => $linkedId,
                'recording_path' => $recordingFile,
                'recording_exists' => !empty($recordingFile) && file_exists($recordingFile),
                'duration'       => (int)($cdr->duration ?? 0),
                'dialstatus'     => $cdr->disposition ?? '',
                'src'            => $cdr->src ?? '',
                'dst'            => $cdr->dst ?? '',
                'calldate'       => $cdr->calldate ?? '',
            ];
        } catch (\Throwable $e) {
            $result->messages[] = 'Recording lookup error: ' . $e->getMessage();
        }
        $this->echoResponse($result->getResult());
        $this->response->sendRaw();
    }

    /**
     * Helper: human-readable task state label.
     */
    private function taskStateLabel(int $state): string
    {
        switch ($state) {
            case \Modules\ModuleAutoDialerManage\Models\Tasks::STATE_OPEN:  return 'open';
            case \Modules\ModuleAutoDialerManage\Models\Tasks::STATE_CLOSE: return 'closed';
            case \Modules\ModuleAutoDialerManage\Models\Tasks::STATE_PAUSE: return 'paused';
            default: return 'unknown';
        }
    }

    /**
     * Helper: human-readable agent state label.
     */
    private function agentStateLabel(string $state): string
    {
        $map = [
            \Modules\ModuleAutoDialerManage\bin\WorkerAMI::STATE_IDLE        => 'idle',
            \Modules\ModuleAutoDialerManage\bin\WorkerAMI::STATE_UP          => 'in_call',
            \Modules\ModuleAutoDialerManage\bin\WorkerAMI::STATE_RINGING     => 'ringing',
            \Modules\ModuleAutoDialerManage\bin\WorkerAMI::STATE_UNAVAILABLE => 'unavailable',
        ];
        return $map[$state] ?? 'unknown';
    }
}
