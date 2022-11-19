<?php namespace DockerRest;

use GuzzleHttp\Psr7\Message;

if(!defined('STDIN'))  define('STDIN',  fopen('php://stdin',  'rb'));
if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'wb'));
if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'wb'));

interface ChunkConsumerInterface {
    public function onChunk($data);
    public function onClose();
}

class HttpHandler {
    const EOF_HDR = "\r\n\r\n";
    const EOF_LINE = "\r\n";
    private static $TRACE = true;  // set to on for tracing of requests/responses
    private static $TRACE_CHUNK = true;  // set to on for tracing of requests/responses

    protected $sock;
    protected string $buffer;
    protected $chunkConsumer;

    private $state;
    private $chunkLen;

    const StateReadingChunkHdr = 1;
    const StateReadingChunkData = 2;
    const StateReadingChunkEof = 3;
    const StateFinishedAllChunks = 4;

    public function __construct($sock, $chunkConsumer = null) {
        $ret = stream_set_blocking($sock, false);
        if ($ret === false) {
            fwrite(STDERR, "Can't set stream to non blocking mode\n");
        }
        $this->sock = $sock;
        $this->buffer = "";
        $this->chunkConsumer = $chunkConsumer;
        $this->state = self::StateReadingChunkHdr;
    }

    public static function setTrace($trace, $dataTrace) {
        self::$TRACE = $trace;
        self::$TRACE_CHUNK = $dataTrace;
    }

    public function getSocket() {
        return $this->sock;
    }

    public const MethodGet  = 1;
    public const MethodHead = 2;
    public const MethodPost = 3;
    public const MethodPut  = 4;
    public const MethodDelete  = 5;
    public const MethodConnect = 6;
    public const MethodOptions = 7;
    public const MethodTrace = 8;
    public const MethodPatch = 9;

    const MethodNames  = array(
        self::MethodGet => "GET",
        self::MethodHead => "HEAD",
        self::MethodPost => "POST",
        self::MethodPut => "PUT",
        self::MethodDelete => "DELETE",
        self::MethodConnect => "CONNECT",
        self::MethodOptions => "OPTIONS",
        self::MethodTrace => "TRACE",
        self::MethodPatch => "PATCH",
    );

    protected function sendCommonRequest(string $url,
                                         array $requestData = null,
                                         int $expectedStatus = 200,
                                         int $method = self::MethodPost,
                                         string $customHdr = "") : array {
        if ($this->sendHeaderCommon($url, $requestData, $method, $customHdr)) {
            $hdr = $this->readHttpResponseHeader();
            if ($hdr != null) {
                $stat = $hdr->getStatusCode();
                $body = $this->parseTransferEncodingBody($hdr);

                if ($stat == $expectedStatus) {
                    return array(true, $body, $hdr);
                }
                fwrite(STDERR, "Status {$stat} not expected for {$url}\n");
                return array(false, $body, $hdr);
            }
        }
        return array(false, null, null);
    }

    protected function sendHeaderCommon(string $url, array $request = null, int $method = self::MethodPost, $customHdr="") : bool {
        $json = "";
        $contentLen = "";

        if ($request != null) {
            $json = json_encode($request);
            $jsonLen = strlen($json);
            $contentLen = "Content-Length: {$jsonLen}\r\n";
        }

        $methodName = self::MethodNames[$method];
        $requestText
            = "{$methodName} {$url} HTTP/1.1\r\n" .
            "Host: localhost\r\n{$contentLen}Accept: */*\r\nContent-Type: application/json{$customHdr}\r\n\r\n{$json}";

        if (self::$TRACE) {
            fwrite(STDERR, "Request\n=======\n{$requestText}\n");
        }

        $len = strlen($requestText);
        if (fwrite($this->sock, $requestText) !== $len) {
            fwrite(STDERR, "Can't send {$urlCommonPart} http request to docker socket\n");
            return false;
        }
        return true;
    }

    public function readHttpResponse() {
        $hdr = $this->readHttpResponseHeader();
        $body = false;
        if ($hdr != null) {
            $body = $this->parseTransferEncodingBody($hdr);
        }
        return array($hdr, $body);
    }

    protected function readHttpResponseHeader() {
        while (true) {
            if (!$this->readSocket()) {
                return null;
            }

            $pos = strpos($this->buffer, self::EOF_HDR);
            if (!($pos === false)) {
                $msg = substr($this->buffer, 0, $pos + strlen(self::EOF_HDR));

                $ret = Message::parseResponse($msg); // strange parser

                if (self::$TRACE) {
                    $has_response = $ret !== null;
                    fwrite(STDERR, "Response-hdr ${has_response}\n============\n{$msg}\n");
                }

                $this->buffer = substr($this->buffer, $pos + strlen(self::EOF_HDR));
                return $ret;
            }
        }
    }

