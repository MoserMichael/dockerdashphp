<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/src//base/runner.php";

use React\EventLoop\LoopInterface;
use React\EventLoop\Loop;

use Ratchet\Server\IoServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;

use GuzzleHttp\Psr7\Message;


class DockerSocketHttpHandshake {
    private $sock;
    private string $containerId;
    private string $buffer;

    const EOF_HDR = "\r\n\r\n";
    const EOF_LINE = "\r\n";

    private static $dockerApiVersion = "v1.41";

    public static function setApiVersion(string $version) : void {
        self::$dockerApiVersion = $version;
    }

    public function __construct($sock, string $containerId) {
        $this->sock = $sock;
        $this->containerId = $containerId;
        $this->buffer = "";
    }

    public function send($data) {
        fwrite($this->sock, $data);
    }

    public function run() : bool {
        $id = $this->execCreate("/bin/sh");
        if ($id != null) {
            return $this->execUpgrade($id);
        }
        return false;
    }

    private function execCreate($shellPath) {
        $this->sendExecCreateRequest($shellPath);

        $hdr = $this->readHttpResponse();
        if ($hdr == null) {
            fwrite(STDERR, "Can't parse http header for exec create request\n");
            return null;
        }
        if ($hdr->getStatusCode() == 201) {

            $requestData = $this->parseTransferEncodingBody($hdr);

            $arr = json_decode($requestData, true);
            if (!array_key_exists("Id", $arr)) {
                return null;
            }
            return $arr["Id"];
        }
    }

    private function parseTransferEncodingBody($hdr) {
        $requestData = "";
        if ($hdr->hasHeader("Transfer-Encoding") && $hdr->getHeader("Transfer-Encoding")[0] == "chunked") {

            $hasChunkLen = false;
            $len = 0;

            while(True) {
                if ($this->buffer == "") {
                    if (!$this->readSocket()) {
                        return;
                    }
                }
                if (!$hasChunkLen) {
                    $pos = strpos($this->buffer, self::EOF_LINE);
                    if (!($pos === false)) {
                        $msg = substr($this->buffer, 0, $pos);
                        $len = hexdec($msg);
                        $this->buffer = substr($this->buffer, $pos + strlen(self::EOF_LINE));

                        if ($len == 0) {
                            break;
                        }
                        $hasChunkLen = true;
                    }
                }
                if ($hasChunkLen) {
                    if (strlen($this->buffer) >= $len) {
                        $chunkData = substr($this->buffer, 0, $len);
                        $this->buffer = substr($this->buffer, $len);
                        if (strlen($this->buffer) >= strlen(self::EOF_LINE)) {
                            $str = substr($this->buffer, 0, strlen(self::EOF_LINE));
                            if ($str == self::EOF_LINE) {
                                $this->buffer = substr($this->buffer, strlen(self::EOF_LINE));
                            }
                        }
                        $requestData = $requestData . $chunkData;
                        $hasChunkLen = false;
                    }
                }
            }
        }
        return $requestData;
    }

    private function execUpgrade($idExec) : bool {
        $this->sendExecUpgradeRequest($idExec);
        $hdr = $this->readHttpResponse();
        if ($hdr == null) {
            fwrite(STDERR, "Can't parse http header for exec exec request\n");
            return false;
        }
        return $hdr->getStatusCode() == 101;
    }

    private function sendExecUpgradeRequest($idExec) : bool {
        $jsonArr = array(
            "id" => $idExec,
            "ExecStartConfig" => array("Tty" => true),
        );
        $json = json_encode($jsonArr);
        $jsonLen = strlen($json);

        $ver = self::$dockerApiVersion;
        $execRequest = "POST /{$ver}/exec/{$idExec}/start HTTP/1.1\r\n" .
            "Host: localhost\r\nContent-Length: {$jsonLen}\r\nUpgrade: tcp\r\nConnection: Upgrade\r\nAccept: */*\r\nContent-Type: application/json\r\n\r\n{$json}";

        fwrite($this->sock, $execRequest);
        return true;
    }

