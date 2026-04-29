<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format a stored MySQL datetime as M/D/YY h:mm AM/PM (Central time).
 */
function sl_security_format_datetime($mysql_datetime) {
    if (empty($mysql_datetime) || 'Unknown' === $mysql_datetime) {
        return 'Unknown';
    }

    try {
        // Parse and display in WP's configured timezone.
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $mysql_datetime, wp_timezone());

        if (false === $dt) {
            return esc_html($mysql_datetime);
        }

        return $dt->format('n/j/y g:i A');
    } catch (Exception $e) {
        return esc_html($mysql_datetime);
    }
}

function sl_security_enqueue_admin_assets($hook) {
    if (strpos($hook, 'sl-security') !== false) {
        wp_enqueue_style(
            'sl-security-admin',
            SL_SECURITY_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SL_SECURITY_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'sl-security-admin',
            SL_SECURITY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            SL_SECURITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'sl-security-passkey-test',
            SL_SECURITY_PLUGIN_URL . 'assets/js/passkey-test.js',
            [],
            SL_SECURITY_PLUGIN_VERSION,
            true
        );

        wp_localize_script('sl-security-passkey-test', 'slSecurityAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'passkeyTestNonce' => wp_create_nonce('sl_passkey_usernameless_test'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'sl_security_enqueue_admin_assets');

function sl_security_mark_passkey_usernameless_verified_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized.'], 403);
    }

    check_ajax_referer('sl_passkey_usernameless_test', 'nonce');

    if (!function_exists('sl_security_set_passkey_usernameless_verified')) {
        wp_send_json_error(['message' => 'Passkey verification settings handler is unavailable.'], 500);
    }

    sl_security_set_passkey_usernameless_verified(true);

    wp_send_json_success([
        'message' => 'Usernameless passkey authentication verified.',
    ]);
}
add_action('wp_ajax_sl_mark_passkey_usernameless_verified', 'sl_security_mark_passkey_usernameless_verified_ajax');

function sl_security_add_plugin_action_links($links) {
    $dashboard_link = '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_MENU_SLUG)) . '">Dashboard</a>';
    $fim_link = '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_FIM_SLUG)) . '">File Integrity</a>';
    $events_link = '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_EVENTS_SLUG)) . '">Security Events</a>';
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_SETTINGS_SLUG)) . '">Settings</a>';

    array_unshift($links, $dashboard_link, $fim_link, $events_link, $settings_link);

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(SL_SECURITY_PLUGIN_FILE), 'sl_security_add_plugin_action_links');

function sl_security_register_admin_pages() {
    add_menu_page(
        SL_SECURITY_BRAND_NAME,
        'Severino Security',
        SL_SECURITY_CAPABILITY,
        SL_SECURITY_MENU_SLUG,
        'sl_security_render_dashboard_page',
        'dashicons-shield-alt',
        3
    );

    add_submenu_page(
        SL_SECURITY_MENU_SLUG,
        'Dashboard',
        'Dashboard',
        SL_SECURITY_CAPABILITY,
        SL_SECURITY_MENU_SLUG,
        'sl_security_render_dashboard_page'
    );

    add_submenu_page(
        SL_SECURITY_MENU_SLUG,
        'File Integrity Monitoring',
        'File Integrity',
        SL_SECURITY_CAPABILITY,
        SL_SECURITY_FIM_SLUG,
        'sl_security_render_fim_page'
    );

    add_submenu_page(
        SL_SECURITY_MENU_SLUG,
        'Security Events',
        'Security Events',
        SL_SECURITY_CAPABILITY,
        SL_SECURITY_EVENTS_SLUG,
        'sl_security_render_events_page'
    );

    add_submenu_page(
        SL_SECURITY_MENU_SLUG,
        'Security Layer Settings',
        'Settings',
        SL_SECURITY_CAPABILITY,
        SL_SECURITY_SETTINGS_SLUG,
        'sl_security_render_settings_page'
    );
}
add_action('admin_menu', 'sl_security_register_admin_pages');

function sl_security_add_admin_bar_link($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $wp_admin_bar->add_node([
        'id' => 'sl-security-admin-bar',
        'title' => 'Severino Security',
        'href' => admin_url('admin.php?page=' . SL_SECURITY_MENU_SLUG),
        'meta' => [
            'title' => 'Open ' . SL_SECURITY_BRAND_NAME,
        ],
    ]);

    $wp_admin_bar->add_node([
        'id' => 'sl-security-admin-bar-dashboard',
        'parent' => 'sl-security-admin-bar',
        'title' => 'Dashboard',
        'href' => admin_url('admin.php?page=' . SL_SECURITY_MENU_SLUG),
    ]);

    $wp_admin_bar->add_node([
        'id' => 'sl-security-admin-bar-fim',
        'parent' => 'sl-security-admin-bar',
        'title' => 'File Integrity',
        'href' => admin_url('admin.php?page=' . SL_SECURITY_FIM_SLUG),
    ]);

    $wp_admin_bar->add_node([
        'id' => 'sl-security-admin-bar-events',
        'parent' => 'sl-security-admin-bar',
        'title' => 'Security Events',
        'href' => admin_url('admin.php?page=' . SL_SECURITY_EVENTS_SLUG),
    ]);

    $wp_admin_bar->add_node([
        'id' => 'sl-security-admin-bar-settings',
        'parent' => 'sl-security-admin-bar',
        'title' => 'Settings',
        'href' => admin_url('admin.php?page=' . SL_SECURITY_SETTINGS_SLUG),
    ]);
}
add_action('admin_bar_menu', 'sl_security_add_admin_bar_link', 80);

function sl_security_handle_admin_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['sl_security_action'])) {
        return;
    }

    check_admin_referer('sl_security_action');

    $action = sanitize_text_field(wp_unslash($_POST['sl_security_action']));

    if ($action === 'save_settings') {
        $settings = isset($_POST['sl_security_settings']) && is_array($_POST['sl_security_settings'])
            ? wp_unslash($_POST['sl_security_settings'])
            : [];

        $branding = isset($_POST['sl_security_branding']) && is_array($_POST['sl_security_branding'])
            ? wp_unslash($_POST['sl_security_branding'])
            : [];

        $fim_settings = [
            'targets' => isset($_POST['sl_security_fim_targets']) ? wp_unslash($_POST['sl_security_fim_targets']) : '',
            'excluded_paths' => isset($_POST['sl_security_fim_excluded_paths']) ? wp_unslash($_POST['sl_security_fim_excluded_paths']) : '',
        ];

        sl_security_update_settings($settings);
        sl_security_update_branding_settings($branding);
        sl_security_update_fim_settings($fim_settings);
        sl_security_redirect_with_message('settings_saved', SL_SECURITY_SETTINGS_SLUG);
    }

    if ($action === 'create_baseline') {
        sl_fim_create_baseline();
        sl_security_redirect_with_message('baseline_created', SL_SECURITY_FIM_SLUG);
    }

    if ($action === 'run_check') {
        sl_fim_run_check();

        $return_page = isset($_POST['sl_return_page'])
            ? sanitize_text_field(wp_unslash($_POST['sl_return_page']))
            : SL_SECURITY_FIM_SLUG;

        sl_security_redirect_with_message('check_completed', $return_page);
    }

    if ($action === 'schedule_check') {
        sl_fim_schedule_event();
        sl_security_redirect_with_message('schedule_created', SL_SECURITY_FIM_SLUG);
    }

    if ($action === 'reschedule_check') {
        sl_fim_clear_event();
        sl_fim_schedule_event();
        sl_security_redirect_with_message('schedule_updated', SL_SECURITY_FIM_SLUG);
    }

    if ($action === 'unschedule_check') {
        sl_fim_clear_event();
        sl_security_redirect_with_message('schedule_removed', SL_SECURITY_FIM_SLUG);
    }

    if ($action === 'clear_log') {
        sl_fim_clear_log();
        sl_security_redirect_with_message('log_cleared', SL_SECURITY_FIM_SLUG);
    }

    if ($action === 'delete_baseline') {
        sl_fim_delete_baseline();
        sl_security_redirect_with_message('baseline_deleted', SL_SECURITY_FIM_SLUG);
    }

    if ($action === 'clear_sem_log') {
        sl_sem_clear_log();
        sl_security_redirect_with_message('sem_log_cleared', SL_SECURITY_EVENTS_SLUG);
    }

    if ($action === 'reset_passkey_usernameless_verification') {
        sl_security_set_passkey_usernameless_verified(false);
        sl_security_redirect_with_message('passkey_verification_reset', SL_SECURITY_SETTINGS_SLUG);
    }

    if ($action === 'save_smtp_settings') {
        $smtp_input = isset($_POST['sl_security_smtp']) && is_array($_POST['sl_security_smtp'])
            ? wp_unslash($_POST['sl_security_smtp'])
            : [];
        sl_security_update_smtp_settings($smtp_input);
        sl_security_redirect_with_message('smtp_settings_saved', SL_SECURITY_SETTINGS_SLUG);
    }
}
add_action('admin_init', 'sl_security_handle_admin_actions');

function sl_security_redirect_with_message($message, $page = SL_SECURITY_MENU_SLUG) {
    wp_safe_redirect(add_query_arg(
        [
            'page' => $page,
            'sl_message' => $message,
        ],
        admin_url('admin.php')
    ));
    exit;
}

function sl_security_get_score_breakdown($fim_enabled, $sem_enabled, $baseline_exists, $fim_status, $events_today) {
    return [
        [
            'label' => 'Plugin active',
            'value' => 20,
            'active' => true,
            'description' => 'Core security monitoring is initialized.',
        ],
        [
            'label' => 'File Integrity Monitoring',
            'value' => $fim_enabled ? 25 : 0,
            'active' => $fim_enabled,
            'description' => 'Monitors file changes in your WordPress installation.',
        ],
        [
            'label' => 'Trusted baseline',
            'value' => $fim_enabled && $baseline_exists ? 15 : 0,
            'active' => $fim_enabled && $baseline_exists,
            'description' => 'A trusted FIM baseline is available.',
        ],
        [
            'label' => 'Recent integrity check',
            'value' => $fim_enabled && isset($fim_status['status']) && $fim_status['status'] === 'passed' ? 10 : 0,
            'active' => $fim_enabled && isset($fim_status['status']) && $fim_status['status'] === 'passed',
            'description' => 'The latest file integrity check passed.',
        ],
        [
            'label' => 'Event monitoring',
            'value' => $sem_enabled ? 20 : 0,
            'active' => $sem_enabled,
            'description' => 'Security events are being tracked.',
        ],
        [
            'label' => 'Clean event history',
            'value' => $sem_enabled && $events_today === 0 ? 10 : 0,
            'active' => $sem_enabled && $events_today === 0,
            'description' => 'No security events were logged today.',
        ],
    ];
}

