<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: https://guvi-intern-md3o.onrender.com");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// --- MySQL (Aiven) ---
$mysqli = mysqli_init();
mysqli_ssl_set($mysqli, NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);

if (!mysqli_real_connect(
    $mysqli,
    getenv("MYSQL_HOST"),
    getenv("MYSQL_USER"),
    getenv("MYSQL_PASSWORD"),
    getenv("MYSQL_DB"),
    3306,
    NULL,
    MYSQLI_CLIENT_SSL
)) {
    echo json_encode(["status" => "error", "msg" => "MySQL connection failed: " . mysqli_connect_error()]);
    exit;
}

// --- POST data ---
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "msg" => "Email and Password required"]);
    exit;
}

// --- Verify user ---
$stmt = $mysqli->prepare("SELECT id, password FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result && password_verify($password, $result['password'])) {
    $userId = $result['id'];
    $sessionId = bin2hex(random_bytes(16)); // 32-char token

    // --- Redis Cloud ---
    try {
        $redisUrl = getenv("REDIS_URL");
        $p = parse_url($redisUrl);
        $redis = new Redis();
        $redis->connect($p['host'], $p['port']);
        if (isset($p['pass'])) {
            $redis->auth($p['pass']);
        }

        // Store session for 1 hour
        $redis->setex($sessionId, 3600, $userId);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "msg" => "Redis failed: " . $e->getMessage()]);
        exit;
    }

    echo json_encode(["status" => "success", "sessionId" => $sessionId]);
} else {
    echo json_encode(["status" => "error", "msg" => "Invalid credentials"]);
}

$mysqli->close();
?>
