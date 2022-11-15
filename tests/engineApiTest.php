<?php declare(strict_types=1);

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

}
