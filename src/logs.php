<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>

<?php
require_once __DIR__ . "/hdr.php";

show_hdr(-1);

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";

$id = escapeshellarg($_GET['id']);
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

$cmd = "docker logs --timestamps ". $id;

if ($since != "") {
    $cmd = $cmd . " --since " . escapeshellarg($since);
}
if ($until != "") {
    $cmd = $cmd . " --until " . escapeshellarg($until);
}

$cmd = $cmd . " 2>&1 | sort -k 1";

?>

<body onLoad="javascript:initialLogRequest();">

<div style="height: 100%;">


<h3>Container Logs</h3>

<table style="">
    <tr colspan="4">
        <td>
            Logs since:
            <input name="since" id="since" value="<?php echo "{$since}";?>" type="input"/>
        </td>
        <td>
            Logs until:
            <input name="until" id="until" value="<?php echo "{$until}";?>" type="input"/>
        </td>
        <td>
            <input id="follow_logs" type="checkbox" name="follow"  value="true"  <?php echo "{$follow_logs_checked}"?> >Follow logs</input>
        </td>
        <td>
            <input type="submit" value="Refresh" onClick="javascript:doLogRequest()"/>
        </td>
    </tr>
</table>

<p/>


<?php
if (!use_docker_api()) {
    $runner = new base\TmpFileRunner($cmd);
    $json = $runner->run();
    $tbl = new base\FmtTable(array(
        "logs" => "logs"
    ));
    $tbl->echo_from_generator($runner->lineGenerator());
    exit(0);
}
?>

<script>

let container_id = <?php echo "{$id}";?>;
let socket = null;


function initialLogRequest() {

    window.onunload = window.onbeforeunload = function() {
        doClose();
    }
    doLogRequest();
}

function parseRelativeTime(timeStr) {
    let i = 0;
    let num = "";
    let ret = -1;

    for(i=0; i < timeStr.length; ++i) {
        let ch = timeStr[i];

        if (ch.trim() == "") {
            continue;
        }

        if (ch >= '0' && ch <= '9') {
            num = num + ch;
        } else {

           let sec = parseInt(num);
           if (sec == NaN) {
               return -1;
           }
           num = "";

           if (ch == 's' || ch == 'S') {
           } else if (ch == 'm' || ch == 'M') {
               sec = sec * 60;
           } else if (ch == 'h' || ch == 'H') {
               sec = sec * 3600;
           } else if (ch == 'D' || ch == 'D') {
               sec = sec * 3600 * 24;
           } else if (ch == 'w' || ch == 'W') {
               sec = sec * 3600 * 24 * 7;
           } else {
               return -1;
           }
           if (ret == -1) {
               ret = 0;
           }
           ret += sec;
        }
    }

    if (ret != -1) {
        let epoch = Math.round(new Date().getTime() / 1000);
        return epoch - ret;
    }
    return ret;
}

function parseTime(idOfInputField) {
    let timeStr = document.getElementById(idOfInputField).value;

    ret = Date.parse(timeStr);
    if (ret == NaN) {
        return -1;
    }
    return parseRelativeTime(timeStr);
}

function doLogRequest() {
    clearContent();
    let follow_logs_value = document.getElementById('follow_logs').checked;

    let since_time_sec = parseTime("since");

    let until_time_sec = parseTime("until");

    sendLogRequest( follow_logs_value, since_time_sec, until_time_sec );
}

