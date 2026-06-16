<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://coinorchronicles.com');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'A valid scroll address is required.']);
    exit;
}

if (strlen($email) > 320) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'That scroll address is too long.']);
    exit;
}

require_once dirname(__DIR__) . '/includes/db.php';

try {
    $saved = save_subscriber($email);
    echo json_encode([
        'ok'      => true,
        'message' => $saved
            ? 'Welcome to the fellowship. The chronicle comes to you.'
            : 'You are already in the fellowship.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'The scribes encountered an error. Try again.']);
}
