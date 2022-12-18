# Docker dashboard / web application

This program provides a browser based UI for working with a local docker installation. For this you will need to start a web server, that runs on the same machine as the docker engine.
The UI displays the running containers and existing images in a table, allows to inspect entries and view container logs & errors, as well as many other things. 
You can also attach and run a shell inside a running container, and all of the UI is running in a browser window.

The server runs on OSX, Linux and on Windows with the Linux subsystem

## Running the server in a docker container

- Download the following bash script ```curl https://raw.githubusercontent.com/MoserMichael/phpexercise/main/run-in-docker.sh >run-in-docker.sh``` (or via link [run-in-docker.sh](https://raw.githubusercontent.com/MoserMichael/phpexercise/main/run-in-docker.sh) )
- ```chmod +x ./run-in-docker.sh```

### Running the server with TLS / with a self signed certificate

- ```./run-in-docker.sh -r -t -p 9000``` This starts the local web server for this tool in the docker and uses ports 9000 
- Use your browser and navigate to https://localhost:9000/images.php . The browser will display a warning on the self signed certificate, and you should click on the 'Advanced Settings' link and then click on the link named 'Proceed/Accept the risks'.

Use of TLS with a self signed certificate means that all of the communication is encrypted, however someone may still have impersonated the server over the network (which is an acceptable risk, when working over a trusted local network)

### Running the server with plain http

- ```./run-in-docker.sh -r -p 9000``` This starts the local web server for this tool in the docker and uses ports 9000 
- Use your browser and navigate to http://localhost:9000/images.php

### To stop the server

- run ```./run-in-docker.sh -s```

## Building & Running locally 

How to use this stuff, after cloning this repository:

- make sure that php-7.4 and composer are installed
- run ```make install``` - this installs the php modules with composer
- Run the server with ```make run```, this runs php as a local web server on port 8010 and port 8011 (for debugging purposes only)
- On the same machine: use your browser and navigate to http://localhost:8001/images.php

### what i learned

I wrote this project in order to pick up some working knowledge of PHP, I think that it's much easier to learn from hands-on projects..

I started to use the php test server, with this exercise. Conventional wisdom says that this is a single threaded web server only, but o wonder - " You can configure the built-in webserver to fork multiple workers in order to test code that requires multiple concurrent requests to the built-in webserver. Set the PHP_CLI_SERVER_WORKERS environment variable to the number of desired workers before starting the server. This is not supported on Windows. ". See [here](https://www.php.net/manual/en/features.commandline.webserver.php).
However this trick has it's limits: you can't have TLS with the php test server.

Another detail learned so far: at first I did this exercise by means of invoking the docker command line. 
Therefore I ran $(docker exec -ti <docker_id> /bin/bash) and passed the process pipes for the stdin/stdout/stderr streams.
Now this doesn't quite work, it gives you the error "the input device is not a TTY".
I could solve this by switching to the "docker engine api" - https://docs.docker.com/engine/api/ , that's a REST api.

Interesting detail: ```curl``` can send http requests via a unix domain socket (the docker cli is sending REST requests to the the docker daemon via a unix domain socket), didn't know that:
The [example](https://docs.docker.com/engine/api/sdk/examples/) shows the following way to do ```docker ps``` : ```curl --unix-socket /var/run/docker.sock http://localhost/v1.41/containers/json```

It is possible to run the tool in a docker container, igven hat the docker engine api is used for all commands (that's because I can mount the unix socket /var/run/docker.sock into the file system of the docker).

Another amazing fact: it turns out that the [same origin policy](https://en.wikipedia.org/wiki/Same-origin_policy) does not apply to web sockets!!! I think that's quite amazing, would like to learn more on this exception.


