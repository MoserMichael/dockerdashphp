<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/src//base/runner.php";


// Your shell script
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

    function onMessage(\Ratchet\ConnectionInterface $from, $msg)
    {
        fwrite(STDERR,"onMessage: " . $msg . "\n");

        if ($this->firstMessage) {
            $this->firstMessage = false;
            $this->openDocker($msg, $from);
        } else {
            $this->dataMessage($msg);
        }
    }

    private function dataMessage($msg) {
        $ret = json_decode($msg, true);
        if ($ret == null) {
            echo "Error: wrong data command has been received";
            return;
        }

        if (array_key_exists("data", $ret)) {
            $data = $ret['data'];
            $data = str_replace($data, '\r', '\n');
            fwrite(STDERR,"send: " . $data . "\n");

            // pass to docker
            fwrite($this->pipes[0], $data);
        }
    }

    private function openDocker($msg, \Ratchet\ConnectionInterface $dockerConnection) {

        $ret = json_decode($msg, true);
        if ($ret == null) {
            echo "Error: wrong connect command has been received";
            return;
        }
        $container_id = $ret['docker_container_id'];
        $cmd = "docker exec -i {$container_id} /bin/sh";
        fwrite(STDERR,"run cmd: ${cmd}\n");
        list($this->process, $this->pipes) = base\runAndReturnPipes($cmd);

        try {
            $this->loop->addReadStream($this->pipes[1], function ($readConn) use ($dockerConnection) {
                $data = @\fread($readConn, 10000);
                if ($data === false) {
                    return;
                }
                $cmd = "{'data':" . escapeshellarg($data) . "}";
                fwrite(STDERR, "send: {$cmd}\n");
                $dockerConnection->send($cmd);
            });

            $this->loop->addReadStream($this->pipes[2], function ($readConn) use ($dockerConnection) {
                $data = @\fread($readConn, 10000);
                if ($data === false) {
                    return;
                }
                $cmd = "{'data':" . escapeshellarg($data) . "}";
                fwrite(STDERR, "send: {$cmd}\n");
                $dockerConnection->send($cmd);
            });
        } catch(Exception $ex) {
            fwrite(STDERR,"can't add handlers {$ex}\n");

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