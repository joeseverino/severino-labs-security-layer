<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disable XML-RPC.
 */
if (sl_security_setting_enabled('disable_xmlrpc')) {
    add_filter('xmlrpc_enabled', '__return_false', 999);
}

/**
 * Disable XML-RPC pingbacks.
 */
if (sl_security_setting_enabled('disable_pingbacks')) {
    add_filter('xmlrpc_methods', function ($methods) {
        unset($methods['pingback.ping']);
        return $methods;
    });
}

/**
 * Whether the current request is coming from a logged-in administrator.
 *
 * Admins legitimately need to read the user list (via Gutenberg, the user
 * directory, profile editors, etc.) and view author archives. The REST and
 * author-enumeration blockers below skip these requests.
 */
function sl_security_request_is_admin() {
    return is_user_logged_in() && current_user_can('list_users');
}

/**
 * Block REST API user enumeration.
 *
 * Admin requests bypass this so the block editor and user-management screens
 * keep working; everyone else gets 401/empty results and an event log entry.
 */
if (sl_security_setting_enabled('block_rest_users')) {
    add_filter('rest_endpoints', function ($endpoints) {
        if (sl_security_request_is_admin()) {
            return $endpoints;
        }

        unset($endpoints['/wp/v2/users']);
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        unset($endpoints['/wp/v2/users/me']);

        return $endpoints;
    });

    add_action('init', function () {
        if (sl_security_request_is_admin()) {
            return;
        }

        $request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        if (
            $request_path === '/wp-json/wp/v2/users' ||
            strpos($request_path, '/wp-json/wp/v2/users/') === 0
        ) {
            if (function_exists('sl_sem_log_event')) {
                sl_sem_log_event('rest_user_enumeration_attempt', [
                    'reason' => 'REST API user endpoint requested',
                ]);
            }
        }
    });
}

/**
 * Block author enumeration.
 *
 * Admin requests bypass this so legitimate author archive previews and
 * author-based queries from the admin area continue to work.
 */
if (sl_security_setting_enabled('block_author_enum')) {
    add_action('template_redirect', function () {
        if (sl_security_request_is_admin()) {
            return;
        }

        if (is_author() || (isset($_GET['author']) && is_numeric($_GET['author']))) {
            if (function_exists('sl_sem_log_event')) {
                sl_sem_log_event('author_enumeration_blocked', [
                    'reason' => 'Author archive or numeric author query blocked',
                ]);
            }

            wp_redirect(home_url(), 301);
            exit;
        }
    });
}

/**
 * Remove WordPress version generator tag.
 */
if (sl_security_setting_enabled('remove_wp_generator')) {
    remove_action('wp_head', 'wp_generator');
}

/**
 * Send browser security headers and CSP.
 */
add_action('send_headers', function () {
    if (sl_security_setting_enabled('enable_security_headers')) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    }

    if (sl_security_setting_enabled('enable_csp')) {
        header(
            "Content-Security-Policy: "
            . "default-src 'self'; "
            . "script-src 'self' https://challenges.cloudflare.com https://www.googletagmanager.com https://static.cloudflareinsights.com 'unsafe-inline' blob:; "
            . "style-src 'self' https: 'unsafe-inline'; "
            . "img-src 'self' data: https:; "
            . "font-src 'self' https:; "
            . "connect-src 'self' https://challenges.cloudflare.com https://www.google-analytics.com https://cloudflareinsights.com; "
            . "frame-src 'self' https://challenges.cloudflare.com; "
            . "worker-src 'self' blob:; "
            . "frame-ancestors 'self'; "
            . "form-action 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self';"
        );
    }
});

/**
 * Block unused public WordPress endpoints.
 */
if (sl_security_setting_enabled('block_unused_endpoints')) {
    add_action('init', function () {
        $request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        $blocked_paths = [
            '/xmlrpc.php',
            '/wp-signup.php',
            '/wp-activate.php',
            '/wp-trackback.php',
            '/wp-links-opml.php',
        ];

        if (in_array($request_path, $blocked_paths, true)) {
            if (function_exists('sl_sem_log_event')) {
                sl_sem_log_event(
                    $request_path === '/xmlrpc.php' ? 'xmlrpc_blocked' : 'blocked_unused_endpoint',
                    [
                        'blocked_path' => $request_path,
                        'reason' => 'Unused public WordPress endpoint blocked',
                    ]
                );
            }

            status_header(403);
            nocache_headers();

            wp_die(
                'Access to this resource is denied.',
                '403 Forbidden',
                ['response' => 403]
            );
        }
    });
}

/**
 * Custom security error page.
 */
if (sl_security_setting_enabled('custom_error_page')) {
    add_filter('wp_die_handler', function () {
        return 'sl_security_render_wp_die_page';
    });
}

function sl_security_render_wp_die_page($message, $title = '', $args = []) {
    $args = wp_parse_args($args, [
        'response' => 500,
    ]);

    $status_code = (int) $args['response'];

    if ($status_code < 400 || $status_code > 599) {
        $status_code = 500;
    }

    status_header($status_code);
    nocache_headers();

    if (
        function_exists('sl_sem_log_event') &&
        function_exists('sl_security_setting_enabled') &&
        sl_security_setting_enabled('enable_sem_403_logging')
    ) {
        sl_sem_log_event('security_error_rendered', [
            'status_code' => $status_code,
            'title' => is_scalar($title) ? $title : '',
        ]);
    }

    require SL_SECURITY_PLUGIN_PATH . 'templates/security-error.php';
    exit;
}