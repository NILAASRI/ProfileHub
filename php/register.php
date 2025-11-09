<?php
// ------------------- CORS -------------------
header("Access-Control-Allow-Origin: https://profilehub-2.onrender.com");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ------------------- MYSQL CONNECTION -------------------
$mysqli = mysqli_init();
mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 10);

// SSL Certificate (for Aiven or managed MySQL)
$caFile = '/var/www/html/ca.pem';
if (file_exists($caFile)) {
    mysqli_ssl_set($mysqli, NULL, NULL, $caFile, NULL, NULL);
} else {
    error_log("⚠️ CA certificate not found at $caFile");
}

// Read from environment
$host = getenv('MYSQL_HOST');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASSWORD');
$db   = getenv('MYSQL_DATABASE');
$port = getenv('MYSQL_PORT') ?: 3306;

if (!$host || !$user || !$pass || !$db) {
    echo json_encode(["status" => "error", "msg" => "❌ Missing MySQL environment variables"]);
    exit;
}

// Connect securely
if (!@mysqli_real_connect($mysqli, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    echo json_encode(["status" => "error", "msg" => "❌ MySQL connection failed: " . mysqli_connect_error()]);
    exit;
}

// ------------------- MONGODB CONNECTION -------------------
require_once __DIR__ . '/../vendor/autoload.php';
use MongoDB\Client;

try {
    $mongo = new Client(getenv('MONGO_URI'));
    $profiles = $mongo->ProfileHub->profiles;
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "❌ MongoDB connection failed: " . $e->getMessage()]);
    $mysqli->close();
    exit;
}

// ------------------- REDIS CONNECTION -------------------
$redis = null;
try {
    $redisUrl = getenv('REDIS_URL');
    if ($redisUrl) {
        $p = parse_url($redisUrl);
        $redis = new Redis();
        $redis->connect($p['host'], $p['port'], 2.5);
        if (isset($p['pass'])) $redis->auth($p['pass']);
        $redis->ping(); // test connection
    }
} catch (Exception $e) {
    error_log("⚠️ Redis not available: " . $e->getMessage());
}

// ------------------- CONNECTION TEST ENDPOINT -------------------
if (isset($_GET['check'])) {
    echo json_encode([
        "status" => "ok",
        "mysql" => "✅ Connected",
        "mongo" => isset($mongo) ? "✅ Connected" : "❌ Failed",
        "redis" => $redis ? "✅ Connected" : "⚠️ Not available"
    ]);
    $mysqli->close();
    exit;
}

// ------------------- INPUT VALIDATION -------------------
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirmPassword'] ?? '';
$dob      = $_POST['dob'] ?? '';
$phone    = trim($_POST['phone'] ?? '');
$age      = intval($_POST['age'] ?? 0);
$address  = trim($_POST['address'] ?? '');
$gender   = trim($_POST['gender'] ?? '');

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "msg" => "⚠️ Name, Email, and Password are required"]);
    $mysqli->close();
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "msg" => "⚠️ Invalid email format"]);
    $mysqli->close();
    exit;
}
if ($password !== $confirm) {
    echo json_encode(["status" => "error", "msg" => "⚠️ Passwords do not match"]);
    $mysqli->close();
    exit;
}

// ------------------- PASSWORD HASH -------------------
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// ------------------- MYSQL INSERT -------------------
$stmt = $mysqli->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
if (!$stmt) {
    echo json_encode(["status" => "error", "msg" => "❌ MySQL prepare failed: " . $mysqli->error]);
    $mysqli->close();
    exit;
}

$stmt->bind_param("ss", $email, $hashedPassword);

if ($stmt->execute()) {
    $userId = $stmt->insert_id;

    try {
        // ------------------- MONGO PROFILE INSERT -------------------
        $profiles->insertOne([
            "userId" => $userId,
            "name" => $name,
            "email" => $email,
            "dob" => $dob,
            "contact" => $phone,
            "age" => $age,
            "address" => $address,
            "gender" => $gender,
            "created_at" => new MongoDB\BSON\UTCDateTime()
        ]);

        // ------------------- REDIS SESSION CACHE -------------------
        if ($redis) {
            $sessionKey = "session:user:$userId";
            $redis->setex($sessionKey, 3600, json_encode([
                'userId' => $userId,
                'email' => $email,
                'created' => time()
            ]));
        }

        echo json_encode(["status" => "success", "msg" => "✅ Registered successfully!"]);
    } catch (Exception $e) {
        $mysqli->query("DELETE FROM users WHERE id=$userId");
        echo json_encode(["status" => "error", "msg" => "❌ MongoDB save failed: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "msg" => "❌ MySQL insert failed: " . $stmt->error]);
}

$stmt->close();
$mysqli->close();
?>
