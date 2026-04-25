<?php

if (!defined('ABSPATH')) {
    exit;
}

define('SL_SEM_LOG_FILE', SL_SECURITY_PLUGIN_PATH . 'data/security-events.log');

function sl_sem_enabled() {
    return function_exists('sl_security_setting_enabled')
        ? sl_security_setting_enabled('enable_sem')
        : true;
}

function sl_sem_get_source_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }

    return 'unknown';
}

function sl_sem_get_request_uri() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = sanitize_text_field(wp_unslash($uri));

    return substr($uri, 0, 500);
}

function sl_sem_get_user_agent() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $user_agent = sanitize_text_field(wp_unslash($user_agent));

    return substr($user_agent, 0, 500);
}

function sl_sem_get_referer() {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $referer = sanitize_text_field(wp_unslash($referer));

    return substr($referer, 0, 500);
}

function sl_sem_clean_details($details) {
    if (!is_array($details)) {
        return [];
    }

    $clean = [];

    foreach ($details as $key => $value) {
        $clean_key = sanitize_key($key);

        if (is_scalar($value)) {
            $clean[$clean_key] = substr(sanitize_text_field((string) $value), 0, 500);
        }
    }

    return $clean;
}

function sl_sem_user_opted_out_from_logs() {
    return is_user_logged_in() && get_user_meta(get_current_user_id(), 'sl_security_exclude_from_sem', true) === '1';
}

function sl_sem_should_exclude_logged_in_user() {
    if (!function_exists('sl_security_setting_enabled')) {
        return false;
    }

    return (
        sl_security_setting_enabled('exclude_logged_in_users_from_sem') &&
        is_user_logged_in()
    ) || sl_sem_user_opted_out_from_logs();
}

function sl_sem_log_event($event_type, $details = []) {
    if (!sl_sem_enabled()) {
        return;
    }

    if (sl_sem_should_exclude_logged_in_user()) {
        return;
    }

    $event = [
        'timestamp' => current_time('mysql'),
        'event_type' => sanitize_key($event_type),
        'method' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'unknown')),
        'uri' => sl_sem_get_request_uri(),
        'source_ip' => sl_sem_get_source_ip(),
        'user_agent' => sl_sem_get_user_agent(),
        'referer' => sl_sem_get_referer(),
        'cf_country' => sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')),
        'cf_ray' => sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_RAY'] ?? '')),
        'user_id' => get_current_user_id(),
        'details' => sl_sem_clean_details($details),
    ];

    file_put_contents(
        SL_SEM_LOG_FILE,
        wp_json_encode($event, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    sl_sem_trim_log();
}

function sl_sem_trim_log($max_lines = 1000) {
    if (!file_exists(SL_SEM_LOG_FILE)) {
        return;
    }

    $lines = file(SL_SEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($lines) || count($lines) <= $max_lines) {
        return;
    }

    $trimmed_lines = array_slice($lines, -absint($max_lines));

    file_put_contents(SL_SEM_LOG_FILE, implode(PHP_EOL, $trimmed_lines) . PHP_EOL, LOCK_EX);
}

function sl_sem_get_recent_events($limit = 50) {
    if (!file_exists(SL_SEM_LOG_FILE)) {
        return [];
    }

    $lines = file(SL_SEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($lines)) {
        return [];
    }

    $lines = array_slice($lines, -absint($limit));
    $events = [];

    foreach (array_reverse($lines) as $line) {
        $event = json_decode($line, true);

        if (is_array($event)) {
            $events[] = $event;
        }
    }

    return $events;
}

function sl_sem_clear_log() {
    file_put_contents(SL_SEM_LOG_FILE, '', LOCK_EX);
}

function sl_sem_count_events_today() {
    $events = sl_sem_get_recent_events(1000);
    $today = current_time('Y-m-d');
    $count = 0;

    foreach ($events as $event) {
        if (!empty($event['timestamp']) && strpos($event['timestamp'], $today) === 0) {
            $count++;
        }
    }

    return $count;
}

function sl_sem_count_events_by_type($event_type) {
    $events = sl_sem_get_recent_events(1000);
    $count = 0;

    foreach ($events as $event) {
        if (($event['event_type'] ?? '') === $event_type) {
            $count++;
        }
    }

    return $count;
}

function sl_sem_count_total_events() {
    $events = sl_sem_get_recent_events(10000); // Get a large number to count total
    return count($events);
}