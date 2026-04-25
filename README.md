# Severino Labs Security Layer

[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![WordPress: 5.8+](https://img.shields.io/badge/wordpress-5.8%2B-21759b.svg)](https://wordpress.org/)
[![PHP: 7.4+](https://img.shields.io/badge/php-7.4%2B-777bb4.svg)](https://www.php.net/)
[![Version: 6.1.0](https://img.shields.io/badge/version-6.1.0-success.svg)](#changelog)

A focused WordPress security plugin that consolidates application hardening, browser-enforced security policies, file integrity monitoring, security event logging, and an optional passkey-first login experience into a single, reviewable codebase.

It exists to replace the usual sprawl of snippets, theme edits, and partially-overlapping security plugins with one well-scoped layer that's easy to audit, extend, and ship through normal version control.

## Table of Contents

- [Highlights](#highlights)
- [Features](#features)
- [Scope and Limitations](#scope-and-limitations)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [The Admin Dashboard](#the-admin-dashboard)
- [Security Score](#security-score)
- [Privacy and Data Collection](#privacy-and-data-collection)
- [Project Structure](#project-structure)
- [Contributing](#contributing)
- [Security Best Practices](#security-best-practices)
- [Changelog](#changelog)
- [License](#license)
- [Author](#author)

## Highlights

- 🔒 **Sensible-by-default hardening** — XML-RPC, REST user enumeration, author archives, generator tag, and unused entry points are centralized in one baseline hardening layer.
- 📁 **Real file integrity monitoring** — SHA-256 baseline, manual or scheduled checks, configurable target groups, and clear add/remove/modify reporting.
- 🛡️ **Local security event log** — Captures blocked/suspicious requests with IP, user agent, country, and Cloudflare metadata, queryable from the admin.
- 🔐 **Passkey-first login** — Optional passkey-only WordPress login screen, gated behind a real verification test so admins can't accidentally lock themselves out.
- 🧾 **Auditable** — One small repo, no remote calls, no bundled libraries, no obfuscation. Every behavior is reachable from `includes/`.

## Features

### 🔒 Application Hardening

- XML-RPC disablement
- Pingback method blocking
- REST API user enumeration reduction (admins still have full access)
- Author archive enumeration blocking (admins still have full access)
- WordPress generator tag removal
- Unused public endpoint blocking (`xmlrpc.php`, `wp-signup.php`, `wp-trackback.php`, `wp-links-opml.php`, `wp-activate.php`)
- Custom security error page

### 🌐 Browser-Facing Security Controls

- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` (camera/microphone/geolocation locked down)
- Configurable Content Security Policy

### 📁 File Integrity Monitoring (FIM)

- SHA-256 baseline of the trusted file state
- Manual or scheduled (daily WP-Cron) integrity checks
- Configurable monitored target groups
- Configurable excluded paths (logs, caches, uploads, vendor directories, etc.)
- Added/removed/modified detection with file-level reporting
- Status summary surfaced in the admin dashboard

### 🛡️ Security Event Monitoring (SEM)

- Local request logging for security-relevant events
- Event type categorization (`xmlrpc_blocked`, `rest_user_enumeration_attempt`, `author_enumeration_blocked`, etc.)
- Source IP, user agent, referer, request URI, and HTTP method capture
- Cloudflare metadata support (`CF-Connecting-IP`, `CF-IPCountry`, `CF-Ray`) where present
- Recent activity panel on the dashboard
- Per-user opt-out from logging via the WordPress profile screen

### 🔐 Passkey-First Login (Optional)

- Custom WordPress login page with passkey-first UI
- Configurable site label and logo
- Required usernameless-passkey verification before the toggle can be enabled, eliminating the "I locked myself out" failure mode
- Pre-flight checklist on the Settings page covering provider, usernameless mode, and registered passkeys
- Provider-aware error messages (the test button distinguishes "WP-WebAuthn missing" from "Usernameless Login disabled" from "no passkeys registered")

> **Optional dependency.** The passkey-only login feature delegates WebAuthn challenge issuance and verification to the [WP-WebAuthn](https://wordpress.org/plugins/wp-webauthn/) plugin. **Every other feature in this plugin works without WP-WebAuthn.** When WP-WebAuthn is missing the passkey toggle stays locked and the plugin falls back to the standard WordPress login screen.

## Scope and Limitations

This plugin is a focused hardening and monitoring layer, not a complete replacement for a WAF, malware scanner, backup system, or host-level firewall. It deliberately does **not**:

- Provide malware signature scanning
- Replace Cloudflare, server firewall rules, or hosting-level controls
- Guarantee origin-server lockdown
- Implement WebAuthn/passkey authentication on its own
- Replace regular WordPress, plugin, theme, and server updates

It plays well with all of those — it's just not trying to be them.

## Requirements

- **WordPress 5.8+**
- **PHP 7.4+**
- **MySQL 5.6+**

### Optional

- **[WP-WebAuthn](https://wordpress.org/plugins/wp-webauthn/)** — only required for the passkey-only login feature. Without it the plugin still installs, activates, and runs cleanly; the passkey toggle stays locked and the standard login screen is used.

## Installation

1. Download or clone this repository into `wp-content/plugins/severino-labs-security-layer/`.
2. Activate **Severino Labs Security Layer** from the WordPress *Plugins* screen.
3. Open **Severino Security → Settings** and review the security controls. Core hardening is on by default.
4. Open **Severino Security → File Integrity** and create a baseline once you've confirmed your current file state is the one you trust.
5. *(Optional)* Install [WP-WebAuthn](https://wordpress.org/plugins/wp-webauthn/), enable Usernameless Login in its settings, register a passkey on your admin account, and run the **Test Usernameless Passkey** button to unlock the passkey-only login toggle.

That's it — there's no signup, no remote service, no API key.

## Configuration

### Security Controls

Each control can be toggled independently from the **Settings** page. The Security Controls table uses two columns:

- **Status** — a colored pill showing the current state (Always Enabled / Enabled / Disabled / Locked)
- **Toggle** — an iOS-style switch for actionable rows; locked rows display an em-dash

Core hardening controls show as *Always Enabled* because they define the baseline security posture.

### Branding

The custom login page can be styled with:

- A custom logo URL
- A site display label
- Per-deployment branding overrides

### File Integrity Monitoring

- **Monitored Targets** — files or directories to include in integrity checks
- **Excluded Paths** — logs, caches, uploads, or temporary directories to skip
- **Baseline** — a SHA-256 snapshot of the approved file state, regenerated on demand
- **Schedule** — daily WP-Cron-driven integrity checks

## The Admin Dashboard

The dashboard surfaces:

- **Security Score** — a 0–100% health indicator based on enabled controls and monitoring state
- **Status Overview** — File Integrity, Event Monitoring, and Baseline status pills
- **Security Metrics** — automation state, events today, total events logged, last FIM check
- **Action Required / Recommendations** — split into urgent vs. nice-to-have
- **Recent Security Activity** — most recent events with country and IP
- **System Information** — plugin, WordPress, and PHP versions

## Security Score

The score is a local indicator of how much of *this plugin*'s coverage is in use. It is **not** a comprehensive security rating for the WordPress environment.

| Component | Points |
| --- | ---: |
| Plugin active | 20 |
| FIM enabled | 25 |
| Trusted baseline created | 15 |
| Latest FIM check passed | 10 |
| SEM enabled | 20 |
| No security events today | 10 |
| **Maximum** | **100** |

## Privacy and Data Collection

Transparency matters more than convenience for a security plugin, so here's what gets stored locally and where:

- **Security event log (`data/security-events.log`)** — for each blocked or suspicious request, the plugin records timestamp, event type, request URI, HTTP method, source IP, user agent, referer, Cloudflare metadata when present, and the user ID (if logged in). The log is rotated to ~1000 lines.
- **File integrity baseline (`data/fim-baseline.json`)** — SHA-256 hashes of monitored files. No file contents.
- **File integrity status / log** — most recent check result and a rolling check history.
- **Plugin settings** — stored in the `sl_security_settings` WordPress option.

**No data leaves the WordPress site.** There are no remote API calls, telemetry, analytics, or external dependencies at runtime. The only optional external integration is the WP-WebAuthn plugin, which is in turn fully self-hosted.

Users can opt out of having their authenticated activity logged from their WordPress profile (look for *Severino Labs Security Layer → Exclude my activity from security logs*).

## Project Structure

```
severino-labs-security-layer/
├── severino-labs-security-layer.php   # Plugin bootstrap (header, constants, activation)
├── includes/
│   ├── settings.php                   # Settings storage, defaults, locked-controls list
│   ├── hardening.php                  # Application hardening + browser security headers
│   ├── file-integrity-monitor.php     # FIM baseline + check engine
│   ├── security-event-monitor.php     # SEM logging + log helpers
│   ├── passkey-login.php              # Passkey-first login screen + WP-WebAuthn glue
│   └── security-admin-page.php        # Admin pages (Dashboard, FIM, Events, Settings)
├── assets/
│   ├── css/admin.css
│   ├── css/login.css
│   ├── js/admin.js
│   ├── js/login.js
│   └── js/passkey-test.js
├── templates/
│   └── security-error.php             # Custom 4xx/5xx page
├── data/                              # Runtime artifacts (logs, baseline) — gitignored
├── LICENSE
└── README.md
```

Each module is independently readable and only loads its hooks if its corresponding setting is enabled.

## Contributing

This plugin is developed as a small, single-maintainer project, but pull requests, issues, and security reports are welcome.

A reasonable workflow:

1. Fork and clone the repo.
2. *(Optional but recommended)* `composer install` to pull in the WordPress IDE stubs used by Intelephense — this gives you autocomplete for WordPress core functions in VS Code.
3. Make changes locally and run them against a development WordPress site.
4. Verify the diff and that no inline styles, dead code, or orphan CSS classes were introduced.
5. Open a PR describing the change and its motivation.

For security-sensitive reports, please open a private discussion or email the author rather than a public issue.

## Security Best Practices

1. Keep the core hardening controls enabled unless you have a specific reason to expose a feature.
2. Create a fresh FIM baseline after trusted plugin, theme, or WordPress core updates so the next check has a correct reference.
3. Review security events on a recurring cadence — repeated patterns from the same source are usually worth investigating.
4. Add cache, upload, log, and temporary directories to FIM **excluded paths**, not monitored targets.
5. Use the daily scheduled FIM check rather than relying on manual runs.
6. Keep WordPress core, themes, plugins, PHP, and hosting controls updated. This plugin reduces exposure; it does not patch known vulnerabilities for you.

## Changelog

### Version 6.1.0

**Passkey login**

- Gated the passkey-only login toggle behind a real usernameless-passkey verification test so an admin can't lock themselves out by enabling passkey login without a working credential.
- Made the WP-WebAuthn dependency explicit with an admin notice when the toggle is enabled but the provider is missing, plus an in-page status row in Settings showing whether the provider is detected.
- Added a numbered pre-flight checklist on the Settings page (WP-WebAuthn active → Usernameless Login enabled → at least one passkey registered) so the prerequisites are visible before clicking the test button.
- Test button now distinguishes four failure modes ("WP-WebAuthn not active" / "Usernameless Login disabled" / unexpected response with body excerpt / "no challenge / no registered passkey") instead of a generic parse error.
- Fixed the test button being silently inert on some setups (a `DOMContentLoaded` race on footer scripts).
- Removed a nested `<form>` (invalid HTML) that was causing "Reset Passkey Verification" to actually trigger "Save All Settings".

**Hardening**

- Whitelisted admin requests through the REST `/users` and author-archive blockers so legitimate admin tooling (block editor, user directory, profile editors) keeps working while still blocking unauthenticated enumeration.

**Admin UI polish**

- Redesigned the Security Controls table with separate **Status** and **Toggle** columns: a uniform color-coded pill (Always Enabled / Enabled / Disabled / Locked) plus a real iOS-style toggle switch for actionable rows.
- Removed a redundant "Status: ..." banner at the top of the File Integrity page (the same information was already in the status cards below it).

**Code quality**

- Renamed `sl_render_wp_die_page` to `sl_security_render_wp_die_page` for prefix consistency — every function in the codebase now uses one of three prefixes (`sl_security_`, `sl_fim_`, `sl_sem_`).
- Cleaned up `.gitignore` so the shared `.vscode/stubs/` (WordPress stubs for Intelephense) stays tracked for contributors.

### Version 6.0.0 — Public repository preparation

**Admin UI overhaul**

- Unified Dashboard, File Integrity, and Security Events around a single status-card system.
- Refined the security-score donut with improved sizing and label balance.
- Rebuilt score breakdown rows with consistent label, description, and value alignment.
- Fixed the collapsible score breakdown toggle.
- Split dashboard recommendations into **Action Required** and **Recommendations** sections.
- Added a Country column to Dashboard Recent Security Activity.
- Updated the Security Events page to match the shared admin layout.
- Added a parallel intro paragraph above Monitored Target Groups so the FIM configuration columns align cleanly.

**Code quality**

- Removed non-dynamic inline styles except runtime-dependent score and card accent styling.
- Removed unused PHP functions.
- Removed dead JavaScript and unused dashboard row JSON blobs.
- Removed orphan CSS rules.
- Fixed missing `</details>` closes in FIM configuration tables.
- Extracted recommendation rendering into a reusable helper.

### Version 5.1.2

- Complete dashboard redesign with security scoring.
- Modular settings configuration.
- Enhanced admin UI styling.
- Configurable branding and FIM targets.
- Improved responsive design.

### Version 5.1.1

- Added modular constants and settings system.
- Implemented configurable FIM targets and exclusions.
- Enhanced passkey login customization with branding support.

### Version 5.1.0

- Initial internal release with core hardening features.
- Added file integrity monitoring.
- Added security event logging.
- Added passkey-first login customization.

## License

Released under the **GNU General Public License v2.0 or later**. See [LICENSE](LICENSE) for the full text.

## Author

**Joe Severino**

- Website: [jseverino.com](https://jseverino.com)
- GitHub: [@joeseverino](https://github.com/joeseverino)

---

*A small, auditable WordPress hardening and monitoring layer — built for production, kept readable.*
