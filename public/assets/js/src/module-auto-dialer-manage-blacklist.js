/* Bit Dream IT extension: DNC Blacklist controller */
(function () {
    'use strict';
    var DATA = window.__BLACKLIST_DATA__ || {};
    var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';

    new Vue({
        el: '#blacklist-app',
        delimiters: ["<%", "%>"],
        data: {
            apiBaseUrl: apiBaseUrl,
            addNumbers: '',
            addReason: '',
            addSource: 'manual',
            adding: false,
            entries: [],
            total: 0,
            limit: 50,
            offset: 0,
            searchQuery: ''
        },
        computed: {
            currentPage: function () {
                return Math.floor(this.offset / this.limit) + 1;
            }
        },
        mounted: function () {
            this.loadList();
        },
        methods: {
            loadList: function () {
                var self = this;
                var url = this.apiBaseUrl + '/blacklist?limit=' + this.limit + '&offset=' + this.offset;
                if (this.searchQuery) url += '&q=' + encodeURIComponent(this.searchQuery);
                fetch(url, {credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j && j.success && j.data) {
                            self.entries = j.data.entries || [];
                            self.total = j.data.total || 0;
                        }
                    })
                    .catch(function (e) { console.error('load error', e); });
            },
            addNumbersToList: function () {
                var self = this;
                if (!this.addNumbers.trim()) return;
                var numbers = this.addNumbers
                    .split(/[\n,]/)
                    .map(function (n) { return n.trim(); })
                    .filter(function (n) { return n.length > 0; });
                this.adding = true;
                fetch(this.apiBaseUrl + '/blacklist', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        numbers: numbers,
                        reason: this.addReason,
                        source: this.addSource
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    self.adding = false;
                    if (j && j.success) {
                        alert('Added ' + j.data.added + ' number(s), skipped ' + j.data.skipped_duplicates + ' duplicate(s).');
                        self.addNumbers = '';
                        self.addReason = '';
                        self.loadList();
                    } else {
                        alert('Error: ' + (j && j.messages ? j.messages.join(', ') : 'unknown'));
                    }
                })
                .catch(function (e) {
                    self.adding = false;
                    alert('Network error: ' + e.message);
                });
            },
            deleteEntry: function (e) {
                if (!confirm('Remove ' + e.number + ' from blacklist?')) return;
                var self = this;
                fetch(this.apiBaseUrl + '/blacklist/' + encodeURIComponent(e.number), {
                    method: 'DELETE',
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.success) self.loadList();
                    else alert('Failed: ' + (j && j.messages ? j.messages.join(', ') : 'unknown'));
                })
                .catch(function (err) { alert('Network error: ' + err.message); });
            },
            nextPage: function () {
                if (this.offset + this.limit < this.total) {
                    this.offset += this.limit;
                    this.loadList();
                }
            },
            prevPage: function () {
                if (this.offset > 0) {
                    this.offset = Math.max(0, this.offset - this.limit);
                    this.loadList();
                }
            },
            formatDate: function (ts) {
                if (!ts) return '';
                var d = new Date(parseInt(ts, 10) * 1000);
                return d.toISOString().replace('T', ' ').substring(0, 19);
            }
        }
    });
})();
