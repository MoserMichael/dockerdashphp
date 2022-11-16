<?php namespace DockerRest;

use GuzzleHttp\Psr7\Message;

class HttpHandler {
    const EOF_HDR = "\r\n\r\n";
    const EOF_LINE = "\r\n";
    const TRACE = false;  // set to on for tracing of requests/responses
    const TRACE_CHUNK = false;  // set to on for tracing of requests/responses

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
                if ($stat == $expectedStatus) {
                    $body = $this->parseTransferEncodingBody($hdr);
                    return array($hdr, $body);
                }
                $stat = $hdr->getStatusCode();
                fwrite(STDERR, "Status {$stat} not expected for {$url}\n");
            }
        }
        return array(false, null);
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

        if (self::TRACE) {
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

                if (self::TRACE) {
                    fwrite(STDERR, "Response\n========\n{$msg}\n");
                }

                $ret = Message::parseResponse($msg); // strange parser
                $this->buffer = substr($this->buffer, $pos + strlen(self::EOF_HDR));
                return $ret;
            }
        }
    }

    protected function parseTransferEncodingBody($hdr) {
        $responseData = "";

        $httpHeaderValue = null;
        if ($hdr->hasHeader("Transfer-Encoding")) {
            $httpHeaderValue = $hdr->getHeader("Transfer-Encoding");
        }

        if ($httpHeaderValue != null &&
            array_key_exists(0, $httpHeaderValue) &&
            $httpHeaderValue[0] == "chunked") {
            
            $hasChunkLen = false;
            $len = 0;

            while (True) {

                if (!$hasChunkLen) {
                    $pos = strpos($this->buffer, self::EOF_LINE);
                    if (!($pos === false)) {
                        $msg = substr($this->buffer, 0, $pos);
                        $len = hexdec($msg);
                        $this->buffer = substr($this->buffer, $pos + strlen(self::EOF_LINE));

                        if (self::TRACE_CHUNK) {
                            fwrite(STDERR, "chunk-len: {$len}\n");
                        }

                        if ($len == 0) {
                            break;
                        }
                        $hasChunkLen = true;
                    } else {
                        if (!$this->readSocket()) {
                            fwrite(STDERR, "socket read error\n");
                            return false;
                        }
                    }
                } else if ($hasChunkLen) {

                    // did we read the chunk - consume the chunk
                    if (strlen($this->buffer) >= $len) {
                        $chunkData = substr($this->buffer, 0, $len);
                        $this->buffer = substr($this->buffer, $len);
                        if (strlen($this->buffer) >= strlen(self::EOF_LINE)) {
                            $str = substr($this->buffer, 0, strlen(self::EOF_LINE));
                            if ($str == self::EOF_LINE) {
                                $this->buffer = substr($this->buffer, strlen(self::EOF_LINE));
                            }
                        }
                        $responseData = $responseData . $chunkData;
                        $hasChunkLen = false;
                    } else {
                        if (!$this->readSocket()) {
                            fwrite(STDERR, "socket read error\n");
                            return false;
                        }
                    }
                }


            }
        }

        if (self::TRACE_CHUNK) {
            $l = strlen($responseData);
            fwrite(STDERR, "Body-len {$l}\n");
        }

        return $responseData;
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
        $l = strlen($ret);
        $this->buffer = $this->buffer . $ret;
        return true;
    }
}
