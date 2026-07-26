{# Bit Dream IT extension: In-module Developer API Guide #}
<div id="api-guide-app" class="ui container fluid">
    <div class="ui secondary menu">
        <div class="header item">
            <i class="code icon"></i> Developer API Guide
        </div>
        <div class="right menu">
            <div class="item">
                <a href="/admin-cabinet/module-auto-dialer-manage/campaigns" class="ui basic button">
                    <i class="list icon"></i> Back to campaigns
                </a>
            </div>
            <div class="item">
                <a href="https://github.com/bitdreamit/ModuleAutoDialerManage" target="_blank" class="ui basic button">
                    <i class="github icon"></i> GitHub
                </a>
            </div>
        </div>
    </div>

    <div class="ui info message">
        <div class="header">UI + API coexist</div>
        <p>The web UI you see in this module is just a Vue.js front-end that calls the same REST endpoints documented below. You can use the UI for day-to-day operations AND call the API from external systems (Laravel, Python, 1C, etc.) at the same time — they share the same database and produce identical results.</p>
    </div>

    <!-- Config box -->
    <div class="ui segment">
        <h3 class="ui dividing header"><i class="cog icon"></i> Your PBX configuration</h3>
        <div class="ui two column grid">
            <div class="column">
                <div class="ui labeled input">
                    <div class="ui label">Base URL</div>
                    <input type="text" readonly :value="pbxHost + apiBaseUrl">
                </div>
            </div>
            <div class="column">
                <div class="ui labeled input">
                    <div class="ui label">Auth (off-PBX only)</div>
                    <input type="text" readonly :value="pbxHost + '/admin-cabinet/session/start'">
                </div>
            </div>
        </div>
        <p class="ui tiny message" style="margin-top: 8px;">
            <small>Local requests from <code>127.0.0.1</code> skip auth entirely. For external clients, POST login/password to the auth URL above to get a <code>PHPSESSID</code> cookie, then reuse it on all subsequent calls.</small>
        </p>
    </div>

    <!-- Tabs: by language -->
    <div class="ui top attached tabular menu">
        <a class="item active" data-tab="curl">curl</a>
        <a class="item" data-tab="laravel">Laravel / PHP HTTP</a>
        <a class="item" data-tab="php">Plain PHP</a>
        <a class="item" data-tab="python">Python</a>
        <a class="item" data-tab="js">JavaScript / Node</a>
    </div>

    <!-- ====== curl tab ====== -->
    <div class="ui bottom attached tab segment active" data-tab="curl">
        <h4 class="ui header">1. Authenticate (off-PBX only)</h4>
        <pre><code>curl -c cookies.txt -X POST {{ pbxHost }}/admin-cabinet/session/start \
  -d "login=admin&password=YOUR_PASSWORD"</code></pre>

        <h4 class="ui header">2. Create a campaign</h4>
        <pre><code>curl -b cookies.txt -X POST {{ pbxHost }}{{ apiBaseUrl }}/task \
  -H "Content-Type: application/json" \
  -d '{
    "crmId": "INV-001",
    "name": "Payment reminders",
    "state": 0,
    "innerNum": "200",
    "innerNumType": "exten",
    "maxCountChannels": 5,
    "dialPrefix": "999",
    "timeStart": 540,
    "timeEnd": 1080,
    "scheduleDays": "1,2,3,4,5",
    "amdEnabled": 1,
    "callbackUrl": "https://crm.example.com/webhooks/dialer",
    "numbers": [
      {"number": "7912345678", "params": {"speach": "Your balance is 1000."}}
    ]
  }'</code></pre>

        <h4 class="ui header">3. List all campaigns</h4>
        <pre><code>curl -b cookies.txt {{ pbxHost }}{{ apiBaseUrl }}/task</code></pre>

        <h4 class="ui header">4. Pause a campaign</h4>
        <pre><code>curl -b cookies.txt -X PUT {{ pbxHost }}{{ apiBaseUrl }}/task/42 \
  -H "Content-Type: application/json" \
  -d '{"state": 2}'</code></pre>

        <h4 class="ui header">5. Get live status (poll every 3-5s)</h4>
        <pre><code>curl -b cookies.txt {{ pbxHost }}{{ apiBaseUrl }}/task/42/status</code></pre>

        <h4 class="ui header">6. Get campaign summary</h4>
        <pre><code>curl -b cookies.txt {{ pbxHost }}{{ apiBaseUrl }}/task/42/summary</code></pre>

        <h4 class="ui header">7. Sync call results incrementally</h4>
        <pre><code># {changeTime} = Unix timestamp cursor
