#!/bin/bash	

set -ex

#git clean -f -d

TMPFILE=$(mktemp -d)
cp -rf . ${TMPFILE}

pushd ${TMPFILE}
cp src/static-files/wssurlProd.js src/static-files/wssurl.js
docker build -f Dockerfile -t ghcr.io/mosermichael/phpdocker-mm:latest . 
popd

rm -rf ${TMPFILE}
