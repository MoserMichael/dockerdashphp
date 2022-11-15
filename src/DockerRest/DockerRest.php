<?php namespace DockerRest;

require_once __DIR__ . "/Http.php";

class DockerEngineApi extends HttpHandler {

    private static $dockerApiVersion = "v1.41";

    public static function setApiVersion(string $version) : void {
        self::$dockerApiVersion = $version;
    }

    public function __construct($sock = null) {

        if ($sock == null) {
            $sock = fsockopen("unix:///var/run/docker.sock");
            if ($sock === false) {
                fwrite(STDERR, "Can't connect docker socket\n");
            }
        }

        parent::__construct($sock);
    }

    public function execResize(string $execId, int $rows, int $cols) : bool {
        if ($this->sendExecResize($execId, $rows, $cols)) {
            $hdr = $this->readHttpResponseHeader();
            if ($hdr != null) {

                $stat = $hdr->getStatusCode();
                if ($stat == 200) {
                    fwrite(STDERR, "wrong exec create response status: {$stat}\n");
                    return true;
                } else {
                    fwrite(STDERR, "exec rize returned {$stat}, expected 200\n");
                }
            } else {
                fwrite(STDERR, "Can't parse http header for exec create request\n");
            }
        }
        return false;
    }


    private function sendExecResize(string $execId, int $rows, int $cols) : bool {
        $ver = self::$dockerApiVersion;
        $resizeRequest
            = "POST /{$ver}/exec/{$execId}/resize?h={$rows}&w={$cols} HTTP/1.1\r\n" .
            "Host: localhost\r\nAccept: */*\r\nContent-Type: application/json\r\n\r\n";

        if (fwrite($this->sock, $resizeRequest) != strlen($resizeRequest)) {
            fwrite(STDERR, "Can't send exec resize http request to docker socket\n");
            return false;
        }
        return true;
    }

    public function exec(string $containerId, string $shellPath = "/bin/sh") : array {
        $execId = $this->execCreate($containerId, $shellPath);
        if ($execId != "") {
            return array($this->execUpgrade($execId), $execId);
        } else  {
            fwrite(STDERR, "exec create failed\n");
        }
        return array(false, "");
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




