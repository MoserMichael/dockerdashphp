I am going to show you my side project, it's a docker dashboard, it helps to work with docker from inside a web browser.

Let's start with an overview of docker :

Docker allows you to run lightweight containers, a lightweight container is an environment for running processes in a different kind of environment.
This different environment may be a different operating system or even a different operating system running on a different kind of processor.
For example: I am running on the MacOS with a M1 processor, however a running docker container my think that it is running on Linux that is running on a 64 bit Intel processor.
However this is a kind of illusion: a process that is running inside the lightweight container is still using the services of the host operating system. 
When the process in such a container is making a system call, then the docker runtime is translating this system call into a call to the host operating system.

Another feature: the process running inside the container is running isolated from the host operating system, for example a process running inside the container doesn't know anything about processes running outside of the container, it doesn't know anything about native processes that are running on the host OS.
The container has it's own file system and it doesn't see any of the file handles opened by processes on the host operating system (that's the default behavior).
The docker runtime is creating the illusion of being on a totally different machine!


All this may sound similar to a what a virtual machine like KVM is doing, however lightweight containers are introducing a smaller overhead than virtual machines, on average.

Enough theory, let's run a Linux container that has it's own interactive shell:

> docker run --rm -ti ubuntu:latest bash

A quick look at the command line:

- docker run    - tells the docker runtime to create a new container instance and to start running it
- ubuntu:latest - ubuntu is the docker image for running the Linux operating system with the ubuntu distribution. A docker image is a archive file, this archive file it is keeping all the files required to run the operating system, in fact it contains the whole file system of that operating system. 
                    - latest is the tag of the image. For each docker image there can be multiple versions, the tag value is standing for a particular version. Usually there is a latest tag that refers to the latest and greatest version of the docker image.
- bash          - tells the container to run a bash shell as its main command. Now this main command will be running inside the container so it will think  that it is a Linux shell. Also the main does have a special status: the container stops running, when the main command stops.

- -ti           - this option for running a shell that appears right in this terminal window. That's like passing -t and -i..
- -i            - means interactive mode, the container is reading the commands that we are typing within the shell.
- -t            - tells docker to create a pseudo-terminal device for that container, this is a requirement for running an interactive shell.
- --rm          - docker does not keep any information about this container, as it exits.


Lets look at the shell: we get the illusion of running as root.

```
> # whoami
> root

> # uname -a
> Linux 9ab0a99ff468 5.10.124-linuxkit #1 SMP PREEMPT Thu Jun 30 08:18:26 UTC 2022 aarch64 aarch64 aarch64 GNU/Linux
```

The ```uname``` command tells us that we are running on the Linux operating system

```
> # cat /etc/*-release
```

The presence of these files is telling us that we are running on the Ubuntu distribution

Let's list all running processes.

```
> # ps -elf 
> F S UID        PID  PPID  C PRI  NI ADDR SZ WCHAN  STIME TTY          TIME CMD
> 4 S root         1     0  0  80   0 -  1035 do_wai 04:25 pts/0    00:00:00 bash
> 4 R root        13     1  0  80   0 -  1603 -      05:01 pts/0    00:00:00 ps -elf
```

The container doesn't know about any process running on the host operating system, there is just the shell and the ps process running (that one is listing all the running processes)
You can see that this is not a real operating system - there are no daemon processes running in the background, just the shell and ps (ps is the command that is listing the processes)
On a full setup or virtual machine one would also see common daemon procsses like ```init``` or ```systemd``` - but here on a docker contaiener there is nothing of that sort.

```
> # stat /.dockerenv
>   File: /.dockerenv
>   Size: 0         	Blocks: 0          IO Block: 4096   regular empty file
> Device: 9ah/154d	Inode: 1975379     Links: 1
```

Also with docker you get this hidden file in the root directory of the file system - that's another way of checking you are running in a docker container.


Let's look at the file system

```
> # ls -al /
> total 56
> drwxr-xr-x   1 root root 4096 Jun  3 04:25 .
> drwxr-xr-x   1 root root 4096 Jun  3 04:25 ..
> -rwxr-xr-x   1 root root    0 Jun  3 04:25 .dockerenv
> lrwxrwxrwx   1 root root    7 May 22 14:05 bin -> usr/bin
> drwxr-xr-x   2 root root 4096 Apr 18  2022 boot
> drwxr-xr-x   5 root root  360 Jun  3 04:25 dev
> drwxr-xr-x   1 root root 4096 Jun  3 04:25 etc
> drwxr-xr-x   2 root root 4096 Apr 18  2022 home
> lrwxrwxrwx   1 root root    7 May 22 14:05 lib -> usr/lib
> drwxr-xr-x   2 root root 4096 May 22 14:05 media
> drwxr-xr-x   2 root root 4096 May 22 14:05 mnt
> drwxr-xr-x   2 root root 4096 May 22 14:05 opt
> dr-xr-xr-x 211 root root    0 Jun  3 04:25 proc
> drwx------   2 root root 4096 May 22 14:10 root
> drwxr-xr-x   5 root root 4096 May 22 14:10 run
> lrwxrwxrwx   1 root root    8 May 22 14:05 sbin -> usr/sbin
> drwxr-xr-x   2 root root 4096 May 22 14:05 srv
> dr-xr-xr-x  13 root root    0 Jun  3 04:25 sys
> drwxrwxrwt   2 root root 4096 May 22 14:10 tmp
> drwxr-xr-x  11 root root 4096 May 22 14:05 usr
> drwxr-xr-x  11 root root 4096 May 22 14:10 var
```

The file system of the container is very different from that of the host. No files are being shared with the host - in this particular case.

Now in another console on the host os: you can list the docker images that can be used to start a docker container

```
> docker images

REPOSITORY                          TAG       IMAGE ID       CREATED       SIZE
ubuntu                              latest    5dce7be29f0d   3 weeks ago   273MB
```

Remember that each docker image is a big archive file. This archive contains the file system that is available to the running container and to the docker runtime. The SIZE column tells you the size of the contained file system.
 the IMAGE ID - that's a number that identifies this docker image uniquely. The name and tag is an alias to this number, note that the image id may change, if the author of the docker image decides to publish a different build for the same image and tag - this ID is a short form of the sha256 hash computed from the whole content of the image file.

We can also get a list of all docker containers running right now:

```
> docker ps 

CONTAINER ID   IMAGE                                      COMMAND                  CREATED             STATUS             PORTS                  NAMES
ba2d516a0dbc   ubuntu:latest                              "bash"                   About an hour ago   Up About an hour                          elegant_faraday
```

Each container has a STATUS - this one is Up, that mans it is running right now. 

The IMAGE column is listing the name of the docker image used to create this container instance

The STATUS column tells us that this container is Up and running.

The CREATED column tells you the uptime of the container, how long that container is already running.

The COMMAND column is the name main command that was used to start the container, this is the main process of the container. The container stops when this main process has stopped.

Each running container instance is identified by a unique id listed in the CONTAINER ID column, you can either use this ID or the NAME of the container - as listed in the NAMES column. Note that we didn't set a name for this container with the docker run command, therefore the docker runtime is making up a name for the container on it's own.

The container id is used to controll the container by means of the docker command line  

```

> docker pause ba2d516a0dbc

```

We just paused the container: when you switch back to the console of the container: it now doesn't react to any keystrokes, you can't enter any commands, everything is frozen.

Now listing all of the containers again, the given container have the 'paused' state

```
> docker ps
```

And you can resume the container again

```
> docker unpause ba2d516a0dbc 

> docker ps 
```

Again listed as running, and the container is now receiving commands again.

The container stops to run as follows:
- The docker cli command can stop it with the command 

```
> docker stop ba2d516a0dbc 

> docker ps -a
```

Note that the container now longer appears in the list of running containers, that's due to the ```--rm``` option passed to docker run - the container object is removed just as it exited.

A container is stopped when the main process running in the container stops - that is the process created via the command passed to the docker run command 

Or by running a docker stop command with the id or name of the container - from outside of the container.


--

There is a different way to run containers, like this one:

```
> docker run ubuntu:latest ls -al /
```

Note that this one is just listing all the files in the root directory of the file system and exiting.

```
> docker ps -a

CONTAINER ID   IMAGE           COMMAND                 CREATED          STATUS                      PORTS     NAMES
d45c22e76249   ubuntu:latest   "ls -al /"              51 seconds ago   Exited (0) 50 seconds ago             stoic_tesla 
```

The status of the container is 'Exited (0)' , Note the the -a option with docker ps, it tells to show all containers, even those that have stopped-. 

The main command displayed all the files in the root directory and then exits, this means that the container is no longer running - this one was run without the --rm option, 

You can still look at the output produced by this container 

```
> docker logs d45c22e76249
```

That command is very usefull for debugging containers!

the command ```docker wait``` is waiting until the container exits and it then show the exit status, similar to what you can do with an operating system process with the wait system call.

```
>docker wait d45c22e76249
 0
```

Now you can clean up the entries for stopped containers

```
> docker container prune -f

> docker ps -a 
```

No more entries of stopped containers.

---

Ok, that was an intro to docker, now let's look at my docker dashboard project, the dashboard gives you a different way to access the functionality of the docker command line tool.
I want to show you how to use the dashboard, but I will also show the equivalent docker CLI commands for each action.

- Let's download the script for starting the dashboard:
```
    curl https://raw.githubusercontent.com/MoserMichael/phpexercise/main/run-in-docker.sh >run-in-docker.sh    
```

- First, let's make this script runnable. 

```
    chmod +x ./run-in-docker.sh
```

- Now run the script to start the WEB server for the docker dashboard:

```
    ./run-in-docker.sh -r 
```
  The dashboard is also running inside a docker container, so it is first downloading it's docker image.

  The docker image contains all of the executables and configurations required for running a WEB server, it's all there in the docker image, ready to run. This is a convenient form to distribute software.

  However ther are some limitations for this case - for example you can't use the dashboard to restart the docker engine, as this would also kill the container that is running the same tool

  The web server is now up and running. Now the URL for accessing the dashboard is written right there in the console, let's put that in a browser:

=====

In the [Images] tab -  you have a screen that is listing all of the docker images available on this machine http://localhost:8000/images.php 
This is similar to the output of the ```docker images``` command, also with some additions.

Inside the browser: you have a [Containers] tab, that's screen that is listing all of the docker containers running on the machine http://localhost:8000/containers.php
This is similar to the output of the ```docker ps``` command, with some additions.

--

The [Pull/Search] tab, Lets search for docker images on the docker hub registry. Docker hub is a big public repository for docker images.

Let's look at the available docker images for the alpine Linux distribution. The Alpine docker image is often used because the image can be quite small - as small as five megabytes.

Let's look at the docker image that is used to run the dashboard server: (click link ghcr.io/mosermichael/phpdocker-mm:latest )

This screen is showing same data as returned by the docker command ```docker inspect ghcr.io/mosermichael/phpdocker-mm:latest```

It shows us a plenty of information in json format, displayed in a more readable format:

    First we different ways of identifying the same docker image:
    First you have the full Id, written as a sha256 hash value - this value can stand in for the docker name or the short id (using the full id can be of benefit, as it would 

    - it is actually better to use the full id, instead of the short one - this would avoid possible conincidences of the short id - which is an abridged version of the full id.




That one is equivalent to running the command 

```
> docker search alpine
```

Sometimes there is an indication on which one of them is the 'official version' let's click on that link:

Now you can also look at the list of available tags. An image name and tag is standing for a particular version of the docker image.

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

Now lets run the container: don't worry, docker can run both images, both with native cpu  architecture, which is arm64, and with amd64 - that's the 64 bit Intel CPU architecture. 

Now docker will run the Intel image by means of the open source QEMU emulator, so that docker will need more memory and time for the non native architecture, as it has to translate all of the instructions on the fly, as the container is running the binary for the first time, this is called dynamic binary translation!

----

Let's create a running container based on a docker image.

In the 'Images tab' we have a 'Create Container' link - now this is a screen with many options...


In this page we are limited to create a docker containers running in 'detached mode' -  here you can't access the input or output streams. these containers are like daemons or services, they are running in the background, 

:: Container Name: my-env

I am giving this container a name, that has a meaning - no two containers can have the same name, so this is a way to make sure that we don't start this container twice.
Note that if you don't assign a name to a container then docker is making up some name for you.

:: Command: sleep infinity

This will create a container with a main command that is just keeping busy, so that the container won't just exit.

:: Mounted Volumes: /Users/mmoser:/var/homedir

The home directory on my mac will be visible as /var/homedir from inside the container

:: Network mode: Host

The container will share exactly the same network as the host, if a process inside the container is listening to a port, then this service will also be reachable from the current user on the mac.

This is not the default networking mode: the default setting is 'bridge', with 'bridge' a virtual ethernet adapter is created especially for the container, in order to create some isolation for the network, a process running inside the contaienr can still connect to the network of the host OS, as all of the network traffic of the container is forwarded to network of the host.
However a service running inside the container which is listening on a tcp or udp port has a harder job: 
If you want to access this service from the host, then you need to explicitly forward this port from the host network to the container network (see the 'Exposed ports' field in this form) 
Also this forwarding process is a bit slower, as compared to sharing the network with that of the host.

Now lets start the container now:

[Containers]

The Containers screen has a new entry for the new container. 

====

Let's get back to the [Containers] Screen: 

http://localhost:8010/containers.php

For each Image there is the /Console/ and /New Tab/ links; the action for this link will attach a terminal to a shell that is running on the container, this terminal will run inside the broswer. "New Tab" will open the browser in a new browser tab, where as "Console" will just run it in the current tab.

On the command line you can also do this by running the following:

```
docker exec -it  /myenv sh
```

However the dashboard is more persistent in its effort - for example: if the docker container doesn't have a shell in it's file system then the dashboard copies the executable of a shell into the docker containers filesystem and then it tries again.


```
====
====
====
```

Let's look at some additional features:



curl --unix-socket /var/run/docker.sock http://localhost/v1.41/containers/json | jq . | less

curl --unix-socket /var/run/docker.sock http://localhost/v1.41/containers/json?size=true | jq . | less

https://github.com/docker/docs/issues/1520

    The "size" information shows the amount of data (on disk) that is used for the writable layer of each container
    The "virtual size" is the total amount of disk-space used for the read-only image data used by the container and the writable layer

check if running on qemu:


# check if running under docker? (this also tells if running on qemu)

apk add virt-what
virt-what

# or check for presence of files: /.dockerenv or /.dockerinit


# docker architecture?

    runc  - creating containers (with namespaces, cgroups, capabilities, and filesystem access controls) / libcontainer - go library to create container. 


https://iximiuz.com/en/posts/implementing-container-runtime-shim/

```
 docker -------> dockerd (docker daemon)
                   |
                   v
                containerd  : uses runC to start a new container   
                   |
                   v
               containderd-shim    (shim is a library that transparently intercepts API calls and changes the arguments passed :- https://en.wikipedia.org/wiki/Shim_(computing) )
                |           ^
                v           v
              runc  -->  container
       (OCI runtime)       
```

?? been looking at docker-shim 

# kubernetes has it's own kind of generalized like interface - CRI - (container runtme interface) - for the purpose of running kubernetes.
# k8s have written their own CRI for dockershim - that was deprecated in k8s 1.20 and removed from 1.24 !!! (lots of politics here)

    OCI = (from docker) a set of standards for containers, describing the image format, runtime, and distribution
    CRI = (part of k8s) an API that allows you to use different container runtimes in Kubernetes.

# find out which container runtime is in use at a given k8s cluster?

    # last column 
    kubectl get nodes -o wide 

    # or...
    kubectl get nodes -o jsonpath='{.spec.containersRuntime}'
//-->
