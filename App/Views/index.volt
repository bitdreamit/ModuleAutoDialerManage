<div class="ui top attached tabular menu">
  <a class="active item" data-tab="polling">{{ t._('mod_AutoDialer_TabPolling') }}</a>
  <a class="item" data-tab="extension">{{ t._('mod_AutoDialer_TabExtensions') }}</a>
  <a class="item" data-tab="settings">{{ t._('mod_AutoDialer_TabSettings') }}</a>
  <a class="item" data-tab="campaigns">🎯 Campaigns</a>
  <a class="item" data-tab="dashboard">📊 Dashboard</a>
  <a class="item" data-tab="results">📞 Call results</a>
  <a class="item" data-tab="polling-results">📋 IVR answers</a>
  <a class="item" data-tab="audio">🎵 Audio files</a>
  <a class="item" data-tab="blacklist">🚫 DNC blacklist</a>
  <a class="item" data-tab="apiguide">💻 API guide</a>
</div>
<div class="ui bottom attached active tab segment" data-tab="polling">
  <button class="ui primary button" id="button-add">{{ t._('mod_AutoDialer_AddQuestion') }} </button>
  <br>
  <br>
  <table id="polling-table" data-report-name="polling" class="ui small very compact single line unstackable celled striped table ">
   <thead>
   <tr>
       <th class="one wide">crmId</th>
       <th class="one three">id</th>
       <th class="eleven wide">{{ t._('mod_AutoDialer_PollingTableName') }}</th>
       <th class="one wide"></th>
   </tr>
   </thead>
   <tbody>
   <tr>
       <td colspan="5" class="dataTables_empty">{{ t._('dt_TableIsEmpty') }}</td>
   </tr>
   </tbody>
  </table>
</div>
<div class="ui bottom attached tab segment" data-tab="extension">
    <button class="ui primary button" id="button-exten-add">{{ t._('mod_AutoDialer_AddExtension') }} </button>
      <br>
      <table id="extensions-table" data-report-name="polling" class="ui small very compact single line unstackable celled striped table ">
       <thead>
       <tr>
           <th class="one wide">{{ t._('mod_AutoDialer_ExtenTableNumber') }}</th>
           <th class="one wide">{{ t._('mod_AutoDialer_PollingTableName') }}</th>
           <th class="six wide">{{ t._('mod_AutoDialer_ExtenTablePollingIdOK') }}</th>
           <th class="six wide">{{ t._('mod_AutoDialer_ExtenTablePollingIdFAIL') }}</th>
           <th class="one wide"></th>
       </tr>
       </thead>
       <tbody>
       {% for extension in extensions %}
       <tr data-exten-id="{{extension['id']}}">
           <th class="one wide">{{ extension['exten'] }}</th>
           <th class="one three">{{ extension['name'] }}</th>
           <th class="six three">{{ extension['pollingIdOKName'] }}</th>
           <th class="six wide">{{ extension['pollingIdFAILName'] }}</th>
           <th class="one wide">
               <div class="ui basic icon buttons action-buttons tiny">
                 <a href="/admin-cabinet/module-auto-dialer-manage/modifyExtension/{{extension['id']}}" class="ui button edit popuped" data-content=""><i class="icon edit blue"></i> </a>
                 <a href="/admin-cabinet/module-auto-dialer-manage/deleteExtension/{{extension['id']}}" class="ui button delete two-steps-delete popuped" data-content=""><i class="icon trash red"></i> </a>
               </div>
           </th>
       </tr>
       {% else %}
       <tr>
           <td colspan="5" class="dataTables_empty">{{ t._('dt_TableIsEmpty') }}</td>
       </tr>
       {% endfor %}

       </tbody>
   </table>
