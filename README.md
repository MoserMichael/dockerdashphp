
# Warning, work in progress!!!

## php exercise

This exercise runs a small web application that allows to run docker commands.
It displays the running containers and existing images in a table, allows to inspect entries and view container logs & errors.
You can also attach and run a shell inside a running container, the shell will run in the browser window!.

How to use this stuff, after cloning this repository:

- make sure that php-7.4 and composer are installed
- run ```make install``` - this installs the php modules with composer
- Run the server with ```make run```, this runs php as a local web server on port 8001 
- On the same machine: use your browser and navigate to http://localhost:8001/src/images.php

### what i learned

This exercise is using the php test server here. Conventional wisdom says that this is a single threaded web server only, but o wonder - " You can configure the built-in webserver to fork multiple workers in order to test code that requires multiple concurrent requests to the built-in webserver. Set the PHP_CLI_SERVER_WORKERS environment variable to the number of desired workers before starting the server. This is not supported on Windows. ". See [here](https://www.php.net/manual/en/features.commandline.webserver.php) 
This once again shows: "never stop digging".

Another detail learned so far: I did this exercise by means of invoking the docker command line. 
Therefore I ran $(docker exec -ti <docker_id> /bin/bash) and passed the process pipes for the stdin/stdout/stderr streams.
Now this doesn't quite work, it gives you the error "the input device is not a TTY".
Maybe you could solve this using the "docker engine api" - https://docs.docker.com/engine/api/ , that's a REST api.

Interesting detail: Curl can send http requests via a unix socket, didn't know that:
The [example](https://docs.docker.com/engine/api/sdk/examples/) shows the following way to do ```docker ps``` : ```curl --unix-socket /var/run/docker.sock http://localhost/v1.41/containers/json```
Now doing that would force me to do my own HTTP parsing as well lets try:

Now If I use the docker engine api for all commands, then I would be able to host this project in a docker container, that's probably the next step... (that's because I can mount the unix socket /var/run/docker.sock into the file system of the docker)
However the docker command line seems to have undergone fewer changes than the REST api...


