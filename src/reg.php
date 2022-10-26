<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(2);
?>
<h3>Pulling docker image</h3>
<form action="/src/gen.php">
    <input type="hidden" name="cmd" value="pull"/>
    Image: <input name="id" type="input"/>
    <p/>
    <input type="submit" value="Pull"/>
</form>

<hr>

<h3>Search for docker image</h3>
<form action="/src/gen.php">
    <input type="hidden" name="cmd" value="search"/>
    Image: <input name="id" type="input"/>
    <p/>
    <input type="submit" value="Search"/>
</form>
