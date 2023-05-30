<?php require_once __DIR__ . "/base/nocache.php"; ?>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(-1);

?>

<h3>Create Volume</h3>

<table>
    <tr id="row1">
        <td style="width: 0">
            <label for="image">Volume Name:</label>
        </td>
        <td>
            <input name="name" id="name" type="input"/>
        </td>
    </tr>
    <tr id="row2">
        <td>
            <label for="tag">Volume Labels:</label>
        </td>
        <td>
            <input name="labels" id='labels' type="input"/>
        </td>
    </tr>
    <tr id="row3">
        <td>
            <label for="tag">Volume Driver:</label>
        </td>
        <td>
            <input name="driver" id='driver' type="input"/>
        </td>
    </tr>
    <tr id="row4">
        <td>
            <label for="tag">Driver Options:</label>
        </td>
        <td>
            <input name="options" id='options' type="input"/>
        </td>
    </tr>
    <tr id="row5">
        <td colspan="2">
            <input type="submit" value="Create Volume" onclick="onCreateVolume()"/>
        </td>
    </tr>
    <tr id="create_volume_res_tr" style="visibility: collapse">
        <td colspan="8">
            <div id="result_status" style="font-family: monospace"></div>
        </td>
    </tr>

</table>

<script  src="/static-files/utils.js"></script>
<script  src="/static-files/prs.js"></script>
<script  src="/contprs.js"></script>
<script>

    function onCreateVolume() {
        let req = {};

        let val = document.getElementById('name').value.trim();
        if (val == "") {
            alert("Must provide volume name");
            return;
        }
        req['Name'] = val;

        val = document.getElementById('driver').value.trim();
        if (val != "") {
            req['Driver'] = val;
        }

        val = document.getElementById('labels').value.trim();
        if (val != "") {
            let cnt = {};

            let res = runParser(val, makeLabelNameParser(), "Volume labels");

            let i = 0;
            for (; i < res.length; ++i) {
                let entry = res[i];
                cnt[entry[0]] = entry[2];
            }

            req['Labels'] = cnt;
        }

        val = document.getElementById('options').value.trim();
        if (val != "") {
            let cnt = {};

            let res = runParser(val, makeLabelNameParser(), "Volume options");

            let i = 0;
            for (; i < res.length; ++i) {
                let entry = res[i];
                cnt[entry[0]] = entry[2];
            }

            req['DriverOpts'] = cnt;
        }

        let json_pretty = JSON.stringify(req, null, 4)
        console.log(json_pretty);
        
        // send
        let xmlHttp = new XMLHttpRequest()
        xmlHttp.onreadystatechange = function() {
            if (this.readyState == 4) {
                let statusRows = [ "create_volume_res_tr" ];
                show_rows(statusRows, true);
                document.getElementById("result_status").innerHTML =  this.responseText;
            }
        };

        console.log("sending...");
        xmlHttp.open( "POST", "/gen.php?cmd=createv&id=", false );
        xmlHttp.send(json_pretty);

    }


</script>