curl -b cookies.txt {{ pbxHost }}{{ apiBaseUrl }}/results/0</code></pre>

        <h4 class="ui header">8. Export CSV</h4>
        <pre><code>curl -b cookies.txt -OJ {{ pbxHost }}{{ apiBaseUrl }}/task/42/export</code></pre>

        <h4 class="ui header">9. Add to DNC blacklist</h4>
        <pre><code>curl -b cookies.txt -X POST {{ pbxHost }}{{ apiBaseUrl }}/blacklist \
  -H "Content-Type: application/json" \
  -d '{"numbers": ["7912345678"], "reason": "complaint", "source": "manual"}'</code></pre>

        <h4 class="ui header">10. Test call single number</h4>
        <pre><code>curl -b cookies.txt -X POST {{ pbxHost }}{{ apiBaseUrl }}/task/42/test-call \
  -H "Content-Type: application/json" \
  -d '{"number": "7912345678", "params": {"speach": "Test"}}'</code></pre>
    </div>

    <!-- ====== Laravel tab ====== -->
    <div class="ui bottom attached tab segment" data-tab="laravel">
        <h4 class="ui header">Service class — <code>app/Services/MikoPBX.php</code></h4>
        <pre><code>&lt;?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MikoPBX
{
    private string $host;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $this->host = config('services.mikopbx.host');
        $this->user = config('services.mikopbx.user');
        $this->pass = config('services.mikopbx.pass');
    }

    private function client()
    {
        $cookie = $this->getSessionCookie();
        return Http::withHeaders(['Cookie' => "PHPSESSID={$cookie}"]);
    }

    private function getSessionCookie(): string
    {
        return Cache::remember('mikopbx_session', now()->addMinutes(30), function () {
            $response = Http::asForm()->post("{$this->host}/admin-cabinet/session/start", [
                'login' => $this->user,
                'password' => $this->pass,
            ]);
            return $response->cookie('PHPSESSID') ?? '';
        });
    }

    public function createCampaign(array $data): array
    {
        return $this->client()
            ->post("{$this->host}/pbxcore/api/module-dialer-manage/v1/task", $data)
            ->json();
    }

    public function pauseCampaign(int $taskId): array
    {
        return $this->client()
            ->put("{$this->host}/pbxcore/api/module-dialer-manage/v1/task/{$taskId}", [
                'state' => 2,
            ])->json();
    }

    public function getCampaignStatus(int $taskId): array
    {
        return $this->client()
            ->get("{$this->host}/pbxcore/api/module-dialer-manage/v1/task/{$taskId}/status")
            ->json();
    }

    public function getSummary(int $taskId): array
    {
        return $this->client()
            ->get("{$this->host}/pbxcore/api/module-dialer-manage/v1/task/{$taskId}/summary")
            ->json();
    }

    public function addToBlacklist(array $numbers, string $reason = ''): array
    {
        return $this->client()
            ->post("{$this->host}/pbxcore/api/module-dialer-manage/v1/blacklist", [
                'numbers' => $numbers,
                'reason' => $reason,
                'source' => 'manual',
            ])->json();
    }

    public function syncResults(callable $callback, int $lookbackSeconds = 0): void
    {
        $cacheKey = 'mikopbx_results_cursor';
        $cursor = Cache::get($cacheKey, time() - $lookbackSeconds);

        $response = $this->client()
            ->get("{$this->host}/pbxcore/api/module-dialer-manage/v1/results/{$cursor}")
            ->json();

        if ($response['result'] ?? false) {
            foreach ($response['data']['results'] ?? [] as $row) {
                $callback($row);
                if (isset($row['changeTime']) && $row['changeTime'] > $cursor) {
                    $cursor = $row['changeTime'];
                }
            }
            Cache::put($cacheKey, $cursor, now()->addDays(7));
        }
    }
}</code></pre>

        <h4 class="ui header">Usage</h4>
        <pre><code>// 1. Launch campaign
$api = app(App\Services\MikoPBX::class);
$result = $api->createCampaign([
    'crmId' => 'INV-2025-001',
    'name' => 'Invoice reminders - July',
    'state' => 0,
    'innerNum' => '200',
    'innerNumType' => 'exten',
    'maxCountChannels' => 5,
    'dialPrefix' => '999',
    'timeStart' => 540,
    'timeEnd' => 1080,
    'scheduleDays' => '1,2,3,4,5',
    'amdEnabled' => 1,
    'callbackUrl' => route('webhooks.dialer'),
    'numbers' => $customers->map(fn($c) => [
        'number' => $c->phone,
        'params' => ['speach' => "Hello {$c->name}, your invoice is due."],
    ])->toArray(),
]);

