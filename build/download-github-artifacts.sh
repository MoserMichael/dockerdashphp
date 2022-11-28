#!/usr/bin/env bash

USER=""
REPO=""

function Help {
    if [[ $1 != "" ]]; then
        echo "Error: $*"
    fi

    if [[ ! -z "${SHORT_HELP_MODE}" ]]; then
        echo "-s <source filter> -f <from> -t <to> [-v -h] : find replace in multiple files"
        exit 1
    fi

cat <<EOF
$0 -u <github user> -r <github reporsitory> [-v -h]

for a given github user and repository: downloads all artifacts for the latest release .

    -u <github user>        - github user
    -r <github repository>  - repository of the given user
    -o <outputdir>          - optional (default: download to current dir
    
    -v                      - verbose output
    -h                      - show this help text

EOF

exit 1
}

USER=""
REPO=""
DIR="."

while getopts "hvu:r:o:" opt; do
  case ${opt} in
    h)
        Help
        ;;
    u)
        USER="$OPTARG"
        ;;
    r)
        REPO="$OPTARG"
        ;;
    o)
        DIR="$OPTARG"
        ;;
    v)
	set -x
	export PS4='+(${BASH_SOURCE}:${LINENO})'
	VERBOSE=1
        ;; 
    *)
        Help "Invalid option"
        ;;
   esac
done	

if [[ $USER == "" ]]; then
    Help "user not given"
fi

if [[ $REPO == "" ]]; then
    Help "repository not given"
fi

LATEST_TAG=$(curl -L -s -S  -H "Accept: application/json" "https://github.com/${USER}/${REPO}/releases/latest" | jq --raw-output .tag_name)

echo "Latest release tag: ${LATEST_TAG}"

for artifact in $(curl -L -s -S -H "Accept: application/vnd.github+json" "https://api.github.com/repos/${USER}/${REPO}/actions/artifacts" | jq --raw-output '.artifacts[] | select(.expired==false) | .name'); do
    echo "downloading artifact: ${artifact} to: ${DIR}/${artifact}"
    ARTIFACT_URL="https://github.com/${USER}/${REPO}/releases/download/${LATEST_TAG}/${artifact}"
    curl -L -s -S "${ARTIFACT_URL}" -o "${DIR}/${artifact}"
done




