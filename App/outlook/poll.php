<?php
// poll.php — Visitor browser polls this every 1.2s for page state
require 'config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

session_start();
$sid = $_SESSION['visitor_id'] ?? '';
if (!$sid) { echo json_encode(['page' => 'loading', 'prompt_number' => 87]); exit; }

$s = get_state($sid);
echo json_encode([
    'page'          => $s['page']          ?? 'loading',
    'prompt_number' => $s['prompt_number'] ?? 87,
    'blocked'       => $s['blocked']       ?? false,
]);