// 2. Scheduled sync (every minute via Laravel scheduler)
$api->syncResults(function ($row) {
    CallResult::updateOrCreate(
        ['linked_id' => $row['linkedId']],
        [
            'task_id' => $row['task_id'],
            'phone' => $row['number'],
            'state' => $row['outDialState'],
            'duration' => $row['duration'],
            'cause' => $row['cause'],
            'happened_at' => $row['time'],
        ]
    );
}, lookbackSeconds: 86400);</code></pre>

        <h4 class="ui header">Webhook receiver — <code>routes/api.php</code></h4>
        <pre><code>Route::post('/webhooks/dialer', function (\Illuminate\Http\Request $request) {
    $payload = $request->validate([
        'event'    => 'required|string|in:campaign.state_changed,campaign.completed',
        'task_id'  => 'required|integer',
        'name'     => 'required|string',
        'crm_id'   => 'nullable|string',
    ]);

    switch ($payload['event']) {
        case 'campaign.state_changed':
            $data = $request->validate([
                'old_state' => 'integer', 'new_state' => 'integer',
                'old_state_label' => 'string', 'new_state_label' => 'string',
                'changed_at' => 'string',
            ]);
            Campaign::where('external_id', $payload['task_id'])
                ->update(['status' => $data['new_state_label']]);
            Log::info("Campaign {$payload['task_id']}: {$data['old_state_label']} → {$data['new_state_label']}");
            break;

        case 'campaign.completed':
            $data = $request->validate([
                'total' => 'integer', 'answered' => 'integer',
                'failed' => 'integer', 'completed_at' => 'string',
            ]);
            Campaign::where('external_id', $payload['task_id'])
                ->update([
                    'status'         => 'completed',
                    'total_dialed'   => $data['total'],
                    'total_answered' => $data['answered'],
                    'completed_at'   => now(),
                ]);
            break;
    }
    return response()->json(['ok' => true]);
});</code></pre>
    </div>

    <!-- ====== Plain PHP tab ====== -->
    <div class="ui bottom attached tab segment" data-tab="php">
        <h4 class="ui header">Minimal PHP client (no framework)</h4>
        <pre><code>&lt;?php
class DialerClient {
    private string $baseUrl;
    private ?string $cookieFile = null;

    public function __construct(string $host, string $user = '', string $pass = '') {
        $this->baseUrl = rtrim($host, '/');
        if ($user && $pass) $this->login($user, $pass);
    }

    private function login(string $user, string $pass): void {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'dialer_cookie_');
        $ch = curl_init("{$this->baseUrl}/admin-cabinet/session/start");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['login' => $user, 'password' => $pass]),
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    public function request(string $method, string $path, ?array $body = null): array {
        $ch = curl_init("{$this->baseUrl}{$path}");
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];
        if ($this->cookieFile) $opts[CURLOPT_COOKIEFILE] = $this->cookieFile;
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?: [];
    }

    public function createCampaign(array $data): array {
        return $this->request('POST', '/pbxcore/api/module-dialer-manage/v1/task', $data);
    }

    public function pause(int $taskId): array {
        return $this->request('PUT', "/pbxcore/api/module-dialer-manage/v1/task/{$taskId}", ['state' => 2]);
    }

    public function getStatus(int $taskId): array {
        return $this->request('GET', "/pbxcore/api/module-dialer-manage/v1/task/{$taskId}/status");
    }
}

// Usage (local, no auth needed):
$client = new DialerClient('{{ pbxHost }}');
$result = $client->createCampaign([
    'crmId' => 'test-1',
    'name' => 'Test campaign',
    'state' => 0,
    'innerNum' => '200',
    'innerNumType' => 'exten',
    'maxCountChannels' => 1,
    'numbers' => [['number' => '7912345678']],
]);
print_r($result);</code></pre>
    </div>

    <!-- ====== Python tab ====== -->
    <div class="ui bottom attached tab segment" data-tab="python">
        <h4 class="ui header">Python <code>requests</code>-based client</h4>
        <pre><code>import requests
import time

class DialerClient:
    def __init__(self, host, user=None, password=None):
        self.base_url = host.rstrip('/')
        self.session = requests.Session()
        if user and password:
            self.session.post(
                f"{self.base_url}/admin-cabinet/session/start",
                data={'login': user, 'password': password}
            )

    def create_campaign(self, data):
        r = self.session.post(
            f"{self.base_url}/pbxcore/api/module-dialer-manage/v1/task",
            json=data
        )
        return r.json()

    def pause(self, task_id):
        r = self.session.put(
            f"{self.base_url}/pbxcore/api/module-dialer-manage/v1/task/{task_id}",
            json={'state': 2}
        )
        return r.json()

    def get_status(self, task_id):
        r = self.session.get(
            f"{self.base_url}/pbxcore/api/module-dialer-manage/v1/task/{task_id}/status"
        )
        return r.json()

    def sync_results(self, callback, lookback_seconds=86400):
        cursor = int(time.time()) - lookback_seconds
        while True:
            r = self.session.get(
                f"{self.base_url}/pbxcore/api/module-dialer-manage/v1/results/{cursor}"
            ).json()
            if not r.get('result'):
                break
            results = r.get('data', {}).get('results', [])
            if not results:
                break
            for row in results:
                callback(row)
                if row.get('changeTime', 0) > cursor:
                    cursor = row['changeTime']

