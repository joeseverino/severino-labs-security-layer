<?php
/**
 * Plugin Name: Severino Labs Security Layer
 * Description: Custom security layer for WordPress that centralizes application hardening, browser-enforced policies, file integrity monitoring, security event logging, and a passkey-only login experience.
 * Author: Joe Severino
 * Author URI: https://jseverino.com
 * Version: 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SL_SECURITY_PLUGIN_FILE', __FILE__);
define('SL_SECURITY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SL_SECURITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SL_SECURITY_PLUGIN_VERSION', '6.0.0');
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