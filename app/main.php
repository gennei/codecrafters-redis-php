<?php
error_reporting(E_ALL);
const PORT = 6379;
// You can use print statements as follows for debugging, they'll be visible when running tests.
echo "Logs from your program will appear here" . PHP_EOL;

$master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($master, SOL_SOCKET, SO_REUSEPORT, 1);
socket_bind($master, "localhost", PORT);
socket_listen($master);

$clients = [$master];
while (true) {
    $read = $clients;

    $write = $exp = null;
    if (socket_select($read, $write, $exp, null) === false) {
        continue;
    }

    if (in_array($master, $read)) {
        $clients[] = socket_accept($master);

        // Q: なぜ master を除外する?
        $key = array_search($master, $read);
        unset($read[$key]);
    }

    foreach ($read as $client) {
        $content = @socket_read($client, 2048, PHP_NORMAL_READ);
        if ($content === false) {
            $key = array_search($client, $clients);
            unset($clients[$key]);
        }

        $content = trim($content);
        var_dump($content);
        if ($content === "ping") {
            socket_write($client, "+PONG\r\n");
        }
    }
}
