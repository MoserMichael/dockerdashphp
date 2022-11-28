#!/usr/bin/env bash

set -ex

mkdir shells || true

./build/download-github-artifacts.sh -u robxu9 -r bash-static -o shells

TAR=tar
if [[ $(uname) == "Darwin" ]]; then
    TAR=gtar
fi

pushd shells

rm -f *.tar || true
rm -rf bin || true

mkdir bin

for file in $(ls bash-*); do
    echo $file
    stat $file

    rm -f bin/bash || true

    cp $file bin/bash
    chmod +x bin/bash

    pushd bin
    ln -sf bash ./sh
    popd
    pwd
    
    ${TAR} -c -v --owner=0 --group=0 -f ${file}.tar ./bin

    rm "${file}"
done

rm -rf bin

