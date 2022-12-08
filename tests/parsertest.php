<html>
<body>
<script>
<?php include( __DIR__ . "/../src/static-files/prs.js"); ?>
<?php include( __DIR__ . "/../src/contprs.js"); ?>
</script>


<h3>this is not an emergency, this is a test!</h3>

<script>

function testAll() {
    let envPars = makeEnvVarsParer();
    let res = runParser("PATH=/lib:/usr/lib;SHELL=/bin/sh;LABEL='very nice string'", envPars, false);
    console.log(res);
}
testAll();

</script>
</body>
</html>
