<?php namespace base;

use phpDocumentor\Reflection\Types\Resource_;

function endsWith($haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
}

class TmpFileRunner
{
    private string $command;
    private $res;
    private $outFile;

    public function __construct(string $command)
    {
        $this->command = $command . " 2>&1";
    }

    public function run() : void {
        $this->delete();

        $descriptorSpec = array(
            1 => array("pipe", "w"),
        );
        $this->res = proc_open($this->command, $descriptorSpec, $pipes);
        if (is_resource($this->res)) {
            $this->outFile = $pipes[1];
        }
    }

    public function lineGenerator() : \Generator {

        if ($this->outFile != null) {
            while (($line = fgets($this->outFile)) !== false) {
                yield $line;
            }

            $this->delete();
        }
    }

    public function delete() : void {
        if (is_resource($this->res)) {
            fclose($this->outFile);
            proc_close($this->res);

            $this->outFile = null;
            $this->res = null;
        }
    }
}


class Runner {

    private string $command;
    private bool $dump;
    private bool $is_json;

    public function __construct(string $command, $is_json=True) {
        $this->command = $command . " 2>&1";
        $this->is_json = $is_json;
        $this->dump = false;
    }

    public function run() {

        $output = shell_exec($this->command);
        if ($output == null) {
            $output = "\n";
        }
        if ($output != null) {
            if ($this->is_json) {
                $ret = $this->slurp($output);
            } else {
                $ret = explode("\n",$output);
            }

            if ($this->dump) {
               var_dump("Result of {$this->command}", $ret);
            }
            return $ret;
        }
        return null;
    }

    public function slurp(string $json_string) : array {
        $arr = explode("\n", $json_string);
        $ret = "[" . rtrim(implode( ",", $arr), ",") . "]";
        return json_decode($ret, true);
    }
}


