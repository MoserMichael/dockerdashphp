I am going to show you my side project, it's a docker dashboard, it helps to work with docker from inside a web browser.

Let's start with an overview of docker and the docker command line:

Docker allows you to run different operating system environments on your computer, what does that mean? I am working on a Mac laptop right now, it is running the Darwin operating system, but I can also run the Linux operating system on on the same machine inside a docker container!

The container will appear to be as just other another application on the mac, but you are actually running on another operating system with its own environment that is isolated from your Mac. 
The container can also share resources with the host operating system - you can access parts of the host operating file system, or share the network of the host operating system. 

So let's run a Linux OS instance on this mac:

We will use the docker command line tool: 

> docker run --rm -it fedora:latest bash

Now you are looking at at the linux console.

> uname -a

It tells us that that it's Linux operating system that is running on an ARM based CPU, and you can see the Kernel version.

> cat /etc/*-release

The fedora distribution on this container - that's what we asked for when we started this container.

Now let's take a quick look at the CLI command used to run the image

> docker run --rm -ti fedora:latest bash

- docker run    - tells the docker runtime to create a new container instance and to run it.
- -t            - tells docker to create a pseudo-terminal, that is required to run a interactive shell.
- -i            - interactive mode, the container is reading the commands that we are typing within the shell.
- --rm          - docker does not keep any information on this container, when it exits.
- fedora:latest - fedora is the docker image - a docker image is a archive file, this archive file it is keeping all the files required to run the operating system, in fact it contains the whole file system of that operating system. It contains the operating system kernel, the runtime library and the applications that we can use, when the operating system is up and running. 
-               - latest is the tag of the image. For each docker image there can be multiple versions, the tag value is standing for a particular version. Usually there is a latest tag that refers to the latest and greatest version of the docker image. (more on this later)
- bash          - once the operating system is up then we want to run a bash shell. Now this is the main command that is run by this container it has a special status: the container stops running, when the main command stops.


Also: docker is a client program, it is reading the command line and sending some request to the docker service. The docker service will in turn do as requested: create a new container, query the state of this container, etc. etc.


Now in another console: you can list the docker images that are available on the host:

> docker images

REPOSITORY                          TAG       IMAGE ID       CREATED       SIZE
fedora                              latest    5dce7be29f0d   3 weeks ago   273MB

Remember that each docker image is a big archive file. This archive contains the file system that is available to the operating system of the container. The Size column tells you the size of the contained file system.
 the IMAGE ID - that's a number that identifies this docker image uniquely. The name and tag is an alias to this number, note that the image id may change, if the author of the docker image decides to publish a different build for the same image and tag. 

We can also get a list of all docker containers:

> docker ps 

CONTAINER ID   IMAGE                                      COMMAND                  CREATED             STATUS             PORTS                  NAMES
ba2d516a0dbc   fedora:latest                              "bash"                   About an hour ago   Up About an hour                          elegant_faraday


Each container has a STATUS - this one is Up, that mans it is running right now. 

The IMAGE column is listing the name of the docker image used to create this container instance

The COMMAND column is the command that was used to start the container, this is the main process of the container. The container stops when this main process has stopped.

The CREATED column tells you the uptime of the container, how long that container is already running.

Each running container instance is identified by a unique id listed in the CONTAINER ID column. 

The container id is used to controll the container with the docker command line - like pausing a docker container 

> docker pause ba2d516a0dbc

When you switch back to the console of the container: it now doesn't react to any keys now, you can't enter any commands, everything is frozen.

Now listing all of the containers again, the given container have the 'paused' state

> docker ps

And you can resume the container again

> docker unpause ba2d516a0dbc 

> docker ps 

Again listed as running, and the container is now receiving commands again.

The container stops to run as follows:
- The docker cli command can stop it with the command 

> docker stop <CONTAINER_ID>

> docker ps -a

Note that the container now longer appears in the list of running containers, that's due to the --rm option passed to docker run - the container object is removed just as it exited.

A container is either stopped by stopping the main process - that is the process created via the command passed to the docker run command 

or by running a docker stop command with the id of the container - from outside of the container.

--

There is a different way to run containers, like this one:

> docker run fedora:latest ls -al /

Note that this one is just listing all the files in the root directory of the file system and exiting.

> docker ps -a

CONTAINER ID   IMAGE           COMMAND                 CREATED          STATUS                      PORTS     NAMES
d45c22e76249   fedora:latest   "ls -al /"              51 seconds ago   Exited (0) 50 seconds ago             stoic_tesla 


The status of the container is 'Exited (0)' , Note the the -a option with docker ps, it tells to show all containers, even those that have stopped-. 

The main command displayed all the files in the root directory and then exits, this means that the container is no longer running - this one was run without the --rm option, 

You can still look at the output produced by this container 

> docker logs d45c22e76249

That command is very usefull for debugging containers!

Now you also wait until the container exits and then show the exit status, just like with a real process.

>docker wait d45c22e76249
 0

Now you can clean up the entries for stopped containers

> docker container prune -f

> docker ps -a 

No more entries of stopped containers.

---

Ok, that was some intro on docker, now let's look at my docker dashboard, the dashboard gives you a different way to access the functionality of the docker command line tool.
I want to show you how to use the dashboard, but I will also show the equivalent docker CLI commands for each action.

- Let's download the script for starting the dashboard:

    curl https://raw.githubusercontent.com/MoserMichael/phpexercise/main/run-in-docker.sh >run-in-docker.sh    

- First, let's make this script runnable. 

    chmod +x ./run-in-docker.sh

- Now run the script to start the WEB server for the docker dashboard:

    ./run-in-docker.sh -r 

  The dashboard is also running inside a docker container, so it is first downloading the docker image for this tool.

  This approach has it's limitation - for example you can't use the dashboard to restart the docker engine, as this would kill the container instance that is hosting the web server for the dashbaord.  
  However this approach also has it's advantage - the docker image contains all of the required tools for running the dashboard server, it's all there, ready to run - a convenient form to distribute your software.

  The URL for accessing the dashboard if right there in the console, let's put that in a browser:

=====

Inside the browser: you have a screen that is listing all of the docker containers that are running on the machine http://localhost:8000/containers.php

And you have a screen that is listing all of the docker images available on this machine http://localhost:8000/images.php

--

Lets look for docker images on docker hub. Docker hub is a big public repository for docker images.

Let's look at the available docker images for the alpine Linux distribution. The Alpine docker image is often used because the image can be quite small - as small as five megabytes.

http://localhost:8000/reg.php

[Pull/Search] 

Let's look for the alpine image;

That one is equivalent to running the command 

> docker search alpine

Sometimes there is an indication on which one of them is the 'official version' let's click on the link:

Now you can also look at the list of available tags. An image name and tag is standing for a particular version of the image.

Now you see something interesting with the Alpine image: each pair of image name and tag has multiple image entries, each of these image entries is a special build for a different CPU architecture! Now what happens if we pass a specific image like linux:latest to docker? Docker pull is always picking the image with the same CPU architecture of the host, which is: arm64 v8 (this Mac has an M1 processor)

Let's download the latest version of alpine:latest

It shows you the progress of downloading the image

Let's also download the previous version of alpine - here I am asking to download the image for the amd64 architecture

(alpine 3.17 amd64)

> once it's done then lets look at the "images" tab - all local images a listed here. 


This is equivalent of:

> docker images

In the UI you can click on the id of the docker image or on the RepoDigest - this opens a screen where you can inspect additional details of the image.

This is equivalent to the docker command 

> docker inspect 'alpine:latest'

This shows a lot of information:
You can see:
    The command that is run by default - if you create a container without specifying a run command, 
    the environment for that command - very important if you need to debug a docker container.
    The operating system and processor architecture of that image.

Let's check the architecture of the previous image: amd64 - as requested.

Now lets run the container: don't worry, docker can run both images, both with native cpu  architecture, which is arm64, and with amd64. Now docker will need more memory and time for the non native architecture, because docker has to translate all of the instructions of the amd64 instruction set to the 64 bit arm instruction set.


----

Let's create a running container based on a docker image.

In the 'Images tab' we have a 'Create Container' link - now this is a screen with many options, but don't worry!

In this page we can only create docker containers running in 'detached mode' - they are like daemon or service, without a visible input or output stream.

:: Container Name: my-env

I am giving this container a name, that has a meaning - no two containers can have the same name, so this is a way to make sure that we don't start this container twice.

:: Command: sleep infinity

This will create a container with a main command that is just keeping busy, so that the container won't just exit.

:: Mounted Volumes: /Users/mmoser:/var/homedir

The home directory on my mac will be visible as /var/homedir from inside the container

:: Network mode: Host

The container will share exactly the same network as the host, if a process inside the container is listening to a port, then this service will also be reachable from the current user on the mac.

This is not the default networking mode: the default setting is 'bridge', here where a virtual ethernet adapter is created for the container.
a process running inside the contaienr can still connect to any host visible on the mac os, all of the network traffic is forwarded ot the real network adapter,
However a service running inside the container which is listening on a port has a harder job: in 'bridge' mode we need to explicitly forward this port from the host network to the container network (see the 'Exposed ports' field in this form)

Let's start the thing.

[Containers]

The Containers screen has a new entry for the new container. 

/New Tab/ is opening a  





=======
OLD TRY
=======



Now the container has been started and the web server is listening on port 9000.

The $(docker ps) command is listing all running docker containers:

```
    docker ps

    CONTAINER ID   IMAGE                                      COMMAND                  CREATED             STATUS             PORTS                  NAMES
    d4d884940a13   ghcr.io/mosermichael/phpdocker-mm:latest   "docker-php-entrypoi…"   About an hour ago   Up About an hour   0.0.0.0:9000->80/tcp   docker-php-admin
```

Lets look at the URL in the web browser:

    http://0.0.0.0:9000/images.php 

First thing you see is a list of locally installed docker images

You can inspect the details of a docker image either by following the link with the image id or on the link with image tag name

    http://0.0.0.0:9000/gen.php?cmd=inspecti&id=ghcr.io/mosermichael/s9k-mm:latest 

This gives you the lower level details for the given image.

- The Config tag shows you the Entry point 
    - that's the command that is run by default when the image is used. 
    - The environment variables set for running the entry point.
- Other important fields are the operating system of the image, the processor architecture that is required to run this image,
- This image has been built for both the Intel 64 processor architecture and the arm64 architecture used by the M1 processor and .
  Here  we are running on a M1 processor so that's the version that has been pulled when we started the server.

Back to the list of images
    
    http://0.0.0.0:9000/images.php

For each image you have the /History/ link, this shows you the sequence of commands that were used to build that image.

    http://0.0.0.0:9000/imageHistory.php?id=a1a76be1abae

An image is typically build by executing the sequence of commands defined in a Dockerfile, here you can follow up on these commands.
Each of these command is changing the docker image by adding a new layer in the image. Later this image will be used by a docker container, at this moment the image is mounted by the containers file system. If a file on the container is accessed then these layers are searched in reverse order - at first it tries to look up the file in the latest layer, if it is not foud then it tries to find the file in the next oldest layer, and so on. 

Let's search for an image on Docker hub - in the Pull/Search screen

    http://0.0.0.0:9000/reg.php

    (Search for fedora)

http://0.0.0.0:9000/searchres.php?cmd=search&id=fedora

    This table is listing all of the matching docker images, similar to $(docker search fedora)
    Now here we have an additional link with each search result

http://0.0.0.0:9000/searchresdetails.php?arg=fedora

    This screen lists all the tags for an image that appears in the search results. A particular image is always described by the combination of the image name and a tag; there may be multiple tags, each describing a different build of the image. Each line has some additional information - the fedora image is always built for the linux operating system, now each tag can have multiple images - each one suitable for a particular processor architectures, amd64 - is 64 bit the Intel x86_64 architecture, there is also arm64 - this is the processor architector of the Apple M1 chip.

    You can follow up on the older tags - right now older tags like version 23 to 20 are only build for the 64 bit intel processor (amd64) 

    Also: images for a given tag may get a retroactive update, in this case the sha of the image will change.
    However you can try to try to require a given exact image by refering to it's sha.
    A Dockerfile can specify the image name, image tag and sha checksum - that will ignore all future retroactive updates for the image.

FROM  fedora:38@sha256:ed0bafb5fc0ef5128ebe28c3d4bfbab2770913afd791d1f5b413d7b20b586ff6
    

Let's pull the latest fedora image from docker hub
    
    http://0.0.0.0:9000/reg.php

    (filling in fedora and latest under "Pulling docker image" and pressing Pull)

You can see a progress indicator here, as the image is being pulled.

Now lets look at the current list of local images 

    http://0.0.0.0:9000/images.php

The Fedora image has just been pulled, and is now listed as a local image.

Let's press at the 'Create Container' link near the 'fedora image'

We will run the container in "detached mode" - meaning that it will run in the background, as a daemon process.

    Command:  "/bin/sleep infinity"

This command is just sleeping -  that means the container will remain in a running state, as long as this main command is running.

Lets mount my home directory into the file system of the container. The container will be able to read and write all files under my home directory - these are accessible under /mnt/home

    Mounted Volumes: /Users/mmoser:/mnt/home

Assigning a container name is optional. You can't create two containers with the same name, this helps to make sure that there is only one instance with this name.

    Name: myenv

Let's create the container
    
    pressing on "Start"

Let's now look at the list of running containers:

    http://0.0.0.0:9000/containers.php

It is possible to get a terminal that runs within the interactive container! Let's follow the /Console/ link.

    <in the console>

    bash

    cat /etc/os-release

Now we have a terminal into a running fedora system, one that can access my home directory via the mount 

    ls /mnt/home

We also have access to the network of the host.

    

We are running on Linux, on a Debian based distribution.

You can see the running processes

    ps -elf

Actually we have another running container here - with the image no_sh:latest you can't just attach a terminal to it, as it doesn't have a shell installed in the image.

Let's try in a terminal 

```
    docker ps 

        CONTAINER ID   IMAGE                                      COMMAND                  CREATED       STATUS       PORTS                  NAMES
        d4d884940a13   ghcr.io/mosermichael/phpdocker-mm:latest   "docker-php-entrypoi…"   5 hours ago   Up 5 hours   0.0.0.0:9000->80/tcp   docker-php-admin
        cdec7ccf5c57   no_sh:latest                               "/bin/sleep infinity…"   7 hours ago   Up 7 hours                          wizardly_morse

    docker exec -it cdec7ccf5c57 /bin/sh
 
        OCI runtime exec failed: exec failed: unable to start container process: exec: "/bin/sh": stat /bin/sh: no such file or directory: unknown
```

Now in the web application you still can attach a terminal to that same docker container!

    http://0.0.0.0:9000/containers.php

That's because the dashboard is going an extra mile: if we failed to attach a terminal with the shell, then it copies a precompiled shell binary onto the container. The next attempt to attach a shell will now succeed!

Let's look at the list of running containers 

    http://0.0.0.0:9000/containers.php

The link /diffs/ shows all modifications of the file system for a running container. You can see which files weer added, modified or deleted.

    http://0.0.0.0:9000/containerDiff.php?id=cdec7ccf5c57

As you see, the file /bin/bash was added (as well as /bin/sh - a symlink to /bin/bash) - the dashboard added these, in order to allow us to attach a terminal.



    
============


Let's look at what is happening behind the scenes:

Let's run a simple docker command line command - to list all running containers

```
docker ps
CONTAINER ID   IMAGE                                      COMMAND                  CREATED      STATUS      PORTS                  NAMES
3c32fa9fc644   ghcr.io/mosermichael/phpdocker-mm:latest   "docker-php-entrypoi…"   3 days ago   Up 3 days   0.0.0.0:8000->80/tcp   docker-php-admin
```

That's the same data as obtained from the REST call,

```
curl --unix-socket /var/run/docker.sock http://localhost/v1.41/containers/json | jq .

[
  {
    "Id": "3c32fa9fc644d5b1ff4d3173e6841c4b3ad3e42dbaa837d684c8368c20932f78",
    "Names": [
      "/docker-php-admin"
    ],
    "Image": "ghcr.io/mosermichael/phpdocker-mm:latest",
    "ImageID": "sha256:972a362c51de76788e265c193a307be7abec0082569ed9a9042d32da9db86df9",
    "Command": "docker-php-entrypoint /bin/sh -c /run-apache.sh",
    "Created": 1672073071,
    "Ports": [
      {
        "IP": "0.0.0.0",
        "PrivatePort": 80,
        "PublicPort": 8000,
        "Type": "tcp"
      }
    ],
    "Labels": {
      "desktop.docker.io/binds/0/Source": "/var/run/docker.sock",
      "desktop.docker.io/binds/0/SourceKind": "dockerSocketProxied",
      "desktop.docker.io/binds/0/Target": "/var/run/docker.sock"
    },
    "State": "running",
    "Status": "Up 3 days",
    "HostConfig": {
      "NetworkMode": "default"
    },
    "NetworkSettings": {
      "Networks": {
        "bridge": {
          "IPAMConfig": null,
          "Links": null,
          "Aliases": null,
          "NetworkID": "0d8b528fa71893567720972d6943427e0822702c070d289bd727e90a678edac0",
          "EndpointID": "c5e74a3b410fee739cca1fd52d81979969de6dfa9e3279745547274b0b4c3614",
          "Gateway": "172.17.0.1",
          "IPAddress": "172.17.0.2",
          "IPPrefixLen": 16,
          "IPv6Gateway": "",
          "GlobalIPv6Address": "",
          "GlobalIPv6PrefixLen": 0,
          "MacAddress": "02:42:ac:11:00:02",
          "DriverOpts": null
        }
      }
    },
    "Mounts": [
      {
        "Type": "bind",
        "Source": "/run/host-services/docker.proxy.sock",
        "Destination": "/var/run/docker.sock",
        "Mode": "",
        "RW": true,
        "Propagation": "rprivate"
      }
    ]
  }
]
```

The docker CLI is sending a REST call to the UNIX domain socket  ```/var/run/docker.sock ```


Let's search for the docker daemon process that is listening to that socket

```
sudo lsof -U | grep docker.sock
com.docke  1360                 mmoser   29u  unix 0xe0ab006a0dc99739      0t0      /Users/mmoser/.docker/run/docker.sock
com.docke  1380                 mmoser   15u  unix 0xe0ab006a0dc99739      0t0      /Users/mmoser/.docker/run/docker.sock
com.docke  1380                 mmoser   24u  unix 0xe0ab006a0dc9d041      0t0      /Users/mmoser/.docker/run/docker.sock
com.docke  1391                 mmoser    3u  unix 0xe0ab006a0dc99739      0t0      /Users/mmoser/.docker/run/docker.sock
```

You see that on OSX all of the listeners are running as the current user!

Actually  /Users/mmoser/.docker/run/docker.sock seems to be an alias for  /var/run/docker.sock 

The following gives you the same output: 

```
curl --unix-socket  /Users/mmoser/.docker/run/docker.sock http://localhost/v1.41/containers/json | jq .
```

The following command gives you the hierarchy of the docker daemon processes

```
pstree 1360
-+= 01360 mmoser /Applications/Docker.app/Contents/MacOS/com.docker.backend -watchdog -native-api
 |--- 01369 mmoser /Applications/Docker.app/Contents/MacOS/com.docker.backend -watchdog -native-api
 |-+- 01374 mmoser /Applications/Docker.app/Contents/MacOS/Docker Desktop.app/Contents/MacOS/Docker Desktop --name=dashboard
 | |--- 01407 mmoser /Applications/Docker.app/Contents/MacOS/Docker Desktop.app/Contents/Frameworks/Docker Desktop Helper (GPU).app/Contents/MacOS/Docker Desktop Helper (GPU) --typ
 | |--- 01416 mmoser /Applications/Docker.app/Contents/MacOS/Docker Desktop.app/Contents/Frameworks/Docker Desktop Helper.app/Contents/MacOS/Docker Desktop Helper --type=utility --
 | \--- 01417 mmoser /Applications/Docker.app/Contents/MacOS/Docker Desktop.app/Contents/Frameworks/Docker Desktop Helper (Renderer).app/Contents/MacOS/Docker Desktop Helper (Rende
 |-+- 01377 mmoser com.docker.vpnkit --ethernet fd:3 --diagnostics fd:4 --pcap fd:5 --vsock-path vms/0/connect --host-names host.docker.internal,docker.for.mac.host.internal,docker
 | \--- 01385 mmoser (uname)
 |--- 01378 mmoser docker serve --address unix:///Users/mmoser/.docker/run/docker-cli-api.sock
 |--- 01379 mmoser vpnkit-bridge --disable wsl2-cross-distro-service,wsl2-bootstrap-expose-ports,transfused,osxfs-data --addr tcp+bootstrap+server://0.0.0.0:0/d46862202e74a291d87d1
 |-+- 01380 mmoser com.docker.driver.amd64-linux -addr fd:3 -debug -native-api
 | |--- 01391 mmoser /Applications/Docker.app/Contents/MacOS/com.docker.driver.amd64-linux -addr fd:3 -debug -native-api
 | \--- 01399 mmoser /Applications/Docker.app/Contents/MacOS/qemu-system-aarch64 -accel hvf -cpu host -machine virt,highmem=off -m 8092 -smp 5 -kernel /Applications/Docker.app/Cont
 |--- 01381 mmoser com.docker.extensions -address extension-manager.sock -watchdog
 \--- 01382 mmoser com.docker.dev-envs
``` 

The main docker daemon is spawning a  qemu-system-aarch64 process for the docker container that has been started. 
qemu-system-aarch64 is an emulator for the ARM architecture on OSX, https://www.qemu.org/docs/master/system/target-arm.html
The Linux OS of the docker container needs to be emulated on a OSX host. For linux running on linux there is a more lightweight mechanism for running containers. The Linux OS has the OS primitive of CGROUPS - these allow you to run the user processes of the container in isolation from the user processes of the host operating system. Both the container and the Linux host OS still share the same running kernel, however the process ids and all file descriptors of the container are not visible to the host operating system, by virtue of the CGROUP abstraction.
