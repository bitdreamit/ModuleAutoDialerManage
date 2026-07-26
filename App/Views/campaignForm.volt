{# Bit Dream IT extension: Campaign create/edit form #}
<div id="campaign-form-app" class="ui container">
    <div class="ui secondary menu">
        <div class="header item">
            <i class="volume control phone icon"></i>
            {{ taskId ? 'Edit campaign' : 'New campaign' }}
        </div>
        <div class="right menu">
            <div class="item">
                <a href="/admin-cabinet/module-auto-dialer-manage/campaigns" class="ui basic button">
                    <i class="arrow left icon"></i> Back to list
                </a>
            </div>
        </div>
    </div>

    <form class="ui form" v-on:submit.prevent="saveCampaign">
        <!-- Section: Basic -->
        <h3 class="ui dividing header"><i class="info circle icon"></i> Basic</h3>
        <div class="fields">
            <div class="eight wide field">
                <label>Name <span class="red">*</span></label>
                <input type="text" v-model="form.name" required placeholder="Invoice reminders - July">
            </div>
            <div class="four wide field">
                <label>CRM ID <span class="red">*</span></label>
                <input type="text" v-model="form.crmId" required placeholder="INV-2025-001">
            </div>
            <div class="four wide field">
                <label>State</label>
                <select v-model="form.state" class="ui dropdown">
                    <option value="0">Open (start immediately)</option>
                    <option value="2">Paused (start paused)</option>
                    <option value="1">Closed</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label>Description</label>
            <textarea v-model="form.description" rows="2" placeholder="Optional description shown on dashboard"></textarea>
        </div>

        <!-- Section: Routing -->
        <h3 class="ui dividing header"><i class="call icon"></i> Call routing</h3>
        <div class="fields">
            <div class="four wide field">
                <label>Type <span class="red">*</span></label>
                <select v-model="form.innerNumType" class="ui dropdown">
                    <option value="exten">Bridge to extension (agent)</option>
                    <option value="polling">Play IVR survey</option>
                </select>
            </div>
            <div class="four wide field" v-if="form.innerNumType === 'exten'">
                <label>Extension / agent number <span class="red">*</span></label>
                <select v-model="form.innerNum" class="ui dropdown">
                    <option value="">— select —</option>
                    {% for ext in allExtensions %}
                    <option value="{{ ext['number'] }}">{{ ext['number'] }} ({{ ext['callerid'] }})</option>
                    {% endfor %}
                </select>
            </div>
            <div class="four wide field" v-if="form.innerNumType === 'polling'">
                <label>Survey / poll <span class="red">*</span></label>
                <select v-model="form.innerNum" class="ui dropdown">
                    <option value="">— select —</option>
                    {% for p in pollings %}
                    <option value="{{ p['id'] }}">{{ p['name'] }}</option>
                    {% endfor %}
                </select>
            </div>
            <div class="four wide field">
                <label>Max simultaneous calls</label>
                <input type="number" v-model="form.maxCountChannels" min="1" max="100" required>
            </div>
            <div class="four wide field">
                <label>Dial prefix</label>
                <input type="text" v-model="form.dialPrefix" placeholder="999 (optional)">
            </div>
        </div>
        <div class="field" v-if="form.innerNumType === 'exten'">
            <div class="ui toggle checkbox">
                <input type="checkbox" v-model="form.isCallbackBool">
                <label>Callback mode (call the customer first, then bridge to agent)</label>
            </div>
        </div>

        <!-- Section: Schedule -->
        <h3 class="ui dividing header"><i class="clock icon"></i> Schedule (business hours)</h3>
        <div class="fields">
            <div class="four wide field">
                <label>Start time</label>
                <input type="time" v-model="startTimeStr" v-on:change="updateTimeStart">
            </div>
            <div class="four wide field">
                <label>End time</label>
                <input type="time" v-model="endTimeStr" v-on:change="updateTimeEnd">
            </div>
            <div class="eight wide field">
                <label>Allowed weekdays</label>
                <div class="ui multiple selection dropdown" id="scheduleDaysDropdown">
                    <input type="hidden" v-model="form.scheduleDays">
                    <i class="dropdown icon"></i>
                    <div class="default text">Every day (default)</div>
                    <div class="menu">
                        <div class="item" data-value="1">Monday</div>
                        <div class="item" data-value="2">Tuesday</div>
                        <div class="item" data-value="3">Wednesday</div>
                        <div class="item" data-value="4">Thursday</div>
                        <div class="item" data-value="5">Friday</div>
                        <div class="item" data-value="6">Saturday</div>
                        <div class="item" data-value="7">Sunday</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Retry -->
        <h3 class="ui dividing header"><i class="redo icon"></i> Retry policy</h3>
        <div class="fields">
            <div class="four wide field">
                <label>Max attempts per number</label>
                <input type="number" v-model="form.maxAttempt" min="1" max="20">
            </div>
            <div class="four wide field">
                <label>Seconds between retries</label>
                <input type="number" v-model="form.tryInterval" min="30" max="86400">
            </div>
            <div class="eight wide field">
                <label>&nbsp;</label>
                <div class="ui toggle checkbox">
                    <input type="checkbox" v-model="form.attemptUntilSignalBool">
                    <label>Keep retrying until any signal received (ignore answer status)</label>
                </div>
            </div>
        </div>

        <!-- Section: Advanced -->
        <h3 class="ui dividing header"><i class="settings icon"></i> Advanced (Bit Dream IT extensions)</h3>
        <div class="fields">
            <div class="four wide field">
                <label>AMD (Answering Machine Detection)</label>
                <div class="ui toggle checkbox">
                    <input type="checkbox" v-model="form.amdEnabledBool">
                    <label>Skip voicemail machines</label>
                </div>
            </div>
            <div class="twelve wide field">
                <label>Webhook URL (called on state change + completion)</label>
                <input type="url" v-model="form.callbackUrl" placeholder="https://crm.example.com/webhooks/dialer">
            </div>
        </div>

        <!-- Section: Numbers -->
        <h3 class="ui dividing header">
            <i class="list ol icon"></i> Phone numbers
            <span class="ui tiny label"><% form.numbers.length %> total</span>
        </h3>
        <p>Add numbers one-by-one, paste a list, or upload a CSV file.</p>

        <!-- Tab menu -->
        <div class="ui top attached tabular menu">
            <a class="item active" data-tab="single">Add single</a>
            <a class="item" data-tab="bulk">Paste bulk</a>
            <a class="item" data-tab="csv">CSV upload</a>
        </div>

        <!-- Single add -->
        <div class="ui bottom attached tab segment active" data-tab="single">
            <div class="fields">
                <div class="four wide field">
                    <label>Phone number <span class="red">*</span></label>
                    <input type="text" v-model="singleNumber.number" placeholder="7912345678">
                </div>
                <div class="four wide field">
                    <label>Name (optional)</label>
                    <input type="text" v-model="singleNumber.name" placeholder="Ivan Ivanov">
                </div>
                <div class="six wide field">
                    <label>TTS text / params (optional)</label>
                    <input type="text" v-model="singleNumber.params" placeholder='e.g. "Your balance is 1000"'>
                </div>
                <div class="two wide field">
                    <label>&nbsp;</label>
                    <button type="button" class="ui fluid green button" v-on:click="addSingleNumber">
                        <i class="plus icon"></i> Add
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk paste -->
        <div class="ui bottom attached tab segment" data-tab="bulk">
            <div class="field">
                <label>Paste numbers (one per line; format: number,name,params or just number)</label>
                <textarea v-model="bulkNumbers" rows="6" placeholder="7912345678,Ivan,Your balance is 1000&#10;7912345679,Petr,Your balance is 500&#10;7912345680"></textarea>
            </div>
            <button type="button" class="ui button" v-on:click="parseBulkNumbers">
                <i class="plus icon"></i> Parse &amp; add to list
            </button>
        </div>

        <!-- CSV upload -->
        <div class="ui bottom attached tab segment" data-tab="csv">
            <div class="field">
                <label>CSV file (header row required: number, name, params)</label>
                <input type="file" accept=".csv,text/csv" ref="csvFile" v-on:change="uploadCsv">
            </div>
            <p class="ui info message">
                <small>CSV format example:<br>
                <code>number,name,params<br>7912345678,Ivan,"{""speach"":""Your balance is 1000""}"</code></small>
            </p>
            <div class="ui info message" v-if="csvUploadResult">
                <% csvUploadResult %>
            </div>
        </div>

        <!-- Numbers table -->
        <table class="ui compact striped table" v-if="form.numbers.length > 0">
            <thead>
            <tr>
                <th class="collapsing">#</th>
                <th>Number</th>
                <th>Name</th>
                <th>Params</th>
                <th class="right aligned">Action</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(n, idx) in form.numbers" :key="idx">
                <td><% idx + 1 %></td>
                <td><code><% n.number %></code></td>
                <td><% n.name || '—' %></td>
                <td><small><% formatParams(n.params) %></small></td>
                <td class="right aligned">
                    <button type="button" class="ui mini icon button" v-on:click="removeNumber(idx)">
                        <i class="trash red icon"></i>
                    </button>
                </td>
            </tr>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="5" class="right aligned">
                    <button type="button" class="ui mini basic button" v-on:click="clearNumbers" v-if="form.numbers.length > 0">
                        <i class="eraser icon"></i> Clear all
                    </button>
                </th>
            </tr>
            </tfoot>
        </table>

        <!-- Submit -->
        <div class="ui clearing divider"></div>
        <button type="submit" class="ui primary big button" :class="{loading: saving}">
            <i class="save icon"></i> {{ taskId ? 'Save changes' : 'Create campaign' }}
        </button>
        <a href="/admin-cabinet/module-auto-dialer-manage/campaigns" class="ui basic button">Cancel</a>

        <div class="ui success message" v-if="successMsg">
            <i class="close icon" v-on:click="successMsg=''"></i>
            <div class="header">Saved</div>
            <p><% successMsg %></p>
        </div>
        <div class="ui error message" v-if="errorMsg">
            <i class="close icon" v-on:click="errorMsg=''"></i>
            <div class="header">Error</div>
            <p><% errorMsg %></p>
        </div>
    </form>
</div>

<script>
    window.__CAMPAIGN_FORM_DATA__ = {
        apiBaseUrl: "{{ apiBaseUrl }}",
        taskId: "{{ taskId }}",
        task: {{ task|json_encode }},
        pollings: {{ pollings|json_encode }},
        extensions: {{ extensions|json_encode }}
    };
</script>
