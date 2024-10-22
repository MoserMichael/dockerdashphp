
let trace_on = false;
let location_with_token = false;

/**
 * Keep location with return value of token parsers.
 * If set/true: the token functions return a list of [ <tokenvalue parsed", number-offset-of-token ]
 * This function must be called before calling any of the token parser generating functions (like makeTokenParser, makeRegexParser)
 * @param on - boolean true - enable tracing. (default: off)
 */
const setKeepLocationWithToken = function(on) {
    location_with_token = on;
}

/**
 * Enable tracing of the parser. This function must be called before calling any of the parser generating functions (like makeSequenceParser, etc.)
 * @param on - boolean true - enable tracing. (default: off)
 */
const setTrace = function(on) {
    trace_on = on;
}

function showRec(arg) {
    let ret = "";
    if (arg instanceof Array) {
        ret += "[";
        let i = 0;
        for (; i < arg.length; ++i) {
            if (i > 0) {
                ret += ",";
            }
            let obj = arg[i];
            ret += showRec(obj);
        }
        ret += "]";
    } else {
        ret += arg;
    }
    return ret;
}

/**
 * class that encapsulates the parsers input and output of a parser.
 * The input is the current position of the parser and the text that is being parsed
 * The returned output is an object that represents the parsed value:
 */
class State {
    constructor(pos, data) {
        this.pos = pos;
        this.data = data;
        this.lastError = null;
    }

    setStateError(newError) {
        if (!(newError instanceof ParserError)) {
            throw newError;
        }
        if (newError.noRecover) {
            throw newError;
        }

        this.lastError = this.lastError == null || newError.pos >= this.lastError.pos ? newError : this.lastError;
    }

    clearLastError() {
        this.lastError = null;
    }

    show() {
        return showRec(this.data);
    }

}

class ParserError extends Error {
    constructor(message, pos, noRecover = false) {
        super(message);
        this.pos = pos;

        this.data = null;
        this.filePath = null;
        this.noRecover = noRecover
    }

    setLastError(lastError) {
        if (lastError == null) {
            return this;
        }
        return this.setLastError_(lastError);
    }

    setLastError_(lastError) {
        if (lastError.pos > this.pos) {
            lastError.lastError = this;
            return lastError;
        }
        if (lastError.pos == this.pos) {
            this.lastError = lastError.lastError;
            return this;
        }
        if (this.lastError == null) {
            return lastError;
        }
        this.lastError = this.lastError.setLastError_(lastError);
        return this;
    }
}


function getMostMatchingError(errors) {
    let mostMatching = null;
    let lastErrorPos = -1;

    for(let i=0; i< errors.length; ++i) {
        let errorPos = errors[i].pos;
        if (errorPos > lastErrorPos) {
            lastErrorPos = errorPos;
            mostMatching = errors[i];
        }
    }
    return [ mostMatching, lastErrorPos ];
}


function throwError(error, lastError) {
    let rt = error.setLastError(lastError);
    //console.log("throw: " + rt.pos + " : " + rt.message);
    throw rt;
}

function getLineNo(data, pos) {
    let count = 1;
    for(let i=0; i<pos; i++) {
        if (data.charAt(i)=='\n') {
            count += 1;
        }
    }
    return count;
}

function getLineAt(data, pos) {

    if (pos >= data.length) {
        pos = data.length - 1;
    }

    // skip back whitespaces.
    for(;pos>0 && isSpace(data.charAt(pos));--pos);

    let start = pos;
    for(;start>=0 && data.charAt(start) != '\n'; --start);
    start += 1;
    let end = pos;
    for(;end < data.length && data.charAt(end) != '\n'; ++end);
    if (start > pos) {
        start = pos;
    }
    return [ data.substring(start, end), pos-start+1];
}

function isSpace(ch) {
    return ch.trim() == "";
}

let skipWhitespace = function(state) {
    while(state.pos < state.data.length) {
        let ch = state.data.charAt(state.pos);
        if (!isSpace(ch)) {
            break
        }
        state.pos+=1;
    }
}

/**
 * set function that is called in order to skip whitespaces
 * this can be used to skip comments as well
 * @param argFunction - function that skips whitespaces. The function gets a State object as parameter and advances the pos member)
 */
function setSkipWhitespaceFunction(argFunction) {
    skipWhitespace = argFunction;
}

/**
 * @returns  function that skips whitespaces
 */
function getSkipWhitespaceFunction() {
    return skipWhitespace;
}

/**
 * format a parser error
 * @param er - instance of ParserError
 * @param data - the string that was being parsed.
 * @returns the formatted message
 */
