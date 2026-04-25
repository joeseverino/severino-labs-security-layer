# Severino Labs Security Layer

A custom WordPress security plugin that centralizes application hardening, browser-facing security controls, file integrity monitoring, security event logging, and passkey-first login customization for my personal WordPress environment.

This project was built to reduce default WordPress exposure, improve visibility into security-relevant events, and keep site-specific hardening logic in one maintainable plugin instead of scattered snippets, theme edits, or unrelated third-party tools.

## Features

### 🔒 Application Hardening

- XML-RPC disablement
- Pingback method blocking
- REST API user enumeration reduction
- Author archive enumeration blocking
- WordPress generator tag removal
- Unused endpoint reduction
- Custom security error page support

### 🌐 Browser-Facing Security Controls

- Configurable security headers
- Content Security Policy support
- Framing and MIME-sniffing protections
- Referrer policy support
- Permissions policy support

### 📁 File Integrity Monitoring

- SHA-256 baseline creation
- Manual integrity checks
- Scheduled daily checks
- Configurable monitored targets
- Excluded path management
- Added, removed, and modified file detection
- File integrity status reporting in the WordPress admin

### 🛡️ Security Event Monitoring

- Security-relevant request logging
- Event type categorization
- Source IP and request metadata capture
- Cloudflare metadata support where available
- Recent event display in the admin dashboard
- Historical event log viewer

### 🔐 Passkey-First Login Customization

- Custom WordPress login page design
- Passkey-first login interface
- Configurable branding
- Logo and label customization
- Required usernameless-passkey verification before the toggle can be flipped on, so an admin can't accidentally lock themselves out

> **Optional dependency for passkey-only login:** this feature delegates WebAuthn challenge issuance and verification to the [WP-WebAuthn](https://wordpress.org/plugins/wp-webauthn/) plugin. WP-WebAuthn must be installed, activated, and configured with at least one registered passkey before the test will pass. **Every other feature in this plugin works without WP-WebAuthn** — if you don't want passkey login you can ignore the dependency entirely. When WP-WebAuthn is missing the passkey toggle stays locked and the plugin falls back to the standard WordPress login screen.

## Scope and Limitations

This plugin is a focused hardening and monitoring layer, not a complete replacement for a WAF, malware scanner, backup system, or host-level firewall.

It does not:

- Provide malware signature scanning
- Replace Cloudflare, server firewall rules, or hosting-level controls
- Guarantee origin server lockdown
- Implement WebAuthn/passkey authentication by itself
- Replace regular WordPress, plugin, theme, and server updates

The passkey-first login functionality is designed around login customization and integration with a compatible WebAuthn/passkey setup.

## Installation

1. Download or clone the plugin files.
2. Upload the plugin folder to `/wp-content/plugins/`.
3. Activate **Severino Labs Security Layer** through the WordPress admin.
4. Configure settings under **Severino Security → Settings**.
5. Create a file integrity baseline after confirming the current file state is expected.

## Configuration

### Security Controls

Individual controls can be enabled or disabled from the plugin settings page. Core hardening controls are intended to reduce unnecessary WordPress exposure while keeping behavior explicit and reviewable.

### Branding Configuration

The login experience can be customized with:

- Custom logo
- Site display name
- Login page label text
- Passkey-first authentication flow styling

### File Integrity Monitoring

File Integrity Monitoring can be configured with:

- **Monitored Targets**: files or directories to include in integrity checks
- **Excluded Paths**: logs, caches, uploads, or temporary directories to ignore
- **Baseline Creation**: a trusted snapshot of the approved file state
- **Scheduled Checks**: daily WordPress cron-based integrity checks

## Dashboard

The admin dashboard provides:

- **Security Score**: simple health indicator based on enabled controls and monitoring status
- **Status Overview**: FIM automation, event monitoring, and baseline status
- **Quick Actions**: run checks, create baselines, and access reports
- **Recent Events**: latest security-relevant activity
- **System Information**: plugin, WordPress, and PHP version details

## Security Score Calculation

The security score is calculated using the plugin’s enabled controls and monitoring state:

- Plugin active: +20 points
- FIM enabled: +25 points
- Baseline created: +15 points
- FIM check passed: +10 points
- SEM enabled: +20 points
- No events today: +10 points

The score is a local status indicator for this plugin. It is not a full security rating for the entire WordPress environment.

## Development Workflow

This plugin is maintained in a private GitHub repository and deployed to the live WordPress environment through a Git-based workflow.

The general workflow is:

1. Make changes locally.
2. Review the diff.
3. Commit and push to GitHub.
4. Pull the latest version on the hosting server.

This keeps the live plugin version-controlled and provides a rollback history for future maintenance.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.6+

### Optional

- **[WP-WebAuthn](https://wordpress.org/plugins/wp-webauthn/)** — required only if you want to use the passkey-only login feature. Without it the plugin still installs, activates, and runs cleanly; the passkey toggle simply stays locked and the standard WordPress login screen is used.

## Security Best Practices

1. Keep core hardening controls enabled unless a specific feature needs to be exposed.
2. Create a new FIM baseline after trusted plugin, theme, or WordPress updates.
3. Review security events regularly for repeated or suspicious activity.
4. Exclude cache, upload, log, and temporary directories from FIM targets.
5. Use scheduled checks for ongoing file integrity monitoring.
6. Keep WordPress core, themes, plugins, PHP, and hosting controls updated.

## Changelog

### Version 6.1.0

- Gated the passkey-only login toggle behind a real usernameless-passkey verification test so an admin can't lock themselves out by enabling passkey login without a working credential.
- Made the WP-WebAuthn dependency explicit with an admin notice when the toggle is enabled but the provider is missing, plus an in-page status row in Settings that shows whether the provider is detected.
- Whitelisted admin requests through the REST `/users` and author-archive blockers so legitimate admin tooling (block editor, user directory, profile editors) keeps working while still blocking unauthenticated enumeration.
- Redesigned the Security Controls table with separate **Status** and **Toggle** columns: a uniform color-coded pill (Always Enabled / Enabled / Disabled / Locked) plus a real iOS-style toggle switch for actionable rows.
- Fixed the passkey-test button being silently inert on some setups (DOMContentLoaded race on footer scripts) and surfaced clearer errors when the WP-WebAuthn AJAX endpoints aren't responding.
- Removed a nested `<form>` (HTML invalid) that was causing the "Reset Passkey Verification" button to actually trigger "Save All Settings".
- Renamed `sl_render_wp_die_page` to `sl_security_render_wp_die_page` for prefix consistency.
- Cleaned up the `.gitignore` so the shared `.vscode/stubs/` (WordPress stubs for Intelephense) stays tracked.

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

This plugin is licensed under the GPL v2 or later.

## Author

**Joe Severino**

- Website: [jseverino.com](https://jseverino.com)
- GitHub: [@joeseverino](https://github.com/joeseverino)

---

*Built as a focused WordPress hardening and monitoring layer for a personal production site.*