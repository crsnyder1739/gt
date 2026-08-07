<?php
// poll.php — Visitor browser polls this every 1.2s
require 'config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

session_start();
$sid = $_SESSION['visitor_id'] ?? '';
if (!$sid) { echo json_encode(['page' => 'loading']); exit; }

$s = get_state($sid);
echo json_encode([
    'page'    => $s['page']    ?? 'loading',
    'blocked' => $s['blocked'] ?? false,
]);
