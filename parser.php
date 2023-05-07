<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/init.php';
$log_dir = __DIR__ . '/logs';

file_put_contents($log_dir . '/parser.log', '[' . date('Y-m-d H:i:s') . '] Start ' . PHP_EOL, FILE_APPEND);

$dbhost = env('MYSQL_HOST', 'localhost');
$dbuser = env('MYSQL_USER', 'root');
$dbpass = env('MYSQL_PASSWORD', '');
$dbname = env('MYSQL_DB', 'telegram_bot');
$table_users = env('MYSQL_TABLE_USERS', 'users');
$table_data = env('MYSQL_TABLE_DATA', 'data');
// Create connection
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    file_put_contents($log_dir . '/parser.log', 'Connection failed' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}
// Check if user exists
$sql = "SELECT * FROM $table_users WHERE link IS NOT NULL";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    file_put_contents($log_dir . '/parser.log', 'Links exists!' . PHP_EOL, FILE_APPEND);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $link = $row['link'];
        $chat_id = $row['chat_id'];
        $refresh_time = $row['refresh_time'] * 60;
        $date_updated = $row['date_updated'];
        $date_added = $row['date_added'];
        $date_now = date('Y-m-d H:i:s');
        $date_diff = strtotime($date_now) - strtotime($date_updated);
        if ($date_diff > $refresh_time) {
            file_put_contents($log_dir . '/parser.log', 'Time to update!' . PHP_EOL, FILE_APPEND);

            $xml = simplexml_load_file($link);
            foreach ($xml->channel->item as $item) {
                // var_dump($item);
                $title = (string)$item->title;
                $link = (string)$item->link;
                $description = (string)$item->description[0];
                $posted_on = (string)$item->pubDate;
                $posted_on = date('Y-m-d H:i:s', strtotime($posted_on));

                // parse description
                $string = (string)$item->description[0];
                $matches = [];
                $pattern = '/(?<=<b>Category<\/b>: )[A-Za-z ]+/';
                if (preg_match($pattern, $string, $matches)) {
                    $category = $matches[0];
                } else {
                    $category = null;
                }
                $matches = [];
                $pattern = '/<b>Skills<\/b>:(.*?)<br \/>/s';
                if (preg_match($pattern, $string, $matches)) {
                    $skills = trim($matches[1]);
                    $skills = explode(',', $skills);
                    $skills = array_map('trim', $skills);
                    $skills = implode(', ', $skills);
                } else {
                    $skills = null;
                }
                $matches = [];
                $pattern = '/(?<=<b>Country<\/b>: )[A-Za-z ]+/';
                if (preg_match($pattern, $string, $matches)) {
                    $country = $matches[0];
                } else {
                    $country = null;
                }
                $matches = [];
                $pattern = '/<br\s*\/><b>Budget<\/b>:\s*\$([0-9,]+(?:\.[0-9]{2})?)/i';
                if (preg_match($pattern, $string, $matches)) {
                    $budget = $matches[1];
                    // remove comma and convert to int
                    $budget = (int)str_replace(',', '', $budget);
                } else {
                    $budget = null;
                }
                $matches = [];
                $pattern = '/<br \/><b>Hourly Range<\/b>: \$([\d,]+)\.([\d,]+)-\$([\d,]+)\.([\d,]+)/';
                if (preg_match($pattern, $string, $matches)) {
                    $hourly_min = (int)$matches[1];
                    $hourly_max = (int)$matches[3];
                } else {
                    $hourly_range = null;
                    $hourly_min = null;
                    $hourly_max = null;
                }
                echo $title . ' | ' . $link . ' | ' . $posted_on . ' | ' . $category . ' | ' . $skills . ' | ' . $country . ' | ' . $budget . ' | ' . $hourly_min . ' | ' . $hourly_max . PHP_EOL;
                echo PHP_EOL;

                // Check if data exists
                $sql = "SELECT * FROM $table_data WHERE link = '$link'";
                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) > 0) {
                    file_put_contents($log_dir . '/parser.log', 'Data exists!' . PHP_EOL, FILE_APPEND);
                } else {
                    file_put_contents($log_dir . '/parser.log', 'Data does not exist!' . PHP_EOL, FILE_APPEND);
                    // Insert data
                    $sql = "INSERT INTO $table_data (chat_id, title, link, posted_on, category, skills, country, budget, hourly_min, hourly_max) VALUES ('$chat_id', '$title', '$link', '$posted_on', '$category', '$skills', '$country', '$budget', '$hourly_min', '$hourly_max')";
                    if (mysqli_query($conn, $sql)) {
                        file_put_contents($log_dir . '/parser.log', 'Data inserted!' . PHP_EOL, FILE_APPEND);
                    } else {
                        file_put_contents($log_dir . '/parser.log', 'Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                    }
                }
            }

            
        } else {
            file_put_contents($log_dir . '/parser.log', 'Time to update: ' . $date_diff . PHP_EOL, FILE_APPEND);
        }
    }

    // Close connection
    mysqli_close($conn);
    return false;
}

$chatId = $update["message"]["chat"]["id"];
$message = $update["message"]["text"];
$message_type = $update["message"]["entities"][0]["type"];

if (strpos($message, "/start") === 0 && $message === '/start' && $user_data['is_bot'] === 0) {
    $user_result = createUser($user_data);
    if ($user_result) {
        file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Hello, " . $user_data['first_name'] . "!" . " Send rss link to your channel");
    } else {
        file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Hello, " . $user_data['first_name'] . "! You are already registered. If you want to change the channel, send me a new link");
    }
} elseif (strpos($message, "https://www.upwork.com/") === 0 && $message_type === 'url') {
    updateUser($user_data);
    file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Ok! I will send you updates from this channel");
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
        file_put_contents($log_dir . '/parser.log', 'Connection failed' . PHP_EOL, FILE_APPEND);
        die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    // Check if user exists
    $sql = "SELECT * FROM $table_users WHERE user_id = " . $user_data['user_id'];
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        file_put_contents($log_dir . '/parser.log', 'User already exists' . PHP_EOL, FILE_APPEND);
        // Close connection
        mysqli_close($conn);
        return false;
    } else {
        $columns = implode(", ", array_keys($user_data));
        $escaped_values = array_map(array($conn, 'real_escape_string'), array_values($user_data));
        $values  = implode("', '", $escaped_values);
        $sql = "INSERT INTO $table_users ($columns) VALUES ('$values')";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            file_put_contents($log_dir . '/parser.log', "Error: " . $sql . PHP_EOL . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
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
        file_put_contents($log_dir . '/parser.log', 'Connection failed' . PHP_EOL, FILE_APPEND);
        die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "UPDATE $table_users SET link = '" . $user_data['link'] . "' WHERE chat_id = " . $user_data['chat_id'];
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $last_id = mysqli_insert_id($conn);
        file_put_contents($log_dir . '/parser.log', "New record created successfully. Last inserted ID is: " . $last_id . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents($log_dir . '/parser.log', "Error: " . $sql . PHP_EOL . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
    }
    // Close connection
    file_put_contents($log_dir . '/parser.log', '[' . date('Y-m-d H:i:s') . '] Close connection' . PHP_EOL . PHP_EOL, FILE_APPEND);
    mysqli_close($conn);
}
