<?php
// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// // IMPORTANT: Uncomment this for production use
// if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
//     header("Location: login.php");
//     exit;
// }

// Configuration
$logFile = '../logs/scraping_logs.txt';
$refreshRate = 10; // Auto-refresh dashboard every 10 seconds

// Process logs with improved parsing
$logs = [];
if (file_exists($logFile)) {
    $file = fopen($logFile, 'r');
    if ($file) {
        while (($line = fgets($file)) !== false) {
            $parts = explode(' - ', $line, 4);
            if (count($parts) >= 3) {
                // Extract IP address if present
                $ip = '';
                if (preg_match('/IP: (\d+\.\d+\.\d+\.\d+)/', $parts[3] ?? '', $matches)) {
                    $ip = $matches[1];
                }
                
                $entry = [
                    'timestamp' => trim($parts[0]),
                    'level' => trim($parts[1]),
                    'message' => trim($parts[3] ?? ''),
                    'ip' => $ip,
                    'type' => 'other'
                ];

                // Categorize security logs
                if (strpos($entry['message'], 'Encryption error') !== false) {
                    $entry['type'] = 'encryption';
                } elseif (strpos($entry['message'], 'Decryption error') !== false) {
                    $entry['type'] = 'decryption';
                } elseif (strpos($entry['message'], 'Scraping attempt') !== false) {
                    $entry['type'] = 'scraping';
                } elseif (strpos($entry['message'], 'Screenshot blocked') !== false) {
                    $entry['type'] = 'screenshot';
                } elseif (strpos($entry['message'], 'Rate limit exceeded') !== false) {
                    $entry['type'] = 'rate_limit';
                } elseif ($entry['level'] === 'WARNING') {
                    $entry['type'] = 'warning';
                } elseif ($entry['level'] === 'ERROR') {
                    $entry['type'] = 'error';
                } else {
                    $entry['type'] = 'info';
                }

                $logs[] = $entry;
            }
        }
        fclose($file);
    }
}

// Most recent logs first
$logs = array_reverse($logs);

// Apply filters
$typeFilter = htmlspecialchars($_GET['type'] ?? 'all');
$searchFilter = htmlspecialchars($_GET['search'] ?? '');
$ipFilter = htmlspecialchars($_GET['ip'] ?? '');
$dateFilter = htmlspecialchars($_GET['date'] ?? '');

$filteredLogs = array_filter($logs, function($log) use ($typeFilter, $searchFilter, $ipFilter, $dateFilter) {
    $typeMatch = ($typeFilter === 'all' || $log['type'] === $typeFilter);
    $searchMatch = empty($searchFilter) || stripos($log['message'], $searchFilter) !== false;
    $ipMatch = empty($ipFilter) || stripos($log['ip'], $ipFilter) !== false;
    $dateMatch = empty($dateFilter) || stripos($log['timestamp'], $dateFilter) !== false;
    return $typeMatch && $searchMatch && $ipMatch && $dateMatch;
});

// Get unique IPs for dropdown
$uniqueIPs = array_unique(array_filter(array_column($logs, 'ip')));
sort($uniqueIPs);

// Get log statistics
$stats = [
    'total' => count($logs),
    'encryption' => count(array_filter($logs, fn($log) => $log['type'] === 'encryption')),
    'decryption' => count(array_filter($logs, fn($log) => $log['type'] === 'decryption')),
    'scraping' => count(array_filter($logs, fn($log) => $log['type'] === 'scraping')),
    'screenshot' => count(array_filter($logs, fn($log) => $log['type'] === 'screenshot')),
    'rate_limit' => count(array_filter($logs, fn($log) => $log['type'] === 'rate_limit')),
    'errors' => count(array_filter($logs, fn($log) => $log['level'] === 'ERROR')),
    'warnings' => count(array_filter($logs, fn($log) => $log['level'] === 'WARNING')),
];

// Get last update time of log file
$lastUpdate = file_exists($logFile) ? date("Y-m-d H:i:s", filemtime($logFile)) : 'Unknown';

