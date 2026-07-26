/* Bit Dream IT extension: Index page tab Vue apps bootstrapper
 *
 * Boots the new Vue-powered tabs that were added to index.volt:
 *   - #campaigns-app      — campaigns list
 *   - #dashboard-app      — live dashboard overview
 *   - #results-app        — call results browser
 *   - #polling-results-app — IVR answers browser
 *   - #audio-app          — audio files management
 *   - #blacklist-app      — DNC blacklist
 *   - #apiguide-app       — compact API guide
 *
 * Each app is independent (its own Vue instance). They auto-mount when
 * the page loads. Tabs that need fresh data fetch it on first activation
 * via the Semantic UI `.tab()` 'onVisible' callback.
 */
(function () {
    'use strict';

    var DATA = window.__INDEX_TABS_DATA__ || {};
    var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';
    var pbxHost = DATA.pbxHost || '';
    var tasks = DATA.tasks || [];
    var pollings = DATA.pollings || [];

    // =====================================================================
    // Shared helpers
    // =====================================================================
    function stateColor(s) {
        s = String(s);
        return s === '0' ? 'green' : s === '1' ? 'grey' : s === '2' ? 'orange' : 'grey';
    }
    function stateLabel(s) {
        s = String(s);
        return s === '0' ? 'open' : s === '1' ? 'closed' : s === '2' ? 'paused' : 'unknown';
    }
    function stateIcon(s) {
        s = String(s);
        return s === '0' ? 'green circle icon' :
               s === '1' ? 'grey circle icon' :
               s === '2' ? 'orange pause circle icon' : 'icon';
    }
    function agentColor(s) {
        if (s === 'Idle') return '#21ba45';
        if (s === 'Up') return '#2185d0';
        if (s === 'Ringing') return '#db2828';
        return '#767676';
    }
    function agentIcon(s) {
        if (s === 'Idle') return 'green circle outline icon';
        if (s === 'Up') return 'blue phone icon';
        if (s === 'Ringing') return 'red bell icon';
        return 'grey minus circle icon';
    }
    function agentStateColor(s) {
        if (s === 'Idle') return 'green';
        if (s === 'Up') return 'blue';
        if (s === 'Ringing') return 'red';
        return 'grey';
    }
    function callStateColor(s) {
        if (s === 'ANSWER') return 'green';
        if (s === 'NOANSWER' || s === 'BUSY') return 'red';
        if (s === 'DIAL') return 'orange';
        return 'grey';
    }
    function formatTime(mins) {
        mins = parseInt(mins, 10) || 0;
        var h = Math.floor(mins / 60), m = mins % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }
    function formatSchedule(days) {
        if (!days) return 'Every day';
        var names = {1:'Mon',2:'Tue',3:'Wed',4:'Thu',5:'Fri',6:'Sat',7:'Sun'};
        return days.split(',').map(function (n) { return names[parseInt(n,10)] || n; }).join(', ');
    }
    function methodColor(m) {
        return { GET: 'blue', POST: 'green', PUT: 'orange', DELETE: 'red' }[m] || 'grey';
    }

    // =====================================================================
    // Tab 1: Campaigns
    // =====================================================================
    function bootCampaigns() {
        var el = document.getElementById('campaigns-app');
        if (!el) return;
        new Vue({
            el: el,
            delimiters: ["<%", "%>"],
            data: {
                campaigns: [],
                filters: {q: '', state: '', type: ''}
            },
            computed: {
                filteredCampaigns: function () {
                    var self = this;
                    var list = this.campaigns.slice();
                    if (this.filters.q) {
                        var q = this.filters.q.toLowerCase();
                        list = list.filter(function (c) {
                            return (c.name || '').toLowerCase().indexOf(q) >= 0
                                || String(c.id).indexOf(q) >= 0;
                        });
                    }
                    if (this.filters.state !== '') {
                        list = list.filter(function (c) { return String(c.state) === self.filters.state; });
                    }
                    if (this.filters.type !== '') {
                        list = list.filter(function (c) { return c.innerNumType === self.filters.type; });
                    }
                    return list;
                }
            },
            mounted: function () { this.loadCampaigns(); },
            methods: {
                loadCampaigns: function () {
                    var self = this;
                    fetch(apiBaseUrl + '/task', {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data && j.data.results) self.campaigns = j.data.results;
                        });
                },
                showNewForm: function () {
                    window.location.href = '/admin-cabinet/module-auto-dialer-manage/campaignForm';
                },
                applyFilters: function () { /* computed handles it */ },
                togglePause: function (c) {
                    var newState = (String(c.state) === '2') ? 0 : 2;
                    fetch(apiBaseUrl + '/task/' + c.id, {
                        method: 'PUT', credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({state: newState})
                    }).then(function () { c.state = String(newState); });
                },
                confirmDelete: function (c) {
                    if (!confirm('Delete campaign "' + c.name + '"? This cannot be undone.')) return;
                    var self = this;
                    fetch(apiBaseUrl + '/task/' + c.id, {method: 'DELETE', credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) { if (j.success) self.loadCampaigns(); });
                },
                stateColor: stateColor, stateLabel: stateLabel,
                formatTime: formatTime, formatSchedule: formatSchedule
            }
        });
    }

    // =====================================================================
    // Tab 2: Dashboard overview
    // =====================================================================
    function bootDashboard() {
        var el = document.getElementById('dashboard-app');
        if (!el) return;
        function mapTaskToCampaign(t) {
            return {
                task_id: t.id, name: t.name, state: parseInt(t.state, 10) || 0,
                in_progress: 0, max_channels: parseInt(t.maxCountChannels, 10) || 1,
                total_dialed: 0, amd_enabled: parseInt(t.amdEnabled, 10) === 1,
                progress_percent: 0
            };
        }
        new Vue({
            el: el,
            delimiters: ["<%", "%>"],
            data: {
                campaigns: tasks.map(mapTaskToCampaign),
                agents: [],
                autoRefresh: true,
                loading: false,
                _timer: null
            },
            mounted: function () {
                var self = this;
                this.refreshAll();
                this._timer = setInterval(function () {
                    if (self.autoRefresh) self.refreshAll();
                }, 4000);
            },
            beforeDestroy: function () { if (this._timer) clearInterval(this._timer); },
            methods: {
                refreshAll: function () {
                    this.fetchStatuses();
                    this.fetchAgents();
                },
                fetchStatuses: function () {
                    var self = this;
                    this.campaigns.forEach(function (c) {
                        fetch(apiBaseUrl + '/task/' + c.task_id + '/status', {credentials: 'same-origin'})
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                if (j && j.success && j.data) {
                                    c.in_progress = j.data.in_progress;
                                    c.total_dialed = j.data.total_dialed;
                                    c.state = j.data.state;
                                }
                            });
                    });
                },
                fetchAgents: function () {
                    var self = this;
                    fetch(apiBaseUrl + '/agents-status', {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data) self.agents = j.data.agents || [];
                        });
                },
                togglePause: function (c) {
                    var newState = c.state === 2 ? 0 : 2;
                    fetch(apiBaseUrl + '/task/' + c.task_id, {
                        method: 'PUT', credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({state: newState})
                    }).then(function () { c.state = newState; });
                },
                stateIcon: stateIcon, stateLabel: stateLabel, stateColor: stateColor,
                agentColor: agentColor, agentIcon: agentIcon, agentStateColor: agentStateColor
            }
        });
    }

    // =====================================================================
    // Tab 3: Call results
    // =====================================================================
    function bootResults() {
        var el = document.getElementById('results-app');
        if (!el) return;
        new Vue({
            el: el,
            delimiters: ["<%", "%>"],
            data: {
                apiBaseUrl: apiBaseUrl,
                tasks: tasks,
                allResults: [],
                filters: {taskId: '', state: '', number: ''},
                page: 1, pageSize: 50
            },
            computed: {
                filteredResults: function () {
                    var self = this;
                    var list = this.allResults.slice();
                    if (this.filters.state) list = list.filter(function (r) { return r.outDialState === self.filters.state; });
                    if (this.filters.number) {
                        var q = this.filters.number.replace(/\D/g, '');
                        list = list.filter(function (r) { return (r.number || '').indexOf(q) >= 0; });
                    }
                    if (this.filters.taskId) {
                        var tid = parseInt(this.filters.taskId, 10);
                        list = list.filter(function (r) { return parseInt(r.task_id, 10) === tid; });
                    }
                    list.sort(function (a, b) { return (b.time || '').localeCompare(a.time || ''); });
                    return list;
                },
                pagedResults: function () {
                    var s = (this.page - 1) * this.pageSize;
                    return this.filteredResults.slice(s, s + this.pageSize);
                },
                answerRate: function () {
                    if (this.allResults.length === 0) return 0;
                    return (this.stateCount('ANSWER') / this.allResults.length * 100).toFixed(1);
                }
            },
            methods: {
                loadInitial: function () {
                    var self = this;
                    var since = Math.floor(Date.now() / 1000) - 86400;
                    fetch(apiBaseUrl + '/results/' + since, {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j.success && j.data && j.data.results) self.allResults = j.data.results;
                        });
                },
                applyFilters: function () { this.page = 1; },
                stateCount: function (s) { return this.allResults.filter(function (r) { return r.outDialState === s; }).length; },
                stateColor: callStateColor
            }
        });
    }

    // =====================================================================
    // Tab 4: IVR answers
    // =====================================================================
    function bootPollingResults() {
        var el = document.getElementById('polling-results-app');
        if (!el) return;
        new Vue({
            el: el,
            delimiters: ["<%", "%>"],
            data: {
                pollings: pollings,
                allResults: [],
                filters: {pollingId: '', number: ''},
                page: 1, pageSize: 50
            },
            computed: {
                filteredResults: function () {
                    var self = this;
                    var list = this.allResults.slice();
                    if (this.filters.pollingId) {
                        var pid = parseInt(this.filters.pollingId, 10);
                        list = list.filter(function (r) { return parseInt(r.pollingId, 10) === pid; });
                    }
                    if (this.filters.number) {
                        var q = this.filters.number.replace(/\D/g, '');
                        list = list.filter(function (r) { return (r.number || '').indexOf(q) >= 0; });
                    }
                    list.sort(function (a, b) { return (b.time || '').localeCompare(a.time || ''); });
                    return list;
                },
                pagedResults: function () {
                    var s = (this.page - 1) * this.pageSize;
                    return this.filteredResults.slice(s, s + this.pageSize);
                }
            },
            methods: {
                loadInitial: function () {
                    var self = this;
                    var since = Math.floor(Date.now() / 1000) - 86400 * 7;
                    fetch(apiBaseUrl + '/polling-results/' + since, {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j.success && j.data && j.data.results) self.allResults = j.data.results;
                        });
                },
                applyFilters: function () { this.page = 1; },
                pollingName: function (pid) {
                    var p = this.pollings.find(function (x) { return parseInt(x.id, 10) === parseInt(pid, 10); });
                    return p ? p.name : pid;
                }
            }
        });
    }

    // =====================================================================
    // Tab 5: Audio files
    // =====================================================================
    function bootAudio() {
        var el = document.getElementById('audio-app');
        if (!el) return;
        new Vue({
            el: el,
            delimiters: ["<%", "%>"],
            data: {
                audioFiles: [],
                uploadMsg: ''
            },
            mounted: function () { this.loadList(); },
            methods: {
                loadList: function () {
                    var self = this;
                    fetch(apiBaseUrl + '/audio', {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j.success && j.data) {
                                var files = Array.isArray(j.data) ? j.data : (j.data.files || j.data.results || []);
                                self.audioFiles = files.map(function (n) { return typeof n === 'string' ? {name: n} : n; });
                            }
                        });
                },
                audioUrl: function (name) {
                    return '/admin-cabinet/assets/img/cache/ModuleAutoDialerManage/audio/' + encodeURIComponent(name);
                },
                uploadFile: function (ev) {
                    var self = this;
                    var file = ev.target.files[0];
                    if (!file) return;
                    var fd = new FormData();
                    fd.append('file', file);
                    var xhr = new XMLHttpRequest();
                    xhr.addEventListener('load', function () {
                        try {
                            var j = JSON.parse(xhr.responseText);
                            if (j.success) { self.uploadMsg = 'Uploaded: ' + file.name; self.loadList(); }
                            else self.uploadMsg = 'Upload failed: ' + (j.messages || []).join(', ');
                        } catch (e) { self.uploadMsg = 'Upload failed: invalid response'; }
                    });
                    xhr.open('POST', apiBaseUrl + '/audio');
                    xhr.withCredentials = true;
                    xhr.send(fd);
                },
                deleteFile: function (f) {
                    if (!confirm('Delete "' + f.name + '"?')) return;
                    var self = this;
                    fetch(apiBaseUrl + '/audio/' + encodeURIComponent(f.name), {method: 'DELETE', credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) { if (j.success) self.loadList(); });
                }
            }
        });
    }

    // =====================================================================
    // Tab 6: DNC Blacklist
    // =====================================================================
    function bootBlacklist() {
        var el = document.getElementById('blacklist-app');
        if (!el) return;
        new Vue({
            el: el,
            delimiters: ["<%", "%>"],
            data: {
                addNumbers: '', addReason: '', addSource: 'manual', adding: false,
                entries: [], total: 0, limit: 50, offset: 0, searchQuery: ''
            },
            mounted: function () { this.loadList(); },
            methods: {
                loadList: function () {
                    var self = this;
                    var url = apiBaseUrl + '/blacklist?limit=' + this.limit + '&offset=' + this.offset;
                    if (this.searchQuery) url += '&q=' + encodeURIComponent(this.searchQuery);
                    fetch(url, {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data) {
                                self.entries = j.data.entries || [];
                                self.total = j.data.total || 0;
                            }
                        });
                },
                addNumbersToList: function () {
                    var self = this;
                    if (!this.addNumbers.trim()) return;
                    var numbers = this.addNumbers.split(/[\n,]/).map(function (n) { return n.trim(); }).filter(function (n) { return n.length > 0; });
                    this.adding = true;
                    fetch(apiBaseUrl + '/blacklist', {
                        method: 'POST', credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({numbers: numbers, reason: this.addReason, source: this.addSource})
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        self.adding = false;
                        if (j.success) {
                            alert('Added ' + j.data.added + ', skipped ' + j.data.skipped_duplicates + ' duplicate(s)');
                            self.addNumbers = ''; self.addReason = ''; self.loadList();
                        } else alert('Error: ' + (j.messages || []).join(', '));
                    });
                },
                deleteEntry: function (e) {
                    if (!confirm('Remove ' + e.number + '?')) return;
                    var self = this;
                    fetch(apiBaseUrl + '/blacklist/' + encodeURIComponent(e.number), {method: 'DELETE', credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) { if (j.success) self.loadList(); });
                },
                formatDate: function (ts) {
                    if (!ts) return '';
                    return new Date(parseInt(ts, 10) * 1000).toISOString().replace('T', ' ').substring(0, 19);
                }
            }
        });
    }

    // =====================================================================
    // Tab 7: API guide (compact)
    // =====================================================================
    function bootApiGuide() {
        var el = document.getElementById('apiguide-app');
        if (!el) return;
        var endpoints = [
            {method: 'GET',    path: '/task',                       purpose: 'List all campaigns'},
            {method: 'POST',   path: '/task',                       purpose: 'Create a new campaign'},
            {method: 'GET',    path: '/task/{id}',                  purpose: 'Get a single campaign'},
            {method: 'PUT',    path: '/task/{id}',                  purpose: 'Update campaign (pause/resume/close)'},
            {method: 'DELETE', path: '/task/{id}',                  purpose: 'Delete a campaign'},
            {method: 'GET',    path: '/task/{id}/status',           purpose: 'Live status (poll every 3-5s)'},
            {method: 'GET',    path: '/task/{id}/summary',          purpose: 'Campaign summary report'},
            {method: 'GET',    path: '/task/{id}/export',           purpose: 'CSV export of call results'},
            {method: 'POST',   path: '/task/{id}/import-csv',       purpose: 'Bulk import numbers from CSV'},
            {method: 'POST',   path: '/task/{id}/test-call',        purpose: 'Test-call single number'},
            {method: 'GET',    path: '/results/{changeTime}',       purpose: 'Call results (incremental sync)'},
            {method: 'GET',    path: '/polling-results/{changeTime}',purpose: 'IVR/poll answers (incremental sync)'},
            {method: 'GET',    path: '/agents-status',              purpose: 'Agent/extension live status'},
            {method: 'POST',   path: '/blacklist',                  purpose: 'Add to DNC blacklist'},
            {method: 'GET',    path: '/blacklist',                  purpose: 'List/search blacklist'},
            {method: 'DELETE', path: '/blacklist/{number}',         purpose: 'Remove from DNC'},
            {method: 'GET',    path: '/recording/{linkedId}',       purpose: 'Find recording file path'},
            {method: 'POST',   path: '/audio',                      purpose: 'Upload audio file'},
            {method: 'GET',    path: '/audio',                      purpose: 'List audio files'},
            {method: 'DELETE', path: '/audio/{name}',               purpose: 'Delete audio file'}
        ];
        new Vue({
            el: el,
            delimiters: ["<%", "%>"],
            data: { endpoints: endpoints },
            methods: { methodColor: methodColor }
        });
    }

    // =====================================================================
    // Boot all apps on DOM ready
    // =====================================================================
    function bootAll() {
        bootCampaigns();
        bootDashboard();
        bootResults();
        bootPollingResults();
        bootAudio();
        bootBlacklist();
        bootApiGuide();
        // Re-init Semantic UI dropdowns inside new tabs (the ones rendered by Vue)
        $('.menu .item').tab({
            onVisible: function () {
                // Re-init dropdowns when tab becomes visible (Vue may have rendered new ones)
                $(this).find('.ui.dropdown').dropdown();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAll);
    } else {
        bootAll();
    }
})();
