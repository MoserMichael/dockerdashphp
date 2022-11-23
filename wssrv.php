<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/src/base/runner.php";
require_once __DIR__ . "/src/DockerRest/DockerRest.php";
require_once __DIR__ . "/src/DockerRest/DockerBinaryStream.php";

use \DockerRest\DockerEngineApi;
use \DockerRest\DockerBinaryStreamHandler;
use \DockerRest\DockerBinaryStream;
use \DockerRest\ChunkConsumerInterface;
use \DockerRest\DockerEngineAuthentication;
use \DockerRest\EventDrivenChunkParser;
use \DockerRest\DockerBinaryStreamChunkConsumer;

use React\EventLoop\LoopInterface;
use React\EventLoop\Loop;

use Ratchet\Server\IoServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;


class DockerCommonBinaryStreamCtx implements DockerBinaryStreamHandler {

    private MessageComponentInterface $component;
    private ConnectionInterface $clientConnection;
    private EventDrivenChunkParser $dockerBinaryStream;
    private DockerBinaryStreamChunkConsumer $chunkConsumer;
    private $streamy;

    public function __construct(MessageComponentInterface $component, ConnectionInterface $clientConnection, $dockerSocket) {
        $this->component = $component;
        $this->clientConnection = $clientConnection;
        $this->chunkConsumer = new DockerBinaryStreamChunkConsumer($this);
        $this->dockerBinaryStream = new EventDrivenChunkParser($dockerSocket, $this->chunkConsumer);

        $this->dockerBinaryStream->consumeData();

        $this->hackItAndSetStream($clientConnection);
    }
    
    private function hackItAndSetStream(ConnectionInterface $clientConnection) {
        $ty = get_class($clientConnection);
        //$tt = $clientConnection->getConnection();

        $r = new ReflectionMethod('Ratchet\WebSocket\WsConnection', 'getConnection');
        $r->setAccessible(true);
        $d = $r->invoke($this->clientConnection);
        $t = get_class($d);

        $r = new ReflectionProperty("Ratchet\Server\IoConnection","conn");
        $r->setAccessible(true);
        $dd = $r->getValue($d);
        $tt = get_class($dd);

        $r = new ReflectionProperty("React\Socket\Connection","input");
        $r->setAccessible(true);
        $ddd = $r->getValue($dd);
        $ttt = get_class($ddd);

        $r = new ReflectionProperty("React\Stream\DuplexResourceStream","buffer");
        $r->setAccessible(true);
        $dddd = $r->getValue($ddd);
        $tttt = get_class($dddd);

        $r = new ReflectionProperty("React\Stream\WritableResourceStream","stream");
        $r->setAccessible(true);

        //fwrite(STDERR,"nested classes: {$ty} {$t} {$tt} {$ttt} {$tttt}\n");

        $this->streamy = $r->getValue($dddd);
    }

    public function getChunkConsumerInterface() : ChunkConsumerInterface {
        return $this->chunkConsumer;
    }

    public function getSocket() {
        return $this->dockerBinaryStream->getSocket();
    }

    public function handleReadData() : void {
        $this->dockerBinaryStream->handleReadData();
    }

    // upon reading a message from the docker socket
    public function onMessage($msg) {
        $arr = array("data" => $msg);
        $json_data = json_encode($arr);


        $f = new \Ratchet\RFC6455\Messaging\Frame($json_data);
        fwrite($this->streamy, $f->getContents());


        //$this->clientConnection->send($json_data); // how do I check that it succeeded?
        // it didn't help to call drain on any of the nested ratchet objects....
        //$dddd->emit('drain');
    }

    public function onClose() {
        $this->component->onClose($this->clientConnection);
    }

    public function sendToDocker($msg) {
    }

    public function getDockerSocker() {
        return $this->dockerBinaryStream->getDockerSocker();
    }

    public function doClose() {
        $this->dockerBinaryStream->doClose();
    }
}


class DockerConsoleBinaryStreamCtx implements DockerBinaryStreamHandler {

    private MessageComponentInterface $component;
    private ConnectionInterface $clientConnection;
    private DockerBinaryStream $dockerBinaryStream;
    private string $execId;


    public function __construct(MessageComponentInterface $component, ConnectionInterface $clientConnection, $dockerSocket, string $execId ) {
        $this->component = $component;
        $this->clientConnection = $clientConnection;
        $this->dockerBinaryStream = new DockerBinaryStream($dockerSocket, $this);
        $this->execId = $execId;
    }

    public function getExecId() : string {
        return $this->execId;
    }

    public function getSocket() {
        return $this->dockerBinaryStream->getSocket();
    }

    public function handleReadData() : void {
        $this->dockerBinaryStream->handleReadData();
    }

    // upon reading a message from the docker socket
    public function onMessage($msg) {
        $arr = array("data" => $msg);
        $json_data = json_encode($arr);

        $this->clientConnection->send($json_data); // how do I check that it succeeded?
        flush(); //?
    }

    public function onClose() {
        $this->component->onClose($this->clientConnection);
    }

    // send data to docker socket (input data has been read from web socket)
    public function sendToDocker($msg) {
        return $this->dockerBinaryStream->sendToDocker($msg);
    }

    public function getDockerSocker() {
        return $this->dockerBinaryStream->getDockerSocker();
    }

    public function doClose() {
        $this->sendToDocker("exit\r\n");
        $this->dockerBinaryStream->doClose();
    }
}

class WebsocketConnectionComponent implements MessageComponentInterface {
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
            fwrite(STDERR,"closing connection handler\n");

            $handler = $this->mapConnToHandler[$objId];
            unset($this->mapConnToHandler[$objId]);