function formatParserError(er, data) {
    if (er instanceof ParserError) {
        //console.log("er: " + JSON.stringify(er));

        let msg = "";
        let pos = er.pos;

        if (er.filePath != null) {
            msg = er.filePath + ": ";
        }

        msg += er.message;
        let entry = getLineAt(data, pos);
        let prefix = getLineNo(data,pos) + ": ";
        msg += "\n" + prefix + entry[0] + "\n" +  Array(entry[1]+prefix.length).join(".") + "^";
        if (er.nextException != null) {
            msg += "\n";
            msg += formatParserError(er.nextException, data);
        }
        return msg;
    }
    return er.message + "\n" + er.stack;
}


const makeTracer = function(parser, title) {
    if (!trace_on) {
        parser.prototype['title'] = title;
        return parser;
    }
    let ret = function(state) {
        console.log("enter parser: " + title);
        //console.debug("enter parser: state.pos: " + state.pos + (state.lastError != null ? " lastError.pos: " + state.lastError.getLatestPos() : ""));

        let entry = getLineAt(state.data,state.pos);
        let msg = entry[0] + "\n" + " ".repeat(entry[1]) + "^";
        console.log(msg);

        let ret = null;
        try {
            ret = parser(state);
        } catch(e) {
            console.log("error in parser: " + title);
            throw e;
        }

        entry = getLineAt(state.data,state.pos);
        msg = entry[0] + "\n" + " ".repeat(entry[1]) + "^";
        console.log(msg);

        console.log("exit parser: " + title);
        //console.debug("exit parser: state.pos: " + state.pos + (state.lastError != null ? " lastError.pos: " + state.lastError.getLatestPos() : ""));

        return ret;
    }
    ret.prototype['title'] = title;
    return ret;
}

/**
 * Returns a parser that can matches/consumes a given regular expression
 * @param regex - the regex to match
 * @param name - optional name of the parser (for more readable error messages)
 * @returns is setKeepLocationWithToken - returns a list [ "<token value>", offset_of_token ] , else the token string is returned.
 */
const makeRegexParser = function (regex, name = null) {
    if (!(regex instanceof RegExp)) {
        let msg = "Illegal parser definition, expected RegRxp, got: " + typeof(regex);
        throw new Error(msg);
    }
    if (name == null) {
        name = regex.source;
    }
    return makeTracer(function (state) {
        skipWhitespace(state);

        if (state.pos >= state.data.length) {
            throw new ParserError(name + " expected", state.pos);
        }

        let remainder = state.data.substring(state.pos);
        let tres = regex.exec( remainder );
        if (tres != null) {
            let data = null;

            if (location_with_token) {
                data = [ tres[0], state.pos ];
            } else {
                data = tres[0];
            }
            state.pos = state.pos + tres[0].length;
            return data;
        }
        throw new ParserError(name + " expected", state.pos);

    }, name=name);
}

/**
 * returns parser that consumes argument token string
 * @param token
 * @returns is setKeepLocationWithToken - returns a list [ "<token value>", offset_of_token ] , else the token string is returned.
 */
const makeTokenParser = function (token) {

    if (typeof(token) != 'string') {
        let msg = "Illegal parser definition, expected string, got " + typeof(token);
        console.log(msg);
        throw new Error(msg);
    }

    return makeTracer(function (state) {
        skipWhitespace(state);
        if (state.pos >= state.data.length) {
            throw new ParserError(token + " expected", state.pos);
        }
        if (state.data.substring(state.pos, state.pos + token.length) == token) {
            let data = null;
            if (location_with_token) {
                data = [ token, state.pos ];
            } else {
                data = token;
            }
            state.pos = state.pos + token.length;
            return data;
        }
        throw new ParserError(token + " expected", state.pos);
    }, name=token);
}

function requireFunction(f) {
    if (!(f instanceof Function)) {
        let msg="argument is not a function";
        console.trace(msg);
        throw new Error(msg);
    }
}

function requireArrayOfFunctions(a) {
    if (!(a instanceof Array)) {
        let msg = "argument is not a array";
        console.trace(msg);
        throw new Error(msg);
    }

    for(let i=0; i < a.length; ++i) {
        if (!a[i] instanceof Function) {
            let msg = "Array element " + i + " is not a function. Requires array of functions";
            console.trace(msg);
            throw new Error(msg);
        }
    }
}

/**
 * returns parser that applies all argument parsers sequentially. The parser succeeds if all argument parsers succeeded to parse their input.
 * @param arrayOfParsers - array of argument parsers, each one applied after the previous one.
 * @param title - optional name of the parser (for more readable error messages)
 * @param concat - if not true: result of each parser is pushed to result array. (default value). if set: result of each parser is concatenated to the result array
 * @param clearError - set to true if the last term is an optional sequence.
 * @returns an array with the results returned by each of the sequence parser.
 */
