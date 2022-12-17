<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/wssurl.js"); ?>
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
    
<script>

let container_id = <?php echo "{$id}";?>;
let socket = null;


function initialLogRequest() {

    window.onunload = window.onbeforeunload = function() {
        doClose();
    }
    window.onunload = window.beforeunload = function() {
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
        console.log("epoch", epoch, "delta", ret);
        return epoch - ret;
    }
    console.log("Invalid time!!!");
    return ret;
}

function parseTime(idOfInputField) {
    let timeStr = document.getElementById(idOfInputField).value;
    let ret = parseRelativeTime(timeStr);
    if (ret == -1) {
        return Date.parse(timeStr);
    }
    return ret;
}

function doLogRequest() {
    clearContent();
    let follow_logs_value = document.getElementById('follow_logs').checked;

    let since_time_sec = parseTime("since");

    let until_time_sec = parseTime("until");

    sendLogRequest( follow_logs_value, since_time_sec, until_time_sec );
}


function doClose() {
    if (socket != null) {
        socket.close();
        socket = null;
    }
}

function sendLogRequest(follow_logs, since_time_sec, until_time_sec) {
    let url = makeWssUrl('wsconn.php');
    doClose();

    socket = new WebSocket(url);
    
    socket.addEventListener('message', (event) => {
        //console.log('Message from server ', event.data);
        let data = JSON.parse(event.data);
        if (data.data !== undefined) {
            //console.log("got: " + data.data);
            let log_div = document.getElementById('text_content');
            log_div.insertAdjacentHTML('beforeend', data.data);
        }
    });

    socket.addEventListener('open', (event) => {
        let json = JSON.stringify({'log_container_id': container_id, 'follow': follow_logs, 'since': since_time_sec, 'until': until_time_sec});
        //console.log("sending: " + json);
        socket.send(json);
    });
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
