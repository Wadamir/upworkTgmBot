<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/init.php';
$log_dir = __DIR__ . '/logs';

file_put_contents($log_dir . '/start.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Start ', FILE_APPEND);

$token = env('TOKEN', null);
if (!$token) {
    file_put_contents($log_dir . '/start.log', ' | Token not found' . PHP_EOL, FILE_APPEND);
    die('Token not found');
}

// Todo move to api
$get_content = file_get_contents("php://input");
if (!$get_content) {
    exit;
}
$update = json_decode($get_content, TRUE);
// file_put_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Received: ' . $get_content . PHP_EOL, FILE_APPEND);

$command_data = '';
if (isset($update['message'])) {
    file_put_contents($log_dir . '/start.log', ' | Message: ' . $update['message']['text'], FILE_APPEND);
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

    $chat_type = 'message';
    $chatId = $update["message"]["chat"]["id"];
    $message = $update["message"]["text"];
    $message_type = $update["message"]["entities"][0]["type"];
} elseif (isset($update['callback_query'])) {
    file_put_contents($log_dir . '/start.log', ' | Callback: ' . $update['callback_query']['data'], FILE_APPEND);
    $user_data = [
        'user_id' => $update['callback_query']['from']['id'],
        'is_bot' => (isset($update['callback_query']['from']['is_bot']) && $update['messacallback_querye']['from']['is_bot'] !== 'false' && $update['callback_query']['from']['is_bot'] !== false) ? 1 : 0,
        'first_name' => (isset($update['callback_query']['from']['first_name']) && $update['callback_query']['from']['first_name'] !== '') ? $update['callback_query']['from']['first_name'] : null,
        'last_name' => (isset($update['callback_query']['from']['last_name']) && $update['callback_query']['from']['last_name'] !== '') ? $update['callback_query']['from']['last_name'] : null,
        'username' => $update['callback_query']['from']['username'],
        'language_code' => $update['callback_query']['from']['language_code'],
        'is_premium' => (isset($update['callback_query']['from']['is_premium']) && $update['callback_query']['from']['is_premium'] !== 'false' && $update['callback_query']['from']['is_premium'] !== false) ? 1 : 0,
        'chat_id' => $update['callback_query']['message']['chat']['id'],
        'text'  => $update['callback_query']['message']['text'],
    ];

    $chat_type = 'callback_query';
    $chatId = $update['callback_query']["message"]["chat"]["id"];
    $message = $update['callback_query']["message"]["text"];
    $message_type = $update['callback_query']["message"]["entities"][0]["type"];
    $command_data = $update['callback_query']['data'];
}