const makeSequenceParser = function(arrayOfParsers, title ="SequenceParser", concat = false, clearError = true) {

    requireArrayOfFunctions(arrayOfParsers);

    return makeTracer( function(state) {
        let result = [];

        skipWhitespace(state); // for error reporting.

        let startPos = state.pos;
        for(let i=0; i<arrayOfParsers.length;++i) {
            let parser = arrayOfParsers[i];
            try {
                let res = parser(state);
                if (concat) {
                    result = result.concat(res);
                } else {
                    result.push(res);
                }
            } catch(er) {
                let errorPos = state.pos;
                state.pos = startPos;

                let termTitle = getParserTitle(arrayOfParsers[i]);
                if (termTitle == null) {
                    termTitle = "term " + i;
                }

                if (state.pos != startPos) {
                    let errorTitle = "Parsing error in " + title;
                    if (i > 0) {
                        errorTitle += " after " + arrayOfParsers[ i - 1].title;
                    }
                    throwError(new ParserError(errorTitle, errorPos), er);
                } else {
                    throw er;
                }
            }
        }

        if (clearError) {
            //console.log("clearLastError: " + title);
            state.clearLastError();
        }

        // todo: i don't like that (get rid of that)
        // Achtung! if last element is an empty array then chop it off
        // that's for cases when we have an optional repetition of clauses
        if (result.length != 0) {
            let last = result[result.length-1];
            if (last instanceof Array && last.length == 0) {
                result.pop();
            }
        }

        return result;
    }, title);
}

/**
 * returns parser that applies argument parser repeatedly
 * @param parser - argument parsing function
 * @param minMatching - number of minimal matches (default 1)
 * @param maxMatching - number of maximum allowed matches, -1 - no limit
 * @param name - optional name of the parser (for more readable error messages)
 * @param concat - if not true: result of each parser is pushed to result array. (default value). if set: result of each parser is concatenated to the result array
 * @returns a list with the results returned by each nested parser invocation
 */
const makeRepetitionParser = function(parser, minMatching = 1, maxMatching = -1, name = "RepetitionParser", concat = false) {

    requireFunction(parser);

    return makeTracer( function (state) {
        let result = [];
        let matching = 0;

        let firstPos = state.pos;

        for (; state.pos < state.data.length && (maxMatching == -1 || matching < maxMatching); ++matching) {
            let lastPos = state.pos;
            try {
                let res = parser(state);

                if (concat) {
                    result = result.concat(res);
                } else {
                    result.push(res);
                }
            } catch(er) {
                state.pos = lastPos;
                state.setStateError(er);
                break;
            }
        }

        if (matching < minMatching && minMatching != 0) {
            state.pos = firstPos;
            if (matching == 0) {
                throw new ParserError("didn't match even one in " + name, state.pos);
            } else {
                throw new ParserError("didn't match enough in " + name, state.pos);
            }
        }

        return result;
    }, name);
}

/**
 * returns parser that applies argument parser repeatedly
 * @param parserMandatory - argument parsing function
 * @param parserRepetition - argument parsing function
 * @param title - optional name of the parser (for more readable error messages)
 * @param concat - if not true: result of each parser is pushed to result array. (default value). if set: result of each parser is concatenated to the result array
 * @returns a list with the results returned by each nested parser invocation
 */
const makeRepetitionRecClause = function(parserMandatory, parserRepetition, title = "RecursiveClause", concat = false) {

    requireFunction(parserMandatory);
    requireFunction(parserRepetition);

    return makeTracer( function (state) {
        let result = [];

        let res = parserMandatory(state);
        if (concat) {
            result = result.concat(res);
        } else {
            result.push(res);
        }

        while(true) {
            let lastPos = state.pos;
            try {
                let res = parserRepetition(state);
                if (concat) {
                    result = result.concat(res);
                } else {
                    result.push( res );
                }
            } catch(er) {
                state.pos = lastPos;
                if (!(er instanceof ParserError)) {
                    console.log(er.message + " " + er.stack)
                } else {
                    state.setStateError(er);
                }
                break;
            }
        }

        return result;
    }, title);
}

/**
 * returns parser that applies the argument parser at least once
 * @param parser
 * @param name - optional name of the parser (for more readable error messages)
 * @returns parsing function that receives a State object for the current position within the input and returns the next state.*
 */
const makeOptParser = function(parser, name = "OptParser") {
    return makeRepetitionParser(parser, 0, 1, name);
}

