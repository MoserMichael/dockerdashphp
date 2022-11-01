

### php exercise

This exercise runs a small web application that allows to run docker commands.
It displays the running containers and existing images in a table, etc. etc.

Run the server with make run, this runs php as a local web server on port 8001

Warning! Work in progress, under construction !!!

```make run``` - runs the web server locally.

Access with http://localhost:8001/src/images.php

### what i learned

This exercise is using the php test server here. Conventional wisdom says that this is a single threaded web server only, but o wonder - " You can configure the built-in webserver to fork multiple workers in order to test code that requires multiple concurrent requests to the built-in webserver. Set the PHP_CLI_SERVER_WORKERS environment variable to the number of desired workers before starting the server. This is not supported on Windows. ". See [here](https://www.php.net/manual/en/features.commandline.webserver.php) 

This once again shows: "never stop digging".

