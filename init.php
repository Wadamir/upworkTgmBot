<?php

require_once __DIR__ . '/vendor/autoload.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$token = env('TOKEN', null);
if (!$token) {
    throw new ErrorException('Не указан токен бота');
} else {
    echo "Токен бота: $token" . PHP_EOL;
}

$url = "https://api.telegram.org/bot$token/sendMessage";

$getQuery = array(
    "chat_id"   => '-1001071760041',
    "text"      => "Не переживайте так... Все будет хорошо!",
    "parse_mode" => "html"
);
echo curl($url, $getQuery);

function curl($url, $data = [], $method = 'GET', $options = [])
{
    $default_options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ];

    if ($method === 'GET') {
        $url .= (strpos($url, '?') === false) ? '?' : '&';
        $url .= http_build_query($data);
    }
    if ($method === 'POST') {
        $options[CURLOPT_POSTFIELDS] = http_build_query($data);
    }
    if ($method === 'JSON') {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type:application/json';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array_replace($default_options, $options));

    $result = curl_exec($ch);
    if ($result === false) {
        throw new ErrorException("Curl error: " . curl_error($ch), curl_errno($ch));
    }
    curl_close($ch);
    return $result;
}


/**
 * @param $key
 * @param null $default
 *
 * @return mixed|null
 */
function env($key, $default = null)
{
    $value = $_ENV[$key] ?? null;

    if (!$value) {
        return $default;
    }

    return $value;
}
