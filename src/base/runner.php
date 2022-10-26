<?php namespace base;

function endsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
}

class Runner {

    private $command;
    private $dump;
    private $is_json;

    public function __construct(string $command, $is_json=True, $dump=False) {

        $is_background = False;
        if (endsWith($command, '&')) {
            $command = rtrim($command);
            $is_background = True;
        }
        $this->command = $command . " 2>&1";
        if ($is_background) {
            $command = $command . " &";
        }
        $this->is_json = $is_json;
        $this->dump = $dump;
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


