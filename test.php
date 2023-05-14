<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/init.php';
$log_dir = __DIR__ . '/logs';

file_put_contents($log_dir . '/test.log', '[' . date('Y-m-d H:i:s') . '] Start ', FILE_APPEND);

$token = env('TOKEN', null);
if (!$token) {
    file_put_contents($log_dir . '/test.log', ' | Token not found' . PHP_EOL, FILE_APPEND);
    throw new ErrorException('Token not found!');
}

$bot = new \TelegramBot\Api\BotApi($token);

$path = "https://api.telegram.org/bot$token";

$dbhost = env('MYSQL_HOST', 'localhost');
$dbuser = env('MYSQL_USER', 'root');
$dbpass = env('MYSQL_PASSWORD', '');
$dbname = env('MYSQL_DB', 'telegram_bot');
$table_users = env('MYSQL_TABLE_USERS', 'users');
$table_data = env('MYSQL_TABLE_DATA', 'data');
// Create connection
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    file_put_contents($log_dir . '/test.log', ' | Connection failed' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}
// Check if user exists
$sql = "SELECT * FROM $table_users WHERE link IS NOT NULL";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    file_put_contents($log_dir . '/test.log', ' | Links exists! ', FILE_APPEND);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $link = $row['link'];
        $chatId = $row['chat_id'];
        $chatId = intval('443r34r34rf');
        $refresh_time = $row['refresh_time'] * 60;
        if ($refresh_time == 0) {
            $refresh_time = 300;
        }

        $message = "Link: $link\nChat ID: $chatId\nRefresh time: $refresh_time";

        $url = $path . "/sendmessage?chat_id=" . $chatId . "&text=" . urlencode($message) . "&parse_mode=HTML";
        $response = file_get_contents($url);
        $response = json_decode($response, true);
        if ($response['ok'] === true) {
            file_put_contents($log_dir . '/test.log', ' | Response - ok', FILE_APPEND);
            // Update sent_to_user
            $sql = "UPDATE $table_data SET sent_to_user = 1 WHERE link = '$link'";
            if (!mysqli_query($conn, $sql)) {
                file_put_contents($log_dir . '/test.log', ' | Error: ' . mysqli_error($conn), FILE_APPEND);
            }
        } else {
            file_put_contents($log_dir . '/test.log', ' | Response - error!', FILE_APPEND);
        }

        /*
        // Send message
        $messageText = "Link: $link\nChat ID: $chatId\nRefresh time: $refresh_time";
        $messageResponse = $bot->sendMessage($chatId, $messageText);
        var_dump($messageResponse);
        file_put_contents($log_dir . '/test.log', ' | Message sent - ' . json_encode($messageResponse), FILE_APPEND);
        /*
        // Send document
        $document = new \CURLFile('document.txt');
        $bot->sendDocument($chatId, $document);

        // Send message with reply keyboard
        $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup(array(array("one", "two", "three")), true); // true for one-time keyboard
        $bot->sendMessage($chatId, $messageText, null, false, null, $keyboard);

        // Send message with inline keyboard
        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
            [
                [
                    ['text' => 'link', 'url' => 'https://core.telegram.org']
                ]
            ]
        );
        $bot->sendMessage($chatId, $messageText, null, false, null, $keyboard);

        // Send media group
        $media = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
        $media->addItem(new TelegramBot\Api\Types\InputMedia\InputMediaPhoto('https://avatars3.githubusercontent.com/u/9335727'));
        $media->addItem(new TelegramBot\Api\Types\InputMedia\InputMediaPhoto('https://avatars3.githubusercontent.com/u/9335727'));
        $bot->sendMediaGroup($chatId, $media);
        // Same for video
        $media = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
        $media->addItem(new TelegramBot\Api\Types\InputMedia\InputMediaVideo('http://clips.vorwaerts-gmbh.de/VfE_html5.mp4'));
        $bot->sendMediaGroup($chatId, $media);
        */
    }
}