function sl_security_render_score_breakdown(array $breakdown) {
    ?>
    <div class="sl-security-score-breakdown sl-security-expandable-table">
        <details>
            <summary class="sl-security-table-toggle">
                <span>Score breakdown</span>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </summary>
            <div class="sl-security-table-content">
                <ul class="sl-security-score-breakdown-list">
                    <?php foreach ($breakdown as $item) : ?>
                        <li class="<?php echo esc_attr($item['active'] ? 'active' : 'inactive'); ?>">
                            <div class="sl-security-score-breakdown-info">
                                <span class="sl-security-score-breakdown-label"><?php echo esc_html($item['label']); ?></span>
                                <span class="sl-security-score-breakdown-note"><?php echo esc_html($item['description']); ?></span>
                            </div>
                            <span class="sl-security-score-breakdown-value"><?php echo esc_html($item['value']); ?> pts</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </details>
    </div>
    <?php
}

function sl_security_render_recommendation_list(array $recommendations) {
    if (empty($recommendations)) {
        return;
    }
    ?>
    <div class="sl-security-action-items">
        <?php foreach ($recommendations as $rec) : ?>
            <div class="sl-security-action-item <?php echo esc_attr($rec['priority']); ?>">
                <div class="sl-security-action-icon">
                    <span class="dashicons <?php echo esc_attr($rec['icon']); ?>"></span>
                </div>
                <div class="sl-security-action-content">
                    <h4><?php echo esc_html($rec['title']); ?></h4>
                    <p><?php echo esc_html($rec['description']); ?></p>
                    <?php if (!empty($rec['action'])) : ?>
                        <div class="sl-security-action-button">
                            <?php echo $rec['action']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function sl_security_render_user_profile_sem_optout($user) {
    $opt_out = get_user_meta($user->ID, 'sl_security_exclude_from_sem', true);
    ?>
    <h2>Severino Labs Security Layer</h2>
    <table class="form-table">
        <tr>
            <th><label for="sl_security_exclude_from_sem">Exclude my activity from security logs</label></th>
            <td>
                <label>
                    <input name="sl_security_exclude_from_sem" type="checkbox" id="sl_security_exclude_from_sem" value="1" <?php checked($opt_out, '1'); ?> />
                    Don't log my authenticated activity in the security event log.
                </label>
                <p class="description">When enabled, your authenticated requests are ignored by Severino Labs event logging.</p>
            </td>
        </tr>
    </table>
    <?php
}

function sl_security_save_user_profile_sem_optout($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    $value = isset($_POST['sl_security_exclude_from_sem']) ? '1' : '0';
    update_user_meta($user_id, 'sl_security_exclude_from_sem', $value);
}

add_action('show_user_profile', 'sl_security_render_user_profile_sem_optout');
add_action('edit_user_profile', 'sl_security_render_user_profile_sem_optout');
add_action('personal_options_update', 'sl_security_save_user_profile_sem_optout');
add_action('edit_user_profile_update', 'sl_security_save_user_profile_sem_optout');

function sl_security_get_baseline_info() {
    if (!file_exists(SL_FIM_BASELINE_FILE)) {
        return null;
    }

    $baseline_data = json_decode(file_get_contents(SL_FIM_BASELINE_FILE), true);

    return is_array($baseline_data) ? $baseline_data : null;
}

function sl_security_calculate_security_score($fim_enabled, $sem_enabled, $baseline_exists, $fim_status, $events_today) {
    $score = 0;

    // Base score for plugin being active
    $score += 20;

    // FIM enabled and configured
    if ($fim_enabled) {
        $score += 25;
        if ($baseline_exists) {
            $score += 15;
        }
        if (isset($fim_status['status']) && $fim_status['status'] === 'passed') {
            $score += 10;
        }
    }

    // SEM enabled
    if ($sem_enabled) {
        $score += 20;
        if ($events_today === 0) {
            $score += 10;
        }
    }

    return min(100, $score);
}

function sl_security_get_overall_security_status($score) {
    if ($score >= 90) {
        return [
            'status' => 'Excellent Security',
            'message' => 'Your WordPress installation is well-protected with comprehensive security monitoring.',
            'color' => '#008a20'
        ];
    } elseif ($score >= 70) {
        return [
            'status' => 'Good Security',
            'message' => 'Security is active but could be enhanced with additional monitoring features.',
            'color' => '#dba617'
        ];
    } elseif ($score >= 50) {
        return [
            'status' => 'Basic Security',
            'message' => 'Core security features are active, but comprehensive monitoring is recommended.',
            'color' => '#b32d2e'
        ];
    } else {
        return [
            'status' => 'Limited Security',
            'message' => 'Enable security features to protect your WordPress installation.',
            'color' => '#646970'
        ];
    }
}

function sl_security_get_recommendations($fim_enabled, $sem_enabled, $baseline_exists, $fim_status, $events_today) {
    $recommendations = [];

    // Check if FIM is disabled
    if (!$fim_enabled) {
        $recommendations[] = [
            'priority' => 'high',
            'icon' => 'dashicons-shield-alt',
            'title' => 'Enable File Integrity Monitoring',
            'description' => 'File Integrity Monitoring helps detect unauthorized changes to your WordPress files.',
            'action' => '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_SETTINGS_SLUG)) . '" class="button button-primary">Enable FIM</a>'
        ];
    }

    // Check if baseline is missing
    if ($fim_enabled && !$baseline_exists) {
        $recommendations[] = [
            'priority' => 'high',
            'icon' => 'dashicons-database-add',
            'title' => 'Create File Baseline',
            'description' => 'Create a baseline of your current file state to monitor for future changes.',
            'action' => '<form method="post" class="sl-security-action-form sl-security-action-form-inline">
                ' . wp_nonce_field('sl_security_action', '_wpnonce', true, false) . '
                <input type="hidden" name="sl_security_action" value="create_baseline">
                <button type="submit" class="button button-primary">Create Baseline</button>
            </form>'
        ];
    }

    // Check if FIM check failed
    if ($fim_enabled && $baseline_exists && isset($fim_status['status']) && $fim_status['status'] !== 'passed') {
        $recommendations[] = [
            'priority' => 'medium',
            'icon' => 'dashicons-search',
            'title' => 'Run Integrity Check',
            'description' => 'Run a manual integrity check to verify your files are secure.',
            'action' => '<form method="post" class="sl-security-action-form sl-security-action-form-inline">
                ' . wp_nonce_field('sl_security_action', '_wpnonce', true, false) . '
                <input type="hidden" name="sl_security_action" value="run_check">
                <input type="hidden" name="sl_return_page" value="' . SL_SECURITY_MENU_SLUG . '">
                <button type="submit" class="button button-primary">Run Check Now</button>
            </form>'
        ];
    }

    // Check if SEM is disabled
    if (!$sem_enabled) {
        $recommendations[] = [
            'priority' => 'high',
            'icon' => 'dashicons-visibility',
            'title' => 'Enable Security Event Monitoring',
            'description' => 'Monitor security events and potential threats to your WordPress installation.',
            'action' => '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_SETTINGS_SLUG)) . '" class="button button-primary">Enable SEM</a>'
        ];
    }

    // Check if there are security events today
    if ($sem_enabled && $events_today > 0) {
        $recommendations[] = [
            'priority' => 'low',
            'icon' => 'dashicons-flag',
            'title' => 'Review Security Events',
            'description' => 'There have been ' . $events_today . ' security events today. Review them to confirm everything is expected.',
            'action' => '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_EVENTS_SLUG)) . '" class="button button-secondary">Review Events</a>'
        ];
    }

    // Enable automated FIM checks
    $fim_schedule_enabled = sl_security_setting_enabled('enable_fim_schedule');
    if ($fim_enabled && $baseline_exists && !$fim_schedule_enabled) {
        $recommendations[] = [
            'priority' => 'low',
            'icon' => 'dashicons-clock',
            'title' => 'Enable Automated Checks',
            'description' => 'Set up daily automated integrity checks to continuously monitor your files.',
            'action' => '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_FIM_SLUG)) . '" class="button button-secondary">Configure Automation</a>'
        ];
    }

    return $recommendations;
}

function sl_security_render_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

    // Get all status data
    $baseline_exists = file_exists(SL_FIM_BASELINE_FILE);
    $baseline_info = sl_security_get_baseline_info();
    $fim_status = function_exists('sl_fim_get_status') ? sl_fim_get_status() : null;
    $next_check = wp_next_scheduled('sl_fim_daily_check');
    $fim_enabled = sl_security_setting_enabled('enable_fim');
    $fim_schedule_enabled = sl_security_setting_enabled('enable_fim_schedule');
    $sem_enabled = sl_security_setting_enabled('enable_sem');
    $events_today = function_exists('sl_sem_count_events_today') ? sl_sem_count_events_today() : 0;
    $total_events = function_exists('sl_sem_count_total_events') ? sl_sem_count_total_events() : 0;

    // Calculate overall security health
    $security_score = sl_security_calculate_security_score($fim_enabled, $sem_enabled, $baseline_exists, $fim_status, $events_today);
    $security_status = sl_security_get_overall_security_status($security_score);

    // Get recent security events
    $recent_events = function_exists('sl_sem_get_recent_events') ? sl_sem_get_recent_events(10) : [];
    $score_breakdown = sl_security_get_score_breakdown($fim_enabled, $sem_enabled, $baseline_exists, $fim_status, $events_today);

    ?>
    <div class="wrap sl-security-wrap">
        <div class="sl-security-page-header">
            <div>
                <h1><?php echo esc_html(SL_SECURITY_BRAND_NAME); ?></h1>
                <p class="sl-security-tagline">Custom WordPress security monitoring and application hardening</p>
            </div>
            <div class="sl-security-header-actions">
                <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=' . SL_SECURITY_SETTINGS_SLUG)); ?>"><span class="dashicons dashicons-admin-settings"></span> Settings</a>
                <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=' . SL_SECURITY_FIM_SLUG)); ?>"><span class="dashicons dashicons-search"></span> File Integrity</a>
                <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=' . SL_SECURITY_EVENTS_SLUG)); ?>"><span class="dashicons dashicons-chart-bar"></span> Security Events</a>
            </div>
        </div>

        <?php sl_security_render_notice(); ?>

        <!-- Main Dashboard Grid -->
        <div class="sl-security-dashboard-grid">

            <!-- Security Score & Status -->
            <div class="sl-security-dashboard-section sl-security-score-section">
                <div class="sl-security-health-overview">
                    <div class="sl-security-health-score">
                        <div class="sl-security-health-circle" style="background: conic-gradient(<?php echo esc_attr($security_status['color']); ?> 0% <?php echo esc_attr($security_score); ?>%, #e0e0e0 <?php echo esc_attr($security_score); ?>% 100%)">
                            <div class="sl-security-health-center">
                                <span class="sl-security-score-number"><?php echo esc_html($security_score); ?>%</span>
                                <span class="sl-security-score-label">Security Score</span>
                            </div>
                        </div>
                    </div>
                    <div class="sl-security-health-details">
                        <h2><?php echo esc_html($security_status['status']); ?></h2>
                        <p><?php echo esc_html($security_status['message']); ?></p>
                        <div class="sl-security-health-indicators">
                            <span class="sl-security-indicator <?php echo $fim_enabled ? 'active' : 'inactive'; ?>">
                                <span class="dashicons dashicons-shield"></span> File Integrity <?php echo $fim_enabled ? 'Active' : 'Inactive'; ?>
                            </span>
                            <span class="sl-security-indicator <?php echo $sem_enabled ? 'active' : 'inactive'; ?>">
                                <span class="dashicons dashicons-visibility"></span> Event Monitoring <?php echo $sem_enabled ? 'Active' : 'Inactive'; ?>
                            </span>
                            <span class="sl-security-indicator <?php echo $baseline_exists ? 'active' : 'inactive'; ?>">
                                <span class="dashicons dashicons-database"></span> Baseline <?php echo $baseline_exists ? 'Ready' : 'Needed'; ?>
                            </span>
                        </div>
                        <?php sl_security_render_score_breakdown($score_breakdown); ?>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="sl-security-dashboard-section sl-security-metrics-section">
                <h3><span class="dashicons dashicons-chart-bar"></span> Security Metrics</h3>
                <div class="sl-security-status-cards">
                    <?php
                    // File Integrity with automation status
                    $fim_auto_status = $fim_enabled && $fim_schedule_enabled && $next_check ? 'Automated' : ($fim_enabled ? 'Manual' : 'Disabled');
                    $fim_auto_color = ($fim_enabled && $fim_schedule_enabled && $next_check) ? '#008a20' : ($fim_enabled ? '#dba617' : '#646970');

                    sl_security_render_status_card(
                        'File Integrity Monitoring',
                        $fim_auto_status,
                        $fim_enabled ? ($next_check ? 'Next check: ' . wp_date('n/j/y g:i A', $next_check) : 'Ready for manual check') : 'Enable in Settings to monitor file changes',
                        $fim_auto_color,
                        'dashicons-shield-alt'
                    );

                    sl_security_render_status_card(
                        'Security Events Today',
                        $events_today,
                        $sem_enabled ? ($events_today > 0 ? 'Security events detected' : 'No security events today') : 'Enable event monitoring in Settings',
                        $events_today > 0 ? '#b32d2e' : ($sem_enabled ? '#008a20' : '#646970'),
                        'dashicons-flag'
                    );

                    sl_security_render_status_card(
                        'Total Events Logged',
                        number_format($total_events),
                        'All-time security event history',
                        $total_events > 0 ? '#2271b1' : '#646970',
                        'dashicons-list-view'
                    );

                    sl_security_render_status_card(
                        'Last FIM Check',
                        $fim_enabled ? sl_security_get_last_check_label($fim_status) : 'Disabled',
                        $fim_enabled ? sl_security_get_last_check_message($fim_status) : 'File integrity monitoring is disabled',
                        $fim_enabled ? sl_security_get_status_color($fim_status) : '#646970',
                        'dashicons-search'
                    );
                    ?>
                </div>
            </div>

            <!-- Action Required & Recommendations -->
            <?php
            $recommendations = sl_security_get_recommendations($fim_enabled, $sem_enabled, $baseline_exists, $fim_status, $events_today);
            $action_items = array_values(array_filter($recommendations, function ($rec) {
                return in_array($rec['priority'], ['high', 'medium'], true);
            }));
            $suggestion_items = array_values(array_filter($recommendations, function ($rec) {
                return $rec['priority'] === 'low';
            }));
            ?>

            <?php if (!empty($action_items)) : ?>
                <div class="sl-security-dashboard-section sl-security-actions-section">
                    <h3><span class="dashicons dashicons-warning"></span> Action Required</h3>
                    <p class="sl-security-section-intro">These items need attention to keep your security posture strong.</p>
                    <?php sl_security_render_recommendation_list($action_items); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($suggestion_items)) : ?>
                <div class="sl-security-dashboard-section sl-security-actions-section">
                    <h3><span class="dashicons dashicons-lightbulb"></span> Recommendations</h3>
                    <p class="sl-security-section-intro">Optional improvements that can strengthen your monitoring.</p>
                    <?php sl_security_render_recommendation_list($suggestion_items); ?>
                </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <?php if ($sem_enabled && !empty($recent_events)) : ?>
            <div class="sl-security-dashboard-section sl-security-activity-section">
                <h3><span class="dashicons dashicons-clock"></span> Recent Security Activity</h3>
                <div class="sl-security-activity-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event Type</th>
                                <th>Details</th>
                                <th>IP Address</th>
                                <th>Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_events as $event) : ?>
                                <tr>
                                    <td><?php echo esc_html(sl_security_format_datetime($event['timestamp'] ?? '')); ?></td>
                                    <td><span class="sl-security-event-type"><?php echo esc_html($event['event_type'] ?? ''); ?></span></td>
                                    <td><?php echo esc_html(substr($event['uri'] ?? '', 0, 50) . (strlen($event['uri'] ?? '') > 50 ? '...' : '')); ?></td>
                                    <td><?php echo esc_html($event['source_ip'] ?? ''); ?></td>
                                    <td><?php echo esc_html($event['cf_country'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="sl-security-activity-footer">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . SL_SECURITY_EVENTS_SLUG)); ?>" class="button button-secondary">View All Events</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="sl-security-dashboard-section sl-security-quick-actions-section">
                <h3><span class="dashicons dashicons-admin-tools"></span> Quick Actions</h3>
                <div class="sl-security-quick-action-buttons">
                    <?php if ($fim_enabled) : ?>
                        <form method="post" class="sl-security-action-form">
                            <?php wp_nonce_field('sl_security_action'); ?>
                            <input type="hidden" name="sl_security_action" value="run_check">
                            <input type="hidden" name="sl_return_page" value="<?php echo esc_attr(SL_SECURITY_MENU_SLUG); ?>">
                            <button type="submit" class="button button-primary"><span class="dashicons dashicons-search"></span> Run Integrity Check</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$baseline_exists && $fim_enabled) : ?>
                        <form method="post" class="sl-security-action-form">
                            <?php wp_nonce_field('sl_security_action'); ?>
                            <input type="hidden" name="sl_security_action" value="create_baseline">
                            <button type="submit" class="button button-secondary"><span class="dashicons dashicons-database-add"></span> Create Baseline</button>
                        </form>
                    <?php endif; ?>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . SL_SECURITY_FIM_SLUG)); ?>" class="button button-secondary"><span class="dashicons dashicons-chart-area"></span> File Integrity Details</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . SL_SECURITY_EVENTS_SLUG)); ?>" class="button button-secondary"><span class="dashicons dashicons-list-view"></span> Security Events</a>
                </div>
            </div>

            <!-- System Status -->
            <div class="sl-security-dashboard-section sl-security-system-section">
                <h3><span class="dashicons dashicons-info"></span> System Status</h3>
                <div class="sl-security-system-grid">
                    <div class="sl-security-system-item">
                        <span class="dashicons dashicons-plugins-checked"></span>
                        <div>
                            <strong>Plugin Version</strong><br>
                            <?php echo esc_html(SL_SECURITY_PLUGIN_VERSION); ?>
                        </div>
                    </div>
                    <div class="sl-security-system-item">
                        <span class="dashicons dashicons-wordpress"></span>
                        <div>
                            <strong>WordPress Version</strong><br>
                            <?php echo esc_html(get_bloginfo('version')); ?>
                        </div>
                    </div>
                    <div class="sl-security-system-item">
                        <span class="dashicons dashicons-admin-site"></span>
                        <div>
                            <strong>PHP Version</strong><br>
                            <?php echo esc_html(PHP_VERSION); ?>
                        </div>
                    </div>
                    <div class="sl-security-system-item">
                        <span class="dashicons dashicons-calendar"></span>
                        <div>
                            <strong>Last Updated</strong><br>
                            <?php echo esc_html(wp_date('n/j/y', filemtime(SL_SECURITY_PLUGIN_FILE))); ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
    <?php
}

