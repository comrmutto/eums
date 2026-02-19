<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => true]);
} else {
    echo json_encode(['valid' => false]);
}