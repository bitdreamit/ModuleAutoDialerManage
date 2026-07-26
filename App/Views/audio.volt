{# Bit Dream IT extension: Audio files management #}
<div id="audio-app" class="ui container">
    <div class="ui secondary menu">
        <div class="header item">
            <i class="file audio icon"></i> Audio files
        </div>
    </div>

    <p>Pre-recorded prompts used by surveys. Upload WAV or MP3. Files are referenced by name in survey questions.</p>

    <!-- Upload -->
    <div class="ui segment">
        <h3 class="ui dividing header">Upload new audio file</h3>
        <div class="ui form">
            <div class="field">
                <label>Audio file (WAV, MP3)</label>
                <input type="file" accept=".wav,.mp3,audio/*" ref="uploadFile" v-on:change="uploadFile">
            </div>
            <div class="ui progress" v-if="uploading" :data-percent="uploadPercent">
                <div class="bar" :style="{width: uploadPercent + '%'}"></div>
                <div class="label">Uploading...</div>
            </div>
            <div class="ui success message" v-if="uploadMsg">
                <i class="close icon" v-on:click="uploadMsg=''"></i>
                <% uploadMsg %>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="ui segment">
        <h3 class="ui dividing header">
            Existing audio files
            <span class="ui tiny label"><% audioFiles.length %></span>
        </h3>
        <table class="ui compact striped table">
            <thead>
            <tr>
                <th class="collapsing">#</th>
                <th>Name</th>
                <th class="collapsing">Play</th>
                <th class="right aligned">Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(f, idx) in audioFiles" :key="f.name">
                <td><% idx + 1 %></td>
                <td><code><% f.name %></code></td>
                <td>
                    <audio controls preload="none" :src="audioUrl(f.name)" style="height: 30px; width: 220px;"></audio>
                </td>
                <td class="right aligned">
                    <button class="ui mini negative icon button" v-on:click="deleteFile(f)">
                        <i class="trash icon"></i>
                    </button>
                </td>
            </tr>
            <tr v-if="audioFiles.length === 0">
                <td colspan="4" class="center aligned">No audio files uploaded yet</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    window.__AUDIO_DATA__ = {
        apiBaseUrl: "{{ apiBaseUrl }}"
    };
</script>
