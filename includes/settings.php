<?php

if (!defined('ABSPATH')) {
    exit;
}

function sl_security_get_default_settings() {
    return [
        // Locked/core hardening controls
        'disable_xmlrpc' => true,
        'disable_pingbacks' => true,
        'block_rest_users' => true,
        'block_author_enum' => true,
        'remove_wp_generator' => true,
        'block_unused_endpoints' => true,
        'custom_error_page' => true,

        // Toggleable controls
        'enable_security_headers' => true,
        'enable_csp' => true,
        'enable_passkey_login' => false,
        'passkey_usernameless_verified' => false,
        'enable_fim' => true,
        'enable_fim_schedule' => true,
        'enable_sem' => true,
        'enable_sem_403_logging' => true,
        'exclude_logged_in_users_from_sem' => false,
    ];
}

function sl_security_get_default_branding_settings() {
    return [
        'passkey_login_logo_url' => '',
        'passkey_login_site_label' => SL_SECURITY_BRAND_NAME,
    ];
}

function sl_security_get_branding_settings() {
    return wp_parse_args(
        get_option('sl_security_branding_settings', []),
        sl_security_get_default_branding_settings()
    );
}

function sl_security_update_branding_settings($branding_settings) {
    $branding_settings = wp_parse_args(
        $branding_settings,
        sl_security_get_default_branding_settings()
    );

    $branding_settings['passkey_login_logo_url'] = trim(sanitize_text_field($branding_settings['passkey_login_logo_url']));
    $branding_settings['passkey_login_site_label'] = trim(sanitize_text_field($branding_settings['passkey_login_site_label']));

    update_option('sl_security_branding_settings', $branding_settings, false);

    return $branding_settings;
}

function sl_security_get_default_smtp_settings() {
    return [
        'smtp_host'                    => '',
        'smtp_port'                    => '587',
        'smtp_user'                    => '',
        'smtp_pass'                    => '',
        'smtp_from'                    => '',
        'smtp_from_name'               => '',
        'smtp_debug'                   => false,
        'alert_email'                  => '',
        // Immediate alerts
        'alert_fim_changes'            => true,
        'alert_sem_spike'              => false,
        'alert_sem_spike_threshold'    => 100,
        // Daily digest emails
        'alert_daily_fim_report' => false,
        'alert_daily_dashboard'  => false,
    ];
}

function sl_security_get_smtp_settings() {
    return wp_parse_args(
        get_option('sl_security_smtp_settings', []),
        sl_security_get_default_smtp_settings()
    );
}

function sl_security_get_smtp_setting($key, $default = '') {
    $settings = sl_security_get_smtp_settings();
    return $settings[$key] ?? $default;
}

function sl_security_smtp_alert_enabled($key) {
    return !empty(sl_security_get_smtp_setting($key));
}

function sl_security_get_alert_email() {
    $email = sl_security_get_smtp_setting('alert_email', '');
    if (is_email($email)) {
        return $email;
    }
    return get_option('admin_email');
}

function sl_security_update_smtp_settings($input) {
    $existing = sl_security_get_smtp_settings();
    $clean    = [];

    $clean['smtp_host']      = sanitize_text_field($input['smtp_host'] ?? '');
    $clean['smtp_port']      = absint($input['smtp_port'] ?? 587) ?: 587;
    $clean['smtp_user']      = sanitize_text_field($input['smtp_user'] ?? '');
    $clean['smtp_from']      = sanitize_email($input['smtp_from'] ?? '');
    $clean['smtp_from_name'] = sanitize_text_field($input['smtp_from_name'] ?? '');
    $clean['smtp_debug']     = !empty($input['smtp_debug']);
    $clean['alert_email']    = sanitize_email($input['alert_email'] ?? '');

    // Keep existing password unless a new one is provided.
    $clean['smtp_pass'] = !empty($input['smtp_pass'])
        ? sanitize_text_field($input['smtp_pass'])
        : $existing['smtp_pass'];

    // Immediate alerts
    $clean['alert_fim_changes']         = !empty($input['alert_fim_changes']);
    $clean['alert_sem_spike']           = !empty($input['alert_sem_spike']);
    $clean['alert_sem_spike_threshold'] = absint($input['alert_sem_spike_threshold'] ?? 100) ?: 100;

    // Daily digest emails
    $clean['alert_daily_fim_report'] = !empty($input['alert_daily_fim_report']);
    $clean['alert_daily_dashboard']  = !empty($input['alert_daily_dashboard']);

    update_option('sl_security_smtp_settings', $clean, false);
    return $clean;
}

