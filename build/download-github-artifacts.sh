#!/usr/bin/env bash

USER=""
REPO=""

function Help {
    if [[ $1 != "" ]]; then
        echo "Error: $*"
    fi

cat <<EOF
$0 -u <github user> -r <github reporsitory> [-v -h]

for a given github user and repository: downloads all artifacts for the latest release .

    -u <github user>        - github user
    -r <github repository>  - repository of the given user
    -o <outputdir>          - optional (default: download to current dir)
    
    -v                      - verbose output
    -h                      - show this help text

EOF

exit 1
}

# True iff all arguments are executable in $PATH , from: https://stackoverflow.com/questions/6569478/detect-if-executable-file-is-on-users-path
function assert_bins_in_path {
  if [[ -n $ZSH_VERSION ]]; then
    builtin whence -p "$1" &> /dev/null
  else  # bash:
    builtin type -P "$1" &> /dev/null
  fi
  if [[ $? != 0 ]]; then
    echo "Error: $1 is not in the current path"
    exit 1
  fi    
  if [[ $# -gt 1 ]]; then
    shift  # We've just checked the first one
    assert_bins_in_path "$@"
  fi
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

assert_bins_in_path "jq" "curl"

if [[ $USER == "" ]]; then
    Help "user not given"
fi

if [[ $REPO == "" ]]; then
    Help "repository not given"
fi

if [[ $DIR != "" ]]; then 
    if [[ ! -d "$DIR" ]]; then
        echo "Error: output directory $DIR does not exist"
        exit 1
    fi
fi     


LATEST_TAG=$(curl -L -s -S  -H "Accept: application/json" "https://github.com/${USER}/${REPO}/releases/latest" | jq --raw-output .tag_name)

echo "Latest release tag: ${LATEST_TAG}"

for artifact in $(curl -L -s -S -H "Accept: application/vnd.github+json" "https://api.github.com/repos/${USER}/${REPO}/actions/artifacts" | jq --raw-output '.artifacts[] | select(.expired==false) | .name'); do
    echo "downloading artifact: ${artifact} to: ${DIR}/${artifact}"
    ARTIFACT_URL="https://github.com/${USER}/${REPO}/releases/download/${LATEST_TAG}/${artifact}"
    curl -L -s -S "${ARTIFACT_URL}" -o "${DIR}/${artifact}"
done




