<?php
require_once __DIR__ . '/init.php';

$token = env('TOKEN', null);
if (!$token) {
    throw new ErrorException('Не указан токен бота');
} else {
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

$update = json_decode(file_get_contents("php://input"), TRUE);
if (!$update) {
    exit;
}

$chatId = $update["message"]["chat"]["id"];
$message = $update["message"]["text"];

if (strpos($message, "/start") === 0) {
    $location = substr($message, 9);
    file_get_contents($path . "/sendmessage?chat_id=" . $chatId . "&text=Here's the start message in ...");
}
