#!/bin/bash	

set -ex

CMD=$1


if  [[ "$CMD" != "nopush" ]]; then

    if [[ $GITHUB_TOKEN == "" ]]; then
        echo "GITHUB_TOKEN env is not defined"
        exit 1
    fi

    if [[ $GITHUB_USER == "" ]]; then
        echo "GITHUB_USER env is not defined"
        exit 1
    fi
    PUSH=--push
else
    PUSH=
fi

echo $GITHUB_TOKEN | docker login ghcr.io -u $GITHUB_USER --password-stdin

TMPFILE=$(mktemp -d)
cp -rf . ${TMPFILE}

pushd ${TMPFILE}
cp src/static-files/wssurlProd.js src/static-files/wssurl.js

docker buildx create --use --name=multiarch --node=multiarch

trap "trap - SIGTERM; set -x; docker logout ghcr.io; docker buildx stop; docker buildx rm; exit 0" SIGINT SIGTERM EXIT


docker buildx build --platform=linux/amd64,linux/arm64 --tag ghcr.io/mosermichael/phpdocker-mm:latest ${PUSH} . 
popd

rm -rf ${TMPFILE}

echo "*** container build completed ***"
