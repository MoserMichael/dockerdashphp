<?php

require_once __DIR__ . "/hdr.php";

show_hdr(-1);

$id = $_GET['id'];

?>


<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>wspty</title>

        <?php include( __DIR__ . "/static-files/css.css"); ?>
        <css>
        <style>
        <?php include( __DIR__ . "/static-files/xterm/lib/xterm.css"); ?>
        </style>
        </css>
    </head>
    <body>
        <script>var docker_container_id="<?php echo $id;?>";</script>
        <div id="term-container"></div>
        <script>
            <?php include( __DIR__ . "/static-files/xterm/lib/xterm.js"); ?>
            <?php include( __DIR__ . "/static-files/xterm/wspty.js"); ?>
            <?php include( __DIR__ . "/static-files/xterm/script.js"); ?>
        </script>
    </body>
</html>