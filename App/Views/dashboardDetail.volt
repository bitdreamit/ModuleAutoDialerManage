{# Bit Dream IT extension: Live campaign dashboard — DETAIL view (drill-down) #}
<div id="dashboard-detail-app" class="ui container fluid">
    <div class="ui secondary menu">
        <div class="header item">
            <i class="chart bar icon"></i>
            Campaign Dashboard — Detail
        </div>
        <div class="right menu">
            <div class="item">
                <a href="/admin-cabinet/module-auto-dialer-manage/dashboard" class="ui mini basic button">
                    <i class="arrow left icon"></i> Back to overview
                </a>
            </div>
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

    <div class="ui warning message" v-if="!task">
        <div class="header">Campaign not found</div>
        <p>The campaign with ID {{ detailId }} does not exist. <a href="/admin-cabinet/module-auto-dialer-manage/dashboard">← Back to overview</a></p>
    </div>

    <div v-if="task">
        <!-- Header card -->
        <div class="ui segment">
            <h2 class="ui header">
                <i :class="stateIcon(task.state)"></i>
                <div class="content">
                    <% task.name %>
                    <div class="sub header">
                        ID: <% task.id %> · CRM ID: <% task.crmId %>
                    </div>
                </div>
            </h2>

            <div class="ui labels">
                <span class="ui label" :class="stateColor(task.state)">
                    <% stateLabel(task.state) %>
                </span>
                <span class="ui label" v-if="task.amdEnabled">
                    <i class="shield icon"></i> AMD enabled
                </span>
                <span class="ui label" v-if="task.scheduleDays">
                    <i class="calendar icon"></i> <% scheduleLabel(task.scheduleDays) %>
                </span>
                <span class="ui label">
                    <i class="clock icon"></i> <% task.timeStart %> - <% task.timeEnd %> (min from midnight)
                </span>
                <span class="ui label" v-if="task.callbackUrl">
                    <i class="bell icon"></i> Webhook on
                </span>
            </div>

            <div class="ui divider"></div>

            <div class="ui mini statistics">
                <div class="statistic">
                    <div class="value"><% status.in_progress %></div>
                    <div class="label">In progress</div>
                </div>
                <div class="statistic">
                    <div class="value"><% status.max_channels %></div>
                    <div class="label">Max channels</div>
                </div>
                <div class="statistic">
                    <div class="value"><% summary.total_dialed %></div>
                    <div class="label">Total dialed</div>
                </div>
                <div class="statistic green">
                    <div class="value"><% summary.answered %></div>
                    <div class="label">Answered</div>
                </div>
                <div class="statistic red">
                    <div class="value"><% summary.failed %></div>
                    <div class="label">Failed</div>
                </div>
                <div class="statistic">
                    <div class="value"><% summary.answer_rate %>%</div>
                    <div class="label">Answer rate</div>
                </div>
                <div class="statistic">
                    <div class="value"><% summary.avg_duration_sec %>s</div>
                    <div class="label">Avg duration</div>
                </div>
            </div>

            <div class="ui progress" style="margin-top: 15px;" :data-percent="progressPercent">
                <div class="bar" :style="{width: progressPercent + '%'}"></div>
                <div class="label">
                    <% summary.answered %> answered of <% summary.total_dialed %> dialed
                    (<% summary.answer_rate %>% answer rate)
                </div>
            </div>

            <div class="ui divider"></div>

            <button class="ui mini basic button" v-on:click="setState(0)">
                <i class="play icon"></i> Resume
            </button>
            <button class="ui mini basic button" v-on:click="setState(2)">
                <i class="pause icon"></i> Pause
            </button>
            <button class="ui mini basic button" v-on:click="setState(1)">
                <i class="stop icon"></i> Close
            </button>
            <a :href="'/pbxcore/api/module-dialer-manage/v1/task/' + task.id + '/export'" class="ui mini basic button">
                <i class="download icon"></i> Export CSV
            </a>
            <a :href="'/admin-cabinet/module-auto-dialer-manage/modifyPolling/' + task.id" class="ui mini basic button">
                <i class="edit icon"></i> Edit campaign
            </a>
        </div>

        <!-- Two-column layout: agent grid + live calls feed -->
        <div class="ui two column stackable grid" style="margin-top: 15px;">
            <!-- Agent panel -->
            <div class="column">
                <h3 class="ui dividing header">
                    <i class="users icon"></i> Agent status
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
            </div>

            <!-- Live call feed -->
            <div class="column">
                <h3 class="ui dividing header">
                    <i class="phone icon"></i> Live call feed (this campaign)
                </h3>
                <table class="ui compact striped table">
                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>Number</th>
                        <th>State</th>
                        <th>Duration</th>
                        <th>Cause</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="call in recentCalls" :key="call.linkedId + call.time">
                        <td><% call.time %></td>
                        <td><code><% call.number %></code></td>
                        <td>
                            <span class="ui mini label" :class="callStateColor(call.outDialState)">
                                <% call.outDialState %>
                            </span>
                        </td>
                        <td><% call.duration %>s</td>
                        <td><small><% call.cause %></small></td>
                        <td>
                            <a v-if="call.linkedId"
                               :href="'/pbxcore/api/module-dialer-manage/v1/recording/' + encodeURIComponent(call.linkedId)"
                               target="_blank" class="ui mini icon button">
                                <i class="file audio icon"></i>
                            </a>
                        </td>
                    </tr>
                    <tr v-if="recentCalls.length === 0">
                        <td colspan="6" class="center aligned">No calls yet for this campaign</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- IVR/poll answers feed (only if campaign uses polling) -->
        <div v-if="task.innerNumType === 'polling'" style="margin-top: 30px;">
            <h3 class="ui dividing header">
                <i class="list ul icon"></i> IVR / poll answer feed
            </h3>
            <table class="ui compact striped table">
                <thead>
                <tr>
                    <th>Time</th>
                    <th>Number</th>
                    <th>Question</th>
                    <th>Key</th>
                    <th>Answer</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(ans, idx) in pollAnswers" :key="idx">
                    <td><% ans.time %></td>
                    <td><code><% ans.number %></code></td>
                    <td>Q<% ans.questionId %></td>
                    <td><span class="ui mini label"><% ans.key %></span></td>
                    <td><% ans.value %></td>
                </tr>
                <tr v-if="pollAnswers.length === 0">
                    <td colspan="5" class="center aligned">No poll answers yet</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.__DASHBOARD_DETAIL_DATA__ = {
        apiBaseUrl: "{{ apiBaseUrl }}",
        detailId: {{ detailId }},
        task: {{ task|json_encode }}
    };
</script>
