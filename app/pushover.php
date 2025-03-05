<?php
include('init.php');
$config = array(
    'user_token' => getenv('PUSHOVER_USER_TOKEN') ?: 'default_user_token',
    'app_token' => getenv('PUSHOVER_APP_TOKEN') ?: 'default_app_token',
);

function pushover($message) {
    global $config;
    $user_token = 'uxoy2h2eun8yt6zidth5xd4m2xqfpv';
    $app_token = 'aojxz24jasosr3bojai1xyun976k5m';
    $url = "https://api.pushover.net/1/messages.json";

    $data = array(
        "token" => $app_token,
        "user" => $user_token,
        "message" => $message
    );

    $options = array(
        "http" => array(
            "method" => "POST",
            "header" => "Content-type: application/x-www-form-urlencoded\r\n",
            "content" => http_build_query($data)
        )
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    return $result;
}

?>
