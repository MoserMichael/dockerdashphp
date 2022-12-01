<?php require_once __DIR__ . "/base/nocache.php"; ?>
<html xmlns="http://www.w3.org/1999/html">
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";

show_hdr(-1);

$image = escapeshellcmd($_GET['ID']);
$name = escapeshellcmd(@$_GET['name']);
$displayName = $name;
if ($displayName=="") {
    $displayName = $image;
}

$runner = new DockerRest\DockerEngineApi();
list($ok, $containerListJsonRaw) = $runner->containersList();
$runner->close();

?>


<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";


?>
<h3>Create & Run docker container with given image</h3>

Container with image: <?php echo "<a title='inspect image' href='/gen.php?cmd=inspecti&id={$image}'>{$displayName}</a>"; ?> will be run in detached mode
</p>

<script  src="/static-files/utils.js"></script>

<script>
    function onHealthCheck() {
        let hostRows = [ "healthRow1", "healthRow2"] ;
        show_rows_on_checkbox(hostRows, "health_check");
    }
    function onHostCfg() {
        let hostRows = [ "hostRow1", "hostRow2", "hostRow3", "hostRow4"];
        show_rows_on_checkbox(hostRows, "host_cfg");
    }

    function isLabelNameCh(ch) {
        return /^[a-zA-Z]\.-/i.test(ch);
    }

    function isSpace(ch) {
        return ch.trim() != "";
    }

    function isNotSpace(ch) {
        return !isSpace(ch);
    }

    function isInCharSet(ch, charSet) {
        return function(ch) {
            return charSet.indexOf(ch) != -1
        }
    }

    function skip_chars(value, offset, func) {
        while(offset < value.length) {
            let ch = value.charAt(offset);
            if (!func(ch)) {
                break;
            }
            offset += 1;
         }
         return offset;
    }

    function expect_ch(value, offset, expectedCh) {

        let after_spaces = skip_chars(value, offset, isSpace);

        if (after_spaces >= value.length) {
            return -1;
        }
        if (value.charAt(after_spaces) == expectedCh) {
            return after_spaces + 1;
        }
        return -1;
    }

    function parse_value(value, offset) {
        let ch = value.charAt(offset);
        let quote_char = '';
        if (ch == "'" || ch=='"') {
            quote_char = ch;
            let mode = 0;
            let next_offset = offset + 1;
            let ret_str = "";
            for(;next_offset < value.length; ++next_offset) {
                ch = value.charAt(next_offset);
                if (mode == 0) {
                    if (ch == '\\') {
                        mode = 1;
                    } else if (ch == quote_char) {
                        mode = 2;
                        break;
                    } else {
                        ret_str += ch;
                    }
                } else {
                    ret_str += ch;
                    mode = 0;
                }
            }
            return [ next_offset+ 1, ret_str ];
        }

        let next_offset = expect_ch(value, offset, isNotSpace);
        return [ next_offset, value.substring(offset, next_offset - offset) ];
    }

    function parse_label_name_value(value, offset) {
        let name_start = skip_chars(value, offset, isSpace);
        if (name_start >= value.length) {
            return null;
        }

        let name_end = skip_chars(isLabelNameCh);
        let name = value.substring(name_start, name_end);

        let after_eq = expect_ch(name_end, name_end, '=')
        if (after_eq == null) {
            return null;
        }
        let val_start = skip_chars(value, offset, isSpace);
        if (val_start >= value.length) {
            return null;
        }

        let val_end = parse_value(value, val_start);
        if (val_end == null) {
            return null;
        }
        value = value.substring(val_start, val_end);

        return [ val_end, name, value ];
    }

    function parse_labels(value) {
        let labels = {};

        value = value.trim();

        let offset = 0;
        while(offset < value.length) {
            let name_value_rec = parse_label_name_value(value, offset);
            if (name_value_rec == null) {
                break;
            }
            offset = name_value_rec[0];
            labels[ name_value_rec[1] ] = name_value_rec[2];

            offset = skip_chars(value, offset, isSpace);
            offset = skip_chars(value, offset, isInCharSet(";"));
        }

        return labels;
    }

    function parse_env_vars(value) {
        let env_vars=[];

        value = value.trim();

        let offset = 0;
        while(offset < value.length) {
            let name_value_rec = parse_label_name_value(value, offset);
            if (name_value_rec == null) {
                break;
            }
            offset = name_value_rec[0];
            let val = name_value_rec[1] + '=' + name_value_rec[2];
            env_vars.push(val);

            offset = skip_chars(value, offset, isSpace);
            offset = skip_chars(value, offset, isInCharSet(";"));
        }
        return env_vars;
    }

    function parse_cmd_line(value) {

        let ret = [];
        value = value.trim();

        let offset = 0;
        while(offset < value.length && offset != -1) {

            let val_start = skip_chars(value, offset, isSpace);
            if (val_start >= value.length) {
                return null;
            }

            offset  = parse_value(value, val_start);
            let cmd_opt = value.substring( val_start, offset - val_start);
            ret.push(cmd_opt);
        }

        return ret;
    }

    function parseInt(elm_id) {
        let str_val = document.getElementById(elm_id);
        str_val = str_val.trim();

        return str_val.toint(); //???
    }

    function makeHealthCheckSection(request) {
        let opt = document.getElementById('health_check');
        if (!opt.checked) {
            return;
        }

        let cmd = document.getElementById("health_check_cmd").value;
        let parsed_cmd_line = parse_cmd_line(cmd);


        cmd = document.getElementById("heatth_check_type");
        let value = cmd.options[cmd.selectedIndex].value;
        request['HealthConfig']['Test'] = [ value, ...parsed_cmd_line ];

        request['HealthConfig']['Interval'] = parseInt('healthcheck_interval');
        request['HealthConfig']['Timeout'] = parseInt('healthcheck_timeout');
        request['HealthConfig']['Retries'] = parseInt('healthcheck_retries');
        request['HealthConfig']['StartPeriod'] = parseInt('healthcheck_start_period');

    }

    // hostPortNumber:containerPortNumber[/[udp|tcp] =>
    // "<containerPort>/<protocolName>": [
    //                {
    //                    "HostIp": "",
    //                    "HostPort": "<hostPort>"
    //                }
    //            ]
    function parsePortDef(portDef) {
        let ret = {};



        return ret;
    }

    function makeHostConfiguration(request) {
        let opt = document.getElementById('host_cfg');
        if (!opt.checked) {
            return;
        }

        let portDef = document.getElementById('ports').value;
        request['HostConfig']['PortBindings'] = parsePortDef(portDef);
    }

    function makeRequest() {
        let request = {};

        request['Image'] = "<?php echo "${image}"?>";

        let opt = document.getElementById('cmd_type');
        val = document.getElementById('cmd').value;
        let opt_val = opt.options[opt.selectedIndex].value;
        request[opt_val] = parse_cmd_line(val);

        let container_labels = document.getElementById('labels').value;
        request['Labels'] = parse_labels(container_labels);

        request['HostConfig'] = {};
        let env_vals = document.getElementById('envvars').value;
        request['Env'] = parse_env_vars(env_vals);

        let val = document.getElementById('rm_on_exit').checked;
        request['HostConfig']['AutoRemove'] = val;

        makeHealthCheckSection(request);
        makeHostConfiguration(request);

        return request;
    }

    function onRun() {
        let jsonRequest = makeRequest();

        let xmlHttp = new XMLHttpRequest()

        let container_name = document.getElementById('name').value.trim();
        if (container_name != "") {
            container_name = urlencode(container_name);
        }

        xmlHttp.open( "POST", "/createAndRun.php?name={container_name}", false );
        xmlHttp.send(jsonRequest);

    }

    function health_check_type_changed() {
        let sel = document.getElementById('health_check_type');
        let edit = document.getElementById('health_check_cmd');
        if (sel.value == "CMD" || sel.value == "CMD-SHELL") {
            edit.disabled = false;
        } else {
            edit.disabled = true;
            edit.value = '';
        }
    }
    
    function container_network_mode_changed() {
        let sel = document.getElementById('container_network_mode');
        let retries = document.getElementById('retries');
        if (sel.value != "on-failure") {
            retries.disabled = true;
            retries.value = "";
        } else {
            retries.disabled = false;
        }
    }
    
    function network_mode_changed() {
        let sel = document.getElementById('network_mode');
        let cont_name = document.getElementById('network_mode_container_name')

        while(cont_name.options.length > 0) {
            cont_name.options.remove(0);
        }

        console.log("sel: " + sel.value);


        if (sel.value == "container") {

            cont_name.disabled = false;

            // get list of container names
            let container_list = [ <?php
                $json = json_decode($containerListJsonRaw, JSON_OBJECT_AS_ARRAY);
                $first = true;
                foreach ($json as $entry) {
                    $image = $entry['Image'];
                    $names = $entry['Names'];
                    $names = implode(" ", $names);
                    if ($names == "") {
                        $names = $image;
                    }
                    $names .= " - " . $image;
                    if ($first) {
                        $first = false;
                    } else {
                        echo ",";
                    }
                    echo "[ '{$image}', '{$names}' ]\n";
                } ?> ];

            for(let i=0; i < container_list.length; ++i) {
                let text = container_list[i][1];
                let value = container_list[i][0];

                let c = document.createElement("option");
                c.text = text;
                c.value = value;
                cont_name.options.add(c);
            }
        } else {
            cont_name.disabled = true;
        }
        
    }
