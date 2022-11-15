<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/src/base/runner.php";
require_once __DIR__ . "/src/DockerRest/DockerRest.php";

use \DockerRest\DockerEngineApi;
use \DockerRest\DockerBinaryStreamHandler;
use \DockerRest\DockerBinaryStream;

use React\EventLoop\LoopInterface;
use React\EventLoop\Loop;

use Ratchet\Server\IoServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;


class DockerBinaryStreamCtx implements DockerBinaryStreamHandler {

    private $component;
    private $clientConnection;
    private string $execId;
    private DockerBinaryStream $stream;


    public function __construct($dockerSocket, $component, $execId, $clientConnection) {
        $this->component = $component;
        $this->clientConnection = $clientConnection;
        $this->execId = $execId;
        $this->stream = new DockerBinaryStream($dockerSocket, $this);
    }

    public function getExecId() : string {
        return $this->execId;
    }

    public function getSocket() {
        return $this->stream->getSocket();
    }

    public function handleData() : void {
        $this->stream->handleData();
    }

    public function onMessage($msg) {
        $arr = array("data" => $msg);
        $json_data = json_encode($arr);
        $this->clientConnection->send($json_data); // how do I check that it succeeded?
    }



    public function onClose() {
        $this->component->onClose($this->clientConnection);
    }

    // send data to docker socket (input data has been read from web socket)
    public function sendToDocker($msg) {
        return $this->stream->sendToDocker($msg);
    }

    public function getDockerSocker() {
        return $this->stream->getDockerSocker();
    }

    public function doClose() {
        $this->stream->doClose();
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
            $clientConn->close();
        }
    }

    function onError(ConnectionInterface $conn, \Exception $e) {
        fwrite(STDERR,"onError\n");
    }

    function onMessage(ConnectionInterface $clientConn, $msg) {
        //fwrite(STDERR,"onMessage: " . $msg . "\n");

        $objId = spl_object_id($clientConn);
        if (!array_key_exists($objId, $this->mapConnToHandler)) {

            list($state, $close) = $this->openDocker($msg, $clientConn);
            if ($state != null) {
                $this->mapConnToHandler[ $objId ] = $state;
                return;
            }
            if ($close) {
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
                $this->onClose($clientConnection);
            }
        }

        if (array_key_exists("cols", $ret) && array_key_exists("rows", $ret)) {
            $http = new DockerEngineApi();
            $http->execResize($handler->getExecId(), intval($ret['rows']), intval($ret['cols']));
        }
    }

    private function openDocker($msg, ConnectionInterface $clientConnection) {
        // get docker id from client request
        $ret = json_decode($msg, true);
        if ($ret == null) {
            echo "Error: input is not json";
            return array(null, true);
        }

        if (!array_key_exists('docker_container_id', $ret)) {
            fwrite(STDERR, "docker_container_id - NOT KEY\n");
            return array(null, false);
        }

        $containerId = $ret['docker_container_id'];
        $socketHandshake = new DockerEngineApi();
        $sock = $socketHandshake->getSocket();

        if (!($sock === false)) {

            list($state, $execId) = $socketHandshake->exec($containerId);
            if ($state === true) {
                try {
                    $socketState = new DockerBinaryStreamCtx($sock, $this, $execId, $clientConnection);
                    $this->loop->addReadStream($sock, function ($sock) use ($socketState) {
                        $socketState->handleData($sock);
                    });
                    return array($socketState, false);
                } catch (Exception $ex) {
                    fwrite(STDERR, "can't add handlers {$ex}. very bad.\n");
                }
            } else {
                fwrite(STDERR, "handshake failed\n");
            }
        } else {
            fwrite(STDERR, "not socket, docker not running\n");
            $clientConnection->close();
        }
        return array(null, true);
    }

}

$listenPort = 8002;
if (array_key_exists(1, $argv)) {
    $listenPort = intval($argv[1]);
}

if (array_key_exists(2, $argv)) {
    $v = $argv[2];
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