</div>
<div class="ui bottom attached tab segment" data-tab="settings">
  <form class="ui large grey segment form" id="module-auto-dialer-manage-form">
      <div class="eight wide field">
          <label>{{ t._('mod_AutoDialer_defDialPrefix') }}</label>
          {{ form.render('defDialPrefix') }}
      </div>
      <div class="ten wide field">
          <label>{{ t._('mod_AutoDialer_ttsService') }}</label>
          {{ form.render('ttsService') }}
      </div>
      <div class="eight wide field yandex-settings">
          <label>{{ t._('mod_AutoDialer_yandexApiKey') }}</label>
          {{ form.render('yandexApiKey') }}
      </div>
      <div class="eight wide field yandex-settings">
          <label>{{ t._('mod_AutoDialer_yandexFolderId') }}</label>
          {{ form.render('yandexFolderId') }}
      </div>
      <div class="field">
          <label>{{ t._('mod_AutoDialer_callbackAlertText') }}</label>
          {{ form.render('callbackAlertText') }}
      </div>
      <h4 class="ui dividing header">{{ t._('mod_AutoDialer_crmSettingsHeader') }}</h4>
      <div class="eight wide field">
          <label>{{ t._('mod_AutoDialer_crmUrl') }}</label>
          {{ form.render('crmUrl') }}
      </div>
      <div class="eight wide field">
          <label>{{ t._('mod_AutoDialer_crmLogin') }}</label>
          {{ form.render('crmLogin') }}
      </div>
      <div class="eight wide field">
          <label>{{ t._('mod_AutoDialer_crmPassword') }}</label>
          {{ form.render('crmPassword') }}
      </div>
      {{ partial("partials/submitbutton",['indexurl':'pbx-extension-modules/index/']) }}
  </form>
</div>