function sl_security_get_default_fim_configuration() {
    return [
        'targets' => [
            ABSPATH . 'wp-config.php',
            ABSPATH . '.htaccess',
            ABSPATH . 'index.php',
            ABSPATH . 'wp-settings.php',
            ABSPATH . 'wp-load.php',
            ABSPATH . 'wp-blog-header.php',
            ABSPATH . 'xmlrpc.php',
            ABSPATH . 'wp-admin/index.php',
            ABSPATH . 'wp-admin/admin.php',
            ABSPATH . 'wp-admin/admin-ajax.php',
            ABSPATH . 'wp-admin/admin-post.php',
            ABSPATH . 'wp-includes/version.php',
            ABSPATH . 'wp-includes/functions.php',
            ABSPATH . 'wp-includes/pluggable.php',
            plugin_dir_path(__DIR__),
            get_stylesheet_directory(),
        ],
        'excluded_paths' => [
            'data/',
            'debug.log',
            '.DS_Store',
            'cache/',
            'litespeed/',
            'uploads/',
            'upgrade/',
            'upgrade-temp-backup/',
            'backup/',
            'backups/',
            'node_modules/',
            'vendor/',
            '.git/',
        ],
    ];
}

function sl_security_get_fim_settings() {
    return wp_parse_args(
        get_option('sl_security_fim_settings', []),
        sl_security_get_default_fim_configuration()
    );
}

function sl_security_update_fim_settings($fim_settings) {
    $defaults = sl_security_get_default_fim_configuration();

    $fim_settings = wp_parse_args($fim_settings, $defaults);
    $fim_settings['targets'] = sl_security_clean_multiline_list($fim_settings['targets']);
    $fim_settings['excluded_paths'] = sl_security_clean_multiline_list($fim_settings['excluded_paths']);

    update_option('sl_security_fim_settings', $fim_settings, false);

    return $fim_settings;
}

function sl_security_clean_multiline_list($value) {
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/\r\n|\r|\n/', trim($value));
    }

    $items = array_map('trim', $items);
    $items = array_filter($items, 'strlen');

    return array_values($items);
}

function sl_security_format_multiline_list(array $items) {
    return implode("\n", array_map('trim', array_filter($items, 'strlen')));
}

function sl_security_get_fim_targets() {
    $fim_settings = sl_security_get_fim_settings();
    return array_values(array_unique($fim_settings['targets']));
}

function sl_security_get_fim_excluded_paths() {
    $fim_settings = sl_security_get_fim_settings();
    return array_values(array_unique($fim_settings['excluded_paths']));
}

function sl_security_get_locked_settings() {
    return [
        'disable_xmlrpc',
        'disable_pingbacks',
        'block_rest_users',
        'block_author_enum',
        'remove_wp_generator',
        'block_unused_endpoints',
        'custom_error_page',
    ];
}

function sl_security_get_settings() {
    $settings = get_option('sl_security_settings', []);

    if (!is_array($settings)) {
        $settings = [];
    }

    return wp_parse_args($settings, sl_security_get_default_settings());
}

function sl_security_setting_enabled($key) {
    $settings = sl_security_get_settings();

    return !empty($settings[$key]);
}

function sl_security_passkey_usernameless_verified() {
    return sl_security_setting_enabled('passkey_usernameless_verified');
}


function sl_security_set_passkey_usernameless_verified($verified) {
    $settings = sl_security_get_settings();
    $settings['passkey_usernameless_verified'] = (bool) $verified;
    if (!$settings['passkey_usernameless_verified']) {
        $settings['enable_passkey_login'] = false;
    }
    update_option('sl_security_settings', $settings, false);
    return $settings;
}