# Usage
client = DialerClient('{{ pbxHost }}')
result = client.create_campaign({
    'crmId': 'py-test-1',
    'name': 'Python test',
    'state': 0,
    'innerNum': '200',
    'innerNumType': 'exten',
    'maxCountChannels': 1,
    'numbers': [{'number': '7912345678'}],
})
print(result)

# Sync results to your DB
client.sync_results(lambda row: print(f"Call: {row['number']} - {row['outDialState']}"))</code></pre>
    </div>

    <!-- ====== JavaScript / Node tab ====== -->
    <div class="ui bottom attached tab segment" data-tab="js">
        <h4 class="ui header">Browser JavaScript (fetch)</h4>
        <pre><code>// Same-origin (already logged into MikoPBX admin)
const API = '/pbxcore/api/module-dialer-manage/v1';

// Create campaign
const r = await fetch(`${API}/task`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        crmId: 'js-test',
        name: 'JS test',
        state: 0,
        innerNum: '200',
        innerNumType: 'exten',
        maxCountChannels: 1,
        numbers: [{number: '7912345678'}]
    })
});
const result = await r.json();
console.log(result);</code></pre>

        <h4 class="ui header">Node.js (axios)</h4>
        <pre><code>const axios = require('axios');
const axiosCookieJarSupport = require('axios-cookiejar-support').default;
const { CookieJar } = require('tough-cookie');

const jar = new CookieJar();
const client = axiosCookieJarSupport.create(axios.create({ jar, withCredentials: true }));

const PBX = '{{ pbxHost }}';
const API = PBX + '/pbxcore/api/module-dialer-manage/v1';

(async () => {
    // 1. Login (off-PBX only)
    await client.post(`${PBX}/admin-cabinet/session/start`, new URLSearchParams({
        login: 'admin', password: 'YOUR_PASSWORD'
    }));

    // 2. Create campaign
    const r = await client.post(`${API}/task`, {
        crmId: 'node-test',
        name: 'Node.js test',
        state: 0,
        innerNum: '200',
        innerNumType: 'exten',
        maxCountChannels: 5,
        numbers: [{number: '7912345678', params: {speach: 'Hello from Node'}}]
    });
    console.log(r.data);

    // 3. Poll status
    const taskId = r.data.data.id;
    setInterval(async () => {
        const s = await client.get(`${API}/task/${taskId}/status`);
        console.log(`In progress: ${s.data.data.in_progress}, total dialed: ${s.data.data.total_dialed}`);
    }, 4000);
})();</code></pre>
    </div>

    <!-- ====== Endpoint reference ====== -->
    <h2 class="ui dividing header" style="margin-top: 30px;">
        <i class="book icon"></i> Full endpoint reference
    </h2>

    <table class="ui compact celled table">
        <thead>
        <tr>
            <th>Method</th>
            <th>Endpoint</th>
            <th>Purpose</th>
            <th>UI page that uses it</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="e in endpoints" :key="e.method + e.path">
            <td><span class="ui mini label" :class="methodColor(e.method)"><% e.method %></span></td>
            <td><code><% e.path %></code></td>
            <td><% e.purpose %></td>
            <td><small><% e.uiPage %></small></td>
        </tr>
        </tbody>
    </table>

    <!-- ====== Webhook payloads ====== -->
    <h2 class="ui dividing header" style="margin-top: 30px;">
        <i class="bell icon"></i> Webhook payloads (sent to your callbackUrl)
    </h2>

    <h4 class="ui header">campaign.state_changed</h4>
    <pre><code>{
  "event": "campaign.state_changed",
  "task_id": 42,
  "name": "Payment reminders - June",
  "crm_id": "1001",
  "old_state": 0,
  "new_state": 2,
  "old_state_label": "open",
  "new_state_label": "paused",
  "changed_at": "2025-07-25T15:30:00+03:00"
}</code></pre>

    <h4 class="ui header">campaign.completed</h4>
    <pre><code>{
  "event": "campaign.completed",
  "task_id": 42,
  "name": "Payment reminders - June",
  "crm_id": "1001",
  "total": 1500,
  "answered": 872,
  "failed": 628,
  "completed_at": "2025-07-25T15:30:00+03:00"
}</code></pre>

    <p class="ui info message">
        <small>State codes: <code>0</code> = open (active), <code>1</code> = closed, <code>2</code> = paused. Both webhook events go to the SAME <code>callbackUrl</code> — switch on the <code>event</code> field in your receiver.</small>
    </p>
</div>

<script>
    window.__API_GUIDE_DATA__ = {
        pbxHost: "{{ pbxHost }}",
        apiBaseUrl: "{{ apiBaseUrl }}"
    };
</script>
