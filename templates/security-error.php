<?php

if (!defined('ABSPATH')) {
    exit;
}

$status_code = isset($args['response']) ? (int) $args['response'] : 500;

if ($status_code < 400 || $status_code > 599) {
    $status_code = 500;
}

$messages = [
    400 => [
        'title' => '400 Bad Request',
        'heading' => '400',
        'message' => 'The request could not be processed.',
    ],
    401 => [
        'title' => '401 Unauthorized',
        'heading' => '401',
        'message' => 'Authentication is required to access this resource.',
    ],
    403 => [
        'title' => '403 Forbidden',
        'heading' => '403',
        'message' => 'Access to this resource is denied.',
    ],
    404 => [
        'title' => '404 Not Found',
        'heading' => '404',
        'message' => 'The requested file could not be found.',
    ],
    500 => [
        'title' => '500 Server Error',
        'heading' => '500',
        'message' => 'The server encountered an error.',
    ],
];

$error = $messages[$status_code] ?? $messages[500];
$custom_message = trim(wp_strip_all_tags((string) ($message ?? '')));
$custom_message = preg_replace('/^\d{3}\s*/', '', $custom_message);
$custom_message = trim($custom_message);
if ($custom_message !== '') {
    $error['message'] = $custom_message;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="robots" content="noindex,nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo esc_html($error['title']); ?> | Joe Severino</title>

  <style>
    :root {
      --text: #08021f;
      --muted: #55515f;
      --border: #d8d8df;
      --background: #f7f7f8;
      --card: #ffffff;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--background);
      color: var(--text);
    }

    main {
      width: min(680px, calc(100% - 40px));
      padding: 48px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: 0 12px 40px rgba(8, 2, 31, 0.08);
      text-align: center;
    }

    .brand {
      margin: 0 0 32px;
      font-size: 18px;
      font-weight: 700;
      letter-spacing: -0.02em;
    }

    h1 {
      margin: 0 0 14px;
      font-size: clamp(52px, 10vw, 96px);
      line-height: 0.9;
      letter-spacing: -0.06em;
    }

    p {
      margin: 0 auto;
      max-width: 480px;
      font-size: 18px;
      line-height: 1.55;
      color: var(--muted);
    }

    .badge {
      display: inline-block;
      margin-top: 24px;
      padding: 6px 10px;
      border: 1px solid var(--border);
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      background: #fafafa;
    }

    @media (max-width: 560px) {
      main {
        width: min(100% - 28px, 680px);
        padding: 36px 28px;
      }

      .brand {
        margin-bottom: 28px;
      }

      p {
        font-size: 16px;
      }

      .badge {
        font-size: 12px;
      }
    }
  </style>
</head>

<body>
  <main>
    <div class="brand">Joe Severino</div>
    <h1><?php echo esc_html($error['heading']); ?></h1>
    <p><?php echo esc_html($error['message']); ?></p>
    <div class="badge">Protected by Severino Labs Security Layer</div>
  </main>
</body>
</html>