    private function readHttpResponse() {
        while(true) {
            if (!$this->readSocket()) {
                return null;
            }

            $pos = strpos($this->buffer, self::EOF_HDR);
            if (!($pos === false)) {
                $msg = substr($this->buffer, 0, $pos + strlen(self::EOF_HDR));
                $ret = Message::parseResponse($msg); // strange parser
                $this->buffer = substr($this->buffer, $pos + strlen(self::EOF_HDR));
                return $ret;
            }
        }
    }

    private function sendExecCreateRequest(string $shell) {
        $jsonArr = array(
            "id" => $this->containerId,
            "AttachStdin" => true,
            "AttachStdout" => true,
            "AttachStderr" => true,
            "DetachKeys" => "ctrl-p,ctrl-q",
            "Tty" => true,
            "Cmd" =>  array($shell)
        );
        $json = json_encode($jsonArr);
        $jsonLen = strlen($json);

        $ver = self::$dockerApiVersion;
        $createExecInstanceRequest
            = "POST /{$ver}/containers/{$this->containerId}/exec HTTP/1.1\r\n" .
            "Host: localhost\r\nContent-Length: {$jsonLen}\r\nAccept: */*\r\nContent-Type: application/json\r\n\r\n{$json}";

        $len = strlen($createExecInstanceRequest);
        if (fwrite($this->sock, $createExecInstanceRequest) !== $len) {
            fwrite(STDERR, "Can't send initial http request to docker socket\n");
        }
    }

    private function readSocket() {
        $r = array($this->sock);
        $w = array();
        $e = array();

        stream_select($r, $w, $e, null);

        $ret = fread($this->sock, 4096);
        if ($ret === false) {
            return false;
        }
        $this->buffer = $this->buffer . $ret;
        return true;
    }
}

class DockerSocketHandler {

    private ConnectionInterface $clientConnection; // web socket
    private $dockerSocket; // docker socket
    private $component;
    private int $state;
    private string $dataBuffer;
    private int $msgType;
    private int $msgLen;

    const State_ParseDockerMessageHeader = 4;
    const State_ParseDockerMessageBody = 4;

    public function __construct($component, ConnectionInterface $clientConnection, $dockerSocket) {

        $this->component = $component;
        $this->clientConnection = $clientConnection;
        $this->dockerSocket = $dockerSocket;
        $this->state = self::State_ParseDockerMessageHeader;
        $this->dataBuffer = "";
    }

    // read data from docker socket
    public function handleData($socket) : void {
        switch($this->state) {
            case self::State_ParseDockerMessageHeader:
                $msgSize = 8;
                $len = strlen($this->dataBuffer);
                if ($len < $msgSize) {
                    $data = fread($socket, $msgSize - $len);
                    if ($data === false || $data === "") {
                        $this->close();
                        return;
                    }
                    $this->dataBuffer = $this->dataBuffer . $data;
                }
                if (strlen($this->dataBuffer) >= $msgSize) {
                    $hdr = substr($this->dataBuffer, 0, $msgSize);
                    $msg = unpack("C*", $hdr);
                    $this->msgType = $msg[1];
                    $this->msgLen = $msg[8] + ($msg[7] << 8) + ($msg[6] << 16) + ($msg[5] << 24);
                    $this->state = static::State_ParseDockerMessageBody;
                    $this->dataBuffer = substr($this->dataBuffer, $msgSize);
                }
                //fallthrough
            case static::State_ParseDockerMessageBody:
                $len = strlen($this->dataBuffer);
                if ($len < $this->msgLen) {
                    $toRead = $this->msgLen - $len;
                    $buf = fread($socket, $toRead);
                    if ($buf === false || $buf === "") {
                        $this->close();
                        return;
                    }
                    $this->dataBuffer = $this->dataBuffer . $buf;
                }
                $len = strlen($this->dataBuffer);
                if ($len >= $this->msgLen) {

                    // consume the buffer!
                    if ($len == $this->msgLen) {
                        $this->sendToClient($this->dataBuffer);
                    } else {
                        $msg = substr($this->dataBuffer, $this->msgLen);
                        $this->sendToClient($msg);
                    }
                    $this->dataBuffer = substr($this->dataBuffer, $this->msgLen);
                }
        }
    }

    public function close() {
        $this->component->onClose($this->clientConnection);
    }