function sl_security_render_fim_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

    $fim_enabled = sl_security_setting_enabled('enable_fim');
    $baseline_exists = file_exists(SL_FIM_BASELINE_FILE);
    $log_exists = file_exists(SL_FIM_LOG_FILE);
    $baseline_info = sl_security_get_baseline_info();
    $fim_status = function_exists('sl_fim_get_status') ? sl_fim_get_status() : null;
    $next_check = wp_next_scheduled('sl_fim_daily_check');

    ?>
    <div class="wrap sl-security-wrap">
        <div class="sl-security-page-header">
            <div>
                <h1>File Integrity Monitoring</h1>
                <p class="sl-security-tagline">Monitor WordPress files for unauthorized changes</p>
            </div>
        </div>

        <?php sl_security_render_notice(); ?>

        <div class="sl-security-description">
            <p>
                File Integrity Monitoring creates a trusted SHA-256 baseline for selected WordPress configuration,
                core entry, plugin, and theme files, then checks for added, removed, or modified files.
            </p>
        </div>

        <!-- FIM Status Cards -->
        <div class="sl-security-status-cards">
            <?php
            sl_security_render_status_card(
                'FIM Status',
                $fim_enabled ? 'Enabled' : 'Disabled',
                $fim_enabled ? 'Monitoring controls are active' : 'Enable FIM in Settings',
                $fim_enabled ? '#008a20' : '#646970',
                'dashicons-shield-alt'
            );

            sl_security_render_status_card(
                'Baseline',
                $baseline_exists ? 'Created' : 'Missing',
                $baseline_exists ? 'Trusted file state exists' : 'Create a baseline before monitoring',
                $baseline_exists ? '#008a20' : '#b32d2e',
                'dashicons-database'
            );

            sl_security_render_status_card(
                'Last Check',
                $fim_enabled ? sl_security_get_last_check_label($fim_status) : 'Disabled',
                $fim_enabled ? sl_security_get_last_check_message($fim_status) : 'FIM is disabled in Settings',
                $fim_enabled ? sl_security_get_status_color($fim_status) : '#646970',
                'dashicons-search'
            );

            sl_security_render_status_card(
                'Changed Files',
                $fim_enabled ? sl_security_get_change_total($fim_status) : 0,
                $fim_enabled ? sl_security_get_changed_files_description($fim_status) : 'Monitoring disabled',
                ($fim_enabled && sl_security_get_change_total($fim_status) > 0) ? '#b32d2e' : '#008a20',
                'dashicons-flag'
            );
            ?>
        </div>

        <!-- Baseline Management Section -->
        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-database"></span> Baseline Management</h2>

            <div class="sl-security-baseline-grid">
                <div class="sl-security-baseline-info">
                    <h3>Baseline Status</h3>
                    <table class="widefat striped sl-security-table">
                        <tbody>
                            <tr>
                                <th>Status</th>
                                <td><?php echo esc_html($baseline_exists ? 'Created' : 'Not created'); ?></td>
                            </tr>

                            <?php if ($baseline_info) : ?>
                                <tr>
                                    <th>Created At</th>
                                    <td><?php echo esc_html(sl_security_format_datetime($baseline_info['created_at'] ?? 'Unknown')); ?></td>
                                </tr>
                                <tr>
                                    <th>Age</th>
                                    <td><?php echo esc_html(sl_security_get_age_label($baseline_info['created_timestamp'] ?? null)); ?></td>
                                </tr>
                                <tr>
                                    <th>Files Monitored</th>
                                    <td><?php echo esc_html($baseline_info['file_count'] ?? 'Unknown'); ?></td>
                                </tr>
                                <tr>
                                    <th>Site URL</th>
                                    <td><?php echo esc_html($baseline_info['site_url'] ?? 'Unknown'); ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($fim_status) : ?>
                                <tr>
                                    <th>Last Check</th>
                                    <td><?php echo esc_html(sl_security_format_datetime($fim_status['checked_at'] ?? 'Unknown')); ?></td>
                                </tr>
                                <tr>
                                    <th>Check Age</th>
                                    <td><?php echo esc_html(sl_security_get_age_label($fim_status['checked_timestamp'] ?? strtotime($fim_status['checked_at'] ?? ''))); ?></td>
                                </tr>
                                <tr>
                                    <th>Last Result</th>
                                    <td><?php echo esc_html($fim_status['message'] ?? 'Unknown'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="sl-security-baseline-actions">
                    <h3>Actions</h3>
                    <div class="sl-security-action-buttons">
                        <form method="post" class="sl-security-action-form">
                            <?php wp_nonce_field('sl_security_action'); ?>
                            <input type="hidden" name="sl_security_action" value="create_baseline">
                            <button type="submit" class="button button-primary" <?php echo $fim_enabled ? '' : 'disabled'; ?>>
                                <span class="dashicons dashicons-database-add"></span> Create Baseline
                            </button>
                        </form>

                        <form method="post" class="sl-security-action-form">
                            <?php wp_nonce_field('sl_security_action'); ?>
                            <input type="hidden" name="sl_security_action" value="run_check">
                            <input type="hidden" name="sl_return_page" value="sl-security-fim">
                            <button type="submit" class="button button-secondary" <?php echo $fim_enabled ? '' : 'disabled'; ?>>
                                <span class="dashicons dashicons-search"></span> Run Check Now
                            </button>
                        </form>

                        <form method="post" class="sl-security-action-form">
                            <?php wp_nonce_field('sl_security_action'); ?>
                            <input type="hidden" name="sl_security_action" value="delete_baseline">
                            <button type="submit" class="button button-danger">
                                <span class="dashicons dashicons-trash"></span> Delete Baseline
                            </button>
                        </form>
                    </div>

                    <div class="sl-security-note">
                        <p><strong>Note:</strong> Only create a baseline after confirming your current files are expected and approved.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Automation Section -->
        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-clock"></span> Automation</h2>

            <div class="sl-security-automation-grid">
                <div class="sl-security-automation-info">
                    <h3>Schedule Status</h3>
                    <table class="widefat striped sl-security-table">
                        <tbody>
                            <tr>
                                <th>Status</th>
                                <td><?php echo esc_html($next_check ? 'Scheduled' : 'Not scheduled'); ?></td>
                            </tr>
                            <tr>
                                <th>Next Check</th>
                                <td>
                                    <?php
                                    echo esc_html(
                                        $next_check
                                            ? wp_date('n/j/y g:i A', $next_check)
                                            : 'No automatic check scheduled'
                                    );
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Frequency</th>
                                <td>Daily</td>
                            </tr>
                            <tr>
                                <th>Cron Hook</th>
                                <td><code>sl_fim_daily_check</code></td>
                            </tr>
                            <tr>
                                <th>Type</th>
                                <td>WordPress cron event</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="sl-security-automation-actions">
                    <h3>Schedule Controls</h3>
                    <div class="sl-security-action-buttons">
                        <?php if ($next_check) : ?>
                            <form method="post" class="sl-security-action-form">
                                <?php wp_nonce_field('sl_security_action'); ?>
                                <input type="hidden" name="sl_security_action" value="reschedule_check">
                                <button type="submit" class="button button-secondary" <?php echo ($fim_enabled && sl_security_setting_enabled('enable_fim_schedule')) ? '' : 'disabled'; ?>>
                                    <span class="dashicons dashicons-update"></span> Reschedule Check
                                </button>
                            </form>

                            <form method="post" class="sl-security-action-form">
                                <?php wp_nonce_field('sl_security_action'); ?>
                                <input type="hidden" name="sl_security_action" value="unschedule_check">
                                <button type="submit" class="button button-secondary">
                                    <span class="dashicons dashicons-no"></span> Unschedule Check
                                </button>
                            </form>
                        <?php else : ?>
                            <form method="post" class="sl-security-action-form">
                                <?php wp_nonce_field('sl_security_action'); ?>
                                <input type="hidden" name="sl_security_action" value="schedule_check">
                                <button type="submit" class="button button-primary" <?php echo ($fim_enabled && sl_security_setting_enabled('enable_fim_schedule')) ? '' : 'disabled'; ?>>
                                    <span class="dashicons dashicons-clock"></span> Schedule Daily Check
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="sl-security-note">
                        <p>WordPress cron runs when the site receives traffic after the scheduled time. For exact timing, consider using a server-side cron job.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Summary -->
        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-chart-bar"></span> Change Summary</h2>
            <?php sl_security_render_change_summary($fim_status); ?>
        </div>

        <!-- Configuration -->
        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-admin-settings"></span> Configuration</h2>

            <div class="sl-security-config-grid">
                <div class="sl-security-config-section">
                    <h3>Monitored Target Groups</h3>
                    <?php sl_security_render_target_groups(); ?>
                </div>

                <div class="sl-security-config-section">
                    <h3>Excluded Paths</h3>
                    <?php sl_security_render_excluded_paths(); ?>
                </div>
            </div>
        </div>

        <!-- Recent Log -->
        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-list-view"></span> Recent Integrity Log</h2>

            <?php if ($log_exists) : ?>
                <div class="sl-security-log-container">
                    <textarea readonly class="sl-security-log-textarea"><?php
                        echo esc_textarea(sl_security_get_recent_log_lines(SL_FIM_LOG_FILE, 80));
                    ?></textarea>
                </div>
            <?php else : ?>
                <p>No integrity log has been created yet.</p>
            <?php endif; ?>

            <div class="sl-security-log-actions">
                <form method="post" class="sl-security-action-form">
                    <?php wp_nonce_field('sl_security_action'); ?>
                    <input type="hidden" name="sl_security_action" value="clear_log">
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span> Clear Log
                    </button>
                </form>
            </div>
        </div>

    </div>
    <?php
}

