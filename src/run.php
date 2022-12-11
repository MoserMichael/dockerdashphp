<?php require_once __DIR__ . "/base/nocache.php"; ?>
<html xmlns="http://www.w3.org/1999/html">


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


<h3>Create & Run docker container with given image</h3>

Container with image: <?php echo "<a title='inspect image' href='/gen.php?cmd=inspecti&id={$image}'>{$displayName}</a>"; ?> will be run in detached mode
</p>

<script  src="/static-files/utils.js"></script>
<script  src="/static-files/prs.js"></script>
<script  src="/contprs.js"></script>

<script>

    let image_id="<?php echo "{$image}"?>";

    function onHealthCheck() {
        let hostRows = [ "healthRow1", "healthRow2"] ;
        show_rows_on_checkbox(hostRows, "health_check");
    }

    function onHostCfg() {
        let hostRows = [ "hostRow1", "hostRow2", "hostRow3", "hostRow4"];
        show_rows_on_checkbox(hostRows, "host_cfg");
    }


    function parse_cmd_line(value) {
        return runParser(value, makeCmdLineParer(),  "Command line");
    }

    function parse_labels(value) {
        let labels = {};

        if (value.trim() == "") {
            return labels;
        }

        let res = runParser(value, makeLabelNameParser(),  "Container labels");

        let i=0;
        for(;i<res.length;++i) {
            labels[res[0]] = res[2];
        }
        return labels;
    }

    function parse_env_vars(value) {

        if (value.trim() == "") {
            return [];
        }

        let env_vars=[];

        let res = runParser(value, makeEnvVarsParer(), "Environment Variables");

        let add_value = function(name_value_res) {
            let val = name_value_res[0] + "=" + name_value_res[2];
            env_vars.push(val);
        }

        let i = 0;

        for(;i<res.length;++i) {
            add_value(res[i]);
        }

        return env_vars;
    }

    function parseMemSize(elm_id) {
        let val = document.getElementById(elm_id).value;

        if (val.trim() == "") {
            return 0;
        }

        let res = runParser(val, makeMemSizeParser(),  "Memory size limit");

        let num = parseInt(res[0],10);

        if (res[1] == 'k' || res[1] == 'K') {
            num *= 1024;
        }
        if (res[1] == 'M' || res[1] == 'M') {
            num *= 1024 * 1024;
        }
        if (res[1] == 'g' || res[1] == 'G') {
            num *= 1024 * 1024 * 1024;
        }

        return num;
    }


    function parsePortDef(portDef) {
        let ret = {};
        let res = runParser(portDef, makePortDefParser(),  "Port mappings");
        let i = 0;
        for(i=0; i< res.length; ++i) {
            let def = res[i];
            let hostPort = def[0];
            let containerPort = def[2];
            let proto = def[3];

            ret[ containerPort + proto ] = {
                "HostIp": "",
                "HostPort": '"' + hostPort + '"'
            }
        }
        return ret;
    }

    function makeHealthCheckSection(request) {
        let opt = document.getElementById('health_check');
        if (!opt.checked) {
            return;
        }

        request['HealthConfig'] = {};

        let cmd = document.getElementById("health_check_cmd").value;
        let parsed_cmd_line = parse_cmd_line(cmd);


        cmd = document.getElementById("heatth_check_type");
        let value = cmd.options[cmd.selectedIndex].value;
        request['HealthConfig']['Test'] = [ value, ...parsed_cmd_line ];
        request['HealthConfig']['Interval'] = parseMemSize('healthcheck_interval');
        request['HealthConfig']['Timeout'] = parseMemSize('healthcheck_timeout');
        request['HealthConfig']['Retries'] = parseMemSize('healthcheck_retries');
        request['HealthConfig']['StartPeriod'] = parseMemSize('healthcheck_start_period');
    }

    function makeVolumeMapping() {
        let parser = makeVolumeMappingParser();
        let text = document.getElementById('');
        runParser(text, makePortDefParser(), "Mounted Volumes");
        return text.trim().split(' ');
    }

    function makeHostConfiguration(request) {
        let opt = document.getElementById('host_cfg');
        if (!opt.checked) {
            return;
        }

        let portDef = document.getElementById('ports').value;
        request['HostConfig']['PortBindings'] = parsePortDef(portDef);
        request['HostConfig']['Binds'] = makeVolumeMapping();
    }

    function makeRequest() {
        let request = {};

        request['Image'] = "<?php echo "${image}"?>";

        let opt = document.getElementById('cmd_type');
        let opt_val = opt.options[opt.selectedIndex].value;
        let cmd_val = parse_cmd_line(document.getElementById('cmd').value);
        if (cmd_val.length != 0) {
            request[opt_val] = cmd_val;
        }

        let container_labels = document.getElementById('labels').value;
        let label_vals = parse_labels(container_labels);
        if (Object.keys(label_vals).length != 0) {
            request['Labels'] = label_vals;
        }

        request['HostConfig'] = {};

        let env_vals = document.getElementById('envvars').value;
        let env_val = parse_env_vars(env_vals);
        if (env_val.length != 0) {
            request['Env'] = env_val;
        }

        let val = document.getElementById('rm_on_exit').checked;
        request['HostConfig']['AutoRemove'] = val;

        makeHealthCheckSection(request);
        makeHostConfiguration(request);

        request["AttachStdin"]= false;
        request["AttachStdout"]=true;
        request["AttachStderr"]=true;
        request["Tty"]=false;
        
        return request;
    }

    function onRun() {
        let jsonRequest = makeRequest();

        let json_pretty = JSON.stringify(jsonRequest, null, 4)
        console.log(json_pretty);

        let xmlHttp = new XMLHttpRequest()

        let container_name = document.getElementById('name').value.trim();
        if (container_name != "") {
            container_name = urlencode(container_name);
        }

        xmlHttp.onreadystatechange = function() {
            if (this.readyState == 4) {
                let statusRows = [ "loading_image_tr" ];
                show_rows(statusRows, true);
                document.getElementById("result_status").innerHTML =  this.responseText;
            }
        };

        console.log("sending...");
        xmlHttp.open( "POST", "/createAndRun.php?id=" + image_id , false );
        xmlHttp.send(json_pretty);

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
                <option value="Command">Command</option>
                <option value="Entrypoint">Entrypoint</option>
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

    <tr id="loading_image_tr" style="visibility: collapse">
        <td colspan="8">
            <div id="result_status" style="font-family: monospace"></div>
        </td>
    </tr>

</table>