</script>

<table>
    <tr>
        <td style="width: 0">
            <label for="name">Container Name:</label>
        </td0">
        <td style="width: 0">
            <input name="name" id="name" size="30" type="input"/>
        </td>
        <td style="width: 0">
            <label for="labels">Container Labels:</label>
        </td>
        <td style="width: 0">
            <input name="labels" id="labels" size="30" type="input"/>
        </td>
        <td colspan="4">
            <input type="checkbox" id="rm_on_exit" checked/> <label for="rm_on_exit">Remove docker container upon container exit</label>
        </td>
    </tr>
    <tr id="row1">
        <td style="width: 0">
            <select name="cmd_type" id="cmd_type" >
                <option value="Entrypoint">Entrypoint</option>
                <option value="Command">Command</option>
            </select>
        </td>
        <td style="width: 0">
            <input name="cmd" id="cmd" size="30" type="input"/>
        </td>
        <td style="width: 0">
            <label for="tag">Environment variables:</label>
        </td>
        <td style="width: 0" colspan="5">
            <input name="envvars" id='envvars' size="30" type="input"/>
        </td>
    </tr>
    <tr>
        <td style="width: 0" colspan="8">
            <input type="checkbox" id="health_check" onchange="onHealthCheck()"> <label for="health_check">Health check</label>
        </td>
    </tr>
    <tr id="healthRow1" style="visibility: collapse">
        <td style="width: 0">
            Check Type
        </td>
        <td style="width: 0">
            <select name="heatth_check_type" id="health_check_type" onchange="health_check_type_changed()" >
                <option value="NONE">None</option>
                <option value="CMD">Command</option>
                <option value="CMD-SHELL">Command in default shell</option>
                <option value="">Inherit</option>
            </select>
        </td>
        <td style="width: 0">
            Command:
        </td>
        <td style="width: 0" colspan="5">
            <input name="health_check_cmd" id='health_check_cmd' size="30" type="input" disabled="true"/>
        </td>
    </tr>

    <tr id="healthRow2" style="visibility: collapse">
        <td colspan="8">
            <table>
                <tr>
                    <td style="width: 0">
                        Interval
                    </td>
                    <td style="width: 0">
                        <input name="healthcheck_interval" id="healthcheck_interval" size="10" type="input"/>
                    </td>
                    <td style="width: 0">
                        Timeout
                    </td>
                    <td style="width: 0">
                        <input name="healthcheck_timeout" id="healthcheck_timeout" size="10" type="input"/>
                    </td>
                    <td style="width: 0">
                        Retries
                    </td>
                    <td style="width: 0">
                        <input name="healthcheck_retries" id="healthcheck_retries" size="10" type="input"/>
                    </td>
                    <td style="width: 0">
                        Start Period
                    </td>
                    <td style="width: 0">
                        <input name="healthcheck_start_period" id="healthcheck_start_period" size="10" type="input"/>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="8">
            <input type="checkbox" id="host_cfg" onchange="onHostCfg()"> <label for="host_cfg">Container Host Configuration</label>
        </td>
    </tr>
    <tr id="hostRow1" style="visibility: collapse">
        <td style="width: 0">
            <label for="image">Exposed Ports:</label>
        </td>
        <td style="width: 0">
            <input name="ports" id="ports" size="30" type="input"/>
        </td>
        <td style="width: 0">
            <label for="tag">Mounted Volumes (on host):</label>
        </td>
        <td style="width: 0" colspan="4">
            <input name="volumes" id="volumes" size="30" type="input"/>
        </td>
    </tr>
    <tr id="hostRow2" style="visibility: collapse">
        <td style="width: 0">
            Network Mode:
        </td>
        <td style="width: 0">
            <select name="network_mode" id="network_mode" onchange="network_mode_changed()" >
                <option value="bridge">Bridge</option>
                <option value="host">Host</option>
                <option value="container">Container</option>
                <option value="no-network">No Network</option>

            </select>
        </td>
            <td style="width: 0">
            Container
        </td>
        <td style="width: 0" colspan="5">
            <select name="network_mode_container_name" id="network_mode_container_name" disabled="true">
                <option value=""></option>
            <select>
        </td>
    </tr>
    <tr id="hostRow3" style="visibility: collapse">
        <td style="width: 0">
            Restart Policy
        </td>
        <td style="width: 0">
            <select name="container_network_mode" id="container_network_mode" onchange="container_network_mode_changed()" >
                <option value="">None</option>
                <option value="on-failure">Restart if process failed</option>
                <option value="unless-stopped">Restart if not stopped by user</option>
                <option value="always">Always restart</option>
            <select>
        </td>
        <td style="width: 0">
            Max. Retries
        </td>
        <td style="width: 0" colspan="5">
            <input name="retries" id="retries" size="5" type="input" disabled="true"/>
        </td>
    </tr>
    <tr id="hostRow4" style="visibility: collapse">
        <td colspan="8">
        <table>
            <tr>
                <td style="width: 0">
                    Max. Memory
                </td>
                <td style="width: 0">
                    <input name="ports" id="ports" size="10" type="input"/>
                </td>
                <td style="width: 0">
                    Max. Memory Swapped
                </td>
                <td style="width: 0">
                    <input name="ports" id="ports" size="10" type="input"/>
                </td>
                <td style="width: 0">
                    Max. Memory Reserved
                </td>
                <td style="width: 0">
                    <input name="ports" id="ports" size="10" type="input"/>
                </td>
            </tr>
        </table>

        </td>

        <!--
    <tr>
        <td colspan="8">
            <input type="checkbox" id="network_cfg"/> <label for="network_cfg">Network Configuration</label>
        </td>
    </tr>
    //-->
    <tr>
        <td colspan="8">
            <input type="submit" value="Start" onclick="onRun()"/>
        </td>
    </tr>
</table>

