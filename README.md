

## php exercise

### Warning! Work in progress, under construction !!!

This exercise runs a small web application that allows to run docker commands.
It displays the running containers and existing images in a table, allows to inspect entries and view container logs & errors etc. etc.

How to use this stuff, after cloning this repository:

- Run the server with ```make run```, this runs php as a local web server on port 8001 
- On the same machine: use your browser and navigate to http://localhost:8001/src/images.php

### what i learned

This exercise is using the php test server here. Conventional wisdom says that this is a single threaded web server only, but o wonder - " You can configure the built-in webserver to fork multiple workers in order to test code that requires multiple concurrent requests to the built-in webserver. Set the PHP_CLI_SERVER_WORKERS environment variable to the number of desired workers before starting the server. This is not supported on Windows. ". See [here](https://www.php.net/manual/en/features.commandline.webserver.php) 
This once again shows: "never stop digging".

Another detail learned so far: I did this exercise by means of invoking the docker command line. 
Therefore I ran $(docker exec -ti <docker_id> /bin/bash) and passed the process pipes for the stdin/stdout/stderr streams.
Now this doesn't quite work, it gives you the error "the input device is not a TTY".
Maybe you could solve this using the "docker engine api" - https://docs.docker.com/engine/api/ , that's a REST api.

There was an PHP library for this REST api, but it was a community effort and is no longer supported [docker-php](https://github.com/docker-php/docker-php)

Now I am left with soem choices:
- write an intermediate proxy that uses the official/supported python or go api.
- generate my own client api from the swagger definition of the api, somehow (but that would create lock-in with the generator tool)
- work with raw http (after all it's REST).

Don't know what the best solution is, as there are lots of versions of the REST api. (talking about choices...)
But taking the feature of attaching to a running container might not be changing too much...

Interesting detail: Curl can send http requests via a unix socket, didn't know that:
The [example](https://docs.docker.com/engine/api/sdk/examples/) shows the following way to do ```docker ps``` : ```curl --unix-socket /var/run/docker.sock http://localhost/v1.41/containers/json```
Now doing that would force me to do my own HTTP parsing as well (and maybe also WebSocket parsing too!) lets try:



