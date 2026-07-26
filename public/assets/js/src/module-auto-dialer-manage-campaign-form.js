/* Bit Dream IT extension: Campaign create/edit form controller */
(function () {
    'use strict';
    var DATA = window.__CAMPAIGN_FORM_DATA__ || {};
    var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';
    var taskId = DATA.taskId || '';
    var existing = DATA.task || null;

    // Convert time-start/end (minutes from midnight) → "HH:MM" string
    function minsToTime(mins) {
        mins = parseInt(mins, 10) || 0;
        var h = Math.floor(mins / 60), m = mins % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }
    function timeToMins(t) {
        var parts = String(t || '0:0').split(':');
        return (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0);
    }

    var defaultForm = {
        crmId: '', name: '', description: '',
        state: '0',
        innerNum: '', innerNumType: 'exten',
        maxCountChannels: 1, dialPrefix: '',
        isCallback: 0, isCallbackBool: false,
        timeStart: 540, timeEnd: 1080,
        scheduleDays: '',
        maxAttempt: 1, tryInterval: 60,
        attemptUntilSignal: 0, attemptUntilSignalBool: false,
        amdEnabled: 0, amdEnabledBool: false,
        callbackUrl: '',
        numbers: []
    };

    var form = Object.assign({}, defaultForm);
    if (existing) {
        Object.keys(form).forEach(function (k) {
            if (existing[k] !== undefined && existing[k] !== null) form[k] = existing[k];
        });
        // Convert ints to booleans for toggle checkboxes
        form.isCallbackBool = parseInt(form.isCallback, 10) === 1;
        form.attemptUntilSignalBool = parseInt(form.attemptUntilSignal, 10) === 1;
        form.amdEnabledBool = parseInt(form.amdEnabled, 10) === 1;
        form.state = String(form.state);
    }

    new Vue({
        el: '#campaign-form-app',
        delimiters: ["<%", "%>"],
        data: {
            apiBaseUrl: apiBaseUrl,
            taskId: taskId,
            form: form,
            startTimeStr: minsToTime(form.timeStart),
            endTimeStr: minsToTime(form.timeEnd),
            singleNumber: {number: '', name: '', params: ''},
            bulkNumbers: '',
            csvUploadResult: '',
            saving: false,
            successMsg: '',
            errorMsg: ''
        },
        mounted: function () {
            // Init semantic-ui tabs + dropdowns
            $('.menu .item').tab();
            $('#scheduleDaysDropdown').dropdown({
                onChange: (function (val) { this.form.scheduleDays = val || ''; }).bind(this)
            });
            $('.ui.dropdown').dropdown();
            $('.ui.checkbox').checkbox();
        },
        methods: {
            updateTimeStart: function () { this.form.timeStart = timeToMins(this.startTimeStr); },
            updateTimeEnd: function () { this.form.timeEnd = timeToMins(this.endTimeStr); },
            addSingleNumber: function () {
                var n = (this.singleNumber.number || '').toString().replace(/\D/g, '');
                if (!n) { alert('Number is required'); return; }
                var entry = {number: n};
                if (this.singleNumber.name) entry.name = this.singleNumber.name;
                if (this.singleNumber.params) {
                    // Try JSON parse, else wrap as text
                    try {
                        entry.params = JSON.parse(this.singleNumber.params);
                    } catch (e) {
                        entry.params = {speach: this.singleNumber.params};
                    }
                }
                this.form.numbers.push(entry);
                this.singleNumber = {number: '', name: '', params: ''};
            },
            parseBulkNumbers: function () {
                var lines = this.bulkNumbers.split('\n');
                var added = 0, skipped = 0;
                lines.forEach(function (line) {
                    line = line.trim();
                    if (!line) { skipped++; return; }
                    var parts = line.split(',').map(function (p) { return p.trim(); });
                    var n = (parts[0] || '').replace(/\D/g, '');
                    if (!n) { skipped++; return; }
                    var entry = {number: n};
                    if (parts[1]) entry.name = parts[1];
                    if (parts[2]) entry.params = {speach: parts[2]};
                    this.form.numbers.push(entry);
                    added++;
                }, this);
                this.bulkNumbers = '';
                alert('Added ' + added + ' number(s), skipped ' + skipped + ' empty line(s).');
            },
            uploadCsv: function (ev) {
                var self = this;
                var file = ev.target.files[0];
                if (!file) return;
                // If editing existing campaign, upload directly to the import endpoint
                if (this.taskId) {
                    var fd = new FormData();
                    fd.append('file', file);
                    fetch(this.apiBaseUrl + '/task/' + this.taskId + '/import-csv', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.success) {
                            self.csvUploadResult = 'Imported ' + (j.data.rows_added || 0) + ' rows directly into campaign.';
                        } else {
                            self.csvUploadResult = 'Import error: ' + (j.messages || []).join(', ');
                        }
                    });
                    return;
                }
                // New campaign — parse CSV client-side and add to the numbers array
                var reader = new FileReader();
                reader.onload = function (e) {
                    var text = e.target.result;
                    var lines = text.split(/\r?\n/);
                    if (lines.length < 2) { self.csvUploadResult = 'CSV has no data rows.'; return; }
                    var header = lines[0].toLowerCase().split(',').map(function (s) { return s.trim(); });
                    var numIdx = header.indexOf('number');
                    var nameIdx = header.indexOf('name');
                    var paramsIdx = header.indexOf('params');
                    if (numIdx < 0) { self.csvUploadResult = 'CSV must have a "number" column.'; return; }
                    var added = 0;
                    for (var i = 1; i < lines.length; i++) {
                        var line = lines[i].trim();
                        if (!line) continue;
                        var cols = line.split(',');
                        var n = (cols[numIdx] || '').replace(/\D/g, '');
                        if (!n) continue;
                        var entry = {number: n};
                        if (nameIdx >= 0 && cols[nameIdx]) entry.name = cols[nameIdx].trim();
                        if (paramsIdx >= 0 && cols[paramsIdx]) {
                            try { entry.params = JSON.parse(cols[paramsIdx]); }
                            catch (e) { entry.params = {speach: cols[paramsIdx].trim()}; }
                        }
                        self.form.numbers.push(entry);
                        added++;
                    }
                    self.csvUploadResult = 'Parsed ' + added + ' number(s) from CSV.';
                };
                reader.readAsText(file);
            },
            removeNumber: function (idx) { this.form.numbers.splice(idx, 1); },
            clearNumbers: function () {
                if (confirm('Remove all ' + this.form.numbers.length + ' numbers?')) {
                    this.form.numbers = [];
                }
            },
            formatParams: function (p) {
                if (!p) return '—';
                if (typeof p === 'string') return p;
                try { return JSON.stringify(p); } catch (e) { return String(p); }
            },
            saveCampaign: function () {
                var self = this;
                if (!this.form.name) { this.errorMsg = 'Name is required'; return; }
                if (!this.form.crmId) { this.errorMsg = 'CRM ID is required'; return; }
                if (!this.form.innerNum) { this.errorMsg = 'Inner number is required'; return; }
                if (this.form.numbers.length === 0 && !this.taskId) {
                    if (!confirm('You have not added any phone numbers. Save the campaign anyway? (You can add numbers later via CSV import.)')) return;
                }
                // Convert booleans back to ints
                var payload = Object.assign({}, this.form);
                payload.isCallback = payload.isCallbackBool ? 1 : 0;
                payload.attemptUntilSignal = payload.attemptUntilSignalBool ? 1 : 0;
                payload.amdEnabled = payload.amdEnabledBool ? 1 : 0;
                delete payload.isCallbackBool;
                delete payload.attemptUntilSignalBool;
                delete payload.amdEnabledBool;
                // For new campaign, include numbers. For edit, send only the task fields.
                if (this.taskId) {
                    delete payload.numbers;
                }
                payload.state = parseInt(payload.state, 10);
                payload.maxCountChannels = parseInt(payload.maxCountChannels, 10);
                payload.maxAttempt = parseInt(payload.maxAttempt, 10);
                payload.tryInterval = parseInt(payload.tryInterval, 10);
                payload.timeStart = parseInt(payload.timeStart, 10);
                payload.timeEnd = parseInt(payload.timeEnd, 10);

                this.saving = true;
                this.errorMsg = '';
                this.successMsg = '';
                var url = this.apiBaseUrl + '/task' + (this.taskId ? '/' + this.taskId : '');
                var method = this.taskId ? 'PUT' : 'POST';
                fetch(url, {
                    method: method,
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    self.saving = false;
                    if (j.success) {
                        self.successMsg = self.taskId
                            ? 'Campaign updated.'
                            : 'Campaign created (ID: ' + (j.data.id || j.data.taskId || '?') + ').';
                        if (!self.taskId && j.data && (j.data.id || j.data.taskId)) {
                            var newId = j.data.id || j.data.taskId;
                            setTimeout(function () {
                                window.location.href = '/admin-cabinet/module-auto-dialer-manage/campaignForm/' + newId;
                            }, 1200);
                        }
                    } else {
                        self.errorMsg = (j.messages || ['Unknown error']).join(', ');
                    }
                })
                .catch(function (e) {
                    self.saving = false;
                    self.errorMsg = 'Network error: ' + e.message;
                });
            }
        }
    });
})();