function sl_security_render_events_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

    $sem_enabled = sl_security_setting_enabled('enable_sem');
    $events = function_exists('sl_sem_get_recent_events') ? sl_sem_get_recent_events(100) : [];
    $events_today = function_exists('sl_sem_count_events_today') ? sl_sem_count_events_today() : 0;
    $xmlrpc_blocks = function_exists('sl_sem_count_events_by_type') ? sl_sem_count_events_by_type('xmlrpc_blocked') : 0;
    $rest_enum = function_exists('sl_sem_count_events_by_type') ? sl_sem_count_events_by_type('rest_user_enumeration_attempt') : 0;
    $author_enum = function_exists('sl_sem_count_events_by_type') ? sl_sem_count_events_by_type('author_enumeration_blocked') : 0;

    ?>
    <div class="wrap sl-security-wrap">
        <div class="sl-security-page-header">
            <div>
                <h1>Security Events</h1>
                <p class="sl-security-tagline">Review blocked and suspicious requests handled by the security layer</p>
            </div>
        </div>

        <?php sl_security_render_notice(); ?>

        <div class="sl-security-description">
            <p>
                Security Event Monitoring records blocked or suspicious requests handled by the Severino Labs Security Layer.
                Cloudflare-blocked requests will not appear here because they are stopped before reaching WordPress.
            </p>
        </div>

        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-chart-bar"></span> Event Dashboard</h2>

            <div class="sl-security-status-cards">
                <?php
                sl_security_render_status_card(
                    'SEM',
                    $sem_enabled ? 'Enabled' : 'Disabled',
                    $sem_enabled ? 'Security events are being logged' : 'Enable SEM in Settings',
                    $sem_enabled ? '#008a20' : '#646970',
                    'dashicons-visibility'
                );

                sl_security_render_status_card(
                    'Events Today',
                    $events_today,
                    'Events logged today',
                    $events_today > 0 ? '#dba617' : '#008a20',
                    'dashicons-flag'
                );

                sl_security_render_status_card(
                    'XML-RPC Blocks',
                    $xmlrpc_blocks,
                    'Recent XML-RPC block events',
                    $xmlrpc_blocks > 0 ? '#dba617' : '#2271b1',
                    'dashicons-shield'
                );

                sl_security_render_status_card(
                    'Enumeration Attempts',
                    $rest_enum + $author_enum,
                    'REST and author enumeration events',
                    ($rest_enum + $author_enum) > 0 ? '#dba617' : '#2271b1',
                    'dashicons-search'
                );
                ?>
            </div>
        </div>

        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-list-view"></span> Recent Security Events</h2>

            <?php if (empty($events)) : ?>
                <p>No security events have been logged yet.</p>
            <?php else : ?>
                <div class="sl-security-events-table-wrapper">
                    <table class="widefat striped sl-security-table-expandable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Method</th>
                                <th>URI</th>
                                <th>Source IP</th>
                                <th>Country</th>
                                <th>User</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event) : ?>
                                <tr class="sl-security-event-summary" tabindex="0">
                                    <td><?php echo esc_html(sl_security_format_datetime($event['timestamp'] ?? '')); ?></td>
                                    <td><code><?php echo esc_html($event['event_type'] ?? ''); ?></code></td>
                                    <td><?php echo esc_html($event['method'] ?? ''); ?></td>
                                    <td><code><?php echo esc_html($event['uri'] ?? ''); ?></code></td>
                                    <td><?php echo esc_html($event['source_ip'] ?? ''); ?></td>
                                    <td><?php echo esc_html($event['cf_country'] ?? ''); ?></td>
                                    <td><?php echo esc_html($event['user_id'] ? $event['user_id'] : 'Guest'); ?></td>
                                    <td class="sl-security-event-expand-icon"><span class="dashicons dashicons-arrow-right-alt2"></span></td>
                                </tr>
                                <tr class="sl-security-event-details-row">
                                    <td colspan="8">
                                        <div class="sl-security-event-details-panel">
                                            <div class="sl-security-event-detail"><strong>User Agent:</strong> <?php echo esc_html($event['user_agent'] ?? ''); ?></div>
                                            <div class="sl-security-event-detail"><strong>Referer:</strong> <?php echo esc_html($event['referer'] ?? ''); ?></div>
                                            <div class="sl-security-event-detail"><strong>CF Ray:</strong> <?php echo esc_html($event['cf_ray'] ?? ''); ?></div>
                                            <div class="sl-security-event-detail"><strong>Details:</strong> <?php echo esc_html(wp_json_encode($event['details'] ?? [])); ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-media-text"></span> Raw Event Log</h2>

            <?php if (defined('SL_SEM_LOG_FILE') && file_exists(SL_SEM_LOG_FILE)) : ?>
                <div class="sl-security-log-container">
                    <textarea readonly class="sl-security-log-textarea"><?php
                        echo esc_textarea(sl_security_get_recent_log_lines(SL_SEM_LOG_FILE, 80));
                    ?></textarea>
                </div>
            <?php else : ?>
                <p>No raw security event log has been created yet.</p>
            <?php endif; ?>
        </div>

        <div class="sl-security-section">
            <h2><span class="dashicons dashicons-warning"></span> Danger Zone</h2>

            <p class="sl-security-danger-zone-note">
                Clearing the event log removes local SEM history only. It does not change firewall rules, Cloudflare settings, or site configuration.
            </p>

            <form method="post">
                <?php wp_nonce_field('sl_security_action'); ?>
                <input type="hidden" name="sl_security_action" value="clear_sem_log">
                <?php submit_button('Clear Security Event Log', 'delete', 'submit', false); ?>
            </form>
        </div>
    </div>
    <?php
}

