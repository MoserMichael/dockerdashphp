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
        while(true) {
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

        if ($httpHeaderValue != null && array_key_exists(0, $httpHeaderValue)  && $httpHeaderValue[0] == "chunked") {

            $hasChunkLen = false;
            $len = 0;

            while(True) {
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

class DockerEngineApi extends HttpHandler {

    private static $dockerApiVersion = "v1.41";

    public static function setApiVersion(string $version) : void {
        self::$dockerApiVersion = $version;
    }


    public function __construct() {

        $sock = fsockopen("unix:///var/run/docker.sock");
        if ($sock === false) {
            fwrite(STDERR, "Can't connect docker socket\n");
        }

        parent::__construct($sock);
    }

    public function exec(string $containerId, string $shellPath = "/bin/sh") : bool {
        $execId = $this->execCreate($containerId, $shellPath);
        if ($execId != "") {
            return $this->execUpgrade($execId);
        } else  {
            fwrite(STDERR, "exec create failed\n");
        }
        return false;
    }

    private function execCreate(string $containerId, string $shellPath) : string {
        if (!$this->sendExecCreateRequest($containerId, $shellPath)) {
            return "";
        }

        $hdr = $this->readHttpResponseHeader();
        if ($hdr != null) {

            $stat = $hdr->getStatusCode();
            if ($stat == 201) {
                $requestData = $this->parseTransferEncodingBody($hdr);

                $arr = json_decode($requestData, true);
                if (array_key_exists("Id", $arr)) {
                    return $arr["Id"];
                }
                fwrite(STDERR, "wrong exec create response: {$requestData}\n");
            } else {
                fwrite(STDERR, "exec create returned {$stat}, expected 201\n");
            }
        } else {
            fwrite(STDERR, "Can't parse http header for exec create request\n");
        }
        return "";
    }

    private function execUpgrade(string $idExec) : bool {
        if (!$this->sendExecUpgradeRequest($idExec)) {
            fwrite(STDERR, "can't send exec upgrade request\n");
            return false;
        }
        $hdr = $this->readHttpResponseHeader();
        if ($hdr == null) {
            fwrite(STDERR, "Can't parse http header for exec exec request\n");
            return false;
        }
        $code = $hdr->getStatusCode();

        if ($code != 101) {
            fwrite(STDERR, "wrong status for upgrade request {$code}\n");
            return false;
        }
        return true;
    }

    private function sendExecUpgradeRequest(string $idExec) : bool {
        $jsonArr = array(
            "id" => $idExec,
            "ExecStartConfig" => array("Tty" => true),
        );
        $json = json_encode($jsonArr);
        $jsonLen = strlen($json);

        $ver = self::$dockerApiVersion;
        $execRequest = "POST /{$ver}/exec/{$idExec}/start HTTP/1.1\r\n" .
            "Host: localhost\r\nContent-Length: {$jsonLen}\r\nUpgrade: tcp\r\nConnection: Upgrade\r\nAccept: */*\r\nContent-Type: application/json\r\n\r\n{$json}";

        if (fwrite($this->sock, $execRequest) != strlen($execRequest)) {
            fwrite(STDERR, "Can't send exec upgrade http request to docker socket\n");
            return false;
        }
        return true;
    }

    private function sendExecCreateRequest(string $containerId, string $shell) : bool {
        $jsonArr = array(
            "id" => $containerId,
            "AttachStdin" => true,
            "AttachStdout" => true,
            "AttachStderr" => true,
            "DetachKeys" => "ctrl-p,ctrl-q",
            "Tty" => true,
            "Cmd" =>  array($shell)
        );
        $json = json_encode($jsonArr);
        $jsonLen = strlen($json);

        $ver = self::$dockerApiVersion;
        $createExecInstanceRequest
            = "POST /{$ver}/containers/{$containerId}/exec HTTP/1.1\r\n" .
            "Host: localhost\r\nContent-Length: {$jsonLen}\r\nAccept: */*\r\nContent-Type: application/json\r\n\r\n{$json}";

        $len = strlen($createExecInstanceRequest);
        if (fwrite($this->sock, $createExecInstanceRequest) !== $len) {
            fwrite(STDERR, "Can't send exec create http request to docker socket\n");
            return false;
        }
        return true;
    }

}