<?php namespace DockerRest;

require_once __DIR__ . "/Http.php";


class DockerEngineAuthentication {

    private $encoded = null;

    public function __construct(string $username, string $password, string $authtoken) {
        $auth_arr = null;
        if ($authtoken != null) {
            $auth_arr = array('username' => $username, 'password' => $password);
        } else if ($username != null && $password != null) {
            $auth_arr = array('identitytoken' => $authtoken);
        }

        if ($auth_arr != null) {
            $auth_str = json_encode($auth_arr);
            if ($auth_str != null) {
                $this->encoded = base64_encode($auth_str);
            }
        }
    }

    public function getEncoded() : string {
        $ret = $this->encoded;
        $this->encoded = "";
        return $ret;
    }
}

class DockerEngineApi extends HttpHandler {

    private static $dockerApiVersion = "v1.41";
    
    public static function setApiVersion(string $version) : void {
        self::$dockerApiVersion = $version;
    }
    
    public static function openDockerSocket() {
        $sock = fsockopen("unix:///var/run/docker.sock");
        if ($sock === false) {
            fwrite(STDERR, "Can't connect docker socket\n");
        }
        return $sock;
    }

    public function __construct($sock = null, ChunkConsumerInterface $chunkConsumer = null) {
        if ($sock == null) {
            $sock = self::openDockerSocket();
        }
        if ($sock === false) {
            fwrite(STDERR, "Can't connect docker socket\n");
        }

        $api = getenv("DOCKER_API_VERSION");
        if ($api !== false) {
            self::$dockerApiVersion = $api;
        }

        parent::__construct($sock, $chunkConsumer);
    }

    public function copyTarFileToContainer(string $id, string $localPath, string $containerPath) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/archive?path={$containerPath}";
        $hdr = "Content-Type: application/x-tar";
        $body = file_get_contents($localPath);

        $len = strlen($body);

        return $this->sendCommonRequest($url, $body, 200, self::MethodPut, $hdr);
    }

    public function containerCreate($body)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/create";
        return $this->sendCommonRequest($url, $body, 201, self::MethodPost);
    }

    public function containerStart(string $id)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/start";
        return $this->sendCommonRequest($url, null, 204, self::MethodPost);
    }

    public function containerLogs(string $id, $followLogs, $from = -1, $to = -1) {
        $ver = self::$dockerApiVersion;
        
        $times = "";
        
        if ($from != -1) {
            $times = "{$times}&since={$from}";
        }

        if ($to != -1) {
            $times = "{$times}&until={$to}";
        }
        
        $url = "/{$ver}/containers/{$id}/logs?follow={$followLogs}&stdout=true&stderr=true&timestamps=true{$times}";

        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    public function containerPause($id) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/pause";
        return $this->sendCommonRequest($url, null, 204, self::MethodPost);
    }

    public function containerStop(string $id) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/stop";
        return $this->sendCommonRequest($url, null, 204, self::MethodPost);
    }

    public function containerKill(string $id) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/kill";
        return $this->sendCommonRequest($url, null, 204, self::MethodPost);
    }

    public function containerResume(string $id) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/unpause";
        return $this->sendCommonRequest($url, null, 204, self::MethodPost);
    }

    public function containerDiff(string $id) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/changes";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }


    public function containerStats(string $id, $stream=false) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/stats?stream={$stream}";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    public function containerProcessList(string $id) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/top";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }
    
    public function containerPrune() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/prune";
        return $this->sendCommonRequest($url, null, 200, self::MethodPost);
    }

    public function imageHistory(string $imageName)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/images/{$imageName}/history";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    public function volumeCreate($json) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/volumes/create";
        return $this->sendCommonRequest($url, $json, 201, self::MethodPost);
    }

    public function volumeList() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/volumes";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    public function volumePrune() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/volumes/prune";
        return $this->sendCommonRequest($url, null, 200, self::MethodPost   );
    }

    public function imagePull(string $imageName, DockerEngineAuthentication $auth = null, string $tag = null) {
        $ver = self::$dockerApiVersion;
        $hdr = "";
        $data = null;

        $urlArg="";
        if ($tag != null) {
            $urlArg= "&tag={$tag}";
        }
        if ($auth != null) {
            $data = $auth->getEncoded();
        }

        $url = "/{$ver}/images/create?fromImage={$imageName}{$urlArg}";
        return $this->sendCommonRequest($url, null, 200, self::MethodPost, $hdr, $data);
    }


    public function imageRemove(string $id) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/images/{$id}";
        return $this->sendCommonRequest($url, null, 200, self::MethodDelete);
    }

    public function imagePrune()
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/images/prune";
        return $this->sendCommonRequest($url, null, 200, self::MethodPost);
    }

    public function inspectContainer(string $id)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/${id}/json";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    public function inspectImage(string $id) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/images/${id}/json";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    public function imageSearch(string $term) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/images/search?term={$term}";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    //*** list images
    public function imageList($show_all = true) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/images/json?all=true&digests=";
        if ($show_all) {
            $url .= "true";
        } else {
            $url .= "false";
        }

        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    //*** list containers
    public function containersList($show_all = true) {
        $ver = self::$dockerApiVersion;
        $all = "false";
        if ($show_all) {
            $all = "true";
        }
        $url = "/{$ver}/containers/json?all={$all}&size=true";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet );
    }

    //*** docker info
    public function dockerInfo() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/info";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet );
    }

    //*** docker version
    public function dockerVersion() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/version";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet );
    }

    //*** docker info
    public function df() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/system/df";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet );
    }

    //*** resize of exec
    public function execResize(string $execId, int $rows, int $cols)  {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/exec/{$execId}/resize?h={$rows}&w={$cols}";
        return $this->sendCommonRequest($url, null, 200, self::MethodPost);
    }

    //*** exec handshake (until connection upgrade)
    public function exec(string $containerId, string $shellPath = "/bin/sh") : array {
        $execId = $this->execCreate($containerId, $shellPath);
        if ($execId != "") {
            list($ok) = $this->execUpgrade($execId);
            return array($ok, $execId);
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
        list($ok, $body, $hdr) = $this->sendCommonRequest($url, $jsonArr, 201 );
            
        if ($ok !== false) {
            $arr = json_decode($body, true);
            if (array_key_exists("Id", $arr)) {
                return $arr["Id"];
            }
        }

        fwrite(STDERR, "wrong exec create response: {$body}\n");
        return "";
    }

    private function execUpgrade(string $idExec) {
        $jsonArr = array(
            "id" => $idExec,
            "ExecStartConfig" => array("Tty" => true),
        );

        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/exec/{$idExec}/start";
        $customHdr = "\r\nUpgrade: tcp\r\nConnection: Upgrade";
        return $this->sendCommonRequest($url, $jsonArr, 101, self::MethodPost, $customHdr);
    }
}




