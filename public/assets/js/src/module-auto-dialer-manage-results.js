/* Bit Dream IT extension: Call results browser controller */
(function () {
    'use strict';
    var DATA = window.__RESULTS_DATA__ || {};
    var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';
    var tasks = DATA.tasks || [];

    new Vue({
        el: '#results-app',
        delimiters: ["<%", "%>"],
        data: {
            apiBaseUrl: apiBaseUrl,
            tasks: tasks,
            allResults: [],
            filters: {taskId: '', state: '', number: '', fromDate: '', toDate: ''},
            page: 1,
            pageSize: 50,
            _lastCursor: 0
        },
        computed: {
            filteredResults: function () {
                var self = this;
                var list = this.allResults.slice();
                if (this.filters.state) {
                    list = list.filter(function (r) { return r.outDialState === self.filters.state; });
                }
                if (this.filters.number) {
                    var q = this.filters.number.replace(/\D/g, '');
                    list = list.filter(function (r) { return (r.number || '').indexOf(q) >= 0; });
                }
                if (this.filters.taskId) {
                    var tid = parseInt(this.filters.taskId, 10);
                    list = list.filter(function (r) { return parseInt(r.task_id, 10) === tid; });
                }
                // Sort by time desc
                list.sort(function (a, b) { return (b.time || '').localeCompare(a.time || ''); });
                return list;
            },
            pagedResults: function () {
                var start = (this.page - 1) * this.pageSize;
                return this.filteredResults.slice(start, start + this.pageSize);
            },
            totalPages: function () {
                return Math.max(1, Math.ceil(this.filteredResults.length / this.pageSize));
            },
            pageNumbers: function () {
                var arr = [];
                var start = Math.max(1, this.page - 3);
                var end = Math.min(this.totalPages, this.page + 3);
                for (var i = start; i <= end; i++) arr.push(i);
                return arr;
            },
            answerRate: function () {
                if (this.allResults.length === 0) return 0;
                var answered = this.stateCount('ANSWER');
                return (answered / this.allResults.length * 100).toFixed(1);
            }
        },
        mounted: function () {
            $('.ui.dropdown').dropdown();
            this.loadInitial();
        },
        methods: {
            loadInitial: function () {
                // Load last 24h of results
                var self = this;
                var since = Math.floor(Date.now() / 1000) - 86400;
                if (this.filters.fromDate) {
                    since = Math.floor(new Date(this.filters.fromDate).getTime() / 1000);
                }
                fetch(this.apiBaseUrl + '/results/' + since, {credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.success && j.data && j.data.results) {
                            self.allResults = j.data.results;
                            self._lastCursor = Math.floor(Date.now() / 1000);
                        }
                    });
            },
            reload: function () {
                this.page = 1;
                this.loadInitial();
            },
            applyFilters: function () {
                this.page = 1;
            },
            resetFilters: function () {
                this.filters = {taskId: '', state: '', number: '', fromDate: '', toDate: ''};
                this.page = 1;
            },
            stateCount: function (s) {
                return this.allResults.filter(function (r) { return r.outDialState === s; }).length;
            },
            stateColor: function (s) {
                if (s === 'ANSWER') return 'green';
                if (s === 'NOANSWER' || s === 'BUSY') return 'red';
                if (s === 'DIAL') return 'orange';
                return 'grey';
            },
            campaignName: function (tid) {
                var t = this.tasks.find(function (x) { return parseInt(x.id, 10) === parseInt(tid, 10); });
                return t ? (t.id + ': ' + t.name) : tid;
            },
            prevPage: function () { if (this.page > 1) this.page--; },
            nextPage: function () { if (this.page < this.totalPages) this.page++; },
            exportCsv: function () {
                // Client-side CSV download of filteredResults
                var rows = [['time', 'campaign_id', 'number', 'state', 'duration_sec', 'attempt', 'cause', 'linked_id']];
                this.filteredResults.forEach(function (r) {
                    rows.push([r.time, r.task_id, r.number, r.outDialState, r.duration, r.attempt, r.cause, r.linkedId]);
                });
                var csv = rows.map(function (r) { return r.map(function (c) { return '"' + String(c || '').replace(/"/g, '""') + '"'; }).join(','); }).join('\n');
                var blob = new Blob(['\xEF\xBB\xBF' + csv], {type: 'text/csv;charset=utf-8'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url; a.download = 'call_results_filtered.csv';
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
            }
        }
    });
})();