if ($chat_type === 'message' && $user_data['is_bot'] === 0 && $message_type === 'bot_command') {
    switch ($message) {
        case '/start':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /start', FILE_APPEND);
            try {
                $bot = new \TelegramBot\Api\BotApi($token);
                $user_result = createUser($user_data);
                if ($user_result === true) {
                    // Send message
                    $messageText = "Hello, " . $user_data['first_name'] . "!" . " Send here your RSS link to get updates from it.";
                    $messageResponse = $bot->sendMessage($chatId, $messageText);
                } else {
                    // Send message
                    $total_links = count($user_result);
                    $user_rss_links = [];
                    foreach ($user_result as $key => $value) {
                        $user_rss_links[] = $key + 1 . '. ' . $value['rss_link'];
                    }
                    $existing_links = implode("\n", $user_rss_links);
                    $messageText = "Hello, " . $user_data['first_name'] . "! You are already registered.\nYou have " . $total_links . " RSS links:\n" . $existing_links . "\nIf you want to add or remove your RSS links use menu.";
                    $messageResponse = $bot->sendMessage($chatId, $messageText);
                    file_put_contents($log_dir . '/start.log', ' | Existing links - ' . $existing_links, FILE_APPEND);
                }
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
            break;
        case '/stop':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /stop', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageText = "You are unsubscribed from bot updates.";
                $messageResponse = $bot->sendMessage($chatId, $messageText);
                deactivateUser($user_data['user_id']);
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
        case '/help':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /help', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageText = "You can get help here - https://wadamir.ru/upwork-tgm-bot/";
                $messageResponse = $bot->sendMessage($chatId, $messageText);
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
            break;
        case '/addrss':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /addrss', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageText = "Send here your RSS link to get updates from it";
                $messageResponse = $bot->sendMessage($chatId, $messageText);
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
            break;
        case '/removerss':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /removerss', FILE_APPEND);
            $existing_links = getRssLinksByUser($user_data['user_id']);
            if (count($existing_links) > 0) {
                try {
                    // Send message
                    $bot = new \TelegramBot\Api\BotApi($token);
                    $rss_links = array();
                    $buttons = array();
                    foreach ($existing_links as $key => $value) {
                        $rss_links[] = $key + 1 . '. ' . $value['rss_link'];
                        $callback = 'removerss_' . $value['id'];
                        $buttons[] = ['text' => ($key + 1), 'callback_data' => $callback];
                    }
                    $existing_links_string = implode("\n", $rss_links);
                    $messageText = "You have " . $total_links . " RSS links:\n" . $existing_links_string . "\nIf you want to remove your RSS link press the button.";

                    // Send message with inline keyboard
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            $buttons
                        ]
                    );
                    $bot->sendMessage($chatId, $messageText, null, false, null, $keyboard);
                } catch (Exception $e) {
                    file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
                }
            } else {
                try {
                    // Send message
                    $bot = new \TelegramBot\Api\BotApi($token);
                    $messageText = "You don't have any RSS links. Send here your RSS link to get updates from it";
                    $messageResponse = $bot->sendMessage($chatId, $messageText);
                } catch (Exception $e) {
                    file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
                }
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
            break;
        default:
            file_put_contents($log_dir . '/start.log', ' | Bot command - undefined', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageResponse = $bot->sendMessage($chatId, "Something went wrong. Try again later, please...");
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
    }
} elseif ($chat_type === 'message' && strpos($message, "https://www.upwork.com/") === 0 && $message_type === 'url') {
    file_put_contents($log_dir . '/start.log', ' | Add RSS link - ' . $message, FILE_APPEND);
    try {
        $bot = new \TelegramBot\Api\BotApi($token);
        $add_rss_link_response = addRssLink($user_data['user_id'], $user_data['text']);
        if ($add_rss_link_response) {
            // Send message
            $existing_links = implode("\n", $user_result);
            $messageText = "Ok, " . $user_data['first_name'] . "! I will send you updates from this channel.";
            $messageResponse = $bot->sendMessage($chatId, $messageText);
        } else {
            // Send message
            $messageText = "Sorry, " . $user_data['first_name'] . "! This RSS link is already added.";
            $messageResponse = $bot->sendMessage($chatId, $messageText);
        }
    } catch (Exception $e) {
        file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
    }
    file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
} elseif ($chat_type === 'callback_query' && strpos($command_data, "removerss") === 0) {
    file_put_contents($log_dir . '/start.log', ' | command_data - ' . $command_data, FILE_APPEND);
    try {
        $bot = new \TelegramBot\Api\BotApi($token);
        $rss_link_id = str_replace('removerss_', '', $command_data);
        $remove_rss_link_response = removeRssLink($user_data['user_id'], $rss_link_id);
        if ($remove_rss_link_response) {
            // Send message
            $messageText = "Ok, " . $user_data['first_name'] . "! This RSS chanel removed.";
            $messageResponse = $bot->sendMessage($chatId, $messageText);
        } else {
            // Send message
            $messageText = "Sorry, " . $user_data['first_name'] . "! This RSS chanel was deleted earlier or is missing..";
            $messageResponse = $bot->sendMessage($chatId, $messageText);
        }
    } catch (Exception $e) {
        file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
    }
    file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/start.log', ' | Bot command - undefined', FILE_APPEND);
    try {
        // Send message
        $bot = new \TelegramBot\Api\BotApi($token);
        $messageResponse = $bot->sendMessage($chatId, "Try again, please");
    } catch (Exception $e) {
        file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
    }
    file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
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
        activateUser($user_data['user_id']);

        $rss_links = getRssLinksByUser($user_data['user_id']);
        // Close connection
        mysqli_close($conn);
        return $rss_links;
    } else {
        // Insert user
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


function activateUser($user_id)
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
    $sql = "UPDATE $table_users SET is_deleted = NULL WHERE user_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }
    // Close connection
    mysqli_close($conn);

    return true;
}


function deactivateUser($user_id)
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
    $sql = "UPDATE $table_users SET is_deleted = 1 WHERE user_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }
    // Close connection
    mysqli_close($conn);

    return true;
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
    mysqli_close($conn);

    return $updateRssLinksResult;
}

function getRssLinksByUser($user_id)
{
    global $log_dir;

    file_put_contents($log_dir . '/start.log', ' | Get RSS Links By User' . PHP_EOL, FILE_APPEND);

    $dbhost = env('MYSQL_HOST', 'localhost');
    $dbuser = env('MYSQL_USER', 'root');
    $dbpass = env('MYSQL_PASSWORD', '');
    $dbname = env('MYSQL_DB', 'telegram_bot');
    $table_users = env('MYSQL_TABLE_USERS', 'users');
    $table_rss_links = env('MYSQL_TABLE_RSS_LINKS', 'rss_links');

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Get RSS Links By User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "SELECT * FROM $table_rss_links WHERE user_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    $rss_links = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rss_links[] = [
                'id'        => $row['id'],
                'rss_link'  => $row['rss_link'],
                'rss_name'  => $row['rss_name'],
            ];
        }
    }
    file_put_contents($log_dir . '/start.log', ' | RSS Links - ' . implode(', ', $rss_links), FILE_APPEND);
    // Close connection
    mysqli_close($conn);

    return $rss_links;
}

function addRssLink($user_id, $rss_link)
{
    global $log_dir;

    file_put_contents($log_dir . '/start.log', ' | Add RSS Link', FILE_APPEND);

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
        file_put_contents($log_dir . '/start.log', ' | RSS Link already exists', FILE_APPEND);
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


// function to remove rss link by user_id & rss_link id
function removeRssLink($user_id, $id)
{
    global $log_dir;

    file_put_contents($log_dir . '/start.log', ' | Remove RSS Link' . $id . ' where user id - ' . $user_id, FILE_APPEND);

    $dbhost = env('MYSQL_HOST', 'localhost');
    $dbhost = env('MYSQL_HOST', 'localhost');
    $dbuser = env('MYSQL_USER', 'root');
    $dbpass = env('MYSQL_PASSWORD', '');
    $dbname = env('MYSQL_DB', 'telegram_bot');
    $table_users = env('MYSQL_TABLE_USERS', 'users');
    $table_rss_links = env('MYSQL_TABLE_RSS_LINKS', 'rss_links');

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Remove RSS Link - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "DELETE FROM $table_rss_links WHERE id = $id AND user_id = $user_id";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new ErrorException("Error: " . $sql . ' | ' . mysqli_error($conn));
    }

    // Close connection
    mysqli_close($conn);

    return $result;
}