function sl_security_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

    $settings = sl_security_get_settings();
    $locked_settings = sl_security_get_locked_settings();
    $groups = sl_security_get_setting_groups();
    $branding = sl_security_get_branding_settings();
    $fim_settings = sl_security_get_fim_settings();
    $passkey_verified = !empty($settings['passkey_usernameless_verified']);
    $passkey_provider_available = function_exists('sl_security_passkey_provider_available')
        && sl_security_passkey_provider_available();

    ?>
    <div class="wrap sl-security-wrap">
        <div class="sl-security-page-header">
            <div>
                <h1><?php echo esc_html(SL_SECURITY_BRAND_NAME); ?> Settings</h1>
                <p class="sl-security-tagline">Configure branding, file integrity monitoring, and security controls</p>
            </div>
        </div>

        <?php sl_security_render_notice(); ?>

        <form method="post">
            <?php wp_nonce_field('sl_security_action'); ?>
            <input type="hidden" name="sl_security_action" value="save_settings">

            <h2>Branding Configuration</h2>
            <p>Customize the appearance of the passkey login page and admin interface.</p>

            <table class="widefat striped sl-security-table">
                <tbody>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="passkey_login_logo_url">Passkey Login Logo URL</label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="passkey_login_logo_url"
                                name="sl_security_branding[passkey_login_logo_url]"
                                value="<?php echo esc_attr($branding['passkey_login_logo_url']); ?>"
                                class="regular-text"
                                placeholder="https://example.com/logo.png"
                            >
                            <p class="description">URL to your logo image for the passkey login page. Leave empty to use default.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="passkey_login_site_label">Passkey Login Site Label</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="passkey_login_site_label"
                                name="sl_security_branding[passkey_login_site_label]"
                                value="<?php echo esc_attr($branding['passkey_login_site_label']); ?>"
                                class="regular-text"
                                placeholder="<?php echo esc_attr(SL_SECURITY_BRAND_NAME); ?>"
                            >
                            <p class="description">Display name shown on the passkey login page.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2>File Integrity Monitoring</h2>
            <p>Configure which files and directories to monitor for changes, and which paths to exclude.</p>

            <table class="widefat striped sl-security-table">
                <tbody>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="fim_targets">Monitored Targets</label>
                        </th>
                        <td>
                            <textarea
                                id="fim_targets"
                                name="sl_security_fim_targets"
                                rows="8"
                                class="large-text code"
                                placeholder="Enter one path per line"
                            ><?php echo esc_textarea(sl_security_format_multiline_list($fim_settings['targets'])); ?></textarea>
                            <p class="description">Files and directories to monitor for integrity. One path per line. Supports wildcards and relative paths.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fim_excluded_paths">Excluded Paths</label>
                        </th>
                        <td>
                            <textarea
                                id="fim_excluded_paths"
                                name="sl_security_fim_excluded_paths"
                                rows="6"
                                class="large-text code"
                                placeholder="Enter one path per line"
                            ><?php echo esc_textarea(sl_security_format_multiline_list($fim_settings['excluded_paths'])); ?></textarea>
                            <p class="description">Paths to exclude from monitoring. One path per line. Useful for logs, caches, and temporary files.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <h2>Passkey Login Readiness</h2>
            <p>
                The passkey login screen delegates WebAuthn handling to the
                <a href="https://wordpress.org/plugins/wp-webauthn/" target="_blank" rel="noopener">WP-WebAuthn</a>
                plugin. Before running the test below, make sure that:
            </p>
            <ol class="sl-security-prereq-list">
                <li>WP-WebAuthn is installed and active.</li>
                <li>Under <em>WP-WebAuthn &rarr; Settings</em>, <strong>Usernameless Login</strong> is enabled.</li>
                <li>At least one passkey is registered on a user account on this site (via <em>WP-WebAuthn &rarr; Authenticators</em> on the user profile).</li>
            </ol>

            <table class="widefat striped sl-security-table">
                <tbody>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">WP-WebAuthn Plugin</th>
                        <td>
                            <?php if ($passkey_provider_available) : ?>
                                <span class="sl-security-status-badge sl-security-status-enabled">
                                    <span class="dashicons dashicons-yes-alt"></span> Detected
                                </span>
                                <p class="description sl-security-passkey-test-status">
                                    WP-WebAuthn is active and exposing the required AJAX endpoints.
                                </p>
                            <?php else : ?>
                                <span class="sl-security-status-badge sl-security-status-locked">
                                    <span class="dashicons dashicons-warning"></span> Missing
                                </span>
                                <p class="description sl-security-passkey-test-status">
                                    Install and activate
                                    <a href="<?php echo esc_url(admin_url('plugin-install.php?s=wp-webauthn&tab=search&type=term')); ?>">WP-WebAuthn</a>
                                    to enable passkey authentication.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">Usernameless Passkey Test</th>
                        <td>
                            <?php if ($passkey_verified) : ?>
                                <span class="sl-security-status-badge sl-security-status-enabled">
                                    <span class="dashicons dashicons-yes-alt"></span> Verified
                                </span>
                                <p class="description sl-security-passkey-test-status">
                                    A usernameless passkey authentication test has completed successfully.
                                </p>
                            <?php elseif (!$passkey_provider_available) : ?>
                                <span class="sl-security-status-badge sl-security-status-disabled">
                                    <span class="dashicons dashicons-marker"></span> Unavailable
                                </span>
                                <p class="description sl-security-passkey-test-status">
                                    Install WP-WebAuthn before running the test.
                                </p>
                            <?php else : ?>
                                <button type="button" id="sl-test-passkey" class="button button-primary">
                                    <span class="dashicons dashicons-shield-alt"></span> Test Usernameless Passkey
                                </button>
                                <span id="sl-passkey-test-status" class="description sl-security-passkey-test-status">
                                    Passkey-only login is locked until this test passes.
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <h2>Security Controls</h2>
            <p>These settings control the behavior of the Severino Labs Security Layer. Core hardening controls are shown as locked because they define the baseline security posture of the plugin.</p>

            <?php foreach ($groups as $group_name => $controls) : ?>
                <h3><?php echo esc_html($group_name); ?></h3>

                <table class="widefat striped sl-security-table sl-security-controls-table">
                    <thead>
                        <tr>
                            <th class="sl-security-controls-col-control">Control</th>
                            <th class="sl-security-controls-col-status">Status</th>
                            <th class="sl-security-controls-col-toggle">Toggle</th>
                            <th>Description</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($controls as $key => $control) : ?>
                            <?php
                            $enabled = !empty($settings[$key]);
                            $locked = in_array($key, $locked_settings, true);
                            $passkey_locked = $key === 'enable_passkey_login'
                                && (!$passkey_verified || !$passkey_provider_available);
                            if ($passkey_locked) {
                                $enabled = false;
                            }
                            $is_locked_row = $locked || $passkey_locked;

                            if ($locked) {
                                $badge_class = 'sl-security-status-always-on';
                                $badge_icon = 'dashicons-lock';
                                $badge_label = 'Always Enabled';
                            } elseif ($passkey_locked) {
                                $badge_class = 'sl-security-status-locked';
                                $badge_icon = 'dashicons-lock';
                                $badge_label = 'Locked';
                            } elseif ($enabled) {
                                $badge_class = 'sl-security-status-enabled';
                                $badge_icon = 'dashicons-yes-alt';
                                $badge_label = 'Enabled';
                            } else {
                                $badge_class = 'sl-security-status-disabled';
                                $badge_icon = 'dashicons-marker';
                                $badge_label = 'Disabled';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($control['label']); ?></strong>
                                </td>

                                <td>
                                    <span class="sl-security-status-badge <?php echo esc_attr($badge_class); ?>">
                                        <span class="dashicons <?php echo esc_attr($badge_icon); ?>"></span>
                                        <?php echo esc_html($badge_label); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($is_locked_row) : ?>
                                        <span class="sl-security-toggle-placeholder" aria-hidden="true">—</span>
                                        <?php if ($passkey_locked) : ?>
                                            <span class="screen-reader-text">Locked until passkey test passes</span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <label class="sl-security-toggle">
                                            <input
                                                type="checkbox"
                                                name="sl_security_settings[<?php echo esc_attr($key); ?>]"
                                                value="1"
                                                <?php checked($enabled); ?>
                                            >
                                            <span class="sl-security-toggle-track" aria-hidden="true">
                                                <span class="sl-security-toggle-thumb"></span>
                                            </span>
                                            <span class="screen-reader-text"><?php echo esc_html($control['label']); ?></span>
                                        </label>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php echo esc_html($control['description']); ?>
                                    <?php if ($passkey_locked) : ?>
                                        <?php if (!$passkey_provider_available) : ?>
                                            <p class="description">WP-WebAuthn is not active; this toggle is locked until the plugin is installed.</p>
                                        <?php else : ?>
                                            <p class="description">Run the passkey test above to unlock.</p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>

            <?php submit_button('Save All Settings'); ?>
        </form>

        <?php if ($passkey_verified) : ?>
            <form method="post" class="sl-security-reset-passkey-form">
                <?php wp_nonce_field('sl_security_action'); ?>
                <input type="hidden" name="sl_security_action" value="reset_passkey_usernameless_verification">
                <button type="submit" class="button button-secondary">
                    <span class="dashicons dashicons-image-rotate"></span> Reset Passkey Verification
                </button>
                <span class="description">Run the test again before re-enabling passkey-only login.</span>
            </form>
        <?php endif; ?>

        <?php
        $smtp = sl_security_get_smtp_settings();
        ?>
        <hr style="margin:32px 0;">
        <h2><span class="dashicons dashicons-email-alt"></span> Email &amp; SMTP Configuration</h2>
        <p>Configure outbound email delivery for all WordPress mail and Severino Labs security alerts. Once saved, these settings take over from whatever SMTP configuration WordPress uses by default.</p>

        <form method="post">
            <?php wp_nonce_field('sl_security_action'); ?>
            <input type="hidden" name="sl_security_action" value="save_smtp_settings">

            <h3>SMTP Relay</h3>
            <table class="widefat striped sl-security-table">
                <tbody>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="sl_smtp_host">SMTP Host</label>
                        </th>
                        <td>
                            <input type="text" id="sl_smtp_host" name="sl_security_smtp[smtp_host]"
                                value="<?php echo esc_attr($smtp['smtp_host']); ?>"
                                class="regular-text" placeholder="smtp-relay.brevo.com">
                            <p class="description">Your SMTP relay server hostname.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="sl_smtp_port">SMTP Port</label>
                        </th>
                        <td>
                            <input type="number" id="sl_smtp_port" name="sl_security_smtp[smtp_port]"
                                value="<?php echo esc_attr($smtp['smtp_port']); ?>"
                                class="small-text" min="1" max="65535">
                            <p class="description">Typically 587 (STARTTLS) or 465 (SSL).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="sl_smtp_user">SMTP Username</label>
                        </th>
                        <td>
                            <input type="text" id="sl_smtp_user" name="sl_security_smtp[smtp_user]"
                                value="<?php echo esc_attr($smtp['smtp_user']); ?>"
                                class="regular-text" autocomplete="off">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="sl_smtp_pass">SMTP Password</label>
                        </th>
                        <td>
                            <input type="password" id="sl_smtp_pass" name="sl_security_smtp[smtp_pass]"
                                value="" class="regular-text" autocomplete="new-password"
                                placeholder="<?php echo !empty($smtp['smtp_pass']) ? '••••••••••••' : 'Enter password'; ?>">
                            <p class="description">Leave blank to keep the current password.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="sl_smtp_from">From Email</label>
                        </th>
                        <td>
                            <input type="email" id="sl_smtp_from" name="sl_security_smtp[smtp_from]"
                                value="<?php echo esc_attr($smtp['smtp_from']); ?>"
                                class="regular-text" placeholder="noreply@yourdomain.com">
                            <p class="description">The address emails appear to come from. Must be authorized on your relay.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="sl_smtp_from_name">From Name</label>
                        </th>
                        <td>
                            <input type="text" id="sl_smtp_from_name" name="sl_security_smtp[smtp_from_name]"
                                value="<?php echo esc_attr($smtp['smtp_from_name']); ?>"
                                class="regular-text" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">SMTP Debug</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sl_security_smtp[smtp_debug]" value="1"
                                    <?php checked(!empty($smtp['smtp_debug'])); ?>>
                                Enable SMTP debug output
                            </label>
                            <p class="description">Logs detailed SMTP handshake output to the PHP error log. Disable in production.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Alert Recipient</h3>
            <table class="widefat striped sl-security-table">
                <tbody>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">
                            <label for="sl_smtp_alert_email">Alert Email</label>
                        </th>
                        <td>
                            <input type="email" id="sl_smtp_alert_email" name="sl_security_smtp[alert_email]"
                                value="<?php echo esc_attr($smtp['alert_email']); ?>"
                                class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                            <p class="description">Where all security alerts and digest emails are sent. Defaults to the WordPress admin email if left blank.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Immediate Alerts</h3>
            <p>These emails fire in real time when an event is detected.</p>
            <table class="widefat striped sl-security-table">
                <tbody>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">File Change Alert</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sl_security_smtp[alert_fim_changes]" value="1"
                                    <?php checked(!empty($smtp['alert_fim_changes'])); ?>>
                                Email me immediately when FIM detects file changes
                            </label>
                            <p class="description">Sends a detailed HTML email listing every added, removed, and modified file as soon as a FIM check finds a discrepancy.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">Security Event Spike</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sl_security_smtp[alert_sem_spike]" value="1"
                                    <?php checked(!empty($smtp['alert_sem_spike'])); ?>>
                                Email me when security event volume spikes
                            </label>
                            <p class="description">Triggers when events logged in a single hour exceed the threshold below.</p>
                            <p style="margin-top:10px;">
                                <label for="sl_smtp_sem_threshold"><strong>Spike threshold:</strong></label>
                                <input type="number" id="sl_smtp_sem_threshold"
                                    name="sl_security_smtp[alert_sem_spike_threshold]"
                                    value="<?php echo esc_attr($smtp['alert_sem_spike_threshold']); ?>"
                                    class="small-text" min="1" style="margin-left:6px;">
                                <span class="description">events / hour</span>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Daily Digest Emails</h3>
            <p>These emails are sent once per day at the time of the scheduled FIM check. Requires <strong>File Integrity Monitoring</strong> and <strong>Automated FIM Scheduling</strong> to be enabled in Security Controls.</p>
            <table class="widefat striped sl-security-table">
                <tbody>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">Daily FIM Report</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sl_security_smtp[alert_daily_fim_report]" value="1"
                                    <?php checked(!empty($smtp['alert_daily_fim_report'])); ?>>
                                Send a file integrity check summary after every scheduled scan
                            </label>
                            <p class="description">Delivers a clean result email after each automated FIM check — whether files passed or changes were found. Includes file counts, status, and any change details.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="sl-security-settings-label">Daily Security Dashboard</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sl_security_smtp[alert_daily_dashboard]" value="1"
                                    <?php checked(!empty($smtp['alert_daily_dashboard'])); ?>>
                                Send a daily security overview with key insights
                            </label>
                            <p class="description">A polished daily security snapshot covering your security score, FIM status, and recent security events — sent once per day at the scheduled FIM check time.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button('Save Email Settings'); ?>
        </form>

    </div>
    <?php
}

