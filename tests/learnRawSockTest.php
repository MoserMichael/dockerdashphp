<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/src/base/fmttable.php';

final class DockerStreamTest extends TestCase
{
    private static function getItemBetweenMarkers(string $from, string $start, string $end) : string {
        $pos = strpos($from, $start);
        if ($pos === false) {
            return "";
        }
        $pos += strlen($start);
        $posEnd = strpos($from, $end, $pos);
        if ($posEnd === false) {
            return "";
        }
        return substr($from, $pos, $posEnd - $pos);
    }

    public function setUp():void {
        $this->markTestSkipped("not needed for now");
    }

    public function testStream(): void {

        echo "learn raw sockets...\n";
        $dockerId = exec("docker run -d  fedora /bin/bash -c 'while true; do sleep 60; done'");
        $this->assertTrue($dockerId !== false);

        $sock = fsockopen("unix:///var/run/docker.sock");
        $this->assertTrue( $sock !== false );

        $jsonArr = array(
            "id" => $dockerId,
            "AttachStdin" => true,
            "AttachStdout" => true,
            "AttachStderr" => true,
            "DetachKeys" => "ctrl-p,ctrl-q",
            "Tty" => true,
            "Cmd" =>  array("/bin/bash")
        );
        $json = json_encode($jsonArr);
        $jsonLen = strlen($json);
        $createExecInstanceRequest
            = "POST /v1.41/containers/{$dockerId}/exec HTTP/1.1\r\n" .
            "Host: localhost\r\nContent-Length: {$jsonLen}\r\nAccept: */*\r\nContent-Type: application/json\r\n\r\n{$json}";

        echo "request: {$createExecInstanceRequest}\n";
        fwrite($sock, $createExecInstanceRequest);

        $execIdJson = stream_get_contents($sock);
        echo "response: {$execIdJson}\n";

        $this->assertTrue( $execIdJson != "" );

        $idExec = DockerStreamTest::getItemBetweenMarkers($execIdJson, '"Id":"', '"');
        echo "exec id: {$idExec}\n";

        $this->assertTrue( $idExec != "" );

        $jsonArr = array(
            "id" => $idExec,
            "ExecStartConfig" => array("Tty" => true),
        );
        $json = json_encode($jsonArr);
        $jsonLen = strlen($json);

        $execRequest = "POST /v1.41/exec/{$idExec}/start HTTP/1.1\r\n" .
            "Host: localhost\r\nContent-Length: {$jsonLen}\r\nUpgrade: tcp\r\nConnection: Upgrade\r\nAccept: */*\r\nContent-Type: application/json\r\n\r\n{$json}";

        echo "request: {$execRequest}\n";
        fwrite($sock, $execRequest);
        $execResponse = stream_get_contents($sock);
        echo "response: $execResponse\n";

        $hexResp = bin2hex($execResponse);
        echo "hexdump:\n{$hexResp}\n";

        // non existing process will give error:
        //�OCI runtime exec failed: exec failed: unable to start container process: exec: "/bin/bash_barabas": stat /bin/bash_barabas: no such file or directory: unknown

        $req = "ls /\r\n";
        fwrite($sock, $req);
        echo "request: {$req}\n";

        $execResponse = stream_get_contents($sock);
        echo "response: {$execResponse}\n";

        $hexResp = bin2hex($execResponse);
        echo "hexdump:\n{$hexResp}\n";

        fclose($sock);

        exec("docker kill {$dockerId}");
    }
}
