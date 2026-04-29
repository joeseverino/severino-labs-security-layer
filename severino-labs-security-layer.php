<?php
/**
 * Plugin Name: Severino Labs Security Layer
 * Description: Custom security layer for WordPress that centralizes application hardening, browser-enforced policies, file integrity monitoring, security event logging, and a passkey-only login experience.
 * Author: Joe Severino
 * Author URI: https://jseverino.com
 * Version: 6.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Optional dependency: the passkey-only login feature delegates WebAuthn
 * challenge issuance and verification to the WP-WebAuthn plugin
 * (https://wordpress.org/plugins/wp-webauthn/). All other features
 * (hardening, file integrity monitoring, security event logging) work
 * independently with no extra plugins required.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SL_SECURITY_PLUGIN_FILE', __FILE__);
define('SL_SECURITY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SL_SECURITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SL_SECURITY_PLUGIN_VERSION', '6.1.0');
define('SL_SECURITY_BRAND_NAME', 'Severino Labs Security Layer');
define('SL_SECURITY_CAPABILITY', 'manage_options');
define('SL_SECURITY_MENU_SLUG', 'sl-security');
define('SL_SECURITY_FIM_SLUG', 'sl-security-fim');
define('SL_SECURITY_EVENTS_SLUG', 'sl-security-events');
define('SL_SECURITY_SETTINGS_SLUG', 'sl-security-settings');

require_once SL_SECURITY_PLUGIN_PATH . 'includes/settings.php';

foreach ([
    'security-event-monitor.php',
    'hardening.php',
    'passkey-login.php',
    'file-integrity-monitor.php',
    'security-admin-page.php',
] as $module_file) {
    require_once SL_SECURITY_PLUGIN_PATH . 'includes/' . $module_file;
}

register_activation_hook(__FILE__, 'sl_security_activate');
register_deactivation_hook(__FILE__, 'sl_security_deactivate');

/**
 * Configure WordPress PHPMailer to use SMTP credentials stored in plugin settings.
 * This hook runs for all wp_mail() calls, including those from Severino Labs Admin Modules.
 */
function sl_security_configure_smtp($phpmailer) {
    $smtp = sl_security_get_smtp_settings();

    if (empty($smtp['smtp_host']) || empty($smtp['smtp_user']) || empty($smtp['smtp_pass'])) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = $smtp['smtp_host'];
    $phpmailer->Port       = (int) $smtp['smtp_port'];
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = $smtp['smtp_user'];
    $phpmailer->Password   = $smtp['smtp_pass'];
    $phpmailer->SMTPSecure = 'tls';

    if (is_email($smtp['smtp_from'])) {
        $phpmailer->From     = $smtp['smtp_from'];
        $phpmailer->FromName = !empty($smtp['smtp_from_name']) ? $smtp['smtp_from_name'] : get_bloginfo('name');
    }

    if (!empty($smtp['smtp_debug'])) {
        $phpmailer->SMTPDebug = 2;
        $phpmailer->Debugoutput = function($str, $level) {
            error_log('SLAM SMTP [' . $level . ']: ' . $str);
        };
    }
}
add_action('phpmailer_init', 'sl_security_configure_smtp');

/**
 * After each scheduled FIM check, send any enabled daily digest emails.
 * Priority 20 ensures this runs after sl_fim_run_check (priority 10 default).
 */
add_action('sl_fim_daily_check', 'sl_security_after_fim_daily_emails', 20);
function sl_security_after_fim_daily_emails() {
    if (sl_security_smtp_alert_enabled('alert_daily_fim_report')
        && function_exists('sl_security_send_fim_daily_report')) {
        sl_security_send_fim_daily_report();
    }

    if (sl_security_smtp_alert_enabled('alert_daily_dashboard')
        && function_exists('sl_security_send_daily_dashboard_email')) {
        sl_security_send_daily_dashboard_email();
    }
}

function sl_security_activate() {
    if (function_exists('sl_security_get_default_settings')) {
        $existing_settings = get_option('sl_security_settings', null);

        if (!is_array($existing_settings)) {
            update_option('sl_security_settings', sl_security_get_default_settings(), false);
        }
    }

    if (
        function_exists('sl_security_setting_enabled') &&
        function_exists('sl_fim_schedule_event') &&
        sl_security_setting_enabled('enable_fim') &&
        sl_security_setting_enabled('enable_fim_schedule')
    ) {
        sl_fim_schedule_event();
    }
}

function sl_security_deactivate() {
    if (function_exists('sl_fim_clear_event')) {
        sl_fim_clear_event();
    }
}
