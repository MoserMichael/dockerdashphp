
let value_parser = makeAlternativeParser([
    makeTransformer(
        makeRegexParser(/^'(\\.|[^'])*'/, "string-const" ),
        function(arg) {
            return arg.substring(1,arg.length-1);
        }
    ),
    makeTransformer(
        makeRegexParser(/^"(\\.|[^"])*"/, "string-const" ),
        function(arg) {
            return arg.substring(1,arg.length-1);
        }
    ),
    makeRegexParser(/^(\\.|[^\ \;\&\#\|\<\>\`\$\*\(\)\'\"\{\}])*/, "safe-token"), // don't accept unsafe chars: https://www.oreilly.com/library/view/learning-the-bash/1565923472/ch01s09.html
]);

function makeLabelNameParser() {
    // see https://docs.docker.com/config/labels-custom-metadata/
    return makeRepetitionParser(
        makeSequenceParser([
            makeRegexParser(/^[a-zA-Z0-9\.\-]+/, "label"),
            makeTokenParser("="),
            makeTransformer(
                value_parser,
                function(arg) {
                    return JSON.stringify((arg));
                }
            )
        ]),
    );
}

function makeCmdLineParer() {
    return makeRepetitionParser(
        value_parser, 0);
}

function makeEnvVarsParer() {
    let name_value_parser = makeSequenceParser([
        makeRegexParser(/^[a-zA-Z][a-zA-Z0-9\_]*/),
        makeTokenParser("="),
        value_parser
    ]);

    return makeSequenceParser(
        [
            makeTransformer(
                name_value_parser,
                function(state) {
                    return [state];
                }
            ),
            makeRepetitionParser(
                makeTransformer(
                    makeSequenceParser([
                        makeTokenParser(";"),
                        name_value_parser
                    ]),
                    function(state) {
                        return state[1];
                    }
                ),
                0,
                -1,
            )
        ], "environment variables", true);
}

function makePortDefParser() {
    return  makeRepetitionParser(
        makeSequenceParser([
            makeRegexParser(/^\d+/,"host port"),
            makeTokenParser(":"),
            makeRegexParser(/^\d+/, "container port"),
            makeTransformer(
                makeOptParser(
                    makeAlternativeParser([
                        makeTokenParser("/udp"),
                        makeTokenParser("/tcp")
                    ], "protocol definition" )
                ),
                function(res) {
                    if (res.length == 0) {
                        return [ "/tcp" ];
                    }
                    return res;
                })
        ])
    );
}

function makeVolumeMappingParser() {

    let path_parser = makeRegexParser(/^(\\[^;$`*?]|[^\ \;\&\#\|\<\>\`\$\*\(\)\'\"\{\}\?\:])+/);
    return makeRepetitionParser(
        makeSequenceParser([
            path_parser,
            makeTokenParser(":"),
            path_parser
        ], "volume mapping")
    ,0, -1, "volume mappigns");    
        
}

function makeMemSizeParser() {
    return makeSequenceParser([
            makeRegexParser(/\d+/),
            makeOptParser(makeRegexParser(/[k|m|b]/))
        ]
    );
}

function runParser(txt, prs, label, show_alert=true) {

    //setTrace(true);
    //console.log("parsing: " + txt);

    let s = new State(0, txt);

    let parser = makeConsumeAll(prs);
    try {
        let result = parser(s);
        console.log("parsing succeeded!")
        console.log(result.show());
        return result.result;
    } catch(ex) {
        console.log(ex.stack);
        let errmsg = formatParserError(ex, txt);
        let msg = "Error in " + label + "\n" + errmsg;
        if (show_alert) {
            alert(msg);
        } else {
            console.log(msg);
        }
    }
}


