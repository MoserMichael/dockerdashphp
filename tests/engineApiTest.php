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
        $images = $api->imageList();
        echo "images:\n$images\n";
        $this->assertTrue($images != "");
    }

    public function testContainers() : void
    {
        $api = new DockerEngineApi();
        $containers  = $api->containersList();
        echo "containers:\n$containers\n";
        $this->assertTrue( $containers != "" );
    }

    public function testInfo() : void
    {
        $api = new DockerEngineApi();
        $txt  = $api->info();
        echo "info:\n$txt\n";
        $this->assertTrue( $txt != "" );
    }

    public function testVersion() : void
    {
        $api = new DockerEngineApi();
        $txt  = $api->version();
        echo "version:\n$txt\n";
        $this->assertTrue( $txt != "" );
    }

    public function testDF() : void
    {
        $api = new DockerEngineApi();
        $txt  = $api->df();
        $this->assertTrue( $txt != "" );
        echo "after df\n";
    }

    public function testImageSearch() {
        $api = new DockerEngineApi();
        $txt  = $api->imageSearch("fedora");
        echo "docker search\n$txt\n";
        $this->assertTrue( $txt != "" );
    }

}
