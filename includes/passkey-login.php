<?php

if (!defined('ABSPATH')) {
    exit;
}

if (
    !sl_security_setting_enabled('enable_passkey_login') ||
    !sl_security_setting_enabled('passkey_usernameless_verified')
) {
    return;
}

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

        add_action('login_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('login_headerurl', [$this, 'header_url']);
        add_filter('login_headertext', [$this, 'header_text']);
        add_action('login_footer', [$this, 'render_custom_login']);
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

new Joe_Passkey_Login();