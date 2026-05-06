<?php
/**
 * Plugin Name: Joe Passkey Login
 * Description: Custom branded WordPress login page with a username-less passkey button.
 * Version: 2.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Joe_Passkey_Login {
    private $logo_url;
    private $site_label;
    private $success_redirect;

    public function __construct() {
        $this->logo_url = 'https://jseverino.com/wp-content/uploads/sites/2/2025/08/cropped-JS-2.png';
        $this->site_label = 'Joe Severino';
        $this->success_redirect = admin_url();

        add_action('login_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('login_headerurl', array($this, 'header_url'));
        add_filter('login_headertext', array($this, 'header_text'));
        add_action('login_footer', array($this, 'render_custom_login'));
    }

    public function header_url() {
        return home_url('/');
    }

    public function header_text() {
        return $this->site_label;
    }

    public function enqueue_assets() {
        $plugin_url = plugin_dir_url(__FILE__);

        wp_enqueue_style(
            'joe-passkey-login',
            $plugin_url . 'assets/css/login.css',
            array(),
            '1.1.1'
        );

        wp_enqueue_script(
            'joe-passkey-login',
            $plugin_url . 'assets/js/login.js',
            array(),
            '1.1.1',
            true
        );

        wp_localize_script('joe-passkey-login', 'joePasskeyLogin', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'redirectUrl' => esc_url($this->success_redirect)
        ));
    }

    public function render_custom_login() {
        $logo = esc_url($this->logo_url);
        $home = esc_url(home_url('/'));
        $site = esc_html($this->site_label);

        echo <<<HTML

<div class="jp-card">
    <div class="jp-logo-wrap">
        <img class="jp-logo" src="{$logo}" alt="{$site} logo">
    </div>

    <p class="jp-subtitle">Use your passkey to access the admin dashboard.</p>

    <button id="jp-passkey-btn" class="jp-passkey-btn" type="button">
        <span class="jp-icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M17 10C17 7.23858 14.7614 5 12 5C9.23858 5 7 7.23858 7 10V11H6C4.89543 11 4 11.8954 4 13V17C4 18.1046 4.89543 19 6 19H18C19.1046 19 20 18.1046 20 17V13C20 11.8954 19.1046 11 18 11H17V10ZM9 10C9 8.34315 10.3431 7 12 7C13.6569 7 15 8.34315 15 10V11H9V10ZM12 14C12.5523 14 13 14.4477 13 15V16C13 16.5523 12.5523 17 12 17C11.4477 17 11 16.5523 11 16V15C11 14.4477 11.4477 14 12 14Z" fill="#fff"/>
            </svg>
        </span>
        Sign in with Passkey
    </button>

    <div id="jp-status" class="jp-status"></div>

    <div class="jp-footer-link">
        <a href="{$home}">← Back to site</a>
    </div>
</div>
HTML;
    }
}

new Joe_Passkey_Login();