            $this->loop->removeReadStream($handler->getDockerSocker());
            $handler->doClose();
            $clientConn->close();
        }
    }

    function onError(ConnectionInterface $conn, \Exception $e) {
        $msg="$e";
        fwrite(STDERR,"onError: {$msg} \n");
        $this->onClose($conn);
    }

    function onMessage(ConnectionInterface $clientConn, $msg) {
        //fwrite(STDERR,"onMessage: " . $msg . "\n");

        $objId = spl_object_id($clientConn);
        if (!array_key_exists($objId, $this->mapConnToHandler)) {

            list($state, $close) = $this->handleInitMessage($msg, $clientConn);

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

    // handle first message of a client connection
    private function handleInitMessage($msg, ConnectionInterface $clientConn) : array {
        
        // get docker id from client request
        $jsonMsg = json_decode($msg, true);
        if ($jsonMsg == null) {
            echo "Error: input is not json";
            return array(null, true);
        }

        if (array_key_exists('docker_container_id', $jsonMsg)) {
            $containerId = $jsonMsg['docker_container_id'];
            return $this->consoleAttachToDocker($containerId, $clientConn);
        } else if (array_key_exists('log_container_id', $jsonMsg)) {
            $containerId = $jsonMsg['log_container_id'];
            return $this->openLogs($containerId, $clientConn, $jsonMsg);
        } else if (array_key_exists('load_image', $jsonMsg)) {
            $image = $jsonMsg['load_image'];
            return $this->initLoadImage($image, $clientConn, $jsonMsg);
        } else {
            fwrite(STDERR, "Unrecognized  message\n");
            return array(null, false);
        }
    }

    private function initLoadImage(string $image, ConnectionInterface $clientConnection, array $jsonMsg) {

        $tag = "";
        $auth = null;

        if (array_key_exists('tag', $jsonMsg)) {
            $tag = $jsonMsg['tag'];
        }

        if (array_key_exists('authtoken', $jsonMsg)) {
            $auth = new DockerEngineAuthentication(null, null, $jsonMsg['authtoken']);

        } else if (array_key_exists('username', $jsonMsg) &&
                   array_key_exists('password', $jsonMsg)) {
            $auth = new DockerEngineAuthentication($jsonMsg['username'], $jsonMsg['password'], null);
        }
        fwrite(STDERR, "initLoadImage {$image} {$tag}\n");

        $dockerSocket = DockerEngineApi::openDockerSocket();
        if ($dockerSocket === false) {
            fwrite(STDERR, "not socket, docker not running\n");
            $clientConnection->close();
        }

        $socketState = new DockerCommonBinaryStreamCtx($this, $clientConnection, $dockerSocket);
        $api = new DockerEngineApi($dockerSocket, $socketState->getChunkConsumerInterface());

        list ($ok) = $api->imagePull($image, $auth, $tag);
        $auth = null;

        if ($ok) {
            try {
                $this->loop->addReadStream($dockerSocket, function ($sock) use ($socketState) {
                    $socketState->handleReadData();
                });
                return array($socketState, false);
            } catch (Exception $ex) {
                fwrite(STDERR, "can't add handlers {$ex}. very bad.\n");
            }
        } else {
            fwrite(STDERR, "handshake failed\n");
        }
        return array(null, false);
    }

    private function openLogs($containerId, ConnectionInterface $clientConnection, array $jsonMsg) {
        $from = -1;
        $to = -1;
        $followLogs = False;

        if (array_key_exists('follow', $jsonMsg)) {
            $followLogs = $jsonMsg['follow'];
        }

        if (array_key_exists('since', $jsonMsg)) {
            $from = $jsonMsg['since'];
        }

        if (array_key_exists('until', $jsonMsg)) {
            $to = $jsonMsg['until'];
        }

        fwrite(STDERR, "openLogs container: {$containerId} follow: {$followLogs} from: {$from} to: {$to}\n");

        $dockerSocket = DockerEngineApi::openDockerSocket();
        if ($dockerSocket === false) {
            fwrite(STDERR, "not socket, docker not running\n");
            $clientConnection->close();
        }

        $socketState = new DockerCommonBinaryStreamCtx($this, $clientConnection, $dockerSocket);
        $logHandshake = new DockerEngineApi($dockerSocket, $socketState->getChunkConsumerInterface());

        list ($ok) = $logHandshake->containerLogs($containerId, $followLogs, $from, $to);
        if ($ok) {
            try {
                $this->loop->addReadStream($dockerSocket, function ($sock) use ($socketState) {
                    $socketState->handleReadData();
                });
                return array($socketState, false);
            } catch (Exception $ex) {
                fwrite(STDERR, "can't add handlers {$ex}. very bad.\n");
            }
        } else {
            fwrite(STDERR, "handshake failed\n");
        }
        return array(null, false);
    }


    private function consoleAttachToDocker($containerId, ConnectionInterface $clientConnection) {

        $socketHandshake = new DockerEngineApi();
        $dockerSocket = $socketHandshake->getSocket();

        if ($dockerSocket !== false) {

            list($state, $execId) = $socketHandshake->exec($containerId);
            if ($state === true) {
                try {
                    $socketState = new DockerConsoleBinaryStreamCtx( $this, $clientConnection, $dockerSocket, $execId);
                    $this->loop->addReadStream($dockerSocket, function ($sock) use ($socketState) {
                        $socketState->handleReadData($sock);
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
}


$listenPort = 8002;

function runServer($listenPort) : void {

    $docker = new WebsocketConnectionComponent();
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
}

if (array_key_exists(1, $argv)) {
    $listenPort = intval($argv[1]);
}

runServer($listenPort);