/* Bit Dream IT extension: In-module API Guide page controller
 * Just handles the tabs and the endpoint reference table.
 */
(function () {
    'use strict';

    var endpoints = [
        // Campaigns
        {method: 'GET',    path: '/task',                       purpose: 'List all campaigns',                          uiPage: 'Campaigns list'},
        {method: 'POST',   path: '/task',                       purpose: 'Create a new campaign',                       uiPage: 'Campaign form (new)'},
        {method: 'GET',    path: '/task/{id}',                  purpose: 'Get a single campaign',                       uiPage: 'Campaign form (edit)'},
        {method: 'PUT',    path: '/task/{id}',                  purpose: 'Update campaign (pause/resume/close/fields)', uiPage: 'Campaign form / list actions'},
        {method: 'DELETE', path: '/task/{id}',                  purpose: 'Delete a campaign',                           uiPage: 'Campaigns list (delete)'},
        {method: 'GET',    path: '/task/{id}/status',           purpose: 'Live status (poll every 3-5s)',               uiPage: 'Dashboard detail'},
        {method: 'GET',    path: '/task/{id}/summary',          purpose: 'Campaign summary report',                     uiPage: 'Dashboard detail'},
        {method: 'GET',    path: '/task/{id}/export',           purpose: 'CSV export of call results',                  uiPage: 'Campaigns list (CSV)'},
        {method: 'POST',   path: '/task/{id}/import-csv',       purpose: 'Bulk import numbers from CSV',                uiPage: 'Campaign form (CSV tab)'},
        {method: 'POST',   path: '/task/{id}/test-call',        purpose: 'Test-call single number',                     uiPage: 'Dashboard (future)'},
        {method: 'POST',   path: '/task-signal-close',          purpose: 'Batch close multiple campaigns',              uiPage: '— (API only)'},

        // Results
        {method: 'GET',    path: '/results/{changeTime}',       purpose: 'Call results (incremental sync)',             uiPage: 'Call results browser'},
        {method: 'GET',    path: '/polling-results/{changeTime}',purpose: 'IVR/poll answers (incremental sync)',        uiPage: 'IVR answers browser'},
        {method: 'GET',    path: '/agents-status',              purpose: 'Agent/extension live status',                 uiPage: 'Dashboard (both views)'},

        // Surveys / polling
        {method: 'GET',    path: '/polling',                    purpose: 'List all surveys',                            uiPage: 'Main module page'},
        {method: 'POST',   path: '/polling',                    purpose: 'Create a survey',                             uiPage: 'Modify polling form'},
        {method: 'GET',    path: '/polling/{id}',               purpose: 'Get a single survey',                         uiPage: 'Modify polling form (edit)'},
        {method: 'DELETE', path: '/polling/{id}',               purpose: 'Delete a survey',                             uiPage: 'Modify polling (delete)'},

        // Audio
        {method: 'POST',   path: '/audio',                      purpose: 'Upload an audio file',                        uiPage: 'Audio files page'},
        {method: 'GET',    path: '/audio',                      purpose: 'List audio files',                            uiPage: 'Audio files page'},
        {method: 'DELETE', path: '/audio/{name}',               purpose: 'Delete an audio file',                        uiPage: 'Audio files page (delete)'},

        // DNC blacklist
        {method: 'POST',   path: '/blacklist',                  purpose: 'Add number(s) to DNC',                        uiPage: 'DNC blacklist (add)'},
        {method: 'GET',    path: '/blacklist?limit=&offset=&q=',purpose: 'List/search blacklist (paginated)',           uiPage: 'DNC blacklist (list)'},
        {method: 'DELETE', path: '/blacklist/{number}',         purpose: 'Remove number from DNC',                      uiPage: 'DNC blacklist (delete)'},

        // Recording lookup
        {method: 'GET',    path: '/recording/{linkedId}',       purpose: 'Find recording file path by linkedId',        uiPage: 'Call results (recording icon)'},

        // Clients (CRUD)
        {method: 'POST',   path: '/client',                     purpose: 'Add a client',                                uiPage: '— (API only)'},
        {method: 'DELETE', path: '/client/{id}',                purpose: 'Delete a client',                             uiPage: '— (API only)'},
        {method: 'GET',    path: '/client-by-phone/{phone}',    purpose: 'Find client by phone',                        uiPage: '— (API only)'},

        // Misc
        {method: 'GET',    path: '/test',                       purpose: 'Health check',                                uiPage: '—'},
        {method: 'POST',   path: '/crm-test',                   purpose: 'Test CRM connection',                         uiPage: 'Main module page'},
        {method: 'POST',   path: '/upload-xls',                 purpose: 'Upload XLS numbers file',                     uiPage: 'Main module page'},
    ];

    function methodColor(m) {
        return {
            GET: 'blue',
            POST: 'green',
            PUT: 'orange',
            DELETE: 'red'
        }[m] || 'grey';
    }

    new Vue({
        el: '#api-guide-app',
        delimiters: ["<%", "%>"],
        data: {
            pbxHost: window.__API_GUIDE_DATA__ ? window.__API_GUIDE_DATA__.pbxHost : '',
            apiBaseUrl: window.__API_GUIDE_DATA__ ? window.__API_GUIDE_DATA__.apiBaseUrl : '/pbxcore/api/module-dialer-manage/v1',
            endpoints: endpoints
        },
        mounted: function () {
            // Init semantic-ui tabs
            $('.menu .item').tab();
        },
        methods: {
            methodColor: methodColor
        }
    });
})();
