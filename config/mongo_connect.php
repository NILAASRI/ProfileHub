<?php
//require 'vendor/autoload.php';
try {
    $mongoClient = new MongoDB\Client("mongodb+srv://<username>:<password>@<cluster-url>/");
    $mongoDB = $mongoClient->selectDatabase("profilehub");
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "MongoDB connection failed: " . $e->getMessage()]);
    exit;
}
?>
