<?php namespace DockerRest;

require_once __DIR__ . "/Http.php";

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

    public function containerLogs($id, $followLogs) {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/logs?follow={$followLogs}&stdout=true&stderr=true&timestamps=true";

        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    public function containerPause($id)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/pause";
        return $this->sendCommonRequest($url, null, 204, self::MethodPost);
    }

    public function containerStop($id)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/stop";
        return $this->sendCommonRequest($url, null, 204, self::MethodPost);
    }

    public function containerResume($id)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/unpause";
        return $this->sendCommonRequest($url, null, 204, self::MethodPost);
    }

    public function containerStats($id, $stream=false)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/stats?stream={$stream}";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);

    }

    public function containerProcessList($id)
    {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/{$id}/top";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }
    
    public function containerPrune() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/prune";
        return $this->sendCommonRequest($url, null, 200, self::MethodPost);
    }

    public function imageRemove($id) {
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
    public function imageList() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/images/json?all=true&digests=true";
        return $this->sendCommonRequest($url, null, 200, self::MethodGet);
    }

    //*** list containers
    public function containersList() {
        $ver = self::$dockerApiVersion;
        $url = "/{$ver}/containers/json?all=true&size=true";
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




