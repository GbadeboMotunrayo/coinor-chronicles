<?php
/**
 * Coinor Chronicles — Web Pipeline Trigger
 * Kicks off the Python AI pipeline from a browser or cron URL.
 *
 * Access: /admin/run-pipeline.php?key=YOUR_SECRET_KEY
 * The key is the first 16 chars of SHA-256(ADMIN_PASSWORD).
 *
 * Add to Hostinger cron:
 *   0 0,6,12,18 * * *  wget -q -O /dev/null "https://coinorchronicles.com/admin/run-pipeline.php?key=YOUR_KEY"
 */
require_once __DIR__ . '/../includes/config.php';

define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: (defined('ADMIN_PASS') ? ADMIN_PASS : 'changeme123'));

$expected_key = substr(hash('sha256', ADMIN_PASSWORD), 0, 16);
$provided_key = $_GET['key'] ?? '';

if (!hash_equals($expected_key, $provided_key)) {
    http_response_code(403);
    exit('Forbidden');
}

// Determine the Python executable (Hostinger uses python3)
$python_bin = '/usr/bin/python3';
if (!file_exists($python_bin)) {
    $python_bin = shell_exec('which python3') ?: 'python3';
    $python_bin = trim($python_bin);
}

// Project root is two levels up from /website/admin/
$project_root = realpath(__DIR__ . '/../../');
$pipeline_script = $project_root . '/python_scripts/pipeline.py';
$log_dir  = $project_root . '/logs';
$log_file = $log_dir . '/pipeline.log';

// Create logs dir if needed
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

if (!file_exists($pipeline_script)) {
    http_response_code(500);
    exit('Pipeline script not found at: ' . $pipeline_script);
}

// Run the pipeline in background so the request returns immediately
$cmd = sprintf(
    'cd %s && %s python_scripts/pipeline.py >> %s 2>&1 &',
    escapeshellarg($project_root),
    escapeshellarg($python_bin),
    escapeshellarg($log_file)
);

shell_exec($cmd);

// Return JSON so it can be called programmatically
header('Content-Type: application/json');
echo json_encode([
    'ok'        => true,
    'message'   => 'Pipeline started. Check /logs/pipeline.log for output.',
    'started_at'=> gmdate('Y-m-d H:i:s') . ' UTC',
]);
