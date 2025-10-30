<?php
//require 'vendor/autoload.php';
try {
    $mongoClient = new MongoDB\Client("mongodb+srv://sriselliprt_db_user:Nilaa%402004@profilehub.yvxrns6.mongodb.net/?appName=ProfileHub");
    $mongoDB = $mongoClient->selectDatabase("profilehub");
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "MongoDB connection failed: " . $e->getMessage()]);
    exit;
}
?>
