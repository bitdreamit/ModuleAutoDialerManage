# Changelog

All notable changes to this Bit Dream IT fork of `ModuleAutoDialer` are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.35-bitdreamit-1] — 2025-07-25

Forked from `mikopbx/ModuleAutoDialer` v1.35. Adds 10+ professional campaign-dialer features.

### Added — Bit Dream IT extensions

- **Live dashboard** — new sidebar item "Dialer dashboard" with real-time campaign cards, agent status grid, and recent calls feed. Auto-refreshes every 4 seconds via REST API polling. Vue.js frontend.
- **DNC (Do-Not-Call) blacklist** — new sidebar item "DNC blacklist" with full CRUD UI. Numbers in the blacklist are skipped by `WorkerDialer` before any other check. New `Models/Blacklist.php` with table `m_ModuleAutoDialerManage_Blacklist`.
- **AMD (Answering Machine Detection)** — per-campaign `amdEnabled` boolean. When enabled, the dialplan runs Asterisk's `AMD()` app before bridging. Voicemail machines are hung up automatically. New `[dialer-manage-amd-machine]` context.
- **Campaign completion webhook** — per-campaign `callbackUrl`. When state changes to closed, the worker POSTs `{event: "campaign.completed", task_id, total, answered, ...}` with 5-second timeout. Fires exactly once per campaign.
- **Scheduling (business hours)** — per-campaign `scheduleDays` field. Comma-separated ISO weekday numbers (1=Mon … 7=Sun). Worker skips dialing outside scheduled days.
- **Campaign summary report** — `GET /task/{id}/summary` returns total dialed, answered, failed, answer rate %, avg duration, total duration.
- **CSV export** — `GET /task/{id}/export` downloads CSV with columns: number, state, duration_sec, attempt, time, cause. UTF-8 with BOM for Excel.
- **CSV import** — `POST /task/{id}/import-csv` accepts multipart CSV upload with columns: number, name (optional), params (optional JSON or text).
- **Test call** — `POST /task/{id}/test-call` dials a single number to preview a campaign/poll before full launch.
- **Recording lookup** — `GET /recording/{linkedId}` joins to MikoPBX core `CallDetailRecords` table to find audio file path.
- **Live status endpoint** — `GET /task/{id}/status` returns in-progress count, max channels, total dialed, AMD flag. Designed for 3-5s polling.
- **Agent status endpoint** — `GET /agents-status` returns all `DialerExtensions` with current state (idle/in_call/ringing/unavailable) from worker cache.

### Changed — Bit Dream IT branding

- `module.json` `developer` field: `MIKO` → `Bit Dream IT`
- `module.json` `support_email` field: `help@miko.ru` → `support@bitdreamit.com`
- `composer.json` `name`: `mikopbx/moduletemplate` → `bitdreamit/module-auto-dialer-manage`
- `composer.json` `description`: updated to mention professional features
- `composer.json` `authors`: added Bit Dream IT as maintainer, kept MIKO authors as upstream contributors
- `composer.json` `support`/`homepage`: pointed to `github.com/bitdreamit/ModuleAutoDialerManage`
- `composer.json` `funding`: GitHub Sponsors instead of Patreon
- All new code files have `Copyright (C) 2025 Bit Dream IT` header

### Renamed — for coexistence with original module

All identifiers are renamed so this module can run alongside the original `ModuleAutoDialer` on the same PBX without conflict:

- Module unique ID: `ModuleAutoDialer` → `ModuleAutoDialerManage`
- Namespace: `Modules\ModuleAutoDialer\` → `Modules\ModuleAutoDialerManage\`
- All PHP class names + filenames (controller, forms, model, etc.)
- URL slug: `module-auto-dialer` → `module-auto-dialer-manage`
- REST API URL prefix: `/pbxcore/api/module-dialer/v1/*` → `/pbxcore/api/module-dialer-manage/v1/*`
- All 8 Asterisk dialplan contexts: `[dialer-out-originate-in]` → `[dialer-manage-out-originate-in]`, etc.
- All 12 DB tables: `m_Clients` → `m_ModuleAutoDialerManage_Clients`, etc.
- Asterisk .call file prefix: `dialer-$taskId-...call` → `dialer-manage-$taskId-...call`
- Asterisk spool dir: `dialer-client-response/` → `dialer-manage-client-response/`
- All translation keys: `repModuleAutoDialer` → `repModuleAutoDialerManage`, etc.
- JS class name: `ModuleAutoDialer` → `ModuleAutoDialerManage`
- All CSS/JS file names: `module-auto-dialer.css` → `module-auto-dialer-manage.css`, etc.

### Added — data migration

- New `transferOldSettings()` method in `Setup/PbxExtensionSetup.php` copies ALL rows from the original module's 12 tables into the new prefixed tables on install. Old tables are NOT dropped (you keep original data as backup). Idempotent: re-running skips rows with existing IDs.

### Added — documentation

- `docs/USER-GUIDE.md` — 17-section operator manual with A-Z coverage
- `docs/DEVELOPER-GUIDE.md` — REST API reference + Laravel/PHP/Python integration examples
- `docs/CHANGELOG.md` — this file
- `README.md` — updated with Bit Dream IT branding and quickstart

### Fixed — upstream boilerplate

- `Messages/en.php`: replaced leftover `ModuleTemplate` boilerplate strings with proper module-specific text
  - `'repModuleAutoDialerManage'` value: `"Module template - %repesent%"` → `"Auto dialer - %repesent%"`
  - `'mo_ModuleModuleAutoDialerManage'` value: `"Module template"` → `"Auto dialer"`
  - `'BreadcrumbModuleAutoDialerManage'` value: `"Template module"` → `"Auto dialer"`
  - `'SubHeaderModuleAutoDialerManage'` value: `"Example to create own modules"` → `"Automatic call dialing and polling"`

### Pinned

- `module.json` `version` field: `%ModuleVersion%` (CI placeholder) → `1.35` (so manual install works)

## [1.35] — upstream (MIKO LLC)

Original `mikopbx/ModuleAutoDialer` v1.35 release. See https://github.com/mikopbx/ModuleAutoDialer/releases/tag/v1.35 for upstream changelog.
