<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/init.php';
$log_dir = __DIR__ . '/logs';

file_put_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Start ' . PHP_EOL, FILE_APPEND);

$token = env('TOKEN', null);
if (!$token) {
    file_get_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Token not found' . PHP_EOL, FILE_APPEND);
    throw new ErrorException('Не указан токен бота');
} else {
    file_put_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Token found: ' . $token . PHP_EOL, FILE_APPEND);
    echo "Токен бота: $token" . PHP_EOL;
}

$url = "https://api.telegram.org/bot$token/sendMessage";

$getQuery = array(
    "chat_id"       => '-1001071760041',
    "text"          => "Не переживайте так... Все будет хорошо!",
    "parse_mode"    => "html"
);
echo curl($url, $getQuery);

$path = "https://api.telegram.org/bot$token";

$get_content = file_get_contents("php://input");
$update = json_decode($get_content, TRUE);
if (!$update) {
    exit;
}

file_put_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Received: ' . $get_content . PHP_EOL, FILE_APPEND);

$chatId = $update["message"]["chat"]["id"];
$message = $update["message"]["text"];

if (strpos($message, "/start") === 0) {
    $location = substr($message, 9);
    file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Send rss link to your channel");
} elseif (strpos($message, "https://www.upwork.com/") === 0) {
    file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Ok! I will send you updates from this channel");
} else {
    file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Try again, please");
}

function createUser()
{
    global $log_dir;

    $dbhost = env('MYSQL_HOST', 'localhost');
    $dbuser = env('MYSQL_USER', 'root');
    $dbpass = env('MYSQL_PASSWORD', '');
    $dbname = env('MYSQL_DB', 'telegram_bot');
    $table_users = env('MYSQL_TABLE_USERS', 'users');
    $table_data = env('MYSQL_TABLE_DATA', 'data');
    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', 'Connection failed' . PHP_EOL, FILE_APPEND);
        die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "INSERT INTO users (name, is_deleted, chat_id, link, refresh_time, last_update, date_added) VALUES ('', 0, '', '', 5, NOW(), NOW())";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $last_id = mysqli_insert_id($conn);
        file_put_contents($log_dir . '/start.log', "New record created successfully. Last inserted ID is: " . $last_id . PHP_EOL, FILE_APPEND);
        echo "New record created successfully. Last inserted ID is: " . $last_id . PHP_EOL;
    } else {
        file_put_contents($log_dir . '/start.log', "Error: " . $sql . PHP_EOL . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
        echo "Error: " . $sql . PHP_EOL . mysqli_error($conn) . PHP_EOL;
    }
    // Close connection
    file_put_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Close connection' . PHP_EOL . PHP_EOL, FILE_APPEND);
    mysqli_close($conn);
}
