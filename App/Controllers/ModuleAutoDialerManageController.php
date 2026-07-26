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

namespace Modules\ModuleAutoDialerManage\App\Controllers;
use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Core\System\Util;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleAutoDialerManage\App\Forms\ModuleAutoDialerManageExtensionForm;
use Modules\ModuleAutoDialerManage\App\Forms\ModuleAutoDialerManageForm;
use Modules\ModuleAutoDialerManage\Models\DialerExtensions;
use Modules\ModuleAutoDialerManage\Models\ModuleAutoDialerManage;
use Modules\ModuleAutoDialerManage\Models\Polling;
use Modules\ModuleAutoDialerManage\Models\Question;
use Modules\ModuleAutoDialerManage\Models\QuestionActions;

class ModuleAutoDialerManageController extends BaseController
{
    private $moduleUniqueID = 'ModuleAutoDialerManage';
    private $moduleDir;

    /**
     * Basic initial class
     */
    public function initialize(): void
    {
        $this->moduleDir = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->logoImagePath = "{$this->url->get()}assets/img/cache/{$this->moduleUniqueID}/logo.svg";
        $this->view->submitMode = null;
        parent::initialize();
    }

    /**
     * Retrieves the description of tables and assigns it to the view data.
     *
     * @return void
     */
    public function getTablesDescriptionAction(): void
    {
        $this->view->data = $this->getTablesDescription();
    }

    /**
     * Retrieves the new records for a given table and assigns them to the view data.
     *
     * @return void
     */
    public function getNewRecordsAction(): void
    {
        // Assign current page to the view
        $currentPage                 = $this->request->getPost('draw');
        $table                       = $this->request->get('table');
        $this->view->draw            = $currentPage;
        $this->view->recordsTotal    = 0;
        $this->view->recordsFiltered = 0;
        $this->view->data            = [];

        // Retrieve the description of tables
        $descriptions = $this->getTablesDescription();

        // If the table description does not exist, return
        if(!isset($descriptions[$table])){
            return;
        }
        $className = $this->getClassName($table);

        // If the class name is not empty, retrieve the records
        if(!empty($className)){
            $filter = [];

            // Apply sorting based on priority column if it exists
            if(isset($descriptions[$table]['cols']['priority'])){
                $filter = ['order' => 'priority'];
            }

            // Retrieve all records and convert them to an array
            $allRecords = $className::find($filter)->toArray();
            $records    = [];
            $emptyRow   = [
                'rowIcon'  =>  $descriptions[$table]['cols']['rowIcon']['icon']??'',
                'DT_RowId' => 'TEMPLATE'
            ];

            // Add empty values for each column except rowIcon
            foreach ($descriptions[$table]['cols'] as $key => $metadata) {
                if('rowIcon' !== $key){
                    $emptyRow[$key] = '';
                }
            }
            $records[] = $emptyRow;

            // Iterate through each record and populate the data array
            foreach ($allRecords as $rowData){
                $tmpData = [];
                $tmpData['DT_RowId'] =  $rowData['id'];
                foreach ($descriptions[$table]['cols'] as $key => $metadata){
                    if('rowIcon' === $key){
                        $tmpData[$key] = $metadata['icon']??'';
                    }elseif('delButton' === $key){
                        $tmpData[$key] = '';
                    }elseif(isset($rowData[$key])){
                        $tmpData[$key] =  $rowData[$key];
                    }
                }
                $records[] = $tmpData;
            }

            // Assign the populated records to the view data
            $this->view->data      = $records;
        }
    }

