<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/src/base/runner.php";
require_once __DIR__ . "/src/DockerRest/DockerRest.php";

use \DockerRest\DockerEngineApi;

use React\EventLoop\LoopInterface;
use React\EventLoop\Loop;

use Ratchet\Server\IoServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;

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
            } /*else {
                $clientConn->close();
            }*/
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

        if (!array_key_exists('docker_container_id', $ret)) {
            fwrite(STDERR, "docker_container_id - NOT KEY\n");
            return null;
        }
        $containerId = $ret['docker_container_id'];

        $socketHandshake = new DockerEngineApi();

        $sock = $socketHandshake->getSocket();
        if ($sock === false) {
            fwrite(STDERR, "not socket\n");
            $clientConnection->close();
            return;
        }

        if ($socketHandshake->exec($containerId) === true) {
            try {
                $socketState = new DockerSocketHandler($this, $clientConnection, $sock);
                $this->loop->addReadStream($sock, function ($sock) use ($socketState) {
                    $socketState->handleData($sock);
                });
                return $socketState;
            } catch (Exception $ex) {
                fwrite(STDERR, "can't add handlers {$ex}. very bad.\n");
            }
        } else {
            fwrite(STDERR, "handshake failed\n");
        }
        return null;
    }
}

$listenPort = 8002;
if (array_key_exists(1, $argv)) {
    $listenPort = intval($argv[1]);
    echo "port: {$listenPort}\n";
}

if (array_key_exists(2, $argv)) {
    $v = $argv[2];
    echo "set api {$v}\n";
    DockerEngineApi::setApiVersion($argv[2]);
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