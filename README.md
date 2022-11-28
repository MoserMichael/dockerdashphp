
# Warning, work in progress!!!

## php exercise

This exercise runs a small web application that allows to run docker commands.
It displays the running containers and existing images in a table, allows to inspect entries and view container logs & errors.
You can also attach and run a shell inside a running container, the shell will run in the browser window!.

How to use this stuff, after cloning this repository:

- make sure that php-7.4 and composer are installed
- run ```make install``` - this installs the php modules with composer
- Run the server with ```make run```, this runs php as a local web server on port 8001 
- On the same machine: use your browser and navigate to http://localhost:8001/images.php

### what i learned

I started to use the php test server, with this exercise. Conventional wisdom says that this is a single threaded web server only, but o wonder - " You can configure the built-in webserver to fork multiple workers in order to test code that requires multiple concurrent requests to the built-in webserver. Set the PHP_CLI_SERVER_WORKERS environment variable to the number of desired workers before starting the server. This is not supported on Windows. ". See [here](https://www.php.net/manual/en/features.commandline.webserver.php).
However this trick has it's limits: you can't have TLS with the php test server.

Another detail learned so far: at first I did this exercise by means of invoking the docker command line. 
Therefore I ran $(docker exec -ti <docker_id> /bin/bash) and passed the process pipes for the stdin/stdout/stderr streams.
Now this doesn't quite work, it gives you the error "the input device is not a TTY".
I could solve this by switching to the "docker engine api" - https://docs.docker.com/engine/api/ , that's a REST api.

Interesting detail: ```curl``` can send http requests via a unix domain socket (the docker cli is sending REST requests to the the docker daemon via a unix domain socket), didn't know that:
The [example](https://docs.docker.com/engine/api/sdk/examples/) shows the following way to do ```docker ps``` : ```curl --unix-socket /var/run/docker.sock http://localhost/v1.41/containers/json```

Now If I use the docker engine api for all commands, then I would be able to host this project in a docker container, that's probably the next step... (that's because I can mount the unix socket /var/run/docker.sock into the file system of the docker).


