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

        $api = getenv("DOCKER_API_VERSION");
        if ($api !== false) {
            self::$dockerApiVersion = $api;
        }

        parent::__construct($sock);
    }

    //*** list images
    public function imageList() : string {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/images/json?all=true&digests=true";
        list($hdr, $body) = $this->sendCommonRequest($url, null, 200, self::MethodGet);
        return $body;
    }

    //*** list containers
    public function containersList() : string {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/json?all=true&size=true";
        list($hdr, $body) = $this->sendCommonRequest($url, null, 200, self::MethodGet );
        return $body;
    }

    //*** docker info
    public function info() : string {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/info";
        list($hdr, $body) = $this->sendCommonRequest($url, null, 200, self::MethodGet );
        return $body;
    }

    //*** docker version
    public function version() : string {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/version";
        list($hdr, $body) = $this->sendCommonRequest($url, null, 200, self::MethodGet );
        return $body;
    }

    //*** docker info
    public function df() : string {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/system/df";
        list($hdr, $body) = $this->sendCommonRequest($url, null, 200, self::MethodGet );
        return $body;
    }

    //*** resize of exec
    public function execResize(string $execId, int $rows, int $cols) : bool {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/exec/{$execId}/resize?h={$rows}&w={$cols}";
        list($hdr, $body) = $this->sendCommonRequest($url, null, 200, self::MethodPost);
        return $hdr !== false;
    }

    //*** exec handshake (until connection upgrade)
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
        $jsonArr = array(
            "id" => $containerId,
            "AttachStdin" => true,
            "AttachStdout" => true,
            "AttachStderr" => true,
            "DetachKeys" => "ctrl-p,ctrl-q",
            "Tty" => true,
            "Cmd" =>  array($shellPath)
        );

        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$containerId}/exec";
        list($hdr, $body) = $this->sendCommonRequest($url, $jsonArr, 201 );
            
        if ($hdr !== false) {
            $arr = json_decode($body, true);
            if (array_key_exists("Id", $arr)) {
                return $arr["Id"];
            }
        }

        fwrite(STDERR, "wrong exec create response: {$body}\n");
        return "";
    }

    private function execUpgrade(string $idExec) : bool {
        $jsonArr = array(
            "id" => $idExec,
            "ExecStartConfig" => array("Tty" => true),
        );

        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/exec/{$idExec}/start";
        $customHdr = "\r\nUpgrade: tcp\r\nConnection: Upgrade";
        list($hdr, $body) = $this->sendCommonRequest($url, $jsonArr, 101, self::MethodPost, $customHdr);
        return $hdr !== null;        
    }
}




