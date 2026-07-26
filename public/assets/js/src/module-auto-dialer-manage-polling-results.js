/* Bit Dream IT extension: Polling results browser controller */
(function () {
    'use strict';
    var DATA = window.__POLLING_RESULTS_DATA__ || {};
    var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';
    var pollings = DATA.pollings || [];

    new Vue({
        el: '#polling-results-app',
        delimiters: ["<%", "%>"],
        data: {
            apiBaseUrl: apiBaseUrl,
            pollings: pollings,
            allResults: [],
            filters: {pollingId: '', number: '', fromDate: ''},
            page: 1,
            pageSize: 50
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
                var start = (this.page - 1) * this.pageSize;
                return this.filteredResults.slice(start, start + this.pageSize);
            },
            totalPages: function () { return Math.max(1, Math.ceil(this.filteredResults.length / this.pageSize)); },
            pageNumbers: function () {
                var arr = [], start = Math.max(1, this.page - 3), end = Math.min(this.totalPages, this.page + 3);
                for (var i = start; i <= end; i++) arr.push(i);
                return arr;
            }
        },
        mounted: function () {
            $('.ui.dropdown').dropdown();
            this.loadInitial();
        },
        methods: {
            loadInitial: function () {
                var self = this;
                var since = Math.floor(Date.now() / 1000) - 86400 * 7; // last 7 days
                if (this.filters.fromDate) since = Math.floor(new Date(this.filters.fromDate).getTime() / 1000);
                fetch(this.apiBaseUrl + '/polling-results/' + since, {credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.success && j.data && j.data.results) self.allResults = j.data.results;
                    });
            },
            reload: function () { this.page = 1; this.loadInitial(); },
            applyFilters: function () { this.page = 1; },
            resetFilters: function () {
                this.filters = {pollingId: '', number: '', fromDate: ''};
                this.page = 1;
            },
            pollingName: function (pid) {
                var p = this.pollings.find(function (x) { return parseInt(x.id, 10) === parseInt(pid, 10); });
                return p ? p.name : pid;
            },
            prevPage: function () { if (this.page > 1) this.page--; },
            nextPage: function () { if (this.page < this.totalPages) this.page++; }
        }
    });
})();