// Real-time log viewing mode
$liveMode = isset($_GET['live']) && $_GET['live'] === 'true';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <?php if ($liveMode): ?>
    <meta http-equiv="refresh" content="<?= $refreshRate ?>">
    <?php endif; ?>
    <style>
        body { padding-top: 70px; }
        .log-item { padding: 10px; border-bottom: 1px solid #eee; }
        .log-item:hover { background: #f8f9fa; }
        .timestamp { color: #666; font-size: 0.9em; }
        .encryption { color: #2c7bb6; }
        .decryption { color: #d7191c; }
        .scraping { color: #fdae61; font-weight: bold; }
        .screenshot { color: #abdda4; }
        .rate_limit { color: #e6550d; font-weight: bold; }
        .warning { color: #fd8d3c; }
        .error { color: #bd0026; }
        .ip-address { font-family: monospace; }
        .log-count { font-size: 1.8rem; font-weight: bold; }
        .live-indicator { 
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #28a745;
            border-radius: 50%;
            margin-right: 5px;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0% { opacity: 0; }
            50% { opacity: 1; }
            100% { opacity: 0; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Security Dashboard</a>
            <div class="d-flex align-items-center">
                <?php if ($liveMode): ?>
                <span class="text-light me-3">
                    <span class="live-indicator"></span> Live Mode
                </span>
                <a href="?<?= http_build_query(array_merge($_GET, ['live' => 'false'])) ?>" class="btn btn-sm btn-outline-light me-2">Stop Live</a>
                <?php else: ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['live' => 'true'])) ?>" class="btn btn-sm btn-outline-light me-2">Live View</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Log Overview</h5>
                            <span class="text-muted small">Last log update: <?= $lastUpdate ?></span>
                        </div>
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-primary mb-2">Scraping Attempts</h6>
                                        <span class="log-count text-primary"><?= $stats['scraping'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-danger mb-2">Rate Limiting</h6>
                                        <span class="log-count text-danger"><?= $stats['rate_limit'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-success mb-2">Screenshot Blocks</h6>
                                        <span class="log-count text-success"><?= $stats['screenshot'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-warning mb-2">Error Events</h6>
                                        <span class="log-count text-warning"><?= $stats['errors'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <!-- Preserve live mode when filtering -->
                    <?php if ($liveMode): ?>
                        <input type="hidden" name="live" value="true">
                    <?php endif; ?>
                    
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Security Logs</option>
                            <option value="scraping" <?= $typeFilter === 'scraping' ? 'selected' : '' ?>>Scraping Attempts</option>
                            <option value="rate_limit" <?= $typeFilter === 'rate_limit' ? 'selected' : '' ?>>Rate Limiting</option>
                            <option value="screenshot" <?= $typeFilter === 'screenshot' ? 'selected' : '' ?>>Screenshot Blocks</option>
                            <option value="encryption" <?= $typeFilter === 'encryption' ? 'selected' : '' ?>>Encryption Events</option>
                            <option value="decryption" <?= $typeFilter === 'decryption' ? 'selected' : '' ?>>Decryption Events</option>
                            <option value="error" <?= $typeFilter === 'error' ? 'selected' : '' ?>>Errors</option>
                            <option value="warning" <?= $typeFilter === 'warning' ? 'selected' : '' ?>>Warnings</option>
                            <option value="info" <?= $typeFilter === 'info' ? 'selected' : '' ?>>Info Messages</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="ip" class="form-select">
                            <option value="">All IP Addresses</option>
                            <?php foreach ($uniqueIPs as $ip): ?>
                                <option value="<?= htmlspecialchars($ip) ?>" <?= $ipFilter === $ip ? 'selected' : '' ?>><?= htmlspecialchars($ip) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date" class="form-control" value="<?= $dateFilter ?>">
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search logs..." value="<?= $searchFilter ?>">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Display Section -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Security Logs</h5>
                    <span class="badge bg-primary"><?= count($filteredLogs) ?> logs found</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 180px;">Timestamp</th>
                                <th style="width: 100px;">Level</th>
                                <th style="width: 120px;">IP Address</th>
                                <th style="width: 120px;">Event Type</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filteredLogs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3">No log entries match your criteria</td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php foreach ($filteredLogs as $log): ?>
                            <tr class="log-item <?= $log['type'] ?>">
                                <td>
                                    <span class="timestamp"><?= htmlspecialchars($log['timestamp']) ?></span>
                                </td>
                                <td><span class="badge <?= strtolower($log['level']) === 'error' ? 'bg-danger' : (strtolower($log['level']) === 'warning' ? 'bg-warning text-dark' : 'bg-info text-dark') ?>"><?= $log['level'] ?></span></td>
                                <td class="ip-address"><?= empty($log['ip']) ? '-' : htmlspecialchars($log['ip']) ?></td>
                                <td><?= ucfirst($log['type']) ?></td>
                                <td><?= htmlspecialchars($log['message']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
