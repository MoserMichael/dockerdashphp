
let value_parser = makeAlternativeParser([
    makeRegexParser(/^'(\\\\.|[^'])*'/, "string-const" ),
    makeRegexParser(/^"(\\\\.|[^"])*"/, "string-const" ),

    makeTransformer(
        makeRegexParser(/[^\;]*/, "safe-token"),
        function(res) {
            return escape(res); // what do you do instead of escape?
        }
    ),
]);

function makeLabelNameParser() {
    return makeRepetitionParser(
        makeSequenceParser([
            makeRegexParser(/^[a-zA-Z0-9]\.-/),
            makeTokenParser("="),
            value_parser
        ]),
    );
}

function makeEnvVarsParer() {
    let name_value_parser = makeSequenceParser([
        makeRegexParser(/^[a-zA-Z][a-zA-Z0-9]*/), // \.\-\_
        makeTokenParser("="),
        value_parser
    ]);

    return makeSequenceParser([
        name_value_parser,
        makeRepetitionParser(
            makeSequenceParser([
                makeTokenParser(";"),
                name_value_parser
            ]),
            0
        )]);
}

function makePortDefParser() {
    return  makeRepetitionParser(
        makeSequenceParser([
            makeRegexParser(/\d+/),
            makeTokenParser(":"),
            makeRegexParser(/\d+/),
            makeOptParser(
                makeAlternativeParser([
                    makeTokenParser("/udp"),
                    makeTokenParser("/tcp"),
                ] )
            )
        ])
    );
}

function makeMemSizeParser() {
    return makeSequenceParser([
            makeRegexParser(/\d+/),
            makeOptParser(makeRegexParser(/[k|m|b]/))
        ]
    );
}

function runParser(txt, prs, label, show_alert=true) {
    let s = new State(0, txt);

    let parser = makeConsumeAll(prs);
    try {
        let result = parser(s);
        console.log("parsing succeeded!")
        console.log(result.show());
        return result.data;
    } catch(er) {
        let msg = "Error in " + label + "\n" + formatParserError(er, txt);
        if (show_alert) {
            alert(msg);
        } else {
            console.log(msg);
        }
    }
}


