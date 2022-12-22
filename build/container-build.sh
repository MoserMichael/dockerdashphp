#!/bin/bash	

set -ex

git clean -f -d

TMPFILE=$(mktemp -d)
cp -rf . ${TMPFILE}

pushd ${TMPFILE}
cp src/static-files/wssurlProd.js src/static-files/wssurl.js

docker buildx create --use
docker buildx build --platform=linux/amd64,linux/arm64 -f Dockerfile -t ghcr.io/mosermichael/phpdocker-mm:latest . 
 
popd

rm -rf ${TMPFILE}
