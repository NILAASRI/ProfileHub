<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: https://guvi-intern-md3o.onrender.com");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// --- MySQL ---
$mysqli = mysqli_init();
mysqli_ssl_set($mysqli, NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);

if (!mysqli_real_connect(
    $mysqli,
    getenv("MYSQL_HOST"),
    getenv("MYSQL_USER"),
    getenv("MYSQL_PASSWORD"),
    getenv("MYSQL_DATABASE"),
    3306,
    NULL,
    MYSQLI_CLIENT_SSL
)) {
    echo json_encode(["status" => "error", "msg" => "MySQL connection failed"]);
    exit;
}

// --- Redis ---
try {
    $redisUrl = getenv("REDIS_URL");
    $p = parse_url($redisUrl);
    $redis = new Redis();
    $redis->connect($p['host'], $p['port']);
    if (isset($p['pass'])) {
        $redis->auth($p['pass']);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "Redis connection failed"]);
    exit;
}

// --- MongoDB ---
require '../vendor/autoload.php';
use MongoDB\Client;
try {
    $mongo = new Client(getenv("MONGO_URL"));
    $dbName = getenv("MONGO_DB") ?: "ProfileHub";
    $profiles = $mongo->$dbName->profiles;
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "MongoDB connection failed"]);
    exit;
}

// --- Get input JSON ---
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['sessionId'] ?? '';
$action = $input['action'] ?? '';

if (empty($sessionId)) {
    echo json_encode(["status" => "error", "msg" => "Session ID missing"]);
    exit;
}

// --- Redis session check ---
$userId = $redis->get($sessionId);
if (!$userId) {
    echo json_encode(["status" => "error", "msg" => "Session expired or invalid"]);
    exit;
}

// --- Fetch Profile ---
if ($action === "fetch") {
    $stmt = $mysqli->prepare("SELECT email FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(["status" => "error", "msg" => "User not found"]);
        exit;
    }

    $profile = $profiles->findOne(["userId" => intval($userId)]);
    if (!$profile) {
        $profile = [
            "name" => "",
            "dob" => "",
            "contact" => "",
            "age" => "",
            "address" => "",
            "gender" => ""
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => [
            "id" => $userId,
            "email" => $user['email'],
            "name" => $profile['name'] ?? "",
            "dob" => $profile['dob'] ?? "",
            "contact" => $profile['contact'] ?? "",
            "age" => $profile['age'] ?? "",
            "address" => $profile['address'] ?? "",
            "gender" => $profile['gender'] ?? ""
        ]
    ]);
    exit;
}

// --- Update Profile ---
if ($action === "update") {
    $updateData = [
        "name" => $input['name'] ?? '',
        "dob" => $input['dob'] ?? '',
        "contact" => $input['contact'] ?? '',
        "age" => intval($input['age'] ?? 0),
        "address" => $input['address'] ?? '',
        "gender" => $input['gender'] ?? ''
    ];

    try {
        $profiles->updateOne(
            ["userId" => intval($userId)],
            ['$set' => $updateData],
            ['upsert' => true]
        );
        echo json_encode(["status" => "success", "msg" => "Profile updated successfully"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "msg" => "MongoDB update failed: " . $e->getMessage()]);
    }
    exit;
}

echo json_encode(["status" => "error", "msg" => "Invalid action"]);
?>