    /**
     * Renders the index page for the module.
     *
     * @return void
     */
    public function indexAction(): void
    {
        // Add JavaScript files to the footer collection
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-index.js", true);
        $footerCollection->addJs('js/vendor/jquery.tablednd.min.js', true);
        // Bit Dream IT extension: Vue.js for the new tabs (campaigns, dashboard, results, etc.)
        $footerCollection->addJs('js/vendor/vue.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-index-tabs.js", true);

        // Add CSS files to the header collection
        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        // Retrieve or create new module settings
        $settings = ModuleAutoDialerManage::findFirst();
        if ($settings === null) {
            $settings = new ModuleAutoDialerManage();
        }
        $pollings = array_column(Polling::find()->toArray(), 'name', 'id');
        $extensions = DialerExtensions::find()->toArray();
        foreach ($extensions as $index => $extension) {
            $extensions[$index]['pollingIdOKName'] = $pollings[$extension['pollingIdOK']];
            $extensions[$index]['pollingIdFAILName'] = $pollings[$extension['pollingIdFAIL']];
        }
        $this->view->extensions = $extensions;
        // Bit Dream IT extension: data for the new tabs (campaigns, results, etc.)
        $this->view->apiBaseUrl = "/pbxcore/api/module-dialer-manage/v1";
        $scheme = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
        $this->view->pbxHost    = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $this->view->tasks      = \Modules\ModuleAutoDialerManage\Models\Tasks::find(['order' => 'id DESC'])->toArray();
        $this->view->pollings   = Polling::find()->toArray();
        // Assign the form and view template
        $this->view->form = new ModuleAutoDialerManageForm($settings, []);
        $this->view->pick("{$this->moduleDir}/App/Views/index");
    }

    /**
     * Bit Dream IT extension: Live campaign dashboard.
     * Two view modes:
     *   - Overview (default): grid of campaign cards with live stats
     *   - Detail: full-page dashboard for a single campaign (drill-down)
     * The mode is selected by the `id` route parameter. If absent → overview.
     */
    public function dashboardAction(string $id = ''): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/vendor/vue.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-dashboard.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        $this->view->apiBaseUrl = "/pbxcore/api/module-dialer-manage/v1";

        if ($id !== '') {
            // Detail mode — single campaign drill-down
            $task = \Modules\ModuleAutoDialerManage\Models\Tasks::findFirstById($id);
            $this->view->task       = $task ? $task->toArray() : null;
            $this->view->detailId   = (int)$id;
            $this->view->mode       = 'detail';
            $this->view->pick("{$this->moduleDir}/App/Views/dashboardDetail");
        } else {
            // Overview mode — card grid of all campaigns
            $tasks = \Modules\ModuleAutoDialerManage\Models\Tasks::find([
                'order' => 'id DESC',
                'limit' => 50,
            ])->toArray();
            $this->view->tasks    = $tasks;
            $this->view->detailId = 0;
            $this->view->mode     = 'overview';
            $this->view->pick("{$this->moduleDir}/App/Views/dashboard");
        }
    }