/**
 * Build a clean, professional HTML notification email.
 *
 * @param string $title      Main heading shown in the email body.
 * @param string $preheader  Short preview text (shown in email client previews).
 * @param array  $sections   Each entry is one of:
 *                           - Table section: ['heading' => 'Label Rows', 'rows' => [['label'=>'','value'=>''], ...]]
 *                           - File list:     ['type'=>'file_list','heading'=>'Added (3)','prefix'=>'+','files'=>[...]]
 * @param string $cta_url    Optional call-to-action button URL.
 * @param string $cta_label  Optional button label.
 * @return string            Full HTML email string.
 */
function sl_security_build_notification_email($title, $preheader, array $sections, $cta_url = '', $cta_label = 'View Dashboard') {
    $site_name   = get_bloginfo('name');
    $brand_color = '#008a20';
    $dark        = '#1d2327';
    $text_color  = '#3c3c3c';
    $border      = '#e0e0e0';
    $bg          = '#f6f6f6';
    $year        = gmdate('Y');

    $sections_html = '';

    foreach ($sections as $section) {
        if (isset($section['type']) && $section['type'] === 'file_list') {
            $prefix      = $section['prefix'] ?? '';
            $color_map   = ['+' => '#008a20', '-' => '#b32d2e', '~' => '#dba617'];
            $item_color  = $color_map[$prefix] ?? $text_color;
            $items_html  = '';
            foreach ($section['files'] as $file) {
                $items_html .= '<li style="font-family:monospace;font-size:12px;padding:3px 0;color:' . esc_attr($item_color) . ';">'
                    . esc_html($prefix . ' ' . $file) . '</li>';
            }
            $sections_html .= '
                    <tr><td style="padding:16px 0 4px;">
                        <strong style="font-size:13px;color:' . esc_attr($dark) . ';">' . esc_html($section['heading'] ?? '') . '</strong>
                        <ul style="margin:8px 0 0;padding:0 0 0 16px;">' . $items_html . '</ul>
                    </td></tr>';
        } else {
            $rows_html = '';
            foreach (($section['rows'] ?? []) as $row) {
                $rows_html .= '
                        <tr>
                            <td style="padding:8px 12px;background:#f9f9f9;border:1px solid ' . $border . ';width:32%;font-weight:600;font-size:13px;color:' . esc_attr($dark) . ';">'
                                . esc_html($row['label']) . '</td>
                            <td style="padding:8px 12px;background:#fff;border:1px solid ' . $border . ';font-size:13px;color:' . esc_attr($text_color) . ';">'
                                . wp_kses_post($row['value']) . '</td>
                        </tr>';
            }
            $heading_html = '';
            if (!empty($section['heading'])) {
                $heading_html = '<p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#646970;margin:20px 0 6px 0;">'
                    . esc_html($section['heading']) . '</p>';
            }
            $sections_html .= '
                    <tr><td>
                        ' . $heading_html . '
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows_html . '</table>
                    </td></tr>';
        }
    }

    $cta_html = '';
    if (!empty($cta_url)) {
        $cta_html = '
                    <tr><td style="padding:28px 0 8px;text-align:center;">
                        <a href="' . esc_url($cta_url) . '"
                           style="display:inline-block;background:' . $brand_color . ';color:#fff;text-decoration:none;font-weight:600;font-size:14px;padding:12px 28px;border-radius:4px;">'
                            . esc_html($cta_label) . '</a>
                    </td></tr>';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>' . esc_html($title) . '</title>
</head>
<body style="margin:0;padding:0;background:' . $bg . ';font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">
<span style="display:none;max-height:0;overflow:hidden;">' . esc_html($preheader) . '&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</span>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $bg . ';padding:32px 16px;">
    <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1);">
            <tr><td style="background:' . $dark . ';padding:22px 32px;">
                <p style="margin:0 0 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:' . $brand_color . ';">' . esc_html($site_name) . '</p>
                <h1 style="margin:0;font-size:21px;font-weight:700;color:#fff;line-height:1.3;">' . esc_html($title) . '</h1>
            </td></tr>
            <tr><td style="padding:28px 32px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    ' . $sections_html . $cta_html . '
                </table>
            </td></tr>
            <tr><td style="background:#f0f0f0;border-top:1px solid ' . $border . ';padding:14px 32px;text-align:center;">
                <p style="margin:0;font-size:11px;color:#646970;">Sent by Severino Labs Security Layer &middot; ' . esc_html($site_name) . ' &middot; &copy; ' . $year . '</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>';
}

