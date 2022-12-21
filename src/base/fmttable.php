<?php namespace base;

function human_readable_size($size) {
    $sizes = array( "", "KB", "MB", "GB", "TB", "EB", "ZB" );
    $prevLimit = 1;
    $limit = 1024;

    $i = 0;
    foreach( $sizes as $suffix) {
        if ($size < $limit) {
            $sval = strval($size/$prevLimit );
            $pos = strpos($sval,".");
            if ($pos !== false) {
                $sval = substr($sval, 0, $pos+2);
            }
            return $sval . $suffix;
        }
        $prevLimit = $limit;
        $limit *= 1024;
        $i += 1;
    }
    return strval($size);

}

class FmtTable {

    private array $tbl_def;

    public function __construct(array $tbl_def) {
        $this->tbl_def = $tbl_def;
    }

    public function show_header() : string {
        $ret = '<table class="js-sort-table"><thead>';
        $keys = array_keys($this->tbl_def);
        foreach( $keys as $key) {
            $ret = $ret . "<th>{$key}</th>";
        }
        return $ret . "</thead>";
    }

    public function format(array $json) : string {
        $ret = $this->show_header();

        #iterate over rows
        foreach( $json as $row) {
            $ret = $ret . "\n<tr>";
            foreach( $this->tbl_def as $tbl_def_key => $tbl_def_val) {

                if (is_array($tbl_def_val)) {
                    $val_key =  $tbl_def_val[0];
                    $val_func = $tbl_def_val[1];
                    
                    $val = $row[ $tbl_def_key ] ?? null;
                    $val = call_user_func($val_func, $val, $row);
                } else if (is_string($tbl_def_val)) {
                    $val = $row[ $tbl_def_val ] ?? null;
                }

                if ($val == null) {
                    $val = "";
                }

                $ret =  $this->add_td($ret, $val);
            }
            $ret = $ret . "\n</tr>";
        }

        return $ret . "</table>";
    }

    private function add_td(string $ret, string $text) : string  {
        return $ret . "<td>" . $text . "</td>";
    }

    public function format_row(array $json) : string
    {
        $ret = $this->show_header();
        return $this->one_json_row($ret, $json, True);
    }

    public function format_row_raw(array $json) : string
    {
        $ret = $this->show_header();
        return $this->one_json_row($ret, $json, False);
    }

    private function one_json_row(string $ret, array $data, bool $is_json) : string {
        $json_pretty = $is_json ? json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : implode("\n",$data);
        $json = "<pre>" . $json_pretty . "</pre>";
        return $ret . "<tr>" . $this->add_td("", $json) . "</tr>" . "</table>";
    }

    public function echo_from_generator(\Generator $gen) : void {
        echo $this->show_header();
        echo "<tr><td><pre>\n";

        foreach($gen as $line) {
            echo $line;
        }

        echo "\n</pre></td></tr></table>";
    }

}

class FmtHeader {

    private $tbl_def;

    public function __construct(array $tbl_def) {
        $this->tbl_def = $tbl_def;
    }

    public function format(int $idx) : string {

        $cnt = 0;
        $ret = "";

        foreach($this->tbl_def as $title => $link) {

            if ($cnt != $idx) {
                $ret = $ret . "[ <a href='{$link}'>{$title}</a> ] ";
            } else {
                $ret = $ret . "<b>[ {$title} ]</b>";
            }
            $ret = $ret . " &nbsp; ";
            $cnt = $cnt + 1;
        }

        return $ret . "<hr/>";
    }
}
