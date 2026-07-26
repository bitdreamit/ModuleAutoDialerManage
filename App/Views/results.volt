{# Bit Dream IT extension: Call results browser #}
<div id="results-app" class="ui container fluid">
    <div class="ui secondary menu">
        <div class="header item">
            <i class="phone icon"></i> Call results
        </div>
        <div class="right menu">
            <div class="item">
                <button class="ui basic button" v-on:click="exportCsv" :disabled="!filteredResults.length">
                    <i class="download icon"></i> Export current view
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="ui segment">
        <div class="ui form">
            <div class="five fields">
                <div class="field">
                    <label>Campaign</label>
                    <select v-model="filters.taskId" class="ui dropdown" v-on:change="reload">
                        <option value="">All campaigns</option>
                        {% for t in tasks %}
                        <option value="{{ t['id'] }}">{{ t['id'] }} — {{ t['name'] }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="field">
                    <label>State</label>
                    <select v-model="filters.state" class="ui dropdown" v-on:change="applyFilters">
                        <option value="">All states</option>
                        <option value="ANSWER">Answered</option>
                        <option value="NOANSWER">No answer</option>
                        <option value="BUSY">Busy</option>
                        <option value="DIAL">In progress</option>
                        <option value="CHANUNAVAIL">Channel unavailable</option>
                        <option value="CONGESTION">Congestion</option>
                        <option value="CANCEL">Canceled</option>
                    </select>
                </div>
                <div class="field">
                    <label>Number contains</label>
                    <input type="text" v-model="filters.number" placeholder="7912..." v-on:keyup.enter="applyFilters">
                </div>
                <div class="field">
                    <label>From date</label>
                    <input type="date" v-model="filters.fromDate" v-on:change="reload">
                </div>
                <div class="field">
                    <label>To date</label>
                    <input type="date" v-model="filters.toDate" v-on:change="reload">
                </div>
            </div>
            <button class="ui primary button" v-on:click="applyFilters">
                <i class="search icon"></i> Apply filters
            </button>
            <button class="ui basic button" v-on:click="resetFilters">
                <i class="undo icon"></i> Reset
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="ui mini statistics" v-if="allResults.length > 0">
        <div class="statistic">
            <div class="value"><% allResults.length %></div>
            <div class="label">Total</div>
        </div>
        <div class="statistic green">
            <div class="value"><% stateCount('ANSWER') %></div>
            <div class="label">Answered</div>
        </div>
        <div class="statistic red">
            <div class="value"><% stateCount('NOANSWER') + stateCount('BUSY') %></div>
            <div class="label">No answer / busy</div>
        </div>
        <div class="statistic orange">
            <div class="value"><% stateCount('DIAL') %></div>
            <div class="label">In progress</div>
        </div>
        <div class="statistic">
            <div class="value"><% answerRate %>%</div>
            <div class="label">Answer rate</div>
        </div>
    </div>

    <!-- Results table -->
    <div class="ui segment">
        <table class="ui compact striped table">
            <thead>
            <tr>
                <th>Time</th>
                <th>Campaign</th>
                <th>Number</th>
                <th>State</th>
                <th>Duration</th>
                <th>Attempt</th>
                <th>Cause</th>
                <th>Recording</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(r, idx) in pagedResults" :key="r.id || idx">
                <td><small><% r.time %></small></td>
                <td><small><% campaignName(r.task_id) %></small></td>
                <td><code><% r.number %></code></td>
                <td>
                    <span class="ui mini label" :class="stateColor(r.outDialState)">
                        <% r.outDialState %>
                    </span>
                </td>
                <td><% r.duration %>s</td>
                <td><% r.attempt %></td>
                <td><small><% r.cause %></small></td>
                <td>
                    <a v-if="r.linkedId" :href="apiBaseUrl + '/recording/' + encodeURIComponent(r.linkedId)" target="_blank" class="ui mini icon button">
                        <i class="file audio icon"></i>
                    </a>
                </td>
            </tr>
            <tr v-if="filteredResults.length === 0">
                <td colspan="8" class="center aligned">No results match the current filters</td>
            </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="ui pagination menu" v-if="totalPages > 1">
            <a class="item" v-on:click="prevPage" :class="{disabled: page === 1}">‹ Prev</a>
            <a class="item" v-for="p in pageNumbers" :key="p" v-on:click="page = p" :class="{active: p === page}">
                <% p %>
            </a>
            <a class="item" v-on:click="nextPage" :class="{disabled: page === totalPages}">Next ›</a>
        </div>
        <p class="ui tiny label">Showing <% (page-1)*pageSize + 1 %>—<% Math.min(page*pageSize, filteredResults.length) %> of <% filteredResults.length %></p>
    </div>
</div>

<script>
    window.__RESULTS_DATA__ = {
        apiBaseUrl: "{{ apiBaseUrl }}",
        tasks: {{ tasks|json_encode }}
    };
</script>
