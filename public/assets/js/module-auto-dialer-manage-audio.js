/* Bit Dream IT extension: Audio files management controller */
(function () {
    'use strict';
    var DATA = window.__AUDIO_DATA__ || {};
    var apiBaseUrl = DATA.apiBaseUrl || '/pbxcore/api/module-dialer-manage/v1';

    new Vue({
        el: '#audio-app',
        delimiters: ["<%", "%>"],
        data: {
            apiBaseUrl: apiBaseUrl,
            audioFiles: [],
            uploading: false,
            uploadPercent: 0,
            uploadMsg: ''
        },
        mounted: function () {
            this.loadList();
        },
        methods: {
            loadList: function () {
                var self = this;
                fetch(this.apiBaseUrl + '/audio', {credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.success && j.data) {
                            // Expecting either array or {files: [...]}
                            var files = Array.isArray(j.data) ? j.data : (j.data.files || j.data.results || []);
                            self.audioFiles = files.map(function (name) {
                                return typeof name === 'string' ? {name: name} : name;
                            });
                        }
                    });
            },
            audioUrl: function (name) {
                // The module serves audio from its public dir via MikoPBX cache
                return '/admin-cabinet/assets/img/cache/ModuleAutoDialerManage/audio/' + encodeURIComponent(name);
            },
            uploadFile: function (ev) {
                var self = this;
                var file = ev.target.files[0];
                if (!file) return;
                var fd = new FormData();
                fd.append('file', file);
                this.uploading = true;
                this.uploadPercent = 0;
                this.uploadMsg = '';
                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) self.uploadPercent = Math.round((e.loaded / e.total) * 100);
                });
                xhr.addEventListener('load', function () {
                    self.uploading = false;
                    try {
                        var j = JSON.parse(xhr.responseText);
                        if (j.success) {
                            self.uploadMsg = 'Uploaded: ' + file.name;
                            self.loadList();
                        } else {
                            self.uploadMsg = 'Upload failed: ' + (j.messages || ['unknown']).join(', ');
                        }
                    } catch (e) {
                        self.uploadMsg = 'Upload failed: invalid response';
                    }
                });
                xhr.addEventListener('error', function () {
                    self.uploading = false;
                    self.uploadMsg = 'Upload failed: network error';
                });
                xhr.open('POST', this.apiBaseUrl + '/audio');
                xhr.withCredentials = true;
                xhr.send(fd);
            },
            deleteFile: function (f) {
                if (!confirm('Delete audio file "' + f.name + '"? This cannot be undone.')) return;
                var self = this;
                fetch(this.apiBaseUrl + '/audio/' + encodeURIComponent(f.name), {
                    method: 'DELETE',
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) self.loadList();
                    else alert('Delete failed: ' + (j.messages || []).join(', '));
                });
            }
        }
    });
})();
