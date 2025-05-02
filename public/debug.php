<?php
header('Content-Type: application/json');
echo json_encode([
    'uri' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'get' => $_GET,
    'post' => $_POST,
    'server' => $_SERVER,
]);
