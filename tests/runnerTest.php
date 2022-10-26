<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/src/base/runner.php';

final class RunnerTest extends TestCase
{
    public function testRunner() : void
    {
        $runner = new base\Runner("docker info --format='{{json .}}'");

        $result = $runner->run();

        $this->assertTrue($result["Driver"] == "overlay2");
        $this->assertTrue($result["ID"] != "");
    }
}
