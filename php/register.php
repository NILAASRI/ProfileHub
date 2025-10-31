<?php
// ------------------- CORS FIX -------------------
header("Access-Control-Allow-Origin: https://profilehub-2.onrender.com"); // frontend domain
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ------------------- BASIC SETTINGS -------------------
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ------------------- MYSQL CONNECTION -------------------
$mysqli = mysqli_init();

// Use your Aiven CA certificate for secure connection
$caFile = '/var/www/html/ca.pem'; // Path to Aiven CA cert on Render
if (file_exists($caFile)) {
    mysqli_ssl_set($mysqli, NULL, NULL, $caFile, NULL, NULL);
} else {
    error_log("Aiven CA file not found at $caFile");
}

// Optional: connection timeout (in seconds)
mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 10);

$mysqlConnected = false;

if (!mysqli_real_connect(
    $mysqli,
    getenv('MYSQL_HOST'),
    getenv('MYSQL_USER'),
    getenv('MYSQL_PASSWORD'),
    getenv('MYSQL_DATABASE'),
    3306,
    NULL,
    MYSQLI_CLIENT_SSL
)) {
    echo json_encode(["status" => "error", "msg" => "❌ MySQL connection failed: " . mysqli_connect_error()]);
    exit;
} else {
    $mysqlConnected = true;
}

// ------------------- MONGODB CONNECTION -------------------
require_once __DIR__ . '/../vendor/autoload.php';
use MongoDB\Client;

$mongoConnected = false;
try {
    $mongo = new Client(getenv('MONGO_URI'));
    $profiles = $mongo->ProfileHub->profiles;
    $mongoConnected = true;
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "❌ MongoDB connection failed: " . $e->getMessage()]);
    exit;
}

// ------------------- REDIS CONNECTION -------------------
$redisConnected = false;
try {
    $redisUrl = getenv('REDIS_URL');
    if ($redisUrl) {
        $p = parse_url($redisUrl);
        $redis = new Redis();
        $redis->connect($p['host'], $p['port'], 2.5);
        if (isset($p['pass'])) $redis->auth($p['pass']);
        if ($redis->ping()) {
            $redisConnected = true;
        }
    }
} catch (Exception $e) {
    error_log("Redis not available: " . $e->getMessage());
}

// ------------------- CONNECTION CHECK MODE -------------------
if (isset($_GET['check'])) {
    echo json_encode([
        "status" => "ok",
        "mysql" => $mysqlConnected ? "✅ Connected" : "❌ Failed",
        "mongo" => $mongoConnected ? "✅ Connected" : "❌ Failed",
        "redis" => $redisConnected ? "✅ Connected" : "❌ Failed"
    ]);
    exit;
}

// ------------------- INPUT VALIDATION -------------------
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirmPassword'] ?? '';
$dob = $_POST['dob'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$age = intval($_POST['age'] ?? 0);
$address = trim($_POST['address'] ?? '');
$gender = trim($_POST['gender'] ?? '');

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "msg" => "Name, Email, and Password are required"]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "msg" => "Invalid email format"]);
    exit;
}
if ($password !== $confirm) {
    echo json_encode(["status" => "error", "msg" => "Passwords do not match"]);
    exit;
}

// ------------------- PASSWORD HASH -------------------
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// ------------------- MYSQL INSERT -------------------
$stmt = $mysqli->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
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
        if (isset($redis)) {
            $sessionKey = "session:user:$userId";
            $redis->setex($sessionKey, 3600, json_encode([
                'userId' => $userId,
                'email' => $email,
                'created' => time()
            ]));
        }

        echo json_encode(["status" => "success", "msg" => "✅ Registered successfully! Cloud connections verified."]);
    } catch (Exception $e) {
        $mysqli->query("DELETE FROM users WHERE id=$userId");
        echo json_encode(["status" => "error", "msg" => "MongoDB profile save failed: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "msg" => "Email already exists or MySQL error: " . $stmt->error]);
}

$stmt->close();
$mysqli->close();
?>
