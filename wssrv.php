<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/src//base/runner.php";


// warning!
// this doesn't work well:
// if you run $(docker exec -ti <docker_id> /bin/bash) then you can't pass it a pipe!!!
// passing pipes for stdin/stdout/stderr gives the error "the input device is not a TTY".
// Maybe you could fix that with the "docker engine api" - https://docs.docker.com/engine/api/
// They have clients for go and python.
//
// But that is too much: this is just a project to learn some PHP.
// Can't burn time on this indefinitely...
//
// Right now I explicitly echo the stdin, which sucks....
// This can't be used in this form.
//

use Ratchet\Server\IoServer;
use \Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class DockerTerminalConnection implements MessageComponentInterface {
    private bool $firstMessage;
    public LoopInterface $loop;
    public $process;
    public $pipes;

    public function __construct() {
        $this->firstMessage = true;
    }

    public function setLoop(LoopInterface $loop) {
        $this->loop = $loop;
    }

    function onOpen(\Ratchet\ConnectionInterface $conn)
    {
        fwrite(STDERR,"onOpen\n");
    }

    function onClose(\Ratchet\ConnectionInterface $conn)
    {
        fwrite(STDERR,"onClose\n");
    }

    function onError(\Ratchet\ConnectionInterface $conn, \Exception $e)
    {
        fwrite(STDERR,"onError\n");
    }

    function onMessage(\Ratchet\ConnectionInterface $clientConnection, $msg)
    {
        fwrite(STDERR,"onMessage: " . $msg . "\n");

        if ($this->firstMessage) {
            $this->firstMessage = false;
            $this->openDocker($msg, $clientConnection);
        } else {
            $this->dataMessage($msg, $clientConnection);

            //$clientConnection->send($msg);

        }
    }

    private function dataMessage($msg, $clientConnection) {
        $ret = json_decode($msg, true);
        if ($ret == null) {
            echo "Error: wrong data command has been received";
            return;
        }

        if (array_key_exists("data", $ret)) {
            $data = $ret['data'];

            // echo back to terminal... (because we can't run the docker with a terminal)
            $echoData = str_replace("\r", "\r\n", $data);
            DockerTerminalConnection::send($echoData, $clientConnection);

            $data = str_replace("\r", "\n", $data);
            if ($data !== "") {
                fwrite(STDERR, "send: " . $data . "\n");

                // pass to docker
                fwrite($this->pipes[0], $data);
            }
        }
    }

    private static function send($data, $clientConnection) {
        $data = str_replace("\n", "\r\n", $data);
        $data = array('data' => $data);
        $str = json_encode($data);
        fwrite(STDERR, "send: {$str}\n");
        $clientConnection->send($str);
    }

    private function openDocker($msg, \Ratchet\ConnectionInterface $clientConnection) {

        $ret = json_decode($msg, true);
        if ($ret == null) {
            echo "Error: wrong connect command has been received";
            return;
        }
        $container_id = $ret['docker_container_id'];
        $cmd = "docker exec -i {$container_id} /bin/sh";
        fwrite(STDERR,"run cmd: ${cmd}\n");
        list($this->process, $this->pipes) = base\runAndReturnPipes($cmd);

        $ty = get_class($this->loop);
        fwrite(STDERR, "loop type {$ty}\n");

        $func = function ($readConn) use ($clientConnection) {
            fwrite(STDERR, "read event!\n");

            $data = @\fread($readConn, 10000);
            if ($data === false) {
                return;
            }

            DockerTerminalConnection::send($data, $clientConnection);
        };

        $this->addReadStream($this->pipes[1],$func);
        $this->addReadStream($this->pipes[2],$func);
    }

    private function addReadStream($stream, $handler) {
        try {
            $meta = stream_get_meta_data($this->pipes[1]);
            if ($meta['mode'] !== 'r') {
                fwrite(STDERR, "Docker stream is not readable!!!");
            }

            $ret = stream_set_blocking($stream, false);
            if ($ret === false) {
                fwrite(STDERR, "Docker can't set stream to non blocking mode!");
            }

            $this->loop->addReadStream($stream, $handler);
        } catch(Exception $ex) {
            fwrite(STDERR,"can't add handlers {$ex}\n");
            exit(0);
        }
    }

}


$listenPort = intval($argv[1]);

$docker = new DockerTerminalConnection();

$loop = \React\EventLoop\Loop::get();

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $docker)
        ),
    $listenPort
);
$docker->setLoop($server->loop);
$server->run();