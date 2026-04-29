<?php

if (!defined('ABSPATH')) {
    exit;
}

// File paths for baseline, event log, and status metadata.
define('SL_FIM_BASELINE_FILE', plugin_dir_path(__DIR__) . 'data/fim-baseline.json');
define('SL_FIM_LOG_FILE', plugin_dir_path(__DIR__) . 'data/fim-events.log');
define('SL_FIM_STATUS_FILE', plugin_dir_path(__DIR__) . 'data/fim-status.json');

// Return whether FIM is enabled by plugin settings or default to enabled.
function sl_fim_enabled() {
    return function_exists('sl_security_setting_enabled')
        ? sl_security_setting_enabled('enable_fim')
        : true;
}

// Return whether scheduled FIM checks are enabled by plugin settings.
function sl_fim_schedule_enabled() {
    return function_exists('sl_security_setting_enabled')
        ? sl_security_setting_enabled('enable_fim_schedule')
        : true;
}

// List files and directories that should be monitored by the integrity scanner.
function sl_fim_get_targets() {
    return function_exists('sl_security_get_fim_targets')
        ? sl_security_get_fim_targets()
        : [];
}

// File and directory path fragments that should not be included in monitoring.
function sl_fim_get_excluded_paths() {
    return function_exists('sl_security_get_fim_excluded_paths')
        ? sl_security_get_fim_excluded_paths()
        : [];
}

// Regular expressions used to determine whether a file path should be skipped.
function sl_fim_get_skip_patterns() {
    return [
        '/\/data\//',
        '/debug\.log$/',
        '/\.DS_Store$/',
        '/\/cache\//',
        '/\/litespeed\//',
        '/\/uploads\//',
        '/\/upgrade\//',
        '/\/upgrade-temp-backup\//',
        '/\/backups?\//',
        '/\/node_modules\//',
        '/\/vendor\//',
        '/\/\.git\//',
    ];
}

// Normalize Windows and Unix path separators for consistent pattern matching.
function sl_fim_normalize_path($path) {
    return str_replace('\\', '/', $path);
}

// Return true if this path should be excluded from monitoring.
function sl_fim_should_skip($path) {
    $normalized_path = sl_fim_normalize_path($path);

    foreach (sl_fim_get_skip_patterns() as $pattern) {
        if (preg_match($pattern, $normalized_path)) {
            return true;
        }
    }

    return false;
}

// Scan a directory recursively and return the list of files to include.
function sl_fim_collect_directory_files($directory) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();

        if ($file->isFile() && !sl_fim_should_skip($path)) {
            $files[] = $path;
        }
    }

    return $files;
}

// Build the full list of monitored files from target files and directories.
function sl_fim_collect_files() {
    $files = [];

    foreach (sl_fim_get_targets() as $target) {
        if (!file_exists($target)) {
            continue;
        }

        if (is_file($target) && !sl_fim_should_skip($target)) {
            $files[] = $target;
            continue;
        }

        if (is_dir($target)) {
            $files = array_merge($files, sl_fim_collect_directory_files($target));
        }
    }

    sort($files);

    return array_values(array_unique($files));
}

// Create a snapshot of current file hashes, sizes, and modified timestamps.
function sl_fim_create_snapshot() {
    $snapshot = [];

    foreach (sl_fim_collect_files() as $file) {
        if (!is_readable($file)) {
            continue;
        }

        $relative_path = str_replace(ABSPATH, '', $file);

        $snapshot[$relative_path] = [
            'hash' => hash_file('sha256', $file),
            'size' => filesize($file),
            'modified' => filemtime($file),
        ];
    }

    ksort($snapshot);

    return $snapshot;
}

