<?php
error_reporting(E_ALL);

class Input
{
    public string $type;
    public string $value;
    public array $array;

    public function __construct(string $type, string $value, array $array)
    {
        $this->type = $type;
        $this->value = $value;
        $this->array = $array;
    }

    public function command(): string
    {
        return $this->value ?: $this->array[0];
    }
}

class Decoder
{
    const SIMPLE_STRING = "+";
    const BULK_STRING = "$";
    const ARRAY = "*";
    const CRLF = "\r\n";

    /**
     * @throws Exception
     */
    public static function decodeRESP(string $str): Input
    {
        $type = substr($str, 0, 1);
        switch ($type) {
            case self::SIMPLE_STRING:
                return self::decodeSimpleString($str);
            case self::BULK_STRING:
                return self::decodeBulkString($str);
            case self::ARRAY:
                return self::decodeArray($str);
            default:
                return self::decode($str);
        }
    }

    /**
     * 改行コードを含まない文字列
     *
     * @param string $str
     * @return Input
     */
    private static function decodeSimpleString(string $str): Input
    {
        $pos = strpos($str, self::CRLF);
        // 1文字目は + なので最初は読まない
        $text = substr($str, 1, $pos - 1);
        return new Input(self::SIMPLE_STRING, $text, []);
    }

    /**
     * 改行コードを含むような文字列。バイナリセーフ。
     *
     * @param string $str
     * @return Input
     */
    private static function decodeBulkString(string $str): Input
    {
        $pos = strpos($str, self::CRLF);
        $offset = substr($str, 1, $pos - 1);
        $text = substr($str, $pos + strlen(self::CRLF), $offset);
        return new Input(self::BULK_STRING, $text, []);
    }

    private static function decodeArray(string $str): Input
    {
        $pos = strpos($str, self::CRLF);
        $loopCount = substr($str, 1, $pos - 1);

        // ループ回数読み終えたポジションが開始位置
        $start_pos = $pos + strlen(self::CRLF);
        $values = [];
        foreach (range(1, $loopCount) as $index) {
            $pos = strpos($str, self::CRLF, $start_pos);
            $offset = substr($str, $start_pos + 1, $pos - $start_pos - 1);
            $start_pos = $pos + strlen(self::CRLF);
            $values[] = substr($str, $start_pos, $offset);
            $start_pos = $start_pos + $offset + strlen(self::CRLF);
        }

        return new Input(self::ARRAY, "", $values);
    }

    private static function decode(string $str): Input
    {
        $pos = strpos($str, self::CRLF);
        $text = substr($str, 0, $pos);
        return new Input("", $text, []);
    }
}

function handle($socket, ?Input $input): void
{
    switch ($input->command()) {
        case "ping":
            socket_write($socket, "+PONG\r\n");
            break;
        case "echo":
            socket_write($socket, "+" . $input->array[1] . "\r\n");
            break;
        default:
            socket_write($socket, "+\r\n");
            break;
    }
}

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
        $key = array_search($master, $read);
        unset($read[$key]);
    }

    foreach ($read as $client) {
        if (($input = @socket_read($client, 2048)) === false) {
            $key = array_search($client, $clients);
            unset($clients[$key]);
            continue;
        }
        if (trim($input) === '') {
            continue;
        }
        handle($client, Decoder::decodeRESP($input));
    }
}
