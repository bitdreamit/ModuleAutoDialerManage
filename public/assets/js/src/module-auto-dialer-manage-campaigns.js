/* Bit Dream IT extension: Campaigns list controller */
(function () {
    'use strict';
    var DATA = window.__CAMPAIGNS_DATA__ || {};
    var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';

    new Vue({
        el: '#campaigns-app',
        delimiters: ["<%", "%>"],
        data: {
            apiBaseUrl: apiBaseUrl,
            campaigns: [],
            filters: {q: '', state: '', type: ''},
            sortField: 'id',
            sortDir: 'desc'
        },
        computed: {
            filteredCampaigns: function () {
                var self = this;
                var list = this.campaigns.slice();
                if (this.filters.q) {
                    var q = this.filters.q.toLowerCase();
                    list = list.filter(function (c) {
                        return (c.name || '').toLowerCase().indexOf(q) >= 0
                            || (c.description || '').toLowerCase().indexOf(q) >= 0
                            || String(c.id).indexOf(q) >= 0;
                    });
                }
                if (this.filters.state !== '') {
                    list = list.filter(function (c) { return String(c.state) === self.filters.state; });
                }
                if (this.filters.type !== '') {
                    list = list.filter(function (c) { return c.innerNumType === self.filters.type; });
                }
                list.sort(function (a, b) {
                    var va = a[self.sortField], vb = b[self.sortField];
                    if (va === vb) return 0;
                    var cmp = (va > vb) ? 1 : -1;
                    return self.sortDir === 'asc' ? cmp : -cmp;
                });
                return list;
            }
        },
        mounted: function () {
            this.loadCampaigns();
        },
        methods: {
            loadCampaigns: function () {
                var self = this;
                fetch(this.apiBaseUrl + '/task', {credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j && j.success && j.data && j.data.results) {
                            self.campaigns = j.data.results;
                        }
                    })
                    .catch(function (e) { console.error('load error', e); });
            },
            togglePause: function (c) {
                var newState = (String(c.state) === '2') ? 0 : 2;
                fetch(this.apiBaseUrl + '/task/' + c.id, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({state: newState})
                }).then(function (r) { return r.json(); })
                  .then(function () { c.state = String(newState); });
            },
            confirmDelete: function (c) {
                if (!confirm('Delete campaign "' + c.name + '" (ID: ' + c.id + ')?\n\nThis will delete the campaign AND all its call results. This cannot be undone.')) return;
                var self = this;
                fetch(this.apiBaseUrl + '/task/' + c.id, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); })
                  .then(function (j) {
                      if (j.success) self.loadCampaigns();
                      else alert('Delete failed: ' + (j.messages || []).join(', '));
                  });
            },
            sortBy: function (field) {
                if (this.sortField === field) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortField = field;
                    this.sortDir = 'asc';
                }
            },
            sortIcon: function (field) {
                if (this.sortField !== field) return 'sort icon';
                return this.sortDir === 'asc' ? 'sort ascending icon' : 'sort descending icon';
            },
            stateColor: function (s) {
                s = String(s);
                return s === '0' ? 'green' : s === '1' ? 'grey' : s === '2' ? 'orange' : 'grey';
            },
            stateLabel: function (s) {
                s = String(s);
                return s === '0' ? 'open' : s === '1' ? 'closed' : s === '2' ? 'paused' : 'unknown';
            },
            formatTime: function (mins) {
                mins = parseInt(mins, 10) || 0;
                var h = Math.floor(mins / 60), m = mins % 60;
                return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            },
            formatSchedule: function (days) {
                if (!days) return 'Every day';
                var names = {1:'Mon',2:'Tue',3:'Wed',4:'Thu',5:'Fri',6:'Sat',7:'Sun'};
                return days.split(',').map(function (n) { return names[parseInt(n,10)] || n; }).join(', ');
            }
        }
    });
})();
