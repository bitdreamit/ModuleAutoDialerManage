{# Bit Dream IT extension: DNC Blacklist management #}
<div id="blacklist-app" class="ui container">
    <h2 class="ui header">
        <i class="ban icon"></i>
        Do-Not-Call (DNC) Blacklist
    </h2>
    <p>Numbers in this list will NEVER be dialed by any campaign.</p>

    <!-- Add form -->
    <div class="ui segment">
        <h3 class="ui dividing header">Add Number(s)</h3>
        <div class="ui form">
            <div class="field">
                <label>Numbers (one per line, or comma-separated)</label>
                <textarea v-model="addNumbers" rows="4" placeholder="77951112233&#10;77952223344, 77953334455"></textarea>
            </div>
            <div class="two fields">
                <div class="field">
                    <label>Reason (optional)</label>
                    <input type="text" v-model="addReason" placeholder="Customer complaint">
                </div>
                <div class="field">
                    <label>Source</label>
                    <select v-model="addSource" class="ui dropdown">
                        <option value="manual">Manual</option>
                        <option value="complaint">Complaint</option>
                        <option value="regulator">Regulator</option>
                        <option value="auto-amd">Auto-detected by AMD</option>
                    </select>
                </div>
            </div>
            <button class="ui primary button" :class="{loading: adding}" v-on:click="addNumbersToList">
                <i class="plus icon"></i> Add to blacklist
            </button>
        </div>
    </div>

    <!-- Search + list -->
    <div class="ui segment">
        <div class="ui fluid search">
            <div class="ui icon input">
                <input type="text" v-model="searchQuery" placeholder="Search numbers..." v-on:keyup.enter="loadList">
                <i class="search icon"></i>
            </div>
            <button class="ui button" v-on:click="loadList">Search</button>
        </div>

        <table class="ui compact striped table" style="margin-top: 15px;">
            <thead>
            <tr>
                <th>Number</th>
                <th>Reason</th>
                <th>Source</th>
                <th>Added</th>
                <th class="right aligned">Action</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="e in entries" :key="e.id">
                <td><code><% e.number %></code></td>
                <td><% e.reason %></td>
                <td><span class="ui mini label"><% e.source %></span></td>
                <td><% formatDate(e.createdAt) %></td>
                <td class="right aligned">
                    <button class="ui mini negative icon button" v-on:click="deleteEntry(e)">
                        <i class="trash icon"></i>
                    </button>
                </td>
            </tr>
            <tr v-if="entries.length === 0">
                <td colspan="5" class="center aligned">No entries found</td>
            </tr>
            </tbody>
        </table>

        <div class="ui pagination menu" v-if="total > limit">
            <a class="item" v-on:click="prevPage" :class="{disabled: offset === 0}">Prev</a>
            <a class="item active"><% currentPage %></a>
            <a class="item" v-on:click="nextPage" :class="{disabled: offset + limit >= total}">Next</a>
        </div>
        <p class="ui tiny label">Total: <% total %> entries</p>
    </div>
</div>

<script>
    window.__BLACKLIST_DATA__ = {
        apiBaseUrl: "{{ apiBaseUrl }}"
    };
</script>
