<?php
/**
 * Plugin Name: Joe Passkey Login
 * Description: Custom branded WordPress login page with a username-less passkey button.
 * Version: 1.0.1
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
        wp_enqueue_script('jquery');

        $ajax_url = admin_url('admin-ajax.php');
        $redirect_url = esc_url($this->success_redirect);

        $css = <<<CSS
html, body {
    background: #f5f7fb !important;
}

html, body {
    background: #f5f7fb !important;
    height: 100%;
}

body.login {
    min-height: 100vh;
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

body.login #login {
    width: auto;
    max-width: none;
    padding: 0;
    position: static;
    margin: 0;
    z-index: 20;
}

body.login div#login {
    margin: 0;
}

body.login #login {
    width: 100%;
    max-width: 440px;
    padding: 0 20px;
}

body.login h1,
body.login #loginform,
body.login #nav,
body.login #backtoblog,
body.login .language-switcher {
    display: none !important;
}

.jp-card {
    width: min(440px, calc(100vw - 40px));
    margin: 0;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
    padding: 28px 28px 20px;
    box-sizing: border-box;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 20;
}

.jp-logo-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 14px;
}

.jp-logo {
    max-width: 72px;
    max-height: 72px;
    border-radius: 16px;
}

.jp-title {
    margin: 0;
    text-align: center;
    font-size: 28px;
    line-height: 1.15;
    font-weight: 700;
    color: #111827;
}

.jp-subtitle {
    margin: 8px auto 22px;
    text-align: center;
    font-size: 14px;
    line-height: 1.5;
    color: #6b7280;
    max-width: 300px;
}

.jp-passkey-btn {
    width: 100%;
    border: 0;
    border-radius: 14px;
    padding: 15px 18px;
    background: #111827;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.08s ease, opacity 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    box-shadow: 0 10px 24px rgba(17, 24, 39, 0.18);
    box-sizing: border-box;
}

.jp-passkey-btn:hover {
    background: #0f172a;
    box-shadow: 0 14px 30px rgba(17, 24, 39, 0.22);
}

.jp-passkey-btn:focus-visible {
    outline: 3px solid rgba(59, 130, 246, 0.35);
    outline-offset: 3px;
}

.jp-passkey-btn:active {
    transform: translateY(1px);
}

.jp-passkey-btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
    box-shadow: none;
}

.jp-passkey-btn:hover {
    opacity: 0.96;
}

.jp-passkey-btn:active {
    transform: translateY(1px);
}

.jp-passkey-btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
    box-shadow: none;
}

.jp-status {
    min-height: 22px;
    margin-top: 12px;
    font-size: 14px;
    text-align: center;
    color: #4b5563;
}

.jp-error {
    color: #b91c1c;
}

.jp-success {
    color: #166534;
}

.jp-footer-link {
    margin-top: 20px;
    text-align: center;
}

body.login .message,
body.login #login_error,
body.login .notice,
body.login .success {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translateX(-50%);
    width: min(520px, calc(100vw - 40px));
    margin: 0;
    z-index: 30;
    box-sizing: border-box;
}

.jp-footer-link a {
    color: #6b7280;
    text-decoration: none;
    font-size: 14px;
}

.jp-footer-link a:hover {
    color: #111827;
}

.jp-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    margin-right: 10px;
    vertical-align: middle;
    flex-shrink: 0;
}

.jp-icon svg {
    display: block;
    width: 18px;
    height: 18px;
}
CSS;

        wp_register_style('joe-passkey-login-inline', false);
        wp_enqueue_style('joe-passkey-login-inline');
        wp_add_inline_style('joe-passkey-login-inline', $css);

        $js = <<<JS
(function() {
    var ajaxUrl = __AJAX_URL__;
    var redirectUrl = __REDIRECT_URL__;

    function base64urlToBase64(input) {
        input = input.replace(/=/g, '').replace(/-/g, '+').replace(/_/g, '/');
        var pad = input.length % 4;
        if (pad) {
            if (pad === 1) {
                throw new Error('Invalid base64url string.');
            }
            input += new Array(5 - pad).join('=');
        }
        return input;
    }

    function arrayBufferToBase64(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    function setStatus(message, type) {
        var el = document.getElementById('jp-status');
        if (!el) return;
        el.textContent = message;
        el.className = 'jp-status' + (type ? ' jp-' + type : '');
    }

    async function startPasskeyLogin() {
        var button = document.getElementById('jp-passkey-btn');
        if (!button) return;

        button.disabled = true;
        setStatus('Starting passkey authentication...', '');

        try {
            var startUrl = ajaxUrl + '?action=wwa_auth_start&type=auth&usernameless=true';
            var startResponse = await fetch(startUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            var startText = await startResponse.text();
            var options;

            try {
                options = JSON.parse(startText);
            } catch (err) {
                throw new Error('Could not parse auth start response.');
            }

            if (!options || typeof options !== 'object') {
                throw new Error('Invalid auth options returned.');
            }

            if (!options.challenge) {
                throw new Error('Missing challenge in auth options.');
            }

            options.challenge = Uint8Array.from(
                window.atob(base64urlToBase64(options.challenge)),
                function(c) { return c.charCodeAt(0); }
            );

            if (Array.isArray(options.allowCredentials)) {
                options.allowCredentials = options.allowCredentials.map(function(item) {
                    item.id = Uint8Array.from(
                        window.atob(base64urlToBase64(item.id)),
                        function(c) { return c.charCodeAt(0); }
                    );
                    return item;
                });
            }

            var clientID = options.clientID;
            delete options.clientID;

            setStatus('Waiting for passkey prompt...', '');

            var credential = await navigator.credentials.get({
                publicKey: options
            });

            if (!credential) {
                throw new Error('No credential returned.');
            }

            var payload = {
                id: credential.id,
                type: credential.type,
                rawId: arrayBufferToBase64(credential.rawId),
                response: {
                    authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
                    clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
                    signature: arrayBufferToBase64(credential.response.signature),
                    userHandle: credential.response.userHandle
                        ? arrayBufferToBase64(credential.response.userHandle)
                        : null
                }
            };

            var formData = new URLSearchParams();
            formData.append('data', window.btoa(JSON.stringify(payload)));
            formData.append('type', 'auth');
            formData.append('remember', 'false');
            formData.append('clientid', clientID);

            setStatus('Verifying passkey...', '');

            var verifyResponse = await fetch(ajaxUrl + '?action=wwa_auth', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData.toString()
            });

            var verifyText = (await verifyResponse.text()).trim();

            if (verifyText === 'true') {
                setStatus('Authentication successful. Redirecting...', 'success');
                window.location.href = redirectUrl;
                return;
            }

            throw new Error('Authentication failed.');
        } catch (error) {
            console.warn(error);
            var message = error && error.message ? error.message : 'Passkey authentication failed.';
            setStatus(message, 'error');
        } finally {
            button.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var button = document.getElementById('jp-passkey-btn');
        if (!button) return;

        if (
            window.PublicKeyCredential === undefined ||
            navigator.credentials === undefined ||
            typeof navigator.credentials.get !== 'function'
        ) {
            button.disabled = true;
            setStatus('This browser does not support passkeys.', 'error');
            return;
        }

        button.addEventListener('click', function(e) {
            e.preventDefault();
            startPasskeyLogin();
        });
    });
})();
JS;

        $js = str_replace(
            array('__AJAX_URL__', '__REDIRECT_URL__'),
            array(
                wp_json_encode($ajax_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                wp_json_encode($redirect_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ),
            $js
        );

        wp_register_script('joe-passkey-login-inline', '', array('jquery'), '1.0.1', true);
        wp_enqueue_script('joe-passkey-login-inline');
        wp_add_inline_script('joe-passkey-login-inline', $js);
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
        <span class="jp-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 10C17 7.23858 14.7614 5 12 5C9.23858 5 7 7.23858 7 10V11H6C4.89543 11 4 11.8954 4 13V17C4 18.1046 4.89543 19 6 19H18C19.1046 19 20 18.1046 20 17V13C20 11.8954 19.1046 11 18 11H17V10ZM9 10C9 8.34315 10.3431 7 12 7C13.6569 7 15 8.34315 15 10V11H9V10ZM12 14C12.5523 14 13 14.4477 13 15V16C13 16.5523 12.5523 17 12 17C11.4477 17 11 16.5523 11 16V15C11 14.4477 11.4477 14 12 14Z" fill="currentColor"/></svg></span>Sign in with Passkey
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
