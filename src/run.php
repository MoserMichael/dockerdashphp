<?php require_once __DIR__ . "/base/nocache.php"; ?>
<html xmlns="http://www.w3.org/1999/html">
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(-1);

$image = escapeshellcmd($_GET['ID']);
$name = escapeshellcmd(@$_GET['name']);
$displayName = $name;
if ($displayName=="") {
    $displayName = $image;
}

if (!use_docker_api()) {
?>
<h3>Docker run command line</h3>
<form action="/gen.php">
    <input type="hidden" name="cmd" value="run"/>
    docker run <input name="id" type="input" value="<?php echo $image;?>"/>
    <p/>
    <input type="submit" value="Run!"/>
</form>

<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";


$runner = new base\Runner("docker run --help", False);
    $json = $runner->run();

    $tbl = new base\FmtTable(array(
        "docker run --help" => "docker run --help"
    ));
    echo $tbl->format_row_raw($json);
} else {
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

    function makeRequest() {
        /*
        let request = {
            "Hostname": "",
            "Domainname": "",
            "User": "",
            "AttachStdin": false,
            "AttachStdout": true,
            "AttachStderr": true,
            "Tty": false,
            "OpenStdin": false,
            "StdinOnce": false,
            "Env": [
                "FOO=bar",
                "BAZ=quux"
            ],
            "Cmd": [
                "date"
            ],
            "Entrypoint": "",
            "Image": "ubuntu",
            "Labels": {
                "com.example.vendor": "Acme",
                "com.example.license": "GPL",
                "com.example.version": "1.0"
            },
            "Volumes": {
                "/volumes/data": {}
            },
            "WorkingDir": "",
            "NetworkDisabled": false,
            "MacAddress": "12:34:56:78:9a:bc",
            "ExposedPorts": {
                "22/tcp": {}
            },
            "StopSignal": "SIGTERM",
            "StopTimeout": 10,
            "HostConfig": {
                "Binds": [
                    "/tmp:/tmp"
                ],
                "Links": [
                    "redis3:redis"
                ],
                "Memory": 0,
                "MemorySwap": 0,
                "MemoryReservation": 0,
                "KernelMemory": 0,
                "NanoCpus": 500000,
                "CpuPercent": 80,
                "CpuShares": 512,
                "CpuPeriod": 100000,
                "CpuRealtimePeriod": 1000000,
                "CpuRealtimeRuntime": 10000,
                "CpuQuota": 50000,
                "CpusetCpus": "0,1",
                "CpusetMems": "0,1",
                "MaximumIOps": 0,
                "MaximumIOBps": 0,
                "BlkioWeight": 300,
                "BlkioWeightDevice": [
                    {}
                ],
                "BlkioDeviceReadBps": [
                    {}
                ],
                "BlkioDeviceReadIOps": [
                    {}
                ],
                "BlkioDeviceWriteBps": [
                    {}
                ],
                "BlkioDeviceWriteIOps": [
                    {}
                ],
                "DeviceRequests": [
                    {
                        "Driver": "nvidia",
                        "Count": -1,
                        "DeviceIDs\"": [
                            "0",
                            "1",
                            "GPU-fef8089b-4820-abfc-e83e-94318197576e"
                        ],
                        "Capabilities": [
                            [
                                "gpu",
                                "nvidia",
                                "compute"
                            ]
                        ],
                        "Options": {
                            "property1": "string",
                            "property2": "string"
                        }
                    }
                ],
                "MemorySwappiness": 60,
                "OomKillDisable": false,
                "OomScoreAdj": 500,
                "PidMode": "",
                "PidsLimit": 0,
                "PortBindings": {
                    "22/tcp": [
                        {
                            "HostPort": "11022"
                        }
                    ]
                },
                "PublishAllPorts": false,
                "Privileged": false,
                "ReadonlyRootfs": false,
                "Dns": [
                    "8.8.8.8"
                ],
                "DnsOptions": [
                    ""
                ],
                "DnsSearch": [
                    ""
                ],
                "VolumesFrom": [
                    "parent",
                    "other:ro"
                ],
                "CapAdd": [
                    "NET_ADMIN"
                ],
                "CapDrop": [
                    "MKNOD"
                ],
                "GroupAdd": [
                    "newgroup"
                ],
                "RestartPolicy": {
                    "Name": "",
                    "MaximumRetryCount": 0
                },
                "AutoRemove": true,
                "NetworkMode": "bridge",
                "Devices": [],
                "Ulimits": [
                    {}
                ],
                "LogConfig": {
                    "Type": "json-file",
                    "Config": {}
                },
                "SecurityOpt": [],
                "StorageOpt": {},
                "CgroupParent": "",
                "VolumeDriver": "",
                "ShmSize": 67108864
            },
            "NetworkingConfig": {
                "EndpointsConfig": {
                    "isolated_nw": {
                        "IPAMConfig": {
                            "IPv4Address": "172.20.30.33",
                            "IPv6Address": "2001:db8:abcd::3033",
                            "LinkLocalIPs": [
                                "169.254.34.68",
                                "fe80::3468"
                            ]
                        },
                        "Links": [
                            "container_1",
                            "container_2"
                        ],
                        "Aliases": [
                            "server_x",
                            "server_y"
                        ]
                    }
                }
            }
        }
      
         */
    }
    function onRun() {
        let requst = makeRequest();
    }
</script>

<table>
    <tr>
        <td style="width: 0">
            <label for="image">Container Name:</label>
        </td0">
        <td style="width: 0">
            <input name="name" id="name" size="30" type="input"/>
        </td>
        <td style="width: 0">
            <label for="tag">Container Labels:</label>
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
            <label for="image">Command:</label>
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
            <select name="heatth_check_type" id="health_check_type" >
                <option value="none">None</option>
                <option value="command">Command</option>
                <option value="commandInShell">Command in default shell</option>
                <option value="inherit">Inherit</option>
            </select>
        </td>
        <td style="width: 0">
            Command:
        </td>
        <td style="width: 0" colspan="5">
            <input name="health_check_cmd" id='health_check_cmd' size="30" type="input"/>
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
                        <input name="healthcheck_timeout" id="healthcheck_interval" size="10" type="input"/>
                    </td>
                    <td style="width: 0">
                        Retries
                    </td>
                    <td style="width: 0">
                        <input name="healthcheck_retries" id="healthcheck_interval" size="10" type="input"/>
                    </td>
                    <td style="width: 0">
                        Start Period
                    </td>
                    <td style="width: 0">
                        <input name="healthcheck_start_period" id="healthcheck_interval" size="10" type="input"/>
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
            <select name="network_mode" id="network_mode" >
                <option value="bridge">Bridge</option>
                <option value="host">Host</option>
                <option value="container">Container</option>
            </select>
        </td>
            <td style="width: 0">
            Container
        </td>
        <td style="width: 0" colspan="5">
            <select name="container_network_mode" id="container_network_mode" disabled="true">
                <option value=""></option>
            <select>
        </td>
    </tr>
    <tr id="hostRow3" style="visibility: collapse">
        <td style="width: 0">
            Restart Policy
        </td>
        <td style="width: 0">
            <select name="container_network_mode" id="container_network_mode" >
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

<?php
}
?>
