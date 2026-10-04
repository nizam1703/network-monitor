<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'config.php belum tersedia',
        'message' => 'Salin config.example.php menjadi config.php lalu sesuaikan konfigurasi.'
    ], JSON_PRETTY_PRINT);
    exit;
}

$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Jakarta');

$db = $config['database'];
try {
    $pdo = new PDO(
        "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    exit;
}

$n8nWebhook = $config['n8n_webhook'] ?? '';
$hosts = $config['hosts'] ?? [];
$pingTimeout = (int)($config['ping_timeout_ms'] ?? 1000);

function sendToN8n($url, $data)
{
    if (empty($url)) return null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) error_log('n8n Webhook Error: ' . curl_error($ch));

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode >= 400) error_log('n8n Webhook HTTP Error: ' . $httpCode);

    curl_close($ch);
    return $response;
}

function formatDuration($seconds)
{
    $seconds = max(0, (int)$seconds);
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    $parts = [];
    if ($hours > 0) $parts[] = "$hours jam";
    if ($minutes > 0) $parts[] = "$minutes menit";
    $parts[] = "$secs detik";
    return implode(' ', $parts);
}

$result = [];

foreach ($hosts as $name => $host) {
    $output = shell_exec(
        'ping -n 1 -w ' . $pingTimeout . ' ' . escapeshellarg($host) . ' 2>&1'
    );

    $status = 'DOWN';
    $latency = null;

    if ($output !== null && preg_match('/(?:time|waktu)[=<]\\s*(\\d+)\\s*ms/i', $output, $match)) {
        $latency = intval($match[1]);
        if (strpos($match[0], '<') !== false) $latency = 1;
        $status = 'UP';
    } elseif ($output !== null && stripos($output, 'TTL=') !== false) {
        $status = 'UP';
        if (preg_match('/[=<]\\s*(\\d+)\\s*ms/i', $output, $match)) {
            $latency = intval($match[1]);
        }
    }

    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare('SELECT * FROM host_status WHERE hostname = ? LIMIT 1');
    $stmt->execute([$name]);
    $previous = $stmt->fetch();

    if (!$previous) {
        $stmt = $pdo->prepare(
            'INSERT INTO host_status
            (hostname, target, status, latency, last_change, last_check)
            VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $host, $status, $latency, $now, $now]);
    } else {
        $previousStatus = $previous['status'];

        if ($previousStatus === 'UP' && $status === 'DOWN') {
            $stmt = $pdo->prepare(
                "SELECT id FROM incidents
                 WHERE hostname = ? AND status = 'OPEN'
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$name]);
            $openIncident = $stmt->fetch();

            if (!$openIncident) {
                $stmt = $pdo->prepare(
                    "INSERT INTO incidents (hostname, target, down_at, status)
                     VALUES (?, ?, ?, 'OPEN')"
                );
                $stmt->execute([$name, $host, $now]);
            }

            sendToN8n($n8nWebhook, [
                'event' => 'INCIDENT',
                'host' => $name,
                'target' => $host,
                'previous_status' => 'UP',
                'status' => 'DOWN',
                'latency' => null,
                'detected_at' => $now
            ]);

            $stmt = $pdo->prepare(
                "UPDATE host_status
                 SET target = ?, status = 'DOWN', latency = NULL,
                     last_change = ?, last_check = ?
                 WHERE hostname = ?"
            );
            $stmt->execute([$host, $now, $now, $name]);
        }
        elseif ($previousStatus === 'DOWN' && $status === 'UP') {
            $stmt = $pdo->prepare(
                "SELECT * FROM incidents
                 WHERE hostname = ? AND status = 'OPEN'
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$name]);
            $incident = $stmt->fetch();

            if ($incident) {
                $downAt = $incident['down_at'];
                $durationSeconds = max(0, strtotime($now) - strtotime($downAt));
                $durationText = formatDuration($durationSeconds);

                $stmt = $pdo->prepare(
                    "UPDATE incidents
                     SET recovered_at = ?, duration_seconds = ?, status = 'CLOSED'
                     WHERE id = ?"
                );
                $stmt->execute([$now, $durationSeconds, $incident['id']]);

                sendToN8n($n8nWebhook, [
                    'event' => 'RECOVERY',
                    'host' => $name,
                    'target' => $host,
                    'previous_status' => 'DOWN',
                    'status' => 'UP',
                    'down_at' => $downAt,
                    'recovered_at' => $now,
                    'duration_seconds' => $durationSeconds,
                    'duration_text' => $durationText,
                    'latency' => $latency
                ]);
            }

            $stmt = $pdo->prepare(
                "UPDATE host_status
                 SET target = ?, status = 'UP', latency = ?,
                     last_change = ?, last_check = ?
                 WHERE hostname = ?"
            );
            $stmt->execute([$host, $latency, $now, $now, $name]);
        }
        else {
            $stmt = $pdo->prepare(
                'UPDATE host_status
                 SET target = ?, latency = ?, last_check = ?
                 WHERE hostname = ?'
            );
            $stmt->execute([$host, $latency, $now, $name]);
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ping_history
        (hostname, target, status, latency, checked_at)
        VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $host, $status, $latency, $now]);

    $result[$name] = [
        'host' => $host,
        'status' => $status,
        'latency' => $latency,
        'checked_at' => $now
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