    protected function parseTransferEncodingBody($hdr) {

        $httpHeaderValue = null;
        if ($hdr->hasHeader("Transfer-Encoding")) {
            $httpHeaderValue = $hdr->getHeader("Transfer-Encoding");
        }

        if ($httpHeaderValue != null &&
            array_key_exists(0, $httpHeaderValue) &&
            $httpHeaderValue[0] == "chunked") {
            return $this->parseChunks();
        }

        return "";
    }

    private function parseChunks() {
        $this->state =  self::StateReadingChunkHdr;
        $responseData = "";
        $rd = true;
        while($this->state != self::StateFinishedAllChunks) {
            $resp = $this->consumeData();
            $responseData .=  $resp;
            if ($rd === false || $this->state == self::StateFinishedAllChunks) {
                break;
            }
            $rd = $this->readSocket();
        }
        return $responseData;
    }

    public function consumeData() {
        $responseData = "";
        $consumeData = true;


        while ($consumeData) {
            switch ($this->state) {
                case self::StateReadingChunkHdr:
                    $pos = strpos($this->buffer, self::EOF_LINE);
                    if ($pos !== false) {
                        $msg = substr($this->buffer, 0, $pos);
                        $this->chunkLen = hexdec($msg);
                        $this->buffer = substr($this->buffer, $pos + strlen(self::EOF_LINE));
                        $this->state = self::StateReadingChunkData;

                        if (self::$TRACE_CHUNK) {
                            fwrite(STDERR, "chunk-len: {$this->chunkLen}\n");
                        }

                    } else {
                        fwrite(STDERR, "no chunk len\n");
                        $consumeData = false;
                        break;
                    }
                //fallthrough

                case self::StateReadingChunkData:
                    $len = strlen($this->buffer);
                    if ($len >= $this->chunkLen) {
                        if ($this->chunkLen > 0) {
                            $msg = substr($this->buffer, 0, $this->chunkLen);

                            if (self::$TRACE_CHUNK) {
                                fwrite(STDERR, "chunk-data: {$msg}\n");
                            }

                            if ($this->chunkConsumer != null) {
                                $this->chunkConsumer->onChunk($msg);
                            } else {
                                $responseData = $responseData . $msg;
                            }

                            $this->buffer = substr($this->buffer, $this->chunkLen);
                        }
                        $this->state = self::StateReadingChunkEof;
                    } else {
                        $consumeData = false;
                        break;
                    }
                //fallthrough

                case self::StateReadingChunkEof:
                    $len = strlen($this->buffer);
                    $eofLen = 2;

                    $dump = bin2hex($this->buffer);
                    fwrite(STDERR, "want-eof {$eofLen} has: {$len} data: {$dump} \n");

                    if ($len >= $eofLen) {

                        fwrite(STDERR, "has-eof {$eofLen} \n");

                        if ($eofLen == 2) {
                            if (substr($this->buffer, 0, 2) != "\r\n") {
                                $this->state = self::StateReadingChunkHdr;
                            }

                            if ($this->chunkLen == 0) {
                                $this->state = self::StateFinishedAllChunks;
                                $consumeData = false;
                            } else {
                                $this->state = self::StateReadingChunkHdr;
                            }
                        }
                        $this->buffer = substr($this->buffer, $eofLen);
                        $dump = bin2hex($this->buffer);
                        fwrite(STDERR, "chunk-eof ok! state: {$this->state} buffer: {$dump}\n");

                    } else {
                        $consumeData = false;
                    }
                }
        }
        $l = strlen($this->buffer);
        fwrite(STDERR, "eof consumeData (buffer-len: {$l})\n");
        return $responseData;
    }
    
    protected function readSocket() {
        $r = array($this->sock);
        $w = array();
        $e = array();

        stream_select($r, $w, $e, null);

        $ret = fread($this->sock, 4096);
        if ($ret === false) {
            if (self::$TRACE) {
                fwrite(STDERR, "error reading\n");
            }
            return false;
        }
        if (self::$TRACE) {
            $l = strlen($ret);
            fwrite(STDERR, "read {$l}\n");
        }
        $this->buffer = $this->buffer . $ret;
        return true;
    }
}

class EventDrivenChunkParser extends HttpHandler {

    public function __construct($sock, $chunkConsumer) {
        parent::__construct($sock, $chunkConsumer);
    }

    public function getDockerSocker() {
        return $this->sock;
    }

    public function doClose() {
        fclose($this->sock);
    }

    public function handleData() {
        fwrite(STDERR, "handleData\n");
        
        $rd = $this->readSocket();
        $this->consumeData();

        if ($rd === false) {
            fwrite(STDERR, "chunkReader: onClose\n");
            $this->chunkConsumer->onClose();
        }
    }

}
