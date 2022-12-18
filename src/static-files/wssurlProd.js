

function makeWssUrl(uri) {
    let wsProtocol = location.protocol === 'http:' ? 'ws' : 'wss';
    let port = parseInt(location.port);
    let url = wsProtocol + '://' + location.hostname + ':' + port + '/' + uri;
    console.log("url: " + url);
    return url;
}