{# ===================== Bit Dream IT extension tabs ===================== #}
{# All new features are accessible as tabs on this same page — no sidebar  #}
{# menu registration needed. Each tab loads a Vue app on first activation. #}

{# Tab: Campaigns #}
<div class="ui bottom attached tab segment" data-tab="campaigns">
  <div id="campaigns-app">
    <div class="ui secondary menu">
      <div class="header item"><i class="volume control phone icon"></i> Campaigns</div>
      <div class="right menu">
        <div class="item">
          <button class="ui primary button" v-on:click="showNewForm"><i class="plus icon"></i> New campaign</button>
        </div>
        <div class="item">
          <button class="ui basic button" v-on:click="loadCampaigns"><i class="refresh icon"></i> Refresh</button>
        </div>
      </div>
    </div>
    <div class="ui form">
      <div class="four fields">
        <div class="field"><label>Search</label><input type="text" v-model="filters.q" placeholder="Name..." v-on:keyup.enter="applyFilters"></div>
        <div class="field"><label>State</label><select v-model="filters.state" class="ui dropdown" v-on:change="applyFilters"><option value="">All</option><option value="0">Open</option><option value="1">Closed</option><option value="2">Paused</option></select></div>
        <div class="field"><label>Type</label><select v-model="filters.type" class="ui dropdown" v-on:change="applyFilters"><option value="">All</option><option value="exten">Extension</option><option value="polling">Survey</option></select></div>
        <div class="field"><label>&nbsp;</label><button class="ui fluid button" v-on:click="applyFilters"><i class="search icon"></i> Filter</button></div>
      </div>
    </div>
    <table class="ui compact striped table">
      <thead><tr><th>ID</th><th>Name</th><th>State</th><th>Type</th><th>Inner</th><th>Ch</th><th>Time</th><th>Days</th><th>Flags</th><th class="right aligned">Actions</th></tr></thead>
      <tbody>
        <tr v-for="c in filteredCampaigns" :key="c.id">
          <td><code><% c.id %></code></td>
          <td><strong><% c.name %></strong></td>
          <td><span class="ui mini label" :class="stateColor(c.state)"><% stateLabel(c.state) %></span></td>
          <td><span class="ui mini basic label"><% c.innerNumType === 'polling' ? 'Survey' : 'Extension' %></span></td>
          <td><% c.innerNum %></td>
          <td><% c.maxCountChannels %></td>
          <td><small><% formatTime(c.timeStart) %>-<% formatTime(c.timeEnd) %></small></td>
          <td><small><% formatSchedule(c.scheduleDays) %></small></td>
          <td><span class="ui mini label" v-if="c.amdEnabled == 1">AMD</span><span class="ui mini label" v-if="c.callbackUrl">Webhook</span></td>
          <td class="right aligned">
            <div class="ui tiny basic icon buttons">
              <a :href="'/admin-cabinet/module-auto-dialer-manage/campaignForm/' + c.id" class="ui button" title="Edit"><i class="edit icon"></i></a>
              <a :href="'/pbxcore/api/module-dialer-manage/v1/task/' + c.id + '/export'" class="ui button" title="CSV"><i class="download icon"></i></a>
              <button class="ui button" v-on:click="togglePause(c)" :title="c.state == 2 ? 'Resume' : 'Pause'"><i :class="c.state == 2 ? 'play icon' : 'pause icon'"></i></button>
              <button class="ui button" v-on:click="confirmDelete(c)" title="Delete"><i class="trash red icon"></i></button>
            </div>
          </td>
        </tr>
        <tr v-if="filteredCampaigns.length === 0"><td colspan="10" class="center aligned">No campaigns. Click "New campaign" to create one.</td></tr>
      </tbody>
    </table>
  </div>
</div>

{# Tab: Dashboard (overview, links to detail) #}
<div class="ui bottom attached tab segment" data-tab="dashboard">
  <div id="dashboard-app">
    <div class="ui secondary menu">
      <div class="header item"><i class="dashboard icon"></i> Live campaign dashboard</div>
      <div class="right menu">
        <div class="item"><div class="ui toggle checkbox"><input type="checkbox" v-model="autoRefresh"><label>Auto-refresh (4s)</label></div></div>
        <div class="item"><button class="ui icon button" v-on:click="refreshAll" :class="{loading: loading}"><i class="refresh icon"></i></button></div>
      </div>
    </div>
    <div class="ui three column stackable grid">
      <div class="column" v-for="c in campaigns" :key="c.task_id">
        <div class="ui card">
          <div class="content">
            <div class="header"><i :class="stateIcon(c.state)"></i> <% c.name %></div>
            <div class="meta"><span class="ui tiny label" :class="stateColor(c.state)"><% stateLabel(c.state) %></span><span class="ui tiny label" v-if="c.amd_enabled">AMD</span></div>
            <div class="description" style="margin-top: 10px;">
              <div class="ui mini progress" :data-percent="c.progress_percent"><div class="bar" :style="{width: c.progress_percent + '%'}"></div></div>
              <div class="ui mini statistics">
                <div class="statistic"><div class="value"><% c.in_progress %></div><div class="label">Live</div></div>
                <div class="statistic"><div class="value"><% c.total_dialed %></div><div class="label">Dialed</div></div>
                <div class="statistic"><div class="value"><% c.max_channels %></div><div class="label">Max</div></div>
              </div>
            </div>
          </div>
          <div class="extra content">
            <a :href="'/admin-cabinet/module-auto-dialer-manage/dashboard/' + c.task_id" class="ui mini basic button"><i class="chart bar icon"></i> Details</a>
            <button class="ui mini basic button" v-on:click="togglePause(c)"><i :class="c.state === 2 ? 'play icon' : 'pause icon'"></i> <% c.state === 2 ? 'Resume' : 'Pause' %></button>
          </div>
        </div>
      </div>
    </div>
    <h3 class="ui dividing header" style="margin-top: 20px;"><i class="users icon"></i> Agents</h3>
    <div class="ui cards" v-if="agents.length > 0">
      <div class="ui card" v-for="a in agents" :key="a.number" :style="{borderLeft: '4px solid ' + agentColor(a.state)}">
        <div class="content">
          <div class="header"><i :class="agentIcon(a.state)"></i> <% a.number %></div>
          <div class="meta"><span class="ui tiny label" :class="agentStateColor(a.state)"><% a.state_label %></span></div>
        </div>
      </div>
    </div>
    <div class="ui placeholder segment" v-else><div class="ui icon header"><i class="users icon"></i> No agents configured</div></div>
  </div>
</div>

{# Tab: Call results #}
<div class="ui bottom attached tab segment" data-tab="results">
  <div id="results-app">
    <div class="ui form">
      <div class="four fields">
        <div class="field"><label>Campaign</label><select v-model="filters.taskId" class="ui dropdown" v-on:change="applyFilters"><option value="">All</option><option v-for="t in tasks" :value="t.id"><% t.id %> — <% t.name %></option></select></div>
        <div class="field"><label>State</label><select v-model="filters.state" class="ui dropdown" v-on:change="applyFilters"><option value="">All</option><option value="ANSWER">Answered</option><option value="NOANSWER">No answer</option><option value="BUSY">Busy</option><option value="DIAL">In progress</option></select></div>
        <div class="field"><label>Number contains</label><input type="text" v-model="filters.number" v-on:keyup.enter="applyFilters"></div>
        <div class="field"><label>&nbsp;</label><button class="ui fluid button" v-on:click="loadInitial"><i class="search icon"></i> Load last 24h</button></div>
      </div>
    </div>
    <div class="ui mini statistics" v-if="allResults.length > 0">
      <div class="statistic"><div class="value"><% allResults.length %></div><div class="label">Total</div></div>
      <div class="statistic green"><div class="value"><% stateCount('ANSWER') %></div><div class="label">Answered</div></div>
      <div class="statistic red"><div class="value"><% stateCount('NOANSWER') + stateCount('BUSY') %></div><div class="label">No answer</div></div>
      <div class="statistic"><div class="value"><% answerRate %>%</div><div class="label">Answer rate</div></div>
    </div>
    <table class="ui compact striped table">
      <thead><tr><th>Time</th><th>Task</th><th>Number</th><th>State</th><th>Dur</th><th>Attempt</th><th>Cause</th><th>Recording</th></tr></thead>
      <tbody>
        <tr v-for="(r, idx) in pagedResults" :key="r.id || idx">
          <td><small><% r.time %></small></td><td><small><% r.task_id %></small></td><td><code><% r.number %></code></td>
          <td><span class="ui mini label" :class="stateColor(r.outDialState)"><% r.outDialState %></span></td>
          <td><% r.duration %>s</td><td><% r.attempt %></td><td><small><% r.cause %></small></td>
          <td><a v-if="r.linkedId" :href="apiBaseUrl + '/recording/' + encodeURIComponent(r.linkedId)" target="_blank" class="ui mini icon button"><i class="file audio icon"></i></a></td>
        </tr>
        <tr v-if="filteredResults.length === 0"><td colspan="8" class="center aligned">No results. Click "Load last 24h" above.</td></tr>
      </tbody>
    </table>
  </div>
</div>

{# Tab: IVR answers #}
<div class="ui bottom attached tab segment" data-tab="polling-results">
  <div id="polling-results-app">
    <div class="ui form">
      <div class="three fields">
        <div class="field"><label>Survey</label><select v-model="filters.pollingId" class="ui dropdown" v-on:change="loadInitial"><option value="">All</option><option v-for="p in pollings" :value="p.id"><% p.id %> — <% p.name %></option></select></div>
        <div class="field"><label>Number contains</label><input type="text" v-model="filters.number" v-on:keyup.enter="applyFilters"></div>
        <div class="field"><label>&nbsp;</label><button class="ui fluid button" v-on:click="loadInitial"><i class="search icon"></i> Load last 7 days</button></div>
      </div>
    </div>
    <table class="ui compact striped table">
      <thead><tr><th>Time</th><th>Survey</th><th>Question</th><th>Number</th><th>Key</th><th>Answer</th><th>CRM ID</th></tr></thead>
      <tbody>
        <tr v-for="(r, idx) in pagedResults" :key="r.id || idx">
          <td><small><% r.time %></small></td><td><small><% pollingName(r.pollingId) %></small></td><td>Q<% r.questionId %></td>
          <td><code><% r.number %></code></td><td><span class="ui mini basic label"><% r.key %></span></td>
          <td><strong><% r.value %></strong></td><td><small><% r.crmId %></small></td>
        </tr>
        <tr v-if="filteredResults.length === 0"><td colspan="7" class="center aligned">No answers. Click "Load last 7 days" above.</td></tr>
      </tbody>
    </table>
  </div>
</div>

{# Tab: Audio files #}
<div class="ui bottom attached tab segment" data-tab="audio">
  <div id="audio-app">
    <h3 class="ui dividing header">Upload new audio file</h3>
    <div class="ui form">
      <div class="field"><label>Audio file (WAV, MP3)</label><input type="file" accept=".wav,.mp3,audio/*" ref="uploadFile" v-on:change="uploadFile"></div>
      <div class="ui success message" v-if="uploadMsg"><% uploadMsg %></div>
    </div>
    <h3 class="ui dividing header">Existing audio files (<% audioFiles.length %>)</h3>
    <table class="ui compact striped table">
      <thead><tr><th>#</th><th>Name</th><th>Play</th><th class="right aligned">Actions</th></tr></thead>
      <tbody>
        <tr v-for="(f, idx) in audioFiles" :key="f.name">
          <td><% idx + 1 %></td><td><code><% f.name %></code></td>
          <td><audio controls preload="none" :src="audioUrl(f.name)" style="height: 30px; width: 220px;"></audio></td>
          <td class="right aligned"><button class="ui mini negative icon button" v-on:click="deleteFile(f)"><i class="trash icon"></i></button></td>
        </tr>
        <tr v-if="audioFiles.length === 0"><td colspan="4" class="center aligned">No audio files uploaded yet</td></tr>
      </tbody>
    </table>
  </div>
</div>

{# Tab: DNC Blacklist #}
<div class="ui bottom attached tab segment" data-tab="blacklist">
  <div id="blacklist-app">
    <h3 class="ui dividing header">Add number(s) to DNC blacklist</h3>
    <div class="ui form">
      <div class="field"><label>Numbers (one per line or comma-separated)</label><textarea v-model="addNumbers" rows="3" placeholder="7912345678&#10;7912345679"></textarea></div>
      <div class="two fields">
        <div class="field"><label>Reason</label><input type="text" v-model="addReason" placeholder="Customer complaint"></div>
        <div class="field"><label>Source</label><select v-model="addSource" class="ui dropdown"><option value="manual">Manual</option><option value="complaint">Complaint</option><option value="regulator">Regulator</option></select></div>
      </div>
      <button class="ui primary button" :class="{loading: adding}" v-on:click="addNumbersToList"><i class="plus icon"></i> Add</button>
    </div>
    <h3 class="ui dividing header">Existing blacklist (<% total %> entries)</h3>
    <div class="ui fluid search"><div class="ui icon input"><input type="text" v-model="searchQuery" placeholder="Search numbers..." v-on:keyup.enter="loadList"><i class="search icon"></i></div><button class="ui button" v-on:click="loadList">Search</button></div>
    <table class="ui compact striped table" style="margin-top: 10px;">
      <thead><tr><th>Number</th><th>Reason</th><th>Source</th><th>Added</th><th class="right aligned">Action</th></tr></thead>
      <tbody>
        <tr v-for="e in entries" :key="e.id">
          <td><code><% e.number %></code></td><td><% e.reason %></td><td><span class="ui mini label"><% e.source %></span></td>
          <td><small><% formatDate(e.createdAt) %></small></td>
          <td class="right aligned"><button class="ui mini negative icon button" v-on:click="deleteEntry(e)"><i class="trash icon"></i></button></td>
        </tr>
        <tr v-if="entries.length === 0"><td colspan="5" class="center aligned">No entries found</td></tr>
      </tbody>
    </table>
  </div>
</div>

{# Tab: API guide (compact inline version) #}
<div class="ui bottom attached tab segment" data-tab="apiguide">
  <div id="apiguide-app">
    <div class="ui info message">
      <div class="header">UI + API coexist</div>
      <p>The web UI you see in these tabs calls the same REST endpoints documented below. Use the UI for day-to-day operations AND call the API from external systems at the same time.</p>
    </div>
    <h3 class="ui header">Base URL</h3>
    <pre><code>{{ pbxHost }}{{ apiBaseUrl }}</code></pre>
    <h3 class="ui header">Auth (off-PBX only)</h3>
    <pre><code>POST {{ pbxHost }}/admin-cabinet/session/start
Body: login=admin&password=YOUR_PASSWORD
→ returns PHPSESSID cookie, reuse on all subsequent calls</code></pre>
    <h3 class="ui header">Quick examples (curl)</h3>
    <h4 class="ui header">Create campaign</h4>
    <pre><code>curl -b cookies.txt -X POST {{ pbxHost }}{{ apiBaseUrl }}/task \
  -H "Content-Type: application/json" \
  -d '{
    "crmId": "INV-001", "name": "Payment reminders", "state": 0,
    "innerNum": "200", "innerNumType": "exten", "maxCountChannels": 5,
    "dialPrefix": "999", "amdEnabled": 1,
    "callbackUrl": "https://crm.example.com/webhooks/dialer",
    "numbers": [{"number": "7912345678", "params": {"speach": "Your balance is 1000."}}]
  }'</code></pre>
    <h4 class="ui header">Pause a campaign</h4>
    <pre><code>curl -b cookies.txt -X PUT {{ pbxHost }}{{ apiBaseUrl }}/task/42 \
  -H "Content-Type: application/json" -d '{"state": 2}'</code></pre>
    <h4 class="ui header">Live status (poll every 3-5s)</h4>
    <pre><code>curl -b cookies.txt {{ pbxHost }}{{ apiBaseUrl }}/task/42/status</code></pre>
    <h4 class="ui header">Sync results incrementally</h4>
    <pre><code>curl -b cookies.txt {{ pbxHost }}{{ apiBaseUrl }}/results/0</code></pre>
    <h4 class="ui header">Add to DNC blacklist</h4>
    <pre><code>curl -b cookies.txt -X POST {{ pbxHost }}{{ apiBaseUrl }}/blacklist \
  -H "Content-Type: application/json" \
  -d '{"numbers": ["7912345678"], "reason": "complaint"}'</code></pre>
    <h3 class="ui header">Laravel example</h3>
    <pre><code>$response = Http::withCookies(['PHPSESSID' => $cookie], $pbxHost)
    ->post("{$pbxHost}/pbxcore/api/module-dialer-manage/v1/task", [
        'crmId' => 'INV-2025-001',
        'name' => 'Invoice reminders',
        'state' => 0,
        'innerNum' => '200',
        'innerNumType' => 'exten',
        'maxCountChannels' => 5,
        'numbers' => [['number' => '7912345678']],
    ]);</code></pre>
    <h3 class="ui header">Full endpoint reference</h3>
    <table class="ui compact celled table">
      <thead><tr><th>Method</th><th>Endpoint</th><th>Purpose</th></tr></thead>
      <tbody>
        <tr v-for="e in endpoints" :key="e.method + e.path">
          <td><span class="ui mini label" :class="methodColor(e.method)"><% e.method %></span></td>
          <td><code><% e.path %></code></td><td><% e.purpose %></td>
        </tr>
      </tbody>
    </table>
    <p><a href="/admin-cabinet/module-auto-dialer-manage/apiGuide" class="ui primary button"><i class="code icon"></i> Open full API guide page</a></p>
    <p><a href="/admin-cabinet/module-auto-dialer-manage/campaignForm" class="ui basic button"><i class="plus icon"></i> Create new campaign (full form)</a></p>
  </div>
</div>

<script>
// Boot data for tab Vue apps
window.__INDEX_TABS_DATA__ = {
    apiBaseUrl: "{{ apiBaseUrl }}",
    pbxHost: "{{ pbxHost }}",
    tasks: {{ tasks|json_encode }},
    pollings: {{ pollings|json_encode }}
};
</script>





