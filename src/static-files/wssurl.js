
function makeWssUrl(uri) {
    let wsProtocol = location.protocol === 'http:' ? 'ws' : 'wss';
    let port = parseInt(location.port) + 1;
    return wsProtocol + '://' + location.hostname + ':' + port + '/' + uri;
}

