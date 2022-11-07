<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/src/base/runner.php';

final class RunnerTest extends TestCase
{
    public function testRunner() : void
    {
        $runner = new base\Runner("docker info --format='{{json .}}'", True);

        $result = $runner->run();
        //var_dump($result);
        $this->assertTrue($result[0]["Driver"] == "overlay2");
        $this->assertTrue($result[0]["ID"] != "");
    }

    public function testDockerRunner() : void {
        $runner = new base\Runner("docker pull fedora", False);
        $ret = $runner->run();

        list ($ret, $pipes) = base\runAndReturnPipes("docker run -i fedora /bin/sh");
        $this->assertTrue(is_resource($ret));

        $this->assertTrue(get_resource_type($pipes[0]) == "stream");
        $this->assertTrue(get_resource_type($pipes[1]) == "stream");

        $cmd = "ls /\n";
        $res = fwrite($pipes[0],$cmd, strlen($cmd));
        $this->assertFalse($res === false);

        //$res = stream_get_contents($pipes[2]);
        //echo "error {$res}\n";

        $cmdRes = fread($pipes[1],1024);


        $res = fwrite($pipes[0],"exit\r\n");
        $this->assertFalse($res === false);


        $lsResult ="afs\nbin\nboot\ndev\netc\nhome\nlib\nlib64\nlost+found\nmedia\nmnt\nopt\nproc\nroot\nrun\nsbin\nsrv\nsys\ntmp\nusr\nvar\n";
        $this->assertEquals($cmdRes, $lsResult);

        proc_close($ret);
    }
}
