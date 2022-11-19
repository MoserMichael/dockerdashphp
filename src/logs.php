<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<div style="height: 100%;">
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(-1);
?>
<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";

$id = $_GET['id'];
$since = @$_GET['since'];
$until = @$_GET['until'];
$follow_logs=false;
if (array_key_exists('follow',$_GET)) {
    $follow_logs=@$_GET['follow'];
}
if ($follow_logs == "true") {
    $follow_logs_checked = "checked";
} else {
    $follow_logs_checked = "";
    $follow_logs = "false";
}


$cmd = "docker logs --timestamps ". escapeshellarg($id);

if ($since != "") {
    $cmd = $cmd . " --since " . escapeshellarg($since);
}
if ($until != "") {
    $cmd = $cmd . " --until " . escapeshellarg($until);
}

$cmd = $cmd . " 2>&1 | sort -k 1";
?>


<h3>Container Logs</h3>

<form action="/logs.php">
    <input type="hidden" name="cmd" value="pull"/>
    <input type="hidden" name="id" value="<?php echo $id;?>"/>
    <table style="">
        <tr colspan="4">
            <td>
                Logs since:
                <input name="since" value="<?php echo "{$since}";?>" type="input"/>
            </td>
            <td>
                Logs until:
                <input name="until" value="<?php echo "{$until}";?>" type="input"/>
            </td>
            <td colspan="2">
                <input type="checkbox" name="follow"  value="true"  <?php echo "{$follow_logs_checked}"?> >Follow logs</input>
            </td>
            <td colspan="2">
                <input type="submit" value="Refresh"/>
            </td>
        </tr>
    </table>

</form>
<p/>


<p/>

<?php

if (!use_docker_api()) {
    $runner = new base\TmpFileRunner($cmd);
    $json = $runner->run();
    $tbl = new base\FmtTable(array(
        "logs" => "logs"
    ));
    $tbl->echo_from_generator($runner->lineGenerator());
}
?>

<script>
    let wsProtocol = location.protocol === 'http:' ? 'ws' : 'wss';
    let port = parseInt(location.port) + 1;
    let url = wsProtocol + '://' + location.hostname + ':' + port + '/wsconn.php';
    let socket = new WebSocket( url );
    socket.onopen = function(event) {
        let json = JSON.stringify({'log_container_id' : '<?php echo "$id" ?>', 'follow' : '<?php echo "{$follow_logs}"; ?>' });
        socket.send( json );
    }
    socket.onmessage = function(event) {
        let data = JSON.parse(event.data);
        if (data.data !== undefined) {
            let log_div = document.getElementById('text_content');
            log_div.insertAdjacentHTML('beforeend', data.data);
        }
    }
</script>

<div id="log_content" style="align-self: flex-end; color: white; background: #000; overflow-y: scroll;  font-family:monospace">
    <pre id="text_content"></pre>
</div>
</div>
