<?php
header('Content-Type: application/json');

$host = getenv('MYSQL_HOST') ?: 'mysql-name-profilehub-mysql.l.aivencloud.com';
$user = getenv('MYSQL_USER') ?: '22362';
$pass = getenv('MYSQL_PASSWORD') ?: '';
$dbname = getenv('MYSQL_DB') ?: 'defaultdb';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}
?>
