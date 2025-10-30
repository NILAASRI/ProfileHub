<?php
// --- Set JSON response type ---
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- CORS Configuration ---
$allowed_origins = ["https://guvi-intern-md3o.onrender.com"]; // frontend domain(s)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    // Optionally, block or allow temporarily for testing
    header("Access-Control-Allow-Origin: https://guvi-intern-md3o.onrender.com");
}

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// --- Handle preflight request ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- MySQL (Aiven Cloud) ---
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
    echo json_encode(["status" => "error", "msg" => "MySQL connection failed: " . mysqli_connect_error()]);
    exit;
}

// --- MongoDB (Atlas) ---
require __DIR__ . '/../vendor/autoload.php';
use MongoDB\Client;

try {
    $mongo = new Client(getenv('MONGO_URI'));
    $profiles = $mongo->ProfileHub->profiles;
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "MongoDB connection failed: " . $e->getMessage()]);
    exit;
}

// --- Redis (Optional) ---
$redis = null;
try {
    $redisUrl = getenv('REDIS_URL');
    if ($redisUrl) {
        $p = parse_url($redisUrl);
        $redis = new Redis();
        $redis->connect($p['host'], $p['port']);
        if (isset($p['pass'])) {
            $redis->auth($p['pass']);
        }
    }
} catch (Exception $e) {
    error_log("Redis not available: " . $e->getMessage());
}

// --- Collect POST data ---
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirmPassword'] ?? '';
$dob = $_POST['dob'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$age = intval($_POST['age'] ?? 0);
$address = trim($_POST['address'] ?? '');
$gender = trim($_POST['gender'] ?? '');

// --- Validation ---
if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "msg" => "Name, Email, and Password required"]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "msg" => "Invalid email"]);
    exit;
}
if ($password !== $confirm) {
    echo json_encode(["status" => "error", "msg" => "Passwords do not match"]);
    exit;
}

// --- Hash Password ---
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// --- Insert into MySQL ---
$stmt = $mysqli->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
$stmt->bind_param("ss", $email, $hashedPassword);

if ($stmt->execute()) {
    $userId = $stmt->insert_id;

    // --- Insert profile into MongoDB ---
    try {
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

        // --- Redis Cache session (optional) ---
        if ($redis) {
            $sessionKey = "session:user:$userId";
            $redis->setex($sessionKey, 3600, json_encode([
                'userId' => $userId,
                'email' => $email,
                'created' => time()
            ]));
        }

        echo json_encode(["status" => "success", "msg" => "Registered successfully"]);
    } catch (Exception $e) {
        $mysqli->query("DELETE FROM users WHERE id=$userId");
        echo json_encode(["status" => "error", "msg" => "MongoDB insert failed: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "msg" => "Email already exists or MySQL error"]);
}

$stmt->close();
$mysqli->close();
?>
