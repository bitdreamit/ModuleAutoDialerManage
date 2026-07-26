{# Bit Dream IT extension: Live campaign dashboard #}
<div id="dashboard-app" class="ui container fluid">
    <div class="ui secondary menu">
        <div class="header item">
            <i class="dashboard icon"></i> Live Campaign Dashboard
        </div>
        <div class="right menu">
            <div class="item">
                <button class="ui icon button" v-on:click="refreshAll()" :class="{loading: loading}">
                    <i class="refresh icon"></i>
                </button>
            </div>
            <div class="item">
                <div class="ui toggle checkbox">
                    <input type="checkbox" v-model="autoRefresh">
                    <label>Auto-refresh ({{ refreshInterval/1000 }}s)</label>
                </div>
            </div>
        </div>
    </div>

    <div class="ui info message" v-if="loading && campaigns.length === 0">
        <div class="header">Loading dashboard...</div>
    </div>

    <div class="ui warning message" v-if="!loading && campaigns.length === 0">
        <div class="header">No campaigns found</div>
        <p>Create a campaign first on the <a href="/admin-cabinet/module-auto-dialer-manage/index">main module page</a>.</p>
    </div>

    <!-- Campaign cards grid -->
    <div class="ui three column stackable grid" v-if="campaigns.length > 0">
        <div class="column" v-for="c in campaigns" :key="c.task_id">
            <div class="ui card">
                <div class="content">
                    <div class="header">
                        <i :class="stateIcon(c.state)"></i>
                        <% c.name %>
                    </div>
                    <div class="meta">
                        <span class="ui tiny label" :class="stateColor(c.state)">
                            <% stateLabel(c.state) %>
                        </span>
                        <span class="ui tiny label" v-if="c.amd_enabled">
                            <i class="shield icon"></i> AMD
                        </span>
                        <span class="ui tiny label">
                            ID: <% c.task_id %>
                        </span>
                    </div>

                    <div class="description" style="margin-top: 10px;">
                        <div class="ui tiny progress" :data-percent="c.progress_percent">
                            <div class="bar" :style="{width: c.progress_percent + '%'}"></div>
                            <div class="label">
                                <% c.total_dialed %> dialed
                                <span v-if="c.in_progress > 0" class="ui mini orange label">
                                    <% c.in_progress %> live
                                </span>
                            </div>
                        </div>

                        <div class="ui mini statistics" style="margin-top: 8px;">
                            <div class="statistic">
                                <div class="value"><% c.max_channels %></div>
                                <div class="label">Max ch</div>
                            </div>
                            <div class="statistic">
                                <div class="value"><% c.in_progress %></div>
                                <div class="label">In progress</div>
                            </div>
                            <div class="statistic">
                                <div class="value"><% c.total_dialed %></div>
                                <div class="label">Total</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="extra content">
                    <a :href="'/admin-cabinet/module-auto-dialer-manage/dashboard/' + c.task_id" class="ui mini basic button">
                        <i class="chart bar icon"></i> Details
                    </a>
                    <a :href="'/admin-cabinet/module-auto-dialer-manage/modifyPolling/' + c.task_id" class="ui mini basic button">
                        <i class="edit icon"></i> Edit
                    </a>
                    <a :href="'/pbxcore/api/module-dialer-manage/v1/task/' + c.task_id + '/export'" class="ui mini basic button">
                        <i class="download icon"></i> CSV
                    </a>
                    <button class="ui mini basic button" v-on:click="togglePause(c)">
                        <i :class="c.state === 2 ? 'play icon' : 'pause icon'"></i>
                        <% c.state === 2 ? 'Resume' : 'Pause' %>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Agents panel -->
    <h3 class="ui dividing header" style="margin-top: 30px;">
        <i class="users icon"></i> Agents / Extensions
    </h3>
    <div class="ui cards" v-if="agents.length > 0">
        <div class="ui card" v-for="a in agents" :key="a.number"
             :style="{borderLeft: '4px solid ' + agentColor(a.state)}">
            <div class="content">
                <div class="header">
                    <i :class="agentIcon(a.state)"></i>
                    <% a.number %>
                </div>
                <div class="meta">
                    <span class="ui tiny label" :class="agentStateColor(a.state)">
                        <% a.state_label %>
                    </span>
                </div>
                <div class="description">
                    <small><% a.name %></small>
                </div>
            </div>
        </div>
    </div>
    <div class="ui placeholder segment" v-else>
        <div class="ui icon header">
            <i class="users icon"></i>
            No agents configured
        </div>
    </div>

    <!-- Recent calls feed -->
    <h3 class="ui dividing header" style="margin-top: 30px;">
        <i class="phone icon"></i> Recent Calls (last 20)
    </h3>
    <table class="ui compact striped table">
        <thead>
        <tr>
            <th>Time</th>
            <th>Number</th>
            <th>State</th>
            <th>Duration</th>
            <th>Cause</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="call in recentCalls" :key="call.linkedId + call.time">
            <td><% call.time %></td>
            <td><% call.number %></td>
            <td>
                <span class="ui mini label" :class="callStateColor(call.outDialState)">
                    <% call.outDialState %>
                </span>
            </td>
            <td><% call.duration %>s</td>
            <td><small><% call.cause %></small></td>
        </tr>
        <tr v-if="recentCalls.length === 0">
            <td colspan="5" class="center aligned">No recent calls</td>
        </tr>
        </tbody>
    </table>
</div>

<script>
    // Boot data passed from PHP controller
    window.__DASHBOARD_DATA__ = {
        apiBaseUrl: "{{ apiBaseUrl }}",
        initialTasks: {{ tasks|json_encode }}
    };
</script>