    /**
     * Bit Dream IT extension: DNC blacklist management page.
     */
    public function blacklistAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-blacklist.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        $this->view->apiBaseUrl = "/pbxcore/api/module-dialer-manage/v1";
        $this->view->pick("{$this->moduleDir}/App/Views/blacklist");
    }

    /**
     * Bit Dream IT extension: Campaigns list page.
     * Sortable/filterable table of all campaigns with state, channels, etc.
     */
    public function campaignsAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs('js/vendor/vue.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-campaigns.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        $this->view->apiBaseUrl = "/pbxcore/api/module-dialer-manage/v1";
        $this->view->pick("{$this->moduleDir}/App/Views/campaigns");
    }

    /**
     * Bit Dream IT extension: Campaign create/edit form.
     * Pass an ID in the URL to edit; no ID = create new.
     */
    public function campaignFormAction(string $id = ''): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/vue.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-campaign-form.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);

        $task = null;
        if ($id !== '') {
            $taskObj = \Modules\ModuleAutoDialerManage\Models\Tasks::findFirstById($id);
            if ($taskObj) {
                $task = $taskObj->toArray();
            }
        }
        // Load pollings for the dropdown selector
        $pollings = \Modules\ModuleAutoDialerManage\Models\Polling::find()->toArray();
        // Load extensions for the inner-num dropdown
        $extensions = \Modules\ModuleAutoDialerManage\Models\DialerExtensions::find()->toArray();
        // Load all SIP extensions from MikoPBX (so user can pick any, not just configured ones)
        $allExtensions = \MikoPBX\Common\Models\Extensions::find([
            'type = :type:',
            'bind' => ['type' => \MikoPBX\Common\Models\Extensions::TYPE_SIP]
        ])->toArray();

        $this->view->task         = $task;
        $this->view->taskId       = $id;
        $this->view->pollings     = $pollings;
        $this->view->extensions   = $extensions;
        $this->view->allExtensions = $allExtensions;
        $this->view->apiBaseUrl   = "/pbxcore/api/module-dialer-manage/v1";
        $this->view->pick("{$this->moduleDir}/App/Views/campaignForm");
    }

    /**
     * Bit Dream IT extension: Call results browser.
     * Paginated, filterable by campaign / state / date range.
     */
    public function resultsAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs('js/vendor/vue.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-results.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        // Load all campaigns for the filter dropdown
        $tasks = \Modules\ModuleAutoDialerManage\Models\Tasks::find([
            'order' => 'id DESC',
        ])->toArray();
        $this->view->tasks       = $tasks;
        $this->view->apiBaseUrl  = "/pbxcore/api/module-dialer-manage/v1";
        $this->view->pick("{$this->moduleDir}/App/Views/results");
    }

    /**
     * Bit Dream IT extension: Polling (IVR) results browser.
     */
    public function pollingResultsAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs('js/vendor/vue.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-polling-results.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        $pollings = \Modules\ModuleAutoDialerManage\Models\Polling::find()->toArray();
        $this->view->pollings   = $pollings;
        $this->view->apiBaseUrl = "/pbxcore/api/module-dialer-manage/v1";
        $this->view->pick("{$this->moduleDir}/App/Views/pollingResults");
    }

    /**
     * Bit Dream IT extension: Audio files management page.
     * Upload, list, delete, and play prompts.
     */
    public function audioAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/vendor/vue.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-audio.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);

        $this->view->apiBaseUrl = "/pbxcore/api/module-dialer-manage/v1";
        $this->view->pick("{$this->moduleDir}/App/Views/audio");
    }

    /**
     * Bit Dream IT extension: In-module Developer API Guide page.
     * Shows all REST endpoints with copy-paste curl / Laravel / PHP / Python examples.
     * Designed to be accessible from the sidebar while building integrations.
     */
    public function apiGuideAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/vendor/vue.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-api-guide.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/highlight/default.min.css', true);

        // Detect PBX host for example URLs
        $scheme = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'your-pbx.local';
        $this->view->pbxHost    = $scheme . '://' . $host;
        $this->view->apiBaseUrl = "/pbxcore/api/module-dialer-manage/v1";
        $this->view->pick("{$this->moduleDir}/App/Views/apiGuide");
    }

    public function modifyExtensionAction(string $id=''): void
    {
        // Add JavaScript files to the footer collection
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-modify-extension.js", true);
        $footerCollection->addJs('js/vendor/jquery.tablednd.min.js', true);
        // Add CSS files to the header collection
        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        // Create options array for form
        $options = [];
        $options['pollings'] = ['' => '-'];
        $pollings = Polling::find();
        foreach ($pollings as $polling){
            $options['pollings'][$polling->id] = $polling->name;
        }
        $settings = DialerExtensions::findFirst("id='$id'");
        if ($settings === null) {
            $settings = new DialerExtensions();
            $settings->exten = Extensions::getNextFreeApplicationNumber();
        }
        // Assign the form and view template
        $this->view->form = new ModuleAutoDialerManageExtensionForm($settings, $options);
        $this->view->pick("$this->moduleDir/App/Views/modifyExtension");
    }

    public function deleteExtensionAction(string $id=''): void
    {
        $settings = DialerExtensions::findFirst("id='$id'");
        Extensions::find("number='$settings->exten'")->delete();
        $this->view->success = $settings->delete();
    }

    public function modifyPollingAction(string $id=''):void
    {
        // Add JavaScript files to the footer collection
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-auto-dialer-manage-modify-polling.js", true);
        $footerCollection->addJs('js/vendor/jquery.tablednd.min.js', true);

        // Add CSS files to the header collection
        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-auto-dialer-manage.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        // Retrieve or create new module settings
        $polling = Polling::findFirst("id='$id'");
        $this->view->pollingId = $id;
        if($polling){
            $this->view->name = $polling->name;
        }
        $questions = Question::find("pollingId='$id'")->toArray();
        foreach($questions as $index => $question){
            $questions[$index]['template'] = '0';
            $questions[$index]['press'] = QuestionActions::find("pollingId='$id' AND questionId='{$question['id']}'")->toArray();
        }

        $templateQuestion = (new Question())->toArray();
        $templateQuestion['id'] = '000000000';
        $templateQuestion['timeout'] = '5';
        $templateQuestion['template'] = '1';
        $templateQuestion['press'][] = (new QuestionActions())->toArray();
        $templateQuestion['press'][] = (new QuestionActions())->toArray();
        foreach ($templateQuestion['press'] as $index => $press) {
            $templateQuestion['press'][$index]['key'] = $index;
            $templateQuestion['press'][$index]['action'] = 'answer';
        }
        array_unshift($questions, $templateQuestion);

        $this->view->questions = $questions;
        $this->view->pick("{$this->moduleDir}/App/Views/modifyPolling");
    }

    /**
     * Saves the form data to the database.
     *
     * @return void
     */
    public function saveExtensionAction() :void
    {
        $data   = $this->request->getPost();
        $this->db->begin();

        $record = DialerExtensions::findFirst("id='{$data['id']}'");
        if ($record === null) {
            $record = new DialerExtensions();
        }

        $oldExten = $record->exten;
        $record->exten = $data['exten']??'';
        $record->name = $data['name']??'';
        $record->pollingIdOK = $data['pollingIdOK']??'';
        $record->pollingIdFAIL = $data['pollingIdFAIL']??'';
        // Save the record to the database
        if ($record->save() === FALSE) {
            $errors = $record->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }

        Extensions::find("number='$oldExten'")->delete();
        $data = Extensions::findFirst('number="' . $record->exten . '"');
        if ($data===null) {
            $data                    = new Extensions();
            $data->number            = $record->exten;
            $data->type              = 'MODULES';
            $data->callerid          = "$record->name <$record->exten>";
            $data->public_access     = 0;
            $data->show_in_phonebook = 1;
            $data->save();
        }

        // Commit the transaction and display success message
        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->id = $record->id;
        $this->view->success = true;
        $this->db->commit();
    }
  /**
     * Saves the form data to the database.
     *
     * @return void
     */
    public function saveAction() :void
    {
        $data       = $this->request->getPost();
        $record = ModuleAutoDialerManage::findFirst();
        if ($record === null) {
            $record = new ModuleAutoDialerManage();
        }
        $this->db->begin();

        // Iterate through each field in the record and update its value
        foreach ($record as $key => $value) {
            switch ($key) {
                case 'id':
                    break;
                case 'checkbox_field':
                case 'toggle_field':
                    // Handle checkbox and toggle fields
                    if (array_key_exists($key, $data)) {
                        $record->$key = ($data[$key] === 'on') ? '1' : '0';
                    } else {
                        $record->$key = '0';
                    }
                    break;
                default:
                    // Handle other fields
                    if (array_key_exists($key, $data)) {
                        $record->$key = $data[$key];
                    } else {
                        $record->$key = '';
                    }
            }
        }

        // Save the record to the database
        if ($record->save() === FALSE) {
            $errors = $record->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }

        // Commit the transaction and display success message
        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->db->commit();
    }

    /**
     * Deletes a record from the specified table.
     *
     * @return void
     */
    public function deleteAction(): void
    {
        $table     = $this->request->get('table');
        $className = $this->getClassName($table);
        // If the class name is empty, return
        if(empty($className)) {
            $this->view->success = false;
            return;
        }
        $id     = $this->request->get('id');
        $record = $className::findFirstById($id);

        // If the record exists and deletion fails, display error message
        if ($record !== null && ! $record->delete()) {
            $this->flash->error(implode('<br>', $record->getMessages()));
            $this->view->success = false;
            return;
        }

        // Set success flag to true
        $this->view->success = true;
    }

    /**

    Retrieves the description of tables.

    @return array The table descriptions.
     */
    private function getTablesDescription():array
    {
        return [];
    }

    /**
     * Saves the data of a table row to the database.
     *
     * @return void
     */
    public function saveTableDataAction():void
    {
        $data       = $this->request->getPost();
        $tableName  = $data['pbx-table-id']??'';

        $className = $this->getClassName($tableName);

        // If the class name is empty, return
        if(empty($className)){
            return;
        }
        $rowId      = $data['pbx-row-id']??'';

        // If the row ID is empty, set success flag to false and return
        if(empty($rowId)){
            $this->view->success = false;
            return;
        }
        $this->db->begin();
        $rowData = $className::findFirst('id="'.$rowId.'"');
        if(!$rowData){
            $rowData = new $className();
        }

        // Update each field of the row data
        foreach ($rowData as $key => $value) {
            if($key === 'id'){
                continue;
            }
            if (array_key_exists($key, $data)) {
                $rowData->writeAttribute($key, $data[$key]);
            }
        }
        // Save the row data
        if ($rowData->save() === FALSE) {
            $errors = $rowData->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }

        // Set the data and success flag for the view
        $this->view->data = ['pbx-row-id'=>$rowId, 'newId'=>$rowData->id, 'pbx-table-id' => $data['pbx-table-id']];
        $this->view->success = true;
        $this->db->commit();

    }

    /**
     * Retrieves the class name for the given table name.
     *
     * @param string $tableName The name of the table.
     * @return string The class name for the table.
     */
    private function getClassName($tableName):string
    {
        if(empty($tableName)){
            return '';
        }
        $className = "Modules\ModuleAutoDialerManage\Models\\$tableName";
        if(!class_exists($className)){
            $className = '';
        }
        return $className;
    }

    /**
     * Updates the priority values for the records in a table.
     *
     * @return void
     */
    public function changePriorityAction(): void
    {
        $this->view->disable();
        $result = true;

        if ( ! $this->request->isPost()) {
            return;
        }
        $priorityTable = $this->request->getPost();
        $tableName     = $this->request->get('table');
        $className = $this->getClassName($tableName);
        // If the class name is empty, display error message and return
        if(empty($className)){
            echo "Table not found: $tableName";
            return;
        }
        $rules = $className::find();
        foreach ($rules as $rule){
            if (array_key_exists ( $rule->id, $priorityTable)){
                $rule->priority = $priorityTable[$rule->id];
                $result         .= $rule->update();
            }
        }
        echo json_encode($result);
    }
}