/*
function beep() {
    let snd = new Audio("data:audio/wav;base64,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qJrx4KOQjahZxWbcZgztj2c49nKmkId44S71j0c8eV9yDK6uPRzx5X18eDvjvQ6yKo9ZSS6l//8elePK/Lf//IInrOF/FvDoADYAGBMGb7FtErm5MXMlmPAJQVgWta7Zx2go+8xJ0UiCb8LHHdftWyLJE0QIAIsI+UbXu67dZMjmgDGCGl1H+vpF4NSDckSIkk7Vd+sxEhBQMRU8j/12UIRhzSaUdQ+rQU5kGeFxm+hb1oh6pWWmv3uvmReDl0UnvtapVaIzo1jZbf/pD6ElLqSX+rUmOQNpJFa/r+sa4e/pBlAABoAAAAA3CUgShLdGIxsY7AUABPRrgCABdDuQ5GC7DqPQCgbbJUAoRSUj+NIEig0YfyWUho1VBBBA//uQZB4ABZx5zfMakeAAAAmwAAAAF5F3P0w9GtAAACfAAAAAwLhMDmAYWMgVEG1U0FIGCBgXBXAtfMH10000EEEEEECUBYln03TTTdNBDZopopYvrTTdNa325mImNg3TTPV9q3pmY0xoO6bv3r00y+IDGid/9aaaZTGMuj9mpu9Mpio1dXrr5HERTZSmqU36A3CumzN/9Robv/Xx4v9ijkSRSNLQhAWumap82WRSBUqXStV/YcS+XVLnSS+WLDroqArFkMEsAS+eWmrUzrO0oEmE40RlMZ5+ODIkAyKAGUwZ3mVKmcamcJnMW26MRPgUw6j+LkhyHGVGYjSUUKNpuJUQoOIAyDvEyG8S5yfK6dhZc0Tx1KI/gviKL6qvvFs1+bWtaz58uUNnryq6kt5RzOCkPWlVqVX2a/EEBUdU1KrXLf40GoiiFXK///qpoiDXrOgqDR38JB0bw7SoL+ZB9o1RCkQjQ2CBYZKd/+VJxZRRZlqSkKiws0WFxUyCwsKiMy7hUVFhIaCrNQsKkTIsLivwKKigsj8XYlwt/WKi2N4d//uQRCSAAjURNIHpMZBGYiaQPSYyAAABLAAAAAAAACWAAAAApUF/Mg+0aohSIRobBAsMlO//Kk4soosy1JSFRYWaLC4qZBYWFRGZdwqKiwkNBVmoWFSJkWFxX4FFRQWR+LsS4W/rFRb/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////VEFHAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAU291bmRib3kuZGUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMjAwNGh0dHA6Ly93d3cuc291bmRib3kuZGUAAAAAAAAAACU=");
    snd.play();
}
 */

function doClose() {
    if (socket != null) {
        socket.close();
        socket = null;
    }
}

function sendLogRequest(follow_logs, since_time_sec, until_time_sec) {
    let wsProtocol = location.protocol === 'http:' ? 'ws' : 'wss';
    let port = parseInt(location.port) + 1;
    let url = wsProtocol + '://' + location.hostname + ':' + port + '/wsconn.php';

    console.log("connect to url: " + url);
    doClose();

    socket = new WebSocket(url);


    socket.addEventListener('message', (event) => {
        console.log('Message from server ', event.data);
        let data = JSON.parse(event.data);
        if (data.data !== undefined) {
            console.log("got: " + data.data);
            let log_div = document.getElementById('text_content');
            log_div.insertAdjacentHTML('beforeend', data.data);
        }
    });

    socket.addEventListener('open', (event) => {
        let json = JSON.stringify({'log_container_id': container_id, 'follow': follow_logs, 'since': since_time_sec, 'until': until_time_sec});
        console.log("sending: " + json);
        socket.send(json);
    });

    /*
    socket.onmessage = function (event) {
        let data = JSON.parse(event.data);
        if (data.data !== undefined) {
            console.log("got: " + data.data);
            let log_div = document.getElementById('text_content');
            log_div.insertAdjacentHTML('beforeend', data.data);
        }
    }
    socket.onopen = function (event) {
        let json = JSON.stringify({'log_container_id': container_id, 'follow': follow_logs});
        console.log("sending: " + json);
        socket.send(json);
    }

     */

}

function clearContent() {
    let textElem = document.getElementById('text_content');
    textElem.innerHTML="";
}
</script>

<div id="log_content" style="align-self: flex-end; color: white; background: #000; overflow-y: scroll;  font-family:monospace">
    <pre id="text_content"></pre>
</div>
</div>
