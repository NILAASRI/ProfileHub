<?php
require 'vendor/autoload.php';

use Predis\Client;

try {
    $redis = new Client([
        'scheme' => 'tcp',
        'host'   => getenv('REDIS_HOST') ?: 'redis-19797.crce206.ap-south-1-1.ec2.redns.redis-cloud.com',
        'port'   => getenv('REDIS_PORT') ?: 19797, //6379
        'password' => getenv('REDIS_PASSWORD') ?: null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Redis connection failed"]);
    exit();
}
?>
