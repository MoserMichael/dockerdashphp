<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../src/base/runner.php";
require_once __DIR__ . "/../src/DockerRest/DockerRest.php";
require_once __DIR__ . "/../src/DockerRest/DockerBinaryStream.php";

use \DockerRest\DockerEngineApi;
use \DockerRest\DockerBinaryStreamHandler;
use \DockerRest\DockerBinaryStream;
use \DockerRest\ChunkConsumerInterface;
use \DockerRest\DockerEngineAuthentication;
use \DockerRest\EventDrivenChunkParser;
use \DockerRest\DockerBinaryStreamChunkConsumer;

use React\EventLoop\LoopInterface;

use Ratchet\MessageComponentInterface;

use Ratchet\ConnectionInterface;

const ERROR_MSG = "OCI runtime exec failed"; //: exec failed: unable to start container process: exec:";

class DockerConsoleBinaryStreamCtx implements DockerBinaryStreamHandler {

    private LoopInterface $loop;
    private MessageComponentInterface $component;
    private ConnectionInterface $clientConnection;
    private DockerBinaryStream $dockerBinaryStream;
    private string $containerId;
    private string $execId;
    private int $msgCount;
    private int $rows;
    private int $cols;

    public function __construct(LoopInterface $loop, MessageComponentInterface $component, ConnectionInterface $clientConnection, $socket, string $execId, string $containerId) {
        $this->loop = $loop;
        $this->component = $component;
        $this->clientConnection = $clientConnection;
        $this->dockerBinaryStream = new DockerBinaryStream($socket, $this);
        
        $this->execId = $execId;
        $this->containerId = $containerId;
        $this->msgCount = 0;
        $this->rows = -1;
        $this->cols = -1;
    }

    public static function consoleAttachToDocker($containerId, ConnectionInterface $clientConnection, MessageComponentInterface $component, LoopInterface $loop) : array {

        $socketHandshake = new DockerEngineApi();
        $dockerSocket = $socketHandshake->getSocket();

        if ($dockerSocket !== false) {

            list($state, $execId) = $socketHandshake->exec($containerId);
            if ($state === true) {
                $socketState = new DockerConsoleBinaryStreamCtx( $loop, $component, $clientConnection, $dockerSocket, $execId, $containerId);
                $socketState->addReadStream($dockerSocket);
                return array($socketState, false);
            } else {
                fwrite(STDERR, "handshake failed\n");
            }
        } else {
            fwrite(STDERR, "not socket, docker not running\n");
            $clientConnection->close();
        }
        return array(null, true);
    }
    
    private function addReadStream($socket) {
        try {
            $socketState = $this->dockerBinaryStream;
            $this->loop->addReadStream($socket, function ($sock) use ($socketState) {
                $socketState->handleReadData($sock);
            });
        } catch (Exception $ex) {
            fwrite(STDERR, "can't add handlers {$ex}. very bad.\n");
        }
    }


    public function getExecId() : string {
        return $this->execId;
    }

    public function getSocket() {
        return $this->dockerBinaryStream->getDockerSocker();
    }

    public function handleReadData() : void {
        $this->dockerBinaryStream->handleReadData();
    }

    // upon reading a message from the docker socket
    public function onMessage($msg) {
        if ($this->msgCount < 3) {
            if (str_starts_with($msg, ERROR_MSG)) {
                $this->msgCount = 3;
                if (!$this->onFailConnect()) {
                    $this->onClose();
                }
                return;
            }
        }

        $this->msgCount += 1;

        if ($this->rows != -1 && $this->cols != -1) {
            fwrite(STDERR, "resize after connect rows: {$this->rows} cols: {$this->cols}\n");
            $this->resize($this->rows, $this->cols);
            $this->rows = -1;
            $this->cols = -1;
        }

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

    private function onFailConnect() : bool {

        fwrite(STDERR, "Start last effort to run the shell!\n");

        $this->loop->removeReadStream($this->getSocket());
        $this->dockerBinaryStream->doClose();

        $api = new DockerEngineApi();

        // find out which os the image is running.
        $shellPath = $this->makeShellPath($this->containerId, $api);

        fwrite(STDERR, "shell path {$shellPath}\n");

        if (file_exists($shellPath)) {
            // copy shell path to container
            list ($ok) = $api->copyTarFileToContainer($this->containerId, $shellPath, "/");
            if ($ok) {
                // run the init handshake again.
                list($state, $execId) = $api->exec($this->containerId);
                if ($state) {
                    $this->dockerBinaryStream->setDockerSocker( $api->getSocket() );
                    $this->addReadStream( $api->getSocket() );
                    $this->execId = $execId;
                    return true;
                } else {
                    fwrite(STDERR, "Shell {$shellPath} not found. (after shell attach fail)\n");
                }
            } else {
                fwrite(STDERR, "failed to copy shell to container. (after shell attach fail)\n");
            }
        } else {
            fwrite(STDERR, "Shell {$shellPath} not found. (after shell attach fail)\n");
        }
        $api->close();
        return false;
    }

    private function makeShellPath(string $containerId, $api) : string {
        $json = $this->getImageJson($containerId, $api);
        if ($json != null) {
            // do we have such a shell here?
            $os = @$json['Os'];
            $arch = @$json['Architecture'];

            if ($arch == "amd64") {
                $arch = "x86_64";
            } else if ($arch == "arm64") {
                $arch = "aarch64"; // this one will probably split up into new arm versions...-
            }

            return "shells/bash-{$os}-{$arch}.tar";
        }
        return "shell-not-determined";
    }

    private function getImageJson(string $containerId, $api) {

        list ($status, $body) = $api->inspectContainer($this->containerId);
        if ($status) {
            $arr = json_decode($body, JSON_OBJECT_AS_ARRAY);
            if ($arr != null) {
                $imageId = $arr['Image'];

                if (str_starts_with($imageId,"sha256:")) {
                    $imageId = substr($imageId, strlen("sha256:"));
                }

                list ($status, $body) = $api->inspectImage($imageId);

                if ($status) {
                    $arr = json_decode($body, JSON_OBJECT_AS_ARRAY);
                    if ($arr != null) {
                        return $arr;
                    } else {
                        fwrite(STDERR, "Cant parse docker image json (after shell attach fail)\n");
                    }
                } else {
                    fwrite(STDERR, "Cant inspect docker image (after shell attach fail)\n");
                }
            } else {
                fwrite(STDERR, "Cant parse docker container json (after shell attach fail)\n");
            }
        } else {
            fwrite(STDERR, "Cant inspect docker container (after shell attach fail)\n");
        }
        return null;
    }

    public function resize(int $rows, int $cols) : void {
        $http = new DockerEngineApi();
        list ($ok) = $http->execResize($this->execId, $rows, $cols);
        if (!$ok) {
            $this->rows = $rows;
            $this->cols = $cols;
        }
        $http->close();
    }

}