/**
 * Send a daily FIM check summary email (pass or fail).
 * Hooked into sl_fim_daily_check at priority 20, after sl_fim_run_check().
 */
function sl_security_send_fim_daily_report() {
    $to = sl_security_get_alert_email();

    if (!is_email($to)) {
        return;
    }

    $site_name  = get_bloginfo('name');
    $fim_status = function_exists('sl_fim_get_status') ? sl_fim_get_status() : null;
    $next_check = wp_next_scheduled('sl_fim_daily_check');
    $checked_at = sl_security_format_datetime(current_time('mysql'));

    $status_label = $fim_status ? sl_security_get_last_check_label($fim_status) : 'Unknown';
    $is_clean     = $fim_status && ($fim_status['status'] ?? '') === 'passed';

    $subject = sprintf('[%s] FIM Report — %s — %s', $site_name, $status_label, wp_date('n/j/y'));
    $preheader = $is_clean ? 'All monitored files passed. No changes detected.' : 'File changes were detected. Review required.';

    $total_changes = (int) ($fim_status['added_count'] ?? 0)
        + (int) ($fim_status['removed_count'] ?? 0)
        + (int) ($fim_status['modified_count'] ?? 0);

    $summary_rows = [
        ['label' => 'Result',        'value' => $status_label],
        ['label' => 'Checked At',    'value' => $checked_at],
        ['label' => 'Files Changed', 'value' => (string) $total_changes],
    ];

    if ($total_changes > 0) {
        if (!empty($fim_status['added_count'])) {
            $summary_rows[] = ['label' => 'Added',    'value' => (string) (int) $fim_status['added_count']];
        }
        if (!empty($fim_status['removed_count'])) {
            $summary_rows[] = ['label' => 'Removed',  'value' => (string) (int) $fim_status['removed_count']];
        }
        if (!empty($fim_status['modified_count'])) {
            $summary_rows[] = ['label' => 'Modified', 'value' => (string) (int) $fim_status['modified_count']];
        }
    }

    if ($next_check) {
        $summary_rows[] = ['label' => 'Next Check', 'value' => wp_date('n/j/y g:i A', $next_check)];
    }

    $sections = [['heading' => 'Check Summary', 'rows' => $summary_rows]];

    if (!empty($fim_status['added'])) {
        $sections[] = ['type' => 'file_list', 'heading' => 'Added (' . count($fim_status['added']) . ')', 'prefix' => '+', 'files' => $fim_status['added']];
    }
    if (!empty($fim_status['removed'])) {
        $sections[] = ['type' => 'file_list', 'heading' => 'Removed (' . count($fim_status['removed']) . ')', 'prefix' => '-', 'files' => $fim_status['removed']];
    }
    if (!empty($fim_status['modified'])) {
        $sections[] = ['type' => 'file_list', 'heading' => 'Modified (' . count($fim_status['modified']) . ')', 'prefix' => '~', 'files' => $fim_status['modified']];
    }

    $body = sl_security_build_notification_email(
        'File Integrity Report',
        $preheader,
        $sections,
        admin_url('admin.php?page=' . SL_SECURITY_FIM_SLUG),
        'View File Integrity'
    );

    wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
}

/**
 * Send a daily security dashboard email with key site insights.
 * Hooked into sl_fim_daily_check at priority 30 (after FIM report at 20).
 */
function sl_security_send_daily_dashboard_email() {
    $to = sl_security_get_alert_email();

    if (!is_email($to)) {
        return;
    }

    $site_name      = get_bloginfo('name');
    $fim_enabled    = sl_security_setting_enabled('enable_fim');
    $sem_enabled    = sl_security_setting_enabled('enable_sem');
    $baseline_exists = file_exists(SL_FIM_BASELINE_FILE);
    $fim_status     = function_exists('sl_fim_get_status') ? sl_fim_get_status() : null;
    $next_check     = wp_next_scheduled('sl_fim_daily_check');
    $events_today   = function_exists('sl_sem_count_events_today') ? sl_sem_count_events_today() : 0;
    $total_events   = function_exists('sl_sem_count_total_events') ? sl_sem_count_total_events() : 0;
    $recent_events  = function_exists('sl_sem_get_recent_events') ? sl_sem_get_recent_events(5) : [];
    $score          = sl_security_calculate_security_score($fim_enabled, $sem_enabled, $baseline_exists, $fim_status, $events_today);
    $status_info    = sl_security_get_overall_security_status($score);

    $subject   = sprintf('[%s] Daily Security Dashboard — %s', $site_name, wp_date('n/j/y'));
    $preheader = sprintf('Security score %d%%. %d event%s today. %s', $score, $events_today, $events_today === 1 ? '' : 's', $status_info['status']);

    $sections = [];

    // Overview
    $overview_rows = [
        ['label' => 'Security Score',    'value' => $score . '%  —  ' . $status_info['status']],
        ['label' => 'Date',              'value' => wp_date('l, F j, Y')],
        ['label' => 'File Integrity',    'value' => $fim_enabled ? sl_security_get_last_check_label($fim_status) : 'Disabled'],
        ['label' => 'Events Today',      'value' => number_format($events_today)],
        ['label' => 'Events All-Time',   'value' => number_format($total_events)],
    ];
    if ($next_check) {
        $overview_rows[] = ['label' => 'Next FIM Check', 'value' => wp_date('n/j/y g:i A', $next_check)];
    }
    $sections[] = ['heading' => 'Security Overview', 'rows' => $overview_rows];

    // FIM detail (when changes exist)
    if ($fim_enabled && $fim_status && ($fim_status['status'] ?? '') === 'changes_detected') {
        $total_changes = (int) ($fim_status['added_count'] ?? 0)
            + (int) ($fim_status['removed_count'] ?? 0)
            + (int) ($fim_status['modified_count'] ?? 0);
        $fim_rows = [
            ['label' => 'Changes Found', 'value' => (string) $total_changes . ' file' . ($total_changes === 1 ? '' : 's')],
        ];
        if (!empty($fim_status['added_count']))    $fim_rows[] = ['label' => 'Added',    'value' => (string) (int) $fim_status['added_count']];
        if (!empty($fim_status['removed_count']))  $fim_rows[] = ['label' => 'Removed',  'value' => (string) (int) $fim_status['removed_count']];
        if (!empty($fim_status['modified_count'])) $fim_rows[] = ['label' => 'Modified', 'value' => (string) (int) $fim_status['modified_count']];
        $sections[] = ['heading' => 'File Integrity Changes', 'rows' => $fim_rows];
    }

    // Recent security events
    if ($sem_enabled && !empty($recent_events)) {
        $event_rows = [];
        foreach ($recent_events as $event) {
            $event_rows[] = [
                'label' => sl_security_format_datetime($event['timestamp'] ?? ''),
                'value' => esc_html(($event['event_type'] ?? 'Unknown') . ($event['source_ip'] ? '  ·  ' . $event['source_ip'] : '')),
            ];
        }
        $sections[] = ['heading' => 'Recent Security Events', 'rows' => $event_rows];
    }

    /**
     * Allow companion plugins to append additional sections to the daily dashboard email.
     *
     * Each section must be an array accepted by sl_security_build_notification_email():
     *   ['heading' => 'Title', 'rows' => [['label' => '', 'value' => ''], ...]]
     *   ['type' => 'file_list', 'heading' => '', 'prefix' => '+', 'files' => [...]]
     *
     * @param array $sections Existing dashboard sections.
     */
    $sections = apply_filters('sl_security_daily_dashboard_sections', $sections);

    $body = sl_security_build_notification_email(
        'Daily Security Dashboard',
        $preheader,
        $sections,
        admin_url('admin.php?page=' . SL_SECURITY_MENU_SLUG),
        'Open Dashboard'
    );

    wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
}

