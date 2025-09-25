<?php
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    echo json_encode([
        'loggedIn' => true,
        'user' => $_SESSION['user'],
        'is_master' => isset($_SESSION['user']['is_master']) ? $_SESSION['user']['is_master'] : false
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
