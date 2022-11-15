<?php namespace DockerRest;

use GuzzleHttp\Psr7\Message;

class HttpHandler {
    const EOF_HDR = "\r\n\r\n";
    const EOF_LINE = "\r\n";
    protected $sock;
    protected string $buffer;

    public function __construct($sock) {
        $ret = stream_set_blocking($sock, false);
        if ($ret === false) {
            fwrite(STDERR, "Can't set stream to non blocking mode\n");
        }
        $this->sock = $sock;
        $this->buffer = "";
    }

    public function getSocket() {
        return $this->sock;
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
                $this->buffer = substr($this->buffer, $pos + strlen(self::EOF_HDR));
                return $ret;
            }
        }
    }

    protected function parseTransferEncodingBody($hdr) {
        $requestData = "";

        $httpHeaderValue = null;
        if ($hdr->hasHeader("Transfer-Encoding")) {
            $httpHeaderValue = $hdr->getHeader("Transfer-Encoding");
        }

        if ($httpHeaderValue != null && array_key_exists(0, $httpHeaderValue) && $httpHeaderValue[0] == "chunked") {

            $hasChunkLen = false;
            $len = 0;

            while (True) {
                if ($this->buffer == "") {
                    if (!$this->readSocket()) {
                        return false;
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
