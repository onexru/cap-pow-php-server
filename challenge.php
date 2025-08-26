<?php
header('Content-Type: application/json');
require_once __DIR__.'/cap.php';

// 挑战配置 - 修正参数名称
$cap = new Cap();
$pow_data = $cap->createChallenge();
echo json_encode($pow_data);