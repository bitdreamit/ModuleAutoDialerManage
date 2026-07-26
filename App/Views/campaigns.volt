{# Bit Dream IT extension: Campaigns list page #}
<div id="campaigns-app" class="ui container fluid">
    <div class="ui secondary menu">
        <div class="header item">
            <i class="volume control phone icon"></i> Campaigns
        </div>
        <div class="right menu">
            <div class="item">
                <a href="/admin-cabinet/module-auto-dialer-manage/campaignForm" class="ui primary button">
                    <i class="plus icon"></i> New campaign
                </a>
            </div>
            <div class="item">
                <a href="/admin-cabinet/module-auto-dialer-manage/dashboard" class="ui basic button">
                    <i class="dashboard icon"></i> Live dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="ui segment">
        <div class="ui form">
            <div class="four fields">
                <div class="field">
                    <label>Search by name</label>
                    <input type="text" v-model="filters.q" placeholder="Campaign name..." v-on:keyup.enter="loadCampaigns">
                </div>
                <div class="field">
                    <label>State</label>
                    <select v-model="filters.state" class="ui dropdown" v-on:change="loadCampaigns">
                        <option value="">All states</option>
                        <option value="0">Open</option>
                        <option value="1">Closed</option>
                        <option value="2">Paused</option>
                    </select>
                </div>
                <div class="field">
                    <label>Type</label>
                    <select v-model="filters.type" class="ui dropdown" v-on:change="loadCampaigns">
                        <option value="">All types</option>
                        <option value="exten">Extension</option>
                        <option value="polling">Survey / poll</option>
                    </select>
                </div>
                <div class="field">
                    <label>&nbsp;</label>
                    <button class="ui fluid button" v-on:click="loadCampaigns">
                        <i class="search icon"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaigns table -->
    <div class="ui segment">
        <table class="ui compact striped table">
            <thead>
            <tr>
                <th v-on:click="sortBy('id')" class="collapsing">ID <i :class="sortIcon('id')"></i></th>
                <th v-on:click="sortBy('name')">Name <i :class="sortIcon('name')"></i></th>
                <th v-on:click="sortBy('state')">State <i :class="sortIcon('state')"></i></th>
                <th v-on:click="sortBy('innerNumType')">Type <i :class="sortIcon('innerNumType')"></i></th>
                <th>Inner num</th>
                <th>Channels</th>
                <th>Time window</th>
                <th>Schedule</th>
                <th>Flags</th>
                <th class="right aligned">Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="c in filteredCampaigns" :key="c.id">
                <td><code><% c.id %></code></td>
                <td>
                    <strong><% c.name %></strong>
                    <div class="sub header" v-if="c.description">
                        <small><% c.description %></small>
                    </div>
                </td>
                <td>
                    <span class="ui mini label" :class="stateColor(c.state)">
                        <% stateLabel(c.state) %>
                    </span>
                </td>
                <td>
                    <span class="ui mini basic label">
                        <% c.innerNumType === 'polling' ? 'Survey' : 'Extension' %>
                    </span>
                </td>
                <td><% c.innerNum %></td>
                <td><% c.maxCountChannels %></td>
                <td><small><% formatTime(c.timeStart) %> - <% formatTime(c.timeEnd) %></small></td>
                <td><small><% formatSchedule(c.scheduleDays) %></small></td>
                <td>
                    <span class="ui mini label" v-if="c.amdEnabled == 1">AMD</span>
                    <span class="ui mini label" v-if="c.callbackUrl">Webhook</span>
                </td>
                <td class="right aligned">
                    <div class="ui tiny basic icon buttons">
                        <a :href="'/admin-cabinet/module-auto-dialer-manage/dashboard/' + c.id" class="ui button" title="Live detail">
                            <i class="chart bar icon"></i>
                        </a>
                        <a :href="'/admin-cabinet/module-auto-dialer-manage/campaignForm/' + c.id" class="ui button" title="Edit">
                            <i class="edit icon"></i>
                        </a>
                        <a :href="'/pbxcore/api/module-dialer-manage/v1/task/' + c.id + '/export'" class="ui button" title="Export CSV">
                            <i class="download icon"></i>
                        </a>
                        <button class="ui button" v-on:click="togglePause(c)" :title="c.state == 2 ? 'Resume' : 'Pause'">
                            <i :class="c.state == 2 ? 'play icon' : 'pause icon'"></i>
                        </button>
                        <button class="ui button" v-on:click="confirmDelete(c)" title="Delete">
                            <i class="trash red icon"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <tr v-if="filteredCampaigns.length === 0">
                <td colspan="10" class="center aligned">
                    <div class="ui placeholder segment">
                        <div class="ui icon header">
                            <i class="volume control phone icon"></i>
                            No campaigns found
                        </div>
                        <a href="/admin-cabinet/module-auto-dialer-manage/campaignForm" class="ui primary button">
                            <i class="plus icon"></i> Create your first campaign
                        </a>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
        <div class="ui right floated tiny label">
            Total: <% filteredCampaigns.length %> campaign(s)
        </div>
    </div>
</div>

<script>
    window.__CAMPAIGNS_DATA__ = {
        apiBaseUrl: "{{ apiBaseUrl }}"
    };
</script>
