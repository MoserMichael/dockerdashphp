<?php require_once __DIR__ . "/base/nocache.php"; ?>
<html>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(3);

?>

<h3>Pulling docker image</h3>

<table>
    <tr id="row1">
        <td style="width: 0">
            <label for="image">Image:</label>
        </td>
        <td>
            <input name="image" id="image" type="input"/>
        </td>
    </tr>
    <tr id="row2">
        <td>
            <label for="tag">Tag:</label>
        </td>
        <td>
            <input name="tag" id='tag' type="input"/>
        </td>
    </tr>
    <tr id="row3">
        <td>
            <label for="auth">Authentication method:</label>
        </td>
        <td>
            <select name="auth" id="auth" onchange="on_select_auth()">
                <option value="default">Default credentials</option>
                <option value="userpassword">Username and password</option>
                <option value="authtoken">Authentication token</option>
            </select>
        </td>
    </tr>

    <tr id="auth_user_password_tr" style="visibility: collapse">
        <td colspan="2">
            <div>
                <label for="username">username</label> <input id="username">
                <p  />
                <label for="password">password</label> <input id="password">
            </div>
        </td>
    </tr>
    <tr id="auth_token_tr" style="visibility: collapse">
        <td colspan="2">
            <div>
                <label for="authtoken">Authentication Token </label> <input id="authtoken"/>
            </div>
        </td>
    </tr>
    <tr id="row4">
        <td colspan="2">
            <input type="submit" value="Pull" onclick="onPullImage()"/>
        </td>
    </tr>
    <tr id="loading_image_tr" style="visibility: collapse">
        <td colspan="2">
            <div id="download_progress" style="font-family: monospace"></div>
        </td>
    </tr>
</table>

<script  src="/static-files/utils.js"></script>
<script  src="/static-files/wssurl.js"></script>

<script>
    var socket;


    function show_load_input(on) {
        let rows = ["row1", "row2", "row3", "auth_user_password_tr", "auth_token_tr", "row4"];
        show_rows(rows, on);
    }

    function show_progress(on) {
        let rows = ["loading_image_tr"];
        show_rows(rows, on);
    }

    function onPullImage() {
        let url = makeWssUrl('wsconn.php');

        socket = new WebSocket(url);

        socket.addEventListener('message', (event) => {
            let date = new Date().toJSON();
            console.log('Message from server ', date, event.data);

            let data = JSON.parse(event.data);
            if (onDownloadProgress(data)) {
                   showProgress();
            }
        });

        socket.addEventListener('open', (event) => {
            show_load_input(false);
            show_progress(true);
            initProgress();
            sendDownloadRequest();
        });


    }

    let progress= {};

    function initProgress() {
        progress= {};
        document.getElementById('download_progress').innerHTML = '';
    }

    function showProgress() {
        let msg = "";
        for (const [key, value] of Object.entries(progress)) {
            msg += value + '<br/>';
        }
        document.getElementById('download_progress').innerHTML = msg;

    }

    function onProgress(data) {
        data = data.trim();
        if (data === "") {
            return true;
        }

        console.log("json<" + data + ">");

        let json = JSON.parse(data);

        if (json.id === undefined) {
            let msg = "";
            if (json.status !== undefined) {
                msg = json.status;
            } else if (json.message !== undefined) {
                msg = json.message;
            } else if (json.error !== undefined) {
                msg = json.error;
            } else {
                msg = JSON.stringify(json);
            }
            progress[666] = msg;
            return true;
        }

        let id = json.id;

        if (id === "Download complete") {
            //progress.delete(id);
            progress[id] = json.progressDetail.id + ': ' + id;
        } else {
            let status = id + ': ' + json.status;

            if (json.progressDetail !== undefined) {
                let detail = json['progressDetail'];

                if (detail.current !== undefined && detail.total !== undefined) {
                    status += " " + detail.current + "/" + detail.total;
                }
            }
            if (json.progress !== undefined) {
                let p = json.progress;
                status += " " + p.replace("\u003e", '');
            }

            progress[id] = status;
        }
        return true;
    }

    function onDownloadProgress(jsonData) {
        let data = jsonData.data;

        let lines = data.split(/\r?\n/);
        for (const line of lines) {
            if (!onProgress(line)) {
                return false;
            }
        }
        return true;
    }

    function sendDownloadRequest() {
        let image = document.getElementById('image').value;
        let tag = document.getElementById('tag').value;

        let json = JSON.stringify({
            'load_image': image,
            'tag': tag
        });

        let username = document.getElementById('username').value;
        if (username != "") {
            json['username'] = username;
            let password = document.getElementById('password').value;
            json['password'] = password;
        }

        let authtoken = document.getElementById('authtoken').value;
        if (authtoken != "") {
            json['authtoken'] = authtoken;
        }
        socket.send(json);
    }

</script>

<script>
function show_auth_token(on) {
    document.getElementById("auth_token_tr").style = on ? "visibility: visible" : "visibility: collapse";
    if (!on) {
        document.getElementById("authtoken").value = "";
    }
}

function show_user_password(on) {
    document.getElementById("auth_user_password_tr").style =  on ? "visibility: visible" : "visibility: collapse";
    document.getElementById("username").value = "";
    document.getElementById("password").value = "";
}


function on_select_auth() {
    let val = document.getElementById('auth').value;
    if (val == 'default') {
        show_user_password(false);
        show_auth_token(false);
    }
    if (val == 'userpassword') {
        show_user_password(true);
        show_auth_token(false);
    }
    if (val == 'authtoken') {
        show_user_password(false);
        show_auth_token(true);
    }
}
</script>
    
<h3>Search the Docker Hub for images</h3>
<form action="/searchres.php">
    <input type="hidden" name="cmd" value="search"/>
    Image: <input name="id" type="input"/>
    <p/>
    <input type="submit" value="Search"/>
</form>