function sl_security_render_status_card($title, $value, $description, $accent_color, $icon = '') {
    ?>
    <div class="sl-security-status-card" style="border-left-color: <?php echo esc_attr($accent_color); ?>;">
        <h3>
            <?php if (!empty($icon)) : ?>
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            <?php endif; ?>
            <span><?php echo esc_html($title); ?></span>
        </h3>
        <div class="sl-security-status-value"><?php echo esc_html($value); ?></div>
        <p class="sl-security-status-desc"><?php echo esc_html($description); ?></p>
    </div>
    <?php
}

function sl_security_get_last_check_label($status) {
    if (!$status || empty($status['status'])) {
        return 'Not Run';
    }

    if ($status['status'] === 'passed') {
        return 'Healthy';
    }

    if ($status['status'] === 'changes_detected') {
        return 'Needs Review';
    }

    if ($status['status'] === 'baseline_created') {
        return 'Ready';
    }

    if ($status['status'] === 'disabled') {
        return 'Disabled';
    }

    if ($status['status'] === 'no_baseline') {
        return 'No Baseline';
    }

    if ($status['status'] === 'invalid_baseline') {
        return 'Invalid Baseline';
    }

    return 'Unknown';
}

function sl_security_get_last_check_message($status) {
    if (!$status || empty($status['message'])) {
        return 'No integrity check has run yet';
    }

    return $status['message'];
}

function sl_security_get_status_color($status) {
    if (!$status || empty($status['status'])) {
        return '#646970';
    }

    if ($status['status'] === 'passed' || $status['status'] === 'baseline_created') {
        return '#008a20';
    }

    if ($status['status'] === 'changes_detected' || $status['status'] === 'invalid_baseline') {
        return '#b32d2e';
    }

    return '#dba617';
}

function sl_security_get_change_total($status) {
    if (!$status) {
        return 0;
    }

    return absint($status['added_count'] ?? 0)
        + absint($status['removed_count'] ?? 0)
        + absint($status['modified_count'] ?? 0);
}

function sl_security_get_changed_files_description($status) {
    $count = sl_security_get_change_total($status);

    return $count === 1 ? 'Changed file requires review' : 'Changed files requiring review';
}

function sl_security_render_change_summary($status) {
    if (!$status) {
        echo '<p>No integrity check results are available yet.</p>';
        return;
    }

    $added = $status['added'] ?? [];
    $removed = $status['removed'] ?? [];
    $modified = $status['modified'] ?? [];

    ?>
    <table class="widefat striped sl-security-change-summary-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Count</th>
                <th>Files</th>
            </tr>
        </thead>
        <tbody>
            <?php sl_security_render_change_row('Added', $added); ?>
            <?php sl_security_render_change_row('Removed', $removed); ?>
            <?php sl_security_render_change_row('Modified', $modified); ?>
        </tbody>
    </table>
    <?php
}

function sl_security_render_change_row($label, $files) {
    ?>
    <tr>
        <td><strong><?php echo esc_html($label); ?></strong></td>
        <td><?php echo esc_html(count($files)); ?></td>
        <td>
            <?php if (empty($files)) : ?>
                <span class="sl-security-change-empty">None</span>
            <?php else : ?>
                <ul class="sl-security-change-files">
                    <?php foreach ($files as $file) : ?>
                        <li><code><?php echo esc_html($file); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

function sl_security_render_target_groups() {
    $wp_root = ABSPATH;
    $plugin_root = plugin_dir_path(__DIR__);

    $groups = [
        'Critical Configuration' => [
            $wp_root . 'wp-config.php',
            $wp_root . '.htaccess',
        ],
        'WordPress Bootstrap Files' => [
            $wp_root . 'index.php',
            $wp_root . 'wp-settings.php',
            $wp_root . 'wp-load.php',
            $wp_root . 'wp-blog-header.php',
            $wp_root . 'xmlrpc.php',
        ],
        'WordPress Admin/Core Files' => [
            $wp_root . 'wp-admin/index.php',
            $wp_root . 'wp-admin/admin.php',
            $wp_root . 'wp-admin/admin-ajax.php',
            $wp_root . 'wp-admin/admin-post.php',
            $wp_root . 'wp-includes/version.php',
            $wp_root . 'wp-includes/functions.php',
            $wp_root . 'wp-includes/pluggable.php',
        ],
        'Custom Code' => [
            $plugin_root,
            get_stylesheet_directory(),
        ],
    ];

    ?>
    <p class="sl-security-config-description">
        These are the WordPress configuration, bootstrap, admin core, and custom code locations actively
        baselined and verified during each integrity check.
    </p>

    <div class="sl-security-expandable-table">
        <details>
            <summary class="sl-security-table-toggle">
                <span>Monitored Target Groups (<?php echo count($groups); ?> groups)</span>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </summary>
            <div class="sl-security-table-content">
                <table class="widefat striped sl-security-config-table">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th>Files/Paths</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group_name => $targets) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($group_name); ?></strong></td>
                                <td>
                                    <ul class="sl-security-target-list">
                                        <?php foreach ($targets as $target) : ?>
                                            <li><code><?php echo esc_html($target); ?></code></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
    <?php
}


function sl_security_render_excluded_paths() {
    $excluded_paths = function_exists('sl_fim_get_excluded_paths') ? sl_fim_get_excluded_paths() : [];

    if (empty($excluded_paths)) {
        echo '<p>No excluded paths are currently defined.</p>';
        return;
    }

    ?>
    <p class="sl-security-config-description">
        These paths are intentionally excluded to avoid noisy alerts from logs, caches, uploads, temporary files,
        backups, dependencies, and the monitor's own data files.
    </p>

    <div class="sl-security-expandable-table">
        <details>
            <summary class="sl-security-table-toggle">
                <span>Excluded Paths (<?php echo count($excluded_paths); ?> paths)</span>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </summary>
            <div class="sl-security-table-content">
                <table class="widefat striped sl-security-config-table">
                    <thead>
                        <tr>
                            <th>Path</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $path_descriptions = [
                            'wp-content/uploads' => 'User uploads directory',
                            'wp-content/cache' => 'Cache files',
                            'wp-content/logs' => 'Log files',
                            'wp-content/backups' => 'Backup files',
                            'wp-content/temp' => 'Temporary files',
                            'wp-content/debug.log' => 'WordPress debug log',
                            'wp-content/litespeed' => 'LiteSpeed cache',
                            '.git' => 'Git repository',
                            'node_modules' => 'Node.js dependencies',
                            'vendor' => 'Composer dependencies',
                            'data' => 'Plugin data directory',
                        ];

                        foreach ($excluded_paths as $path) :
                            $description = 'Excluded path';
                            foreach ($path_descriptions as $key => $desc) {
                                if (strpos($path, $key) !== false) {
                                    $description = $desc;
                                    break;
                                }
                            }
                        ?>
                            <tr>
                                <td><code><?php echo esc_html($path); ?></code></td>
                                <td><?php echo esc_html($description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
    <?php
}

function sl_security_render_notice() {
    if (!isset($_GET['sl_message'])) {
        return;
    }

    $message = sanitize_text_field(wp_unslash($_GET['sl_message']));

    $messages = [
        'baseline_created' => ['success', 'Current file state trusted and baseline created successfully.'],
        'check_completed' => ['success', 'FIM check completed. Review the dashboard summary below.'],
        'schedule_created' => ['success', 'Daily FIM check scheduled successfully.'],
        'schedule_updated' => ['success', 'Daily FIM check rescheduled successfully.'],
        'schedule_removed' => ['success', 'Daily FIM check unscheduled successfully.'],
        'log_cleared' => ['success', 'Integrity log cleared successfully.'],
        'baseline_deleted' => ['warning', 'Trusted baseline deleted. Create a new baseline before relying on integrity checks.'],
        'settings_saved' => ['success', 'Security layer settings saved successfully.'],
        'smtp_settings_saved' => ['success', 'Email & SMTP settings saved successfully.'],
        'sem_log_cleared' => ['success', 'Security event log cleared successfully.'],
        'passkey_verification_reset' => ['warning', 'Usernameless passkey verification was reset. Passkey-only login is disabled until the test passes again.'],
    ];

    if (!isset($messages[$message])) {
        return;
    }

    [$type, $text] = $messages[$message];

    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
}

function sl_security_get_age_label($timestamp) {
    if (empty($timestamp) || !is_numeric($timestamp)) {
        return 'Unknown';
    }

    $diff = time() - absint($timestamp);

    if ($diff < 60) {
        return 'Less than 1 minute ago';
    }

    if ($diff < HOUR_IN_SECONDS) {
        $minutes = floor($diff / MINUTE_IN_SECONDS);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    if ($diff < DAY_IN_SECONDS) {
        $hours = floor($diff / HOUR_IN_SECONDS);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = floor($diff / DAY_IN_SECONDS);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function sl_security_get_recent_log_lines($file, $lines = 80) {
    if (!file_exists($file)) {
        return '';
    }

    $contents = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($contents)) {
        return '';
    }

    return implode(PHP_EOL, array_slice($contents, -absint($lines)));
}