function sl_security_update_settings($new_settings) {
    $defaults = sl_security_get_default_settings();
    $locked = sl_security_get_locked_settings();
    $current_settings = sl_security_get_settings();
    $clean_settings = [];

    foreach ($defaults as $key => $default_value) {
        if (in_array($key, $locked, true)) {
            $clean_settings[$key] = true;
            continue;
        }

        if ($key === 'passkey_usernameless_verified') {
            $clean_settings[$key] = !empty($current_settings[$key]);
            continue;
        }

        $clean_settings[$key] = !empty($new_settings[$key]);
    }

    if (empty($clean_settings['passkey_usernameless_verified'])) {
        $clean_settings['enable_passkey_login'] = false;
    }

    update_option('sl_security_settings', $clean_settings, false);

    if (
        !empty($clean_settings['enable_fim']) &&
        !empty($clean_settings['enable_fim_schedule']) &&
        function_exists('sl_fim_schedule_event')
    ) {
        sl_fim_schedule_event();
    }

    if (
        (
            empty($clean_settings['enable_fim']) ||
            empty($clean_settings['enable_fim_schedule'])
        ) &&
        function_exists('sl_fim_clear_event')
    ) {
        sl_fim_clear_event();
    }

    return $clean_settings;
}

function sl_security_get_setting_groups() {
    return [
        'Application Hardening' => [
            'disable_xmlrpc' => [
                'label' => 'Disable XML-RPC',
                'description' => 'Disables XML-RPC because this site does not use legacy remote publishing or XML-RPC integrations.',
            ],
            'disable_pingbacks' => [
                'label' => 'Disable XML-RPC Pingbacks',
                'description' => 'Removes the pingback XML-RPC method to reduce unnecessary abuse surface.',
            ],
            'block_rest_users' => [
                'label' => 'Block REST API User Enumeration',
                'description' => 'Removes public REST API user endpoints that can expose account information.',
            ],
            'block_author_enum' => [
                'label' => 'Block Author Enumeration',
                'description' => 'Redirects author archive enumeration attempts back to the home page.',
            ],
            'remove_wp_generator' => [
                'label' => 'Remove WordPress Generator Tag',
                'description' => 'Removes the public WordPress version generator tag from page output.',
            ],
            'block_unused_endpoints' => [
                'label' => 'Block Unused Public WordPress Endpoints',
                'description' => 'Blocks unused public endpoints such as xmlrpc.php, wp-signup.php, wp-activate.php, trackbacks, and OPML.',
            ],
        ],

        'Browser Security' => [
            'enable_security_headers' => [
                'label' => 'Send Baseline Security Headers',
                'description' => 'Sends browser security headers for framing, MIME sniffing, referrer behavior, and permissions policy.',
            ],
            'enable_csp' => [
                'label' => 'Send Content Security Policy',
                'description' => 'Sends the site Content Security Policy. This is toggleable because CSP can affect frontend compatibility.',
            ],
        ],

        'Authentication' => [
            'enable_passkey_login' => [
                'label' => 'Enable Passkey-Only Login UI',
                'description' => 'Replaces the default login experience with the custom passkey-only login screen.',
            ],
        ],

        'Monitoring' => [
            'enable_fim' => [
                'label' => 'Enable File Integrity Monitoring',
                'description' => 'Enables the file integrity monitoring module and manual FIM controls.',
            ],
            'enable_fim_schedule' => [
                'label' => 'Enable Scheduled FIM Checks',
                'description' => 'Allows the plugin to schedule the daily file integrity check through WordPress cron.',
            ],
        ],

        'Security Event Monitoring' => [
            'enable_sem' => [
                'label' => 'Enable Security Event Monitoring',
                'description' => 'Logs blocked or suspicious requests handled by the security layer.',
            ],
            'enable_sem_403_logging' => [
                'label' => 'Log Security Error Page Events',
                'description' => 'Logs requests that reach the custom security error page with a 4xx or 5xx response.',
            ],
            'exclude_logged_in_users_from_sem' => [
                'label' => 'Exclude Signed-In Users from Event Logs',
                'description' => 'When signed in, authenticated users are excluded from security event logging.',
            ],
        ],

        'Error Handling' => [
            'custom_error_page' => [
                'label' => 'Use Custom Security Error Page',
                'description' => 'Uses the plugin security error template for blocked or denied WordPress responses.',
            ],
        ],
    ];
}