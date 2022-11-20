<?php declare(strict_types=1);

require_once dirname(__DIR__) . "/vendor/autoload.php";
require_once dirname(__DIR__)  . "/src/DockerRest/DockerRest.php";

use PHPUnit\Framework\TestCase;
use \DockerRest\DockerEngineApi;

final class EngineApiTes extends TestCase
{

    public function testImages() : void
    {
        $api = new DockerEngineApi();
        list($res, $images) = $api->imageList();
        echo "images:\n$images\n";
        
        $this->assertTrue($res);
        $this->assertTrue($images != "");
    }

    public function testContainers() : void
    {
        $api = new DockerEngineApi();
        list($res, $containers)  = $api->containersList();
        echo "containers:\n$containers\n";
        
        $this->assertTrue( $res );
        $this->assertTrue( $containers != "" );
    }

    public function testInfo() : void
    {
        $api = new DockerEngineApi();
        list($res,$txt)  = $api->dockerInfo();
        echo "info:\n$txt\n";
        $this->assertTrue( $txt != "" );
    }

    public function testVersion() : void
    {
        $api = new DockerEngineApi();
        list($res, $txt)  = $api->dockerVersion();
        echo "version:\n$txt\n";
        $this->assertTrue( $res );
        $this->assertTrue( $txt != "" );
    }

    public function testImageSearch() {
        $api = new DockerEngineApi();
        list ($txt)  = $api->imageSearch("fedora");
        echo "docker search\n$txt\n";
        $this->assertTrue( $txt != "" );
    }

    public function testImagePull()
    {
        $api = new DockerEngineApi();
        list($res) = $api->imagePull("ubuntu", "latest");
        $this->assertTrue($res);

        list ($res) = $api->imageRemove("ubuntu:latest");
        $this->assertTrue($res);
    }

}
