<?php
header('Content-Type: application/json');
require_once __DIR__.'/cap.php';

// 兑换挑战
$cap = new Cap();
$input = file_get_contents('php://input');
$req = json_decode($input, true);
$result = $cap->redeemChallenge($req);
echo json_encode($result);