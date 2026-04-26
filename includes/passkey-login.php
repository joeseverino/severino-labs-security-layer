<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the WP-WebAuthn plugin is active and exposing the AJAX endpoints
 * this plugin depends on for passkey authentication.
 */
function sl_security_passkey_provider_available() {
    return defined('WPWEBAUTHN_VERSION')
        || defined('WWA_VERSION')
        || has_action('wp_ajax_wwa_auth_start')
        || has_action('wp_ajax_nopriv_wwa_auth_start')
        || has_action('wp_ajax_wwa_auth')
        || has_action('wp_ajax_nopriv_wwa_auth');
}

/**
 * Show a persistent admin notice when a passkey-related setting is enabled
 * but the WP-WebAuthn provider plugin isn't installed/active.
 */
function sl_security_render_passkey_dependency_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $needs_provider = sl_security_setting_enabled('enable_passkey_login')
        || sl_security_setting_enabled('passkey_usernameless_verified');

    if (!$needs_provider || sl_security_passkey_provider_available()) {
        return;
    }

    $install_url = admin_url('plugin-install.php?s=wp-webauthn&tab=search&type=term');

    echo '<div class="notice notice-error"><p>';
    echo '<strong>Severino Labs Security Layer:</strong> The passkey login feature requires the ';
    echo '<a href="' . esc_url($install_url) . '">WP-WebAuthn</a> plugin. ';
    echo 'Install and activate it, or disable passkey login under ';
    echo '<a href="' . esc_url(admin_url('admin.php?page=' . SL_SECURITY_SETTINGS_SLUG)) . '">Severino Security &rarr; Settings</a>.';
    echo '</p></div>';
}
add_action('admin_notices', 'sl_security_render_passkey_dependency_notice');

class Joe_Passkey_Login {
    private $branding;
    private $success_redirect;

    public function __construct() {
        $this->branding = function_exists('sl_security_get_branding_settings')
            ? sl_security_get_branding_settings()
            : [
                'passkey_login_logo_url' => '',
                'passkey_login_site_label' => SL_SECURITY_BRAND_NAME,
            ];

        $this->success_redirect = admin_url();

        add_action('login_enqueue_scripts', [$this, 'remove_provider_login_assets'], 1);
        add_action('login_enqueue_scripts', [$this, 'enqueue_assets'], 999);

        add_filter('login_headerurl', [$this, 'header_url']);
        add_filter('login_headertext', [$this, 'header_text']);
        add_action('login_footer', [$this, 'render_custom_login'], 999);
    }

    public function remove_provider_login_assets() {
        global $wp_scripts, $wp_styles;

        if ($wp_scripts instanceof WP_Scripts) {
            foreach ($wp_scripts->registered as $handle => $script) {
                $src = isset($script->src) ? (string) $script->src : '';

                if (
                    stripos($handle, 'webauthn') !== false ||
                    stripos($handle, 'wwa') !== false ||
                    stripos($src, 'webauthn') !== false ||
                    stripos($src, 'wp-webauthn') !== false ||
                    stripos($src, 'wwa') !== false
                ) {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
            }
        }

        if ($wp_styles instanceof WP_Styles) {
            foreach ($wp_styles->registered as $handle => $style) {
                $src = isset($style->src) ? (string) $style->src : '';

                if (
                    stripos($handle, 'webauthn') !== false ||
                    stripos($handle, 'wwa') !== false ||
                    stripos($src, 'webauthn') !== false ||
                    stripos($src, 'wp-webauthn') !== false ||
                    stripos($src, 'wwa') !== false
                ) {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                }
            }
        }
    }

    public function header_url() {
        return home_url('/');
    }

    public function header_text() {
        return $this->branding['passkey_login_site_label'] ?: SL_SECURITY_BRAND_NAME;
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'sl-passkey-login',
            SL_SECURITY_PLUGIN_URL . 'assets/css/login.css',
            [],
            SL_SECURITY_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'sl-passkey-login',
            SL_SECURITY_PLUGIN_URL . 'assets/js/login.js',
            [],
            SL_SECURITY_PLUGIN_VERSION,
            true
        );

        wp_localize_script('sl-passkey-login', 'slPasskeyLogin', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'redirectUrl' => esc_url($this->success_redirect),
        ]);
    }

    public function render_custom_login() {
        $logo = esc_url($this->branding['passkey_login_logo_url']);
        $home = esc_url(home_url('/'));
        $site = esc_html($this->branding['passkey_login_site_label'] ?: SL_SECURITY_BRAND_NAME);

        echo '<div class="jp-card">';

        if (!empty($logo)) {
            echo '<div class="jp-logo-wrap"><img class="jp-logo" src="' . $logo . '" alt="' . $site . ' logo"></div>';
        } else {
            echo '<div class="jp-logo-wrap jp-logo-fallback"><span>' . $site . '</span></div>';
        }

        echo '<p class="jp-subtitle">Use your passkey to access the admin dashboard.</p>';
        echo '<button id="jp-passkey-btn" class="jp-passkey-btn" type="button">';
        echo '<span class="jp-icon" aria-hidden="true">';
        echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
        echo '<path d="M17 10C17 7.23858 14.7614 5 12 5C9.23858 5 7 7.23858 7 10V11H6C4.89543 11 4 11.8954 4 13V17C4 18.1046 4.89543 19 6 19H18C19.1046 19 20 18.1046 20 17V13C20 11.8954 19.1046 11 18 11H17V10ZM9 10C9 8.34315 10.3431 7 12 7C13.6569 7 15 8.34315 15 10V11H9V10ZM12 14C12.5523 14 13 14.4477 13 15V16C13 16.5523 12.5523 17 12 17C11.4477 17 11 16.5523 11 16V15C11 14.4477 11.4477 14 12 14Z" fill="#fff"/>';
        echo '</svg>';
        echo '</span>';
        echo 'Sign in with Passkey';
        echo '</button>';
        echo '<div id="jp-status" class="jp-status"></div>';
        echo '<div class="jp-footer-link"><a href="' . $home . '">← Back to site</a></div>';
        echo '</div>';
    }
}

add_action('plugins_loaded', function () {
    if (
        !sl_security_setting_enabled('enable_passkey_login') ||
        !sl_security_setting_enabled('passkey_usernameless_verified') ||
        !sl_security_passkey_provider_available()
    ) {
        return;
    }

    new Joe_Passkey_Login();
}, 20);