    public function getDockerSocker() {
        return $this->dockerSocket;
    }
    public function doClose() {
        fclose($this->dockerSocket);
        $this->clientConnection->close();
    }


    // send data to websocket (input data has been read from docker connection)
    private function sendToClient($msg) {
        $arr = array("data" => $msg);
        $json_data = json_encode($arr);
        $this->clientConnection->send($json_data); // how do I check that it succeeded?
    }


    // send data to docker socket (input data has been read from web socket)
    public function sendToDocker($msg) {
        return fwrite($this->dockerSocket, $msg);
    }
}

class WebsocketToTerminalComponent implements MessageComponentInterface {
    public LoopInterface $loop;
    public array $mapConnToHandler;

    public function __construct() {
        $this->mapConnToHandler = array();
    }

    public function setLoop(LoopInterface $loop) {
        $this->loop = $loop;
    }

    function onOpen(ConnectionInterface $conn) {
        fwrite(STDERR,"onOpen\n");
    }

    function onClose(ConnectionInterface $clientConn) {
        fwrite(STDERR,"onClose\n");
        $objId = spl_object_id($clientConn);
        if (array_key_exists($objId, $this->mapConnToHandler)) {
            $handler = $this->mapConnToHandler[$objId];
            $this->loop->removeReadStream($handler->getDockerSocker());
            $handler->doClose();
            unset($this->mapConnToHandler[$objId]);
        }
    }

    function onError(ConnectionInterface $conn, \Exception $e) {
        fwrite(STDERR,"onError\n");
    }

    function onMessage(ConnectionInterface $clientConn, $msg) {
        //fwrite(STDERR,"onMessage: " . $msg . "\n");

        $objId = spl_object_id($clientConn);
        if (!array_key_exists($objId, $this->mapConnToHandler)) {
            $state = $this->openDocker($msg, $clientConn);
            if ($state != null) {
                $this->mapConnToHandler[ $objId ] = $state;
            } else {
                $clientConn->close();
            }
        } else {
            $handler = $this->mapConnToHandler[ $objId ];
            $this->dataMessage($msg, $clientConn, $handler);
        }
    }

    private function dataMessage($msg, $clientConnection, $handler) {
        $ret = json_decode($msg, true);
        if ($ret == null) {
            echo "Error: wrong data command has been received";
            return;
        }

        if (array_key_exists("data", $ret)) {

            $data = $ret['data'];

            if ($handler->sendToDocker($data) === false) {
                onClose($clientConnection);
            }
        }
    }

    private function openDocker($msg, ConnectionInterface $clientConnection) {

        // get docker id from client request
        $ret = json_decode($msg, true);
        if ($ret == null) {
            echo "Error: wrong connect command has been received";
            return null;
        }
        $containerId = $ret['docker_container_id'];

        $sock = $this->connectDockerSocket($containerId);

        $socketHandshake = new DockerSocketHttpHandshake($sock, $containerId);

        if ($socketHandshake->run() === true) {
            try {
                $socketState = new DockerSocketHandler($this, $clientConnection, $sock);
                $this->loop->addReadStream($sock, function ($sock) use ($socketState) {
                    $socketState->handleData($sock);
                });
                return $socketState;
            } catch (Exception $ex) {
                fwrite(STDERR, "can't add handlers {$ex}. very bad.\n");
            }
        }
        return null;
    }

    private function connectDockerSocket(string $containerId) {

        $sock = fsockopen("unix:///var/run/docker.sock");
        if ($sock === false) {
            fwrite(STDERR, "Can't connect docker socket\n");
            return false;
        }

        $ret = stream_set_blocking($sock, false);
        if ($ret === false) {
            fwrite(STDERR, "Can't set stream to non blocking mode\n");
            return false;
        }
        return $sock;
    }
}

$listenPort = 8002;
if (array_key_exists(1, $argv)) {
    $listenPort = intval($argv[1]);
}

if (array_key_exists(2, $argv)) {

    DockerSocketHttpHandshake::setApiVersion($argv[1]);

}

$docker = new WebsocketToTerminalComponent();
$loop = Loop::get();
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $docker)
        ),
    $listenPort
);
$docker->setLoop($server->loop);
$server->run();