<?php namespace DockerRest;

require_once __DIR__ . "/DockerBinaryStreamHandler.php";

class DockerBinaryStream {
    private DockerBinaryStreamHandler $streamHandler;
    private $dockerSocket; // docker socket
    private int $state;
    private string $dataBuffer;
    private int $msgType;
    private int $msgLen;

    const StateParseDockerMessageHeader = 1;
    const StateParseDockerMessageBody = 2;

    public function __construct($dockerSocket, DockerBinaryStreamHandler $streamHandler) {
        $this->dockerSocket = $dockerSocket;
        $this->streamHandler = $streamHandler;
        $this->state = self::StateParseDockerMessageHeader;
        $this->dataBuffer = "";
    }

    // read data from docker socket
    public function handleData() : void {

        switch($this->state) {
        case self::StateParseDockerMessageHeader:
            $msgSize = 8;
            $len = strlen($this->dataBuffer);
            if ($len < $msgSize) {
                $toread = $msgSize - $len;
                $data = fread($this->dockerSocket, $toread);

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

                $this->state = static::StateParseDockerMessageBody;
                $this->dataBuffer = substr($this->dataBuffer, $msgSize);
            }
           //fallthrough

        case static::StateParseDockerMessageBody:
            $len = strlen($this->dataBuffer);
            if ($len < $this->msgLen) {
                $toRead = $this->msgLen - $len;
                $buf = fread($this->dockerSocket, $toRead);

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
                    $this->streamHandler->onMessage($this->dataBuffer);
                    $this->dataBuffer = "";
                } else {
                    $msg = substr($this->dataBuffer, $this->msgLen);
                    $this->streamHandler->onMessage($msg);
                    $this->dataBuffer = substr($this->dataBuffer, $this->msgLen);
                }
                $this->state = static::StateParseDockerMessageHeader;
            }
            break;
        }
    }

    public function close() {
        $this->streamHandler->onClose();
    }

    public function getDockerSocker() {
        return $this->dockerSocket;
    }

    public function doClose() {
        fclose($this->dockerSocket);
        //$this->clientConnection->close();
    }

    // send data to docker socket (input data has been read from web socket)
    public function sendToDocker($msg) {
       return fwrite($this->dockerSocket, $msg);
    }
}
