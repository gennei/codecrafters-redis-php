<?php
error_reporting(E_ALL);
const PORT = 6379;

// You can use print statements as follows for debugging, they'll be visible when running tests.
echo "Logs from your program will appear here" . PHP_EOL;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($sock, SOL_SOCKET, SO_REUSEPORT, 1);
socket_bind($sock, "localhost", PORT);
socket_listen($sock, 5);
$socket = socket_accept($sock);

while (true) {
    $message = socket_read($socket, 2048, PHP_NORMAL_READ);
    $message = trim($message);
    if ($message === "") {
        continue;
    }
    if ($message === "exit") {
        $res = "👋👋👋\r\n";
        socket_write($socket, $res, strlen($res));
        break;
    }
    if ($message === "ping") {
        $response = "+PONG\r\n";
        socket_write($socket, $response, strlen($response));
        continue;
    }

    echo $message . " is not support command" . PHP_EOL;
}
socket_close($socket);
socket_close($sock);
