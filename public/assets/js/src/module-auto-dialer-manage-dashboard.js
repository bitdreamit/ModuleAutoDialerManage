/* Bit Dream IT extension: Live dashboard controller
 * Handles TWO view modes (auto-detected by which boot data is present):
 *   1. Overview  — grid of campaign cards  (window.__DASHBOARD_DATA__)
 *   2. Detail    — single campaign drill-down (window.__DASHBOARD_DETAIL_DATA__)
 *
 * Polls the REST API every 4 seconds and updates the Vue-rendered UI.
 */
(function () {
    'use strict';

    // =====================================================================
    // Shared helpers
    // =====================================================================

    function mapTaskToCampaign(t) {
        return {
            task_id: t.id,
            name: t.name,
            state: parseInt(t.state, 10) || 0,
            in_progress: 0,
            max_channels: parseInt(t.maxCountChannels, 10) || 1,
            total_dialed: 0,
            amd_enabled: parseInt(t.amdEnabled, 10) === 1,
            progress_percent: 0
        };
    }

    function stateIcon(s) {
        return s === 0 ? 'green circle icon' :
               s === 1 ? 'grey circle icon' :
               s === 2 ? 'orange pause circle icon' : 'icon';
    }
    function stateLabel(s) {
        return ['open', 'closed', 'paused'][s] || 'unknown';
    }
    function stateColor(s) {
        return s === 0 ? 'green' :
               s === 1 ? 'grey' :
               s === 2 ? 'orange' : 'grey';
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

    // =====================================================================
    // MODE 1: Overview (card grid)
    // =====================================================================

    function bootOverview() {
        var DATA = window.__DASHBOARD_DATA__ || {};
        var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';
        var initialTasks = DATA.initialTasks || [];

        var app = new Vue({
            el: '#dashboard-app',
            delimiters: ["<%", "%>"],
            data: {
                apiBaseUrl: apiBaseUrl,
                autoRefresh: true,
                refreshInterval: 4000,
                loading: false,
                campaigns: initialTasks.map(mapTaskToCampaign),
                agents: [],
                recentCalls: [],
                lastResultsChangeTime: Math.floor(Date.now() / 1000) - 86400,
                _timer: null
            },
            mounted: function () {
                var self = this;
                this.refreshAll();
                this._timer = setInterval(function () {
                    if (self.autoRefresh) self.refreshAll();
                }, this.refreshInterval);
            },
            beforeDestroy: function () {
                if (this._timer) clearInterval(this._timer);
            },
            methods: {
                refreshAll: function () {
                    this.fetchCampaignStatuses();
                    this.fetchAgentsStatus();
                    this.fetchRecentCalls();
                },
                fetchCampaignStatuses: function () {
                    var self = this;
                    this.campaigns.forEach(function (c) {
                        fetch(self.apiBaseUrl + '/task/' + c.task_id + '/status', {credentials: 'same-origin'})
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                if (j && j.success && j.data) {
                                    c.in_progress = j.data.in_progress;
                                    c.max_channels = j.data.max_channels;
                                    c.total_dialed = j.data.total_dialed;
                                    c.amd_enabled = j.data.amd_enabled;
                                    c.state = j.data.state;
                                    c.progress_percent = Math.min(100, c.total_dialed > 0 ? 100 : 0);
                                }
                            })
                            .catch(function (e) { console.warn('status fetch failed for', c.task_id, e); });
                    });
                },
                fetchAgentsStatus: function () {
                    var self = this;
                    fetch(self.apiBaseUrl + '/agents-status', {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data) {
                                self.agents = j.data.agents || [];
                            }
                        })
                        .catch(function (e) { console.warn('agents fetch failed', e); });
                },
                fetchRecentCalls: function () {
                    var self = this;
                    fetch(self.apiBaseUrl + '/results/' + this.lastResultsChangeTime, {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data && j.data.results) {
                                var newOnes = j.data.results.reverse().slice(0, 20);
                                newOnes.forEach(function (r) {
                                    self.recentCalls.unshift(r);
                                });
                                if (self.recentCalls.length > 20) {
                                    self.recentCalls = self.recentCalls.slice(0, 20);
                                }
                                if (j.data.results.length > 0) {
                                    self.lastResultsChangeTime = Math.floor(Date.now() / 1000);
                                }
                            }
                        })
                        .catch(function (e) { console.warn('results fetch failed', e); });
                },
                togglePause: function (c) {
                    var newState = c.state === 2 ? 0 : 2;
                    fetch(this.apiBaseUrl + '/task/' + c.task_id, {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({state: newState})
                    }).then(function () { c.state = newState; });
                },
                stateIcon: stateIcon,
                stateLabel: stateLabel,
                stateColor: stateColor,
                agentColor: agentColor,
                agentIcon: agentIcon,
                agentStateColor: agentStateColor,
                callStateColor: callStateColor
            }
        });
    }

    // =====================================================================
    // MODE 2: Detail (single campaign drill-down)
    // =====================================================================

    function bootDetail() {
        var DATA = window.__DASHBOARD_DETAIL_DATA__ || {};
        var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';
        var detailId = DATA.detailId || 0;
        var initialTask = DATA.task || null;

        new Vue({
            el: '#dashboard-detail-app',
            delimiters: ["<%", "%>"],
            data: {
                apiBaseUrl: apiBaseUrl,
                detailId: detailId,
                task: initialTask,
                autoRefresh: true,
                refreshInterval: 4000,
                loading: false,
                status: {in_progress: 0, max_channels: 0, total_dialed: 0, amd_enabled: 0, state: 0},
                summary: {total_dialed: 0, answered: 0, failed: 0, answer_rate: 0, avg_duration_sec: 0},
                agents: [],
                recentCalls: [],
                pollAnswers: [],
                lastResultsChangeTime: Math.floor(Date.now() / 1000) - 86400,
                lastPollingChangeTime: Math.floor(Date.now() / 1000) - 86400,
                _timer: null
            },
            computed: {
                progressPercent: function () {
                    var total = this.summary.total_dialed || 0;
                    return total > 0 ? Math.min(100, Math.round((this.summary.answered / total) * 100)) : 0;
                }
            },
            mounted: function () {
                var self = this;
                this.refreshAll();
                this._timer = setInterval(function () {
                    if (self.autoRefresh) self.refreshAll();
                }, this.refreshInterval);
            },
            beforeDestroy: function () {
                if (this._timer) clearInterval(this._timer);
            },
            methods: {
                refreshAll: function () {
                    if (!this.task) return;
                    this.fetchStatus();
                    this.fetchSummary();
                    this.fetchAgentsStatus();
                    this.fetchRecentCalls();
                    if (this.task.innerNumType === 'polling') {
                        this.fetchPollAnswers();
                    }
                },
                fetchStatus: function () {
                    var self = this;
                    fetch(this.apiBaseUrl + '/task/' + this.detailId + '/status', {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data) {
                                self.status = j.data;
                                if (self.task) self.task.state = j.data.state;
                            }
                        })
                        .catch(function (e) { console.warn('status fetch failed', e); });
                },
                fetchSummary: function () {
                    var self = this;
                    fetch(this.apiBaseUrl + '/task/' + this.detailId + '/summary', {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data) self.summary = j.data;
                        })
                        .catch(function (e) { console.warn('summary fetch failed', e); });
                },
                fetchAgentsStatus: function () {
                    var self = this;
                    fetch(self.apiBaseUrl + '/agents-status', {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data) {
                                self.agents = j.data.agents || [];
                            }
                        })
                        .catch(function (e) { console.warn('agents fetch failed', e); });
                },
                fetchRecentCalls: function () {
                    var self = this;
                    fetch(self.apiBaseUrl + '/results/' + this.lastResultsChangeTime, {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data && j.data.results) {
                                // Filter to this campaign only
                                var mine = j.data.results.filter(function (r) {
                                    return parseInt(r.task_id, 10) === parseInt(self.detailId, 10);
                                });
                                mine.reverse().slice(0, 50).forEach(function (r) {
                                    self.recentCalls.unshift(r);
                                });
                                if (self.recentCalls.length > 50) {
                                    self.recentCalls = self.recentCalls.slice(0, 50);
                                }
                                if (j.data.results.length > 0) {
                                    self.lastResultsChangeTime = Math.floor(Date.now() / 1000);
                                }
                            }
                        })
                        .catch(function (e) { console.warn('results fetch failed', e); });
                },
                fetchPollAnswers: function () {
                    var self = this;
                    fetch(self.apiBaseUrl + '/polling-results/' + this.lastPollingChangeTime, {credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.success && j.data && j.data.results) {
                                var mine = j.data.results.filter(function (r) {
                                    return parseInt(r.pollingId, 10) === parseInt(self.task.innerNum, 10);
                                });
                                mine.reverse().slice(0, 50).forEach(function (r) {
                                    self.pollAnswers.unshift(r);
                                });
                                if (self.pollAnswers.length > 50) {
                                    self.pollAnswers = self.pollAnswers.slice(0, 50);
                                }
                                if (j.data.results.length > 0) {
                                    self.lastPollingChangeTime = Math.floor(Date.now() / 1000);
                                }
                            }
                        })
                        .catch(function (e) { console.warn('polling fetch failed', e); });
                },
                setState: function (newState) {
                    var self = this;
                    fetch(this.apiBaseUrl + '/task/' + this.detailId, {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({state: newState})
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (self.task) self.task.state = newState;
                    });
                },
                stateIcon: stateIcon,
                stateLabel: stateLabel,
                stateColor: stateColor,
                agentColor: agentColor,
                agentIcon: agentIcon,
                agentStateColor: agentStateColor,
                callStateColor: callStateColor,
                scheduleLabel: function (days) {
                    if (!days) return 'Every day';
                    var names = {1:'Mon',2:'Tue',3:'Wed',4:'Thu',5:'Fri',6:'Sat',7:'Sun'};
                    var parts = days.split(',').map(function (n) { return names[parseInt(n,10)] || n; });
                    return parts.join(', ');
                }
            }
        });
    }

    // =====================================================================
    // Auto-detect mode and boot
    // =====================================================================

    if (document.getElementById('dashboard-app')) {
        bootOverview();
    } else if (document.getElementById('dashboard-detail-app')) {
        bootDetail();
    }
})();
