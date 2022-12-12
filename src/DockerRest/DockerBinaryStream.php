<?php namespace DockerRest;

require_once __DIR__ . "/DockerBinaryStreamHandler.php";
require_once __DIR__ . "/Http.php";


class DockerBinaryStreamBase  {
    private DockerBinaryStreamHandler $streamHandler;
    private int $state;
    private string $dataBuffer;
    private int $msgType;
    private int $msgLen;
    private bool $passThrough;
    protected static $TRACE = true;  // set to on for tracing of requests/responses

    const StateParseDockerMessageHeader = 1;
    const StateParseDockerMessageBody = 2;
    const HeaderMsgSize = 8;

    public static function setTrace($trace) {
        self::$TRACE = $trace;
    }

    public function __construct(DockerBinaryStreamHandler $streamHandler = null) {
        $this->streamHandler = $streamHandler;
        $this->state = self::StateParseDockerMessageHeader;
        $this->dataBuffer = "";
        $this->passThrough = false;
    }

    protected function processData($data) : void {
        if ($data !== false) {
            $this->dataBuffer = $this->dataBuffer . $data;
        }

        /*
        if (self::$TRACE) {
            $h = $this->hexDump($this->dataBuffer);
            fwrite(STDERR, "buffer\n" . $h . "\n");
        }
        */

        if (!$this->passThrough) {
            while (true) {
                if ($this->state == self::StateParseDockerMessageHeader) {
                    $len = strlen($this->dataBuffer);
                    if ($len >= self::HeaderMsgSize) {
                        if (!$this->parseHeader()) {
                            $this->passThrough = true;
                            break;
                        }
                        $this->state = self::StateParseDockerMessageBody;
                    } else {
                        break;
                    }
                }

                if ($this->state == self::StateParseDockerMessageBody) {
                    $len = strlen($this->dataBuffer);
                    if ($len >= $this->msgLen) {
                        $this->consumeMessage();
                        $this->state = static::StateParseDockerMessageHeader;
                    } else {
                        break;
                    }
                }
            }
        }
        if ($this->passThrough) {
            $this->streamHandler->onMessage($this->dataBuffer);
            $this->dataBuffer = "";
        }
    }

    private function parseHeader() {
        $hdr = substr($this->dataBuffer, 0, self::HeaderMsgSize);
        $msg = unpack("C*", $hdr);
        $this->msgType = $msg[1];

        if ($this->msgType != 1 && $this->msgType != 2) {
            return false;
        }

        $this->msgLen = $msg[8] + ($msg[7] << 8) + ($msg[6] << 16) + ($msg[5] << 24);
        $this->dataBuffer = substr($this->dataBuffer, self::HeaderMsgSize);

        if (self::$TRACE) {
            fwrite(STDERR, "stream::parseHeader msgType: {$this->msgType} len: {$this->msgLen}\n");
            $h = $this->hexDump($hdr);
            fwrite(STDERR, "header-data\n" . $h . "\n");
        }
        return true;
    }
    
    private function consumeMessage() : void {
        // consume the message data!
        $msg = substr($this->dataBuffer, 0, $this->msgLen);
        if (self::$TRACE) {
            fwrite(STDERR, "stream::consumeMessage len: {$this->msgLen}\n");
            $h = $this->hexDump($msg);
            fwrite(STDERR, "message-data:\n" . $h . "\n");
        }
        $this->streamHandler->onMessage($msg);
        $this->dataBuffer = substr($this->dataBuffer , $this->msgLen);
    }

    // copied from: https://stackoverflow.com/questions/1057572/how-can-i-get-a-hex-dump-of-a-string-in-php
    private function hexDump($data, $newline="\n") {
        static $from = '';
        static $to = '';

        static $width = 16; # number of bytes per line
        static $pad = '.'; # padding for non-visible characters

        if ($from==='')
        {
            for ($i=0; $i<=0xFF; $i++)
            {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(bin2hex($data), $width*2);
        $chars = str_split(strtr($data, $from, $to), $width);

        $offset = 0;
        $ret = "";

        foreach ($hex as $i => $line)
        {
            $ret = $ret . sprintf('%6X',$offset).' : '.implode(' ', str_split($line,2)) . ' [' . $chars[$i] . ']' . $newline;
            $offset += $width;
        }

        return $ret;
    }

    public function close() {
        $this->streamHandler->onClose();
    }
}

class DockerBinaryStreamChunkConsumer extends DockerBinaryStreamBase implements ChunkConsumerInterface {

    public function __construct(DockerBinaryStreamHandler $streamHandler) {
        parent::__construct($streamHandler);
    }

    public function onChunk($data) {
        $this->processData($data);
    }

    public function onClose() {
    }
}

class DockerBinaryStream extends DockerBinaryStreamBase
{
    private $dockerSocket; // docker socket

    public function __construct($dockerSocket = null, DockerBinaryStreamHandler $streamHandler = null)
    {
        $this->dockerSocket = $dockerSocket;
        parent::__construct($streamHandler);
    }

    public function getDockerSocker() {
        return $this->dockerSocket;
    }

    public function setDockerSocker($sock) {
        $this->dockerSocket = $sock;
    }

    public function doClose() {
        fclose($this->dockerSocket);
        $this->dockerSocket = null;
    }

    // send data to docker socket (input data has been read from web socket)
    public function sendToDocker($msg) {
        return fwrite($this->dockerSocket, $msg);
    }
    
    public function handleReadData() {

        $data = fread($this->dockerSocket, 4096);

        if (self::$TRACE) {
            $len = strlen($data);
            fwrite(STDERR, "dockerStream::handleData len: {$len} data: {$data} --\n");
        }

        $this->processData($data);

        if ($data === false || $data == "") {
            $this->close();
        }
    }
}
