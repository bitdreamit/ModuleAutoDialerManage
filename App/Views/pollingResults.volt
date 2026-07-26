{# Bit Dream IT extension: Polling (IVR) results browser #}
<div id="polling-results-app" class="ui container fluid">
    <div class="ui secondary menu">
        <div class="header item">
            <i class="list ul icon"></i> IVR / poll answers
        </div>
    </div>

    <div class="ui segment">
        <div class="ui form">
            <div class="three fields">
                <div class="field">
                    <label>Survey / poll</label>
                    <select v-model="filters.pollingId" class="ui dropdown" v-on:change="reload">
                        <option value="">All surveys</option>
                        {% for p in pollings %}
                        <option value="{{ p['id'] }}">{{ p['id'] }} — {{ p['name'] }}</option>
                        {% endfor %}
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
            </div>
            <button class="ui primary button" v-on:click="applyFilters"><i class="search icon"></i> Apply</button>
            <button class="ui basic button" v-on:click="resetFilters"><i class="undo icon"></i> Reset</button>
        </div>
    </div>

    <div class="ui segment">
        <table class="ui compact striped table">
            <thead>
            <tr>
                <th>Time</th>
                <th>Survey</th>
                <th>Question ID</th>
                <th>Number</th>
                <th>Key pressed</th>
                <th>Answer</th>
                <th>CRM ID</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(r, idx) in pagedResults" :key="r.id || idx">
                <td><small><% r.time %></small></td>
                <td><small><% pollingName(r.pollingId) %></small></td>
                <td>Q<% r.questionId %></td>
                <td><code><% r.number %></code></td>
                <td><span class="ui mini basic label"><% r.key %></span></td>
                <td><strong><% r.value %></strong></td>
                <td><small><% r.crmId %></small></td>
            </tr>
            <tr v-if="filteredResults.length === 0">
                <td colspan="7" class="center aligned">No poll answers match the current filters</td>
            </tr>
            </tbody>
        </table>
        <div class="ui pagination menu" v-if="totalPages > 1">
            <a class="item" v-on:click="prevPage" :class="{disabled: page === 1}">‹ Prev</a>
            <a class="item" v-for="p in pageNumbers" :key="p" v-on:click="page = p" :class="{active: p === page}"><% p %></a>
            <a class="item" v-on:click="nextPage" :class="{disabled: page === totalPages}">Next ›</a>
        </div>
    </div>
</div>

<script>
    window.__POLLING_RESULTS_DATA__ = {
        apiBaseUrl: "{{ apiBaseUrl }}",
        pollings: {{ pollings|json_encode }}
    };
</script>
