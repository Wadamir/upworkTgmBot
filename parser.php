<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/init.php';
$log_dir = __DIR__ . '/logs';

file_put_contents($log_dir . '/parser.log', '[' . date('Y-m-d H:i:s') . '] Start ', FILE_APPEND);

$token = env('TOKEN', null);
if (!$token) {
    file_put_contents($log_dir . '/parser.log', ' | Token not found' . PHP_EOL, FILE_APPEND);
    throw new ErrorException('Не указан токен бота');
}

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
    file_put_contents($log_dir . '/parser.log', ' | Connection failed' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}
// Check if user exists
$sql = "SELECT * FROM $table_users WHERE link IS NOT NULL";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    file_put_contents($log_dir . '/parser.log', ' | Links exists! ', FILE_APPEND);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $link = $row['link'];
        $chat_id = $row['chat_id'];
        $refresh_time = $row['refresh_time'] * 60;
        if ($refresh_time == 0) {
            $refresh_time = 300;
        }
        // echo $refresh_time . PHP_EOL;
        $date_updated = $row['date_updated'];
        $date_added = $row['date_added'];
        $date_now = date('Y-m-d H:i:s');
        $date_diff = strtotime($date_now) - strtotime($date_updated);
        // echo $date_diff . PHP_EOL;
        if ($date_diff > $refresh_time) {

            file_put_contents($log_dir . '/parser.log', ' | Time to update!', FILE_APPEND);

            $xml = simplexml_load_file($link);
            foreach ($xml->channel->item as $item) {
                // var_dump($item);
                $title = (string)$item->title;
                if (strlen($title) > 255) $title = substr($title, 0, 250) . '...';
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
                    if (strlen($skills) > 255) {
                        $skills = substr($skills, 0, 250) . '...';
                    }
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
                    $budget = 0;
                }
                $matches = [];
                $pattern = '/<br \/><b>Hourly Range<\/b>: \$([\d,]+)\.([\d,]+)-\$([\d,]+)\.([\d,]+)/';
                if (preg_match($pattern, $string, $matches)) {
                    $hourly_min = (int)$matches[1];
                    $hourly_max = (int)$matches[3];
                } else {
                    $hourly_min = 0;
                    $hourly_max = 0;
                }
                // echo $title . ' | ' . $link . ' | ' . $posted_on . ' | ' . $category . ' | ' . $skills . ' | ' . $country . ' | ' . $budget . ' | ' . $hourly_min . ' | ' . $hourly_max . PHP_EOL;

                // Check if data exists
                $sql = "SELECT * FROM $table_data WHERE link = '$link'";
                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) === 0) {
                    // Escape strings
                    $chat_id = mysqli_real_escape_string($conn, $chat_id);
                    $title = mysqli_real_escape_string($conn, $title);
                    $link = mysqli_real_escape_string($conn, $link);
                    $posted_on = mysqli_real_escape_string($conn, $posted_on);
                    $category = mysqli_real_escape_string($conn, $category);
                    $skills = mysqli_real_escape_string($conn, $skills);
                    $country = mysqli_real_escape_string($conn, $country);
                    $budget = mysqli_real_escape_string($conn, $budget);
                    $hourly_min = mysqli_real_escape_string($conn, $hourly_min);
                    $hourly_max = mysqli_real_escape_string($conn, $hourly_max);
                    // Insert data
                    $sql = "INSERT INTO $table_data (chat_id, title, link, posted_on, category, skills, country, budget, hourly_min, hourly_max) VALUES ('$chat_id', '$title', '$link', '$posted_on', '$category', '$skills', '$country', '$budget', '$hourly_min', '$hourly_max')";
                    if (!mysqli_query($conn, $sql)) {
                        file_put_contents($log_dir . '/parser.log', ' | Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                    }
                }
            }
        } else {
            file_put_contents($log_dir . '/parser.log', ' | Time to update: ' . $date_diff, FILE_APPEND);
        }
    }
}

// Send messages to telegram
$sql = "SELECT * FROM $table_data WHERE sent_to_user = 0 OR sent_to_user IS NULL";
$result = mysqli_query($conn, $sql);
$counter = 0;
if (mysqli_num_rows($result) > 0) {
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $chat_id = $row['chat_id'];
        $title = $row['title'];
        $link = $row['link'];
        $posted_on = $row['posted_on'];
        $category = $row['category'];
        $skills = $row['skills'];
        $country = $row['country'];
        $budget = $row['budget'];
        $hourly_min = $row['hourly_min'];
        $hourly_max = $row['hourly_max'];

        $message = "<b>$title</b>\n";
        $message .= "<b>Category</b>: $category\n";
        $message .= "<b>Skills</b>: $skills\n";
        $message .= "<b>Country</b>: $country\n";
        if ($budget > 0) {
            $message .= "<b>Budget</b>: $budget\n";
        }
        if ($hourly_min > 0 && $hourly_max > 0) {
            $message .= "<b>Hourly Range</b>: $hourly_min - $hourly_max\n";
        }
        if ($budget == 0 && $hourly_min == 0 && $hourly_max == 0) {
            $message .= "<b>Budget</b>: No data!\n";
        }
        $message .= "<b>Posted on</b>: $posted_on\n";
        $message .= "<a href='$link'>Link</a>";

        $url = $path . "/sendmessage?chat_id=" . $chat_id . "&text=" . urlencode($message) . "&parse_mode=HTML";
        $response = file_get_contents($url);
        $response = json_decode($response, true);
        if ($response['ok'] === true) {
            file_put_contents($log_dir . '/parser.log', ' | Response - ok', FILE_APPEND);
            // Update sent_to_user
            $sql = "UPDATE $table_data SET sent_to_user = 1 WHERE link = '$link'";
            if (!mysqli_query($conn, $sql)) {
                file_put_contents($log_dir . '/parser.log', ' | Error: ' . mysqli_error($conn), FILE_APPEND);
            }
        } else {
            file_put_contents($log_dir . '/parser.log', ' | Response - error!', FILE_APPEND);
        }
        $counter++;
    }
}

file_put_contents($log_dir . '/parser.log', ' | Total messages sent: ' . $counter . PHP_EOL, FILE_APPEND);

mysqli_close($conn);