function getParserTitle(parser) {
    if ("title" in parser.prototype) {
        return parser.prototype.title;
    }
    return null;
}

function makeDetailedAltErrorMessage(arrayOfParsers) {
    let errMsg = "";
    for(let i =0; i<arrayOfParsers.length; i++) {
        let title = getParserTitle(arrayOfParsers[i]);
        if (title != null) {
            if (errMsg != "") {
                errMsg += ", ";
            }
            errMsg += title;
        }
    }
    return errMsg;
}


/**
 * returns parser that must requires one of the argument parsers to math the input
 * @param arrayOfParsers - array of argument parsers, tries to apply each of them, consecutively.
 * @param name - optional name of the parser (for more readable error messages)
 * @param forwardWithIndex
 * @returns parsing function that receives a State object for the current position within the input and returns the next state.
 */
const makeAlternativeParser = function(arrayOfParsers, name = "AlternativeParser", forwardWithIndex = false) {

    requireArrayOfFunctions(arrayOfParsers);

    let detailedErrorMessage = "error in " + name + " expected one of: " + makeDetailedAltErrorMessage(arrayOfParsers);

    return makeTracer(function (state) {

        let i = 0;

        // required for error handling
        skipWhitespace(state);

        let initialPos = state.pos;

        let errors = [];

        for(;i < arrayOfParsers.length;++i) {
            let parser = arrayOfParsers[i];

            try {
                state.pos = initialPos;
                let res = parser(state);
                if (forwardWithIndex) {
                    res = [res, i];
                }
                return res;
            } catch(er) {
                state.pos = initialPos;
                if (er instanceof ParserError) {
                    errors.push(er);
                } else {
                    throw er;
                }
            }
        }

        // pick the most advanced error
        let [ nested, _ ] = getMostMatchingError(errors);
        //console.log("throw: " + state.pos + " : " +  detailedErrorMessage);
        throwError(new ParserError(detailedErrorMessage, state.pos), nested);
    }, name);
}

/**
 * returns parser that must consume all of the input
 * @param nestedParser
 * @returns parsing function that receives a State object for the current position within the input and returns the next state.
 */
const makeConsumeAll = function(nestedParser) {

    requireFunction(nestedParser);

    return function consumeAll(state) {
        let res = nestedParser(state);
        skipWhitespace(state);
        if (state.pos < state.data.length) {
            if (state.lastError == null) {
                throw new ParserError("trailing text", state.pos);
            } else {
                //console.log("use last error : " + state.lastError.pos);
                throw state.lastError;
            }
        }
        return res;
    }
}

/**
 * returns parser that applies the argument parser to the input, then it calls the argument function to transform the result
 * @param nestedParser - nested parser
 * @param transformResult - transformation function, receives result of nested parser as input.
 * @returns return the result of the transformResult parser
 */
const makeTransformer = function(nestedParser, transformResult) {

    requireFunction(nestedParser);
    requireFunction(transformResult);

    let ret = function(state) {
        let posStart = state.pos;
        res = nestedParser(state);
        let posRange = [ posStart, state.pos ];
        return transformResult(res, posRange);
    }

    ret.prototype.title = nestedParser.prototype.title;

    return ret;
}

/**
 * returns a forwarding object,
 *   - the inner parser can be set later on by calling the setInner(parser) member function.
 *   - the forwarded parser is called by the forward() member function.
 * This construct is used to express recursive grammars.
 * @returns the result of the forwarded function.
 */
const makeForwarder = function (innerFunc = null) {

    if (innerFunc != null) {
        requireFunction(innerFunc);
    }

    this.forward = function() {
        return function(state) {
            return innerFunc(state);
        }
    };

    this.setInner = function(newVal) {
        requireFunction(newVal);
        innerFunc = newVal;
    }
}

/**
 * Applies the parser on an input string.
 * @param parser - a parser constructed by any of the makeXXXparser functions
 * @parser data - input string
 * @returns the result of the applied parser
 * @throws ParserError - in case of syntax error. also see formatParserError function.
 */
let parseString = function(parser, data) {
    let state = new State(0, data);
    return parser(state);
}


if (typeof(module) == 'object') {
    module.exports = {
        State,
        ParserError,
        formatParserError,
        setTrace,
        setKeepLocationWithToken,
        makeTokenParser,
        makeRegexParser,
        makeOptParser,
        makeRepetitionParser,
        makeSequenceParser,
        makeAlternativeParser,
        makeRepetitionRecClause,
        makeConsumeAll,
        makeTransformer,
        makeForwarder,
        parseString,
        getLineAt,
        getLineNo,
        isSpace,
        setSkipWhitespaceFunction,
        getSkipWhitespaceFunction
    }
}
