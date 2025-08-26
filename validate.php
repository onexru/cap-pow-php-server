<?php
header('Content-Type: application/json');
require_once __DIR__.'/cap.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$cap = new Cap();
$validate = $cap->validateToken($data['token'] ?? null);

echo json_encode($validate);