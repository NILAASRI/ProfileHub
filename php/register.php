<?php
// ------------------- CORS FIX -------------------
header("Access-Control-Allow-Origin: https://profilehub-2.onrender.com");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Stop CORS preflight errors (browser sends OPTIONS before POST)
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
mysqli_ssl_set($mysqli, NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);

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
    echo json_encode(["status" => "error", "msg" => "MySQL connection failed"]);
    exit;
}

// ------------------- MONGODB CONNECTION -------------------
//require __DIR__ . '/../vendor/autoload.php';
use MongoDB\Client;

try {
    $mongo = new Client(getenv('MONGO_URI'));
    $profiles = $mongo->ProfileHub->profiles;
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "MongoDB connection failed"]);
    exit;
}

// ------------------- REDIS CONNECTION (OPTIONAL) -------------------
$redis = null;
try {
    $redisUrl = getenv('REDIS_URL');
    if ($redisUrl) {
        $p = parse_url($redisUrl);
        $redis = new Redis();
        $redis->connect($p['host'], $p['port']);
        if (isset($p['pass'])) $redis->auth($p['pass']);
    }
} catch (Exception $e) {
    error_log("Redis not available: " . $e->getMessage());
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
        if ($redis) {
            $sessionKey = "session:user:$userId";
            $redis->setex($sessionKey, 3600, json_encode([
                'userId' => $userId,
                'email' => $email,
                'created' => time()
            ]));
        }

        echo json_encode(["status" => "success", "msg" => "Registered successfully!"]);
    } catch (Exception $e) {
        $mysqli->query("DELETE FROM users WHERE id=$userId");
        echo json_encode(["status" => "error", "msg" => "Profile save failed"]);
    }
} else {
    echo json_encode(["status" => "error", "msg" => "Email already exists or MySQL error"]);
}

$stmt->close();
$mysqli->close();
?>
