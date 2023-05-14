<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/init.php';
$log_dir = __DIR__ . '/logs';

file_put_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Start ', FILE_APPEND);

$token = env('TOKEN', null);
if (!$token) {
    file_put_contents($log_dir . '/start.log', ' | Token not found', FILE_APPEND);
    throw new ErrorException('Не указан токен бота');
}

$path = "https://api.telegram.org/bot$token";

$get_content = file_get_contents("php://input");
if (!$get_content) {
    exit;
}
$update = json_decode($get_content, TRUE);

file_put_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Received: ' . $get_content . PHP_EOL, FILE_APPEND);

$user_data = [
    'user_id' => $update['message']['from']['id'],
    'is_bot' => (isset($update['message']['from']['is_bot']) && $update['message']['from']['is_bot'] !== 'false' && $update['message']['from']['is_bot'] !== false) ? 1 : 0,
    'first_name' => (isset($update['message']['from']['first_name']) && $update['message']['from']['first_name'] !== '') ? $update['message']['from']['first_name'] : null,
    'last_name' => (isset($update['message']['from']['last_name']) && $update['message']['from']['last_name'] !== '') ? $update['message']['from']['last_name'] : null,
    'username' => $update['message']['from']['username'],
    'language_code' => $update['message']['from']['language_code'],
    'is_premium' => (isset($update['message']['from']['is_premium']) && $update['message']['from']['is_premium'] !== 'false' && $update['message']['from']['is_premium'] !== false) ? 1 : 0,
    'chat_id' => $update['message']['chat']['id'],
    'text'  => $update['message']['text'],
];

$chatId = $update["message"]["chat"]["id"];
$message = $update["message"]["text"];
$message_type = $update["message"]["entities"][0]["type"];

if (strpos($message, "/start") === 0 && $message === '/start' && $user_data['is_bot'] === 0) {
    try {
        $user_result = createUser($user_data);
        if ($user_result) {
            file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Hello, " . $user_data['first_name'] . "!" . " Send here your rss link to get updates from it");
        } else {
            $user_result = json_decode($user_result, true);
            $existing_links = implode("/n", $user_result);
            file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Hello, " . $user_data['first_name'] . "! You are already registered. Your RSS link:/n" . $existing_links . "/nIf you want to add or remove your RSS links use menu.");
        }
    } catch (Exception $e) {
        file_put_contents($log_dir . '/start.log', ' | ' . $e->getMessage(), FILE_APPEND);
        file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Sorry, something went wrong. Try again later");
    }
} elseif (strpos($message, "https://www.upwork.com/") === 0 && $message_type === 'url') {
    try {
        $add_rss_link_response = addRssLink($user_data['user_id'], $user_data['text']);
        if ($add_rss_link_response) {
            file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Ok! I will send you updates from this channel");
        } else {
            file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Sorry, this RSS link is already exists");
        }
    } catch (Exception $e) {
        file_put_contents($log_dir . '/start.log', ' | ' . $e->getMessage(), FILE_APPEND);
        file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Sorry, something went wrong. Try again later");
    }
} else {
    file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Try again, please");
}

function createUser($user_data)
{
    global $log_dir;
    $dbhost = env('MYSQL_HOST', 'localhost');
    $dbuser = env('MYSQL_USER', 'root');
    $dbpass = env('MYSQL_PASSWORD', '');
    $dbname = env('MYSQL_DB', 'telegram_bot');
    $table_users = env('MYSQL_TABLE_USERS', 'users');
    $table_data = env('MYSQL_TABLE_DATA', 'data');
    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Connection failed', FILE_APPEND);
        throw new ErrorException("Connection failed: " . mysqli_connect_error());
    }
    // Check if user exists
    $sql = "SELECT * FROM $table_users WHERE user_id = " . $user_data['user_id'];
    $result = mysqli_query($conn, $sql);
    $rss_links = '';
    if (mysqli_num_rows($result) > 0) {
        file_put_contents($log_dir . '/start.log', ' | User already exists', FILE_APPEND);
        $rss_links = mysqli_fetch_assoc($result)['rss_links'];
        // Close connection
        mysqli_close($conn);
        return $rss_links;
    } else {
        // Insert user
        // $rss_links = json_encode($user_data['text']);
        // $user_data['rss_links'] = $rss_links;
        unset($user_data['text']);
        $columns = implode(", ", array_keys($user_data));
        $escaped_values = array_map(array($conn, 'real_escape_string'), array_values($user_data));
        $values  = implode("', '", $escaped_values);
        $sql = "INSERT INTO $table_users ($columns) VALUES ('$values')";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
            throw new ErrorException("Error: " . $sql . ' | ' . mysqli_error($conn));
        }
        // Close connection
        mysqli_close($conn);
        return true;
    }
}


function updateUser($user_data)
{
    global $log_dir;

    $dbhost = env('MYSQL_HOST', 'localhost');
    $dbuser = env('MYSQL_USER', 'root');
    $dbpass = env('MYSQL_PASSWORD', '');
    $dbname = env('MYSQL_DB', 'telegram_bot');
    $table_users = env('MYSQL_TABLE_USERS', 'users');
    $table_data = env('MYSQL_TABLE_DATA', 'data');
    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Update User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $updateRssLinksResult  = addRssLink($user_data['user_id'], $user_data['text']);

    // Close connection
    file_put_contents($log_dir . '/start.log', ' | [' . date('Y-m-d H:i:s') . '] Close connection' . PHP_EOL, FILE_APPEND);
    mysqli_close($conn);

    return $updateRssLinksResult;
}

function addRssLink($user_id, $rss_link)
{
    global $log_dir;

    file_put_contents($log_dir . '/start.log', ' | [' . date('Y-m-d H:i:s') . '] Add RSS Link' . PHP_EOL, FILE_APPEND);

    $dbhost = env('MYSQL_HOST', 'localhost');
    $dbuser = env('MYSQL_USER', 'root');
    $dbpass = env('MYSQL_PASSWORD', '');
    $dbname = env('MYSQL_DB', 'telegram_bot');
    $table_users = env('MYSQL_TABLE_USERS', 'users');
    $table_rss_links = env('MYSQL_TABLE_RSS_LINKS', 'rss_links');
    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Add RSS Link - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "SELECT * FROM $table_rss_links WHERE rss_link = '" . $rss_link . "'";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        file_put_contents($log_dir . '/start.log', ' | Rss link already exists', FILE_APPEND);
        mysqli_close($conn);
        return false;
    } else {
        $sql = "INSERT INTO $table_rss_links (user_id,rss_link) VALUES ('$user_id','$rss_link')";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
            throw new ErrorException("Error: " . $sql . ' | ' . mysqli_error($conn));
        }
    }

    // Close connection
    mysqli_close($conn);
    return true;
}