// Write structured JSON data to a file with exclusive locking.
function sl_fim_write_json_file($file, $data) {
    file_put_contents(
        $file,
        wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

// Append a timestamped message to the FIM log file.
function sl_fim_write_log($message) {
    $timestamp = current_time('mysql');
    $line = '[' . $timestamp . '] ' . $message . PHP_EOL;

    file_put_contents(SL_FIM_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    sl_fim_trim_log();
}

// Keep the log file bounded by trimming older lines.
function sl_fim_trim_log($max_lines = 500) {
    if (!file_exists(SL_FIM_LOG_FILE)) {
        return;
    }

    $lines = file(SL_FIM_LOG_FILE, FILE_IGNORE_NEW_LINES);

    if (!is_array($lines) || count($lines) <= $max_lines) {
        return;
    }

    $trimmed_lines = array_slice($lines, -absint($max_lines));
    file_put_contents(SL_FIM_LOG_FILE, implode(PHP_EOL, $trimmed_lines) . PHP_EOL, LOCK_EX);
}

// Save the latest integrity status data to the status file.
function sl_fim_write_status($status) {
    $default_status = [
        'checked_at' => current_time('mysql'),
        'checked_timestamp' => time(),
        'status' => 'unknown',
        'message' => '',
        'added_count' => 0,
        'removed_count' => 0,
        'modified_count' => 0,
        'added' => [],
        'removed' => [],
        'modified' => [],
    ];

    $status = array_merge($default_status, $status);
    sl_fim_write_json_file(SL_FIM_STATUS_FILE, $status);
}

// Read JSON data from a file and return it as an array.
function sl_fim_read_json_file($file) {
    if (!file_exists($file)) {
        return null;
    }

    $data = json_decode(file_get_contents($file), true);

    return is_array($data) ? $data : null;
}

// Get the current saved FIM status.
function sl_fim_get_status() {
    return sl_fim_read_json_file(SL_FIM_STATUS_FILE);
}

// Create or refresh the trusted baseline snapshot.
function sl_fim_create_baseline() {
    if (!sl_fim_enabled()) {
        sl_fim_write_log('FIM is disabled. Baseline creation skipped.');
        return null;
    }

    $snapshot = sl_fim_create_snapshot();

    $data = [
        'created_at' => current_time('mysql'),
        'created_timestamp' => time(),
        'site_url' => home_url(),
        'file_count' => count($snapshot),
        'files' => $snapshot,
    ];

    sl_fim_write_json_file(SL_FIM_BASELINE_FILE, $data);
    sl_fim_write_log('Trusted baseline created with ' . count($snapshot) . ' monitored files.');

    sl_fim_write_status([
        'status' => 'baseline_created',
        'message' => 'Trusted baseline created.',
        'added_count' => 0,
        'removed_count' => 0,
        'modified_count' => 0,
        'added' => [],
        'removed' => [],
        'modified' => [],
    ]);

    return $data;
}

// Run an integrity check using the saved baseline and current snapshot.
function sl_fim_run_check() {
    if (!sl_fim_enabled()) {
        sl_fim_write_log('FIM is disabled. Integrity check skipped.');
        sl_fim_write_status([
            'status' => 'disabled',
            'message' => 'File Integrity Monitoring is disabled.',
        ]);
        return;
    }

    if (!file_exists(SL_FIM_BASELINE_FILE)) {
        sl_fim_write_log('No baseline found. Integrity check skipped.');
        sl_fim_write_status([
            'status' => 'no_baseline',
            'message' => 'No baseline found. Create a trusted baseline before running checks.',
        ]);
        return;
    }

    $baseline_data = sl_fim_read_json_file(SL_FIM_BASELINE_FILE);

    if (!is_array($baseline_data) || empty($baseline_data['files'])) {
        sl_fim_write_log('Invalid baseline file. Integrity check skipped.');
        sl_fim_write_status([
            'status' => 'invalid_baseline',
            'message' => 'Invalid baseline file. Integrity check skipped.',
        ]);
        return;
    }

    $baseline = $baseline_data['files'];
    $current = sl_fim_create_snapshot();

    $added = array_diff_key($current, $baseline);
    $removed = array_diff_key($baseline, $current);
    $modified = [];

    foreach ($current as $path => $details) {
        if (!isset($baseline[$path])) {
            continue;
        }

        if ($details['hash'] !== $baseline[$path]['hash']) {
            $modified[$path] = [
                'old_hash' => $baseline[$path]['hash'],
                'new_hash' => $details['hash'],
                'old_size' => $baseline[$path]['size'] ?? null,
                'new_size' => $details['size'] ?? null,
                'old_modified' => $baseline[$path]['modified'] ?? null,
                'new_modified' => $details['modified'] ?? null,
            ];
        }
    }

    $added_paths = array_keys($added);
    $removed_paths = array_keys($removed);
    $modified_paths = array_keys($modified);

    if (empty($added) && empty($removed) && empty($modified)) {
        sl_fim_write_log('Integrity check passed. No file changes detected.');
        sl_fim_write_status([
            'status' => 'passed',
            'message' => 'No file changes detected.',
            'added_count' => 0,
            'removed_count' => 0,
            'modified_count' => 0,
            'added' => [],
            'removed' => [],
            'modified' => [],
        ]);
        return;
    }

    sl_fim_write_log(
        'Integrity check detected changes. Added: ' . count($added) .
        ', Removed: ' . count($removed) .
        ', Modified: ' . count($modified) . '.'
    );

    foreach ($added_paths as $path) {
        sl_fim_write_log('ADDED: ' . $path);
    }

    foreach ($removed_paths as $path) {
        sl_fim_write_log('REMOVED: ' . $path);
    }

    foreach ($modified_paths as $path) {
        sl_fim_write_log('MODIFIED: ' . $path);
    }

    sl_fim_write_status([
        'status' => 'changes_detected',
        'message' => 'File changes detected. Review the change summary.',
        'added_count' => count($added),
        'removed_count' => count($removed),
        'modified_count' => count($modified),
        'added' => $added_paths,
        'removed' => $removed_paths,
        'modified' => $modified_paths,
    ]);

    if (function_exists('sl_security_smtp_alert_enabled') && sl_security_smtp_alert_enabled('alert_fim_changes')) {
        sl_fim_send_change_notification($added_paths, $removed_paths, $modified_paths);
    }
}

// Clear the event log file.
function sl_fim_clear_log() {
    file_put_contents(SL_FIM_LOG_FILE, '', LOCK_EX);
}

// Remove baseline and status files from disk.
function sl_fim_delete_baseline() {
    if (file_exists(SL_FIM_BASELINE_FILE)) {
        unlink(SL_FIM_BASELINE_FILE);
    }

    if (file_exists(SL_FIM_STATUS_FILE)) {
        unlink(SL_FIM_STATUS_FILE);
    }

    sl_fim_write_log('Trusted baseline deleted.');
}

// Send an HTML email alert when file changes are detected.
function sl_fim_send_change_notification($added, $removed, $modified) {
    $to = function_exists('sl_security_get_alert_email')
        ? sl_security_get_alert_email()
        : get_option('admin_email');

    if (!is_email($to)) {
        return;
    }

    $site_name      = get_bloginfo('name');
    $dashboard_url  = admin_url('admin.php?page=sl-security-fim');
    $added_count    = count($added);
    $removed_count  = count($removed);
    $modified_count = count($modified);
    $total          = $added_count + $removed_count + $modified_count;
    $checked_at     = function_exists('sl_security_format_datetime')
        ? sl_security_format_datetime(current_time('mysql'))
        : current_time('mysql');

    $subject = sprintf(
        '[%s] File Integrity Alert — %d file %s detected',
        $site_name,
        $total,
        $total === 1 ? 'change' : 'changes'
    );

    $preheader = sprintf('%d file %s detected on %s.', $total, $total === 1 ? 'change' : 'changes', $site_name);

    $sections = [];

    $sections[] = [
        'heading' => 'Summary',
        'rows' => [
            ['label' => 'Site',          'value' => $site_name],
            ['label' => 'Detected At',   'value' => $checked_at],
            ['label' => 'Files Added',   'value' => (string) $added_count],
            ['label' => 'Files Removed', 'value' => (string) $removed_count],
            ['label' => 'Files Modified','value' => (string) $modified_count],
        ],
    ];

    if ($added_count > 0) {
        $sections[] = [
            'type'    => 'file_list',
            'heading' => 'Added (' . $added_count . ')',
            'prefix'  => '+',
            'files'   => $added,
        ];
    }

    if ($removed_count > 0) {
        $sections[] = [
            'type'    => 'file_list',
            'heading' => 'Removed (' . $removed_count . ')',
            'prefix'  => '-',
            'files'   => $removed,
        ];
    }

    if ($modified_count > 0) {
        $sections[] = [
            'type'    => 'file_list',
            'heading' => 'Modified (' . $modified_count . ')',
            'prefix'  => '~',
            'files'   => $modified,
        ];
    }

    if (function_exists('sl_security_build_notification_email')) {
        $body = sl_security_build_notification_email(
            'File Integrity Alert',
            $preheader,
            $sections,
            $dashboard_url,
            'Review Changes'
        );
        $headers = ['Content-Type: text/html; charset=UTF-8'];
    } else {
        // Fallback plain text if builder is unavailable.
        $body    = $preheader . "\n\nReview changes: " . $dashboard_url;
        $headers = [];
    }

    wp_mail($to, $subject, $body, $headers);
}

// Schedule a daily FIM check if scheduling is enabled.
function sl_fim_schedule_event() {
    if (!sl_fim_enabled() || !sl_fim_schedule_enabled()) {
        return;
    }

    if (!wp_next_scheduled('sl_fim_daily_check')) {
        wp_schedule_event(time(), 'daily', 'sl_fim_daily_check');
    }
}

// Remove the scheduled daily FIM check hook.
function sl_fim_clear_event() {
    wp_clear_scheduled_hook('sl_fim_daily_check');
}

add_action('sl_fim_daily_check', 'sl_fim_run_check');
