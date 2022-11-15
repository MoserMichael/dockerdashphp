<?php namespace DockerRest;

interface DockerBinaryStreamHandler {
    public function onMessage($data);
    public function onClose();
}

