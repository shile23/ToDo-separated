<?php
/*

Copyrights for code authored by Yahoo! Inc. is licensed under the following terms:
MIT License
Copyright (c) 2013-2015 Yahoo! Inc. All Rights Reserved.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Origin: https://github.com/zordius/lightncandy
*/

/**
 * file to keep LightnCandy Parser
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;

use \LightnCandy\Token;
use \LightnCandy\SafeString;

/**
 * LightnCandy Parser
 */
class Parser extends Token
{
    // Compile time error handling flags
    const BLOCKPARAM = 9999;
    const LITERAL = -1;
    const SUBEXP = -2;

    /**
     * Get block params and fix the variable list
     *
     * @param array<boolean|integer|array> $vars parsed token
     *
     * @return array<string>|null Return list of block params or null
     *
     */
    public static function getBlockParams(&$vars) {
        if (isset($vars[static::BLOCKPARAM])) {
            $list = $vars[static::BLOCKPARAM];
            unset($vars[static::BLOCKPARAM]);
            return $list;
        }
    }

    /**
     * Return array presentation for an expression
     *
     * @param string $name variable name.
     * @param boolean $asis keep the name as is or not
     * @param boolean $quote add single quote or not
     *
     * @return array<integer|string> Return variable name array
     *
     */
    protected static function getLiteral($name, $asis, $quote = false) {
        return $asis ? array($name) : array(static::LITERAL, $quote ? "'$name'" : $name);
    }

    /**
     * Return array presentation for an expression
     *
     * @param string $v analyzed expression names.
     * @param array<string,array|string|integer> $context Current compile content.
     * @param integer $pos expression position
     *
     * @return array<integer,string> Return variable name array
     *
     * @expect array('this') when input 'this', array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 0)), 0
     * @expect array() when input 'this', array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1)), 0
     * @expect array(1) when input '../', array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(1) when input '../.', array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(1) when input '../this', array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(1, 'a') when input '../a', array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(2, 'a', 'b') when input '../../a.b', array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(2, '[a]', 'b') when input '../../[a].b', array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(2, 'a', 'b') when input '../../[a].b', array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(0, 'id') when input 'this.id', array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array('this', 'id') when input 'this.id', array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(0, 'id') when input './id', array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 0
     * @expect array(-1, '\'a.b\'') when input '"a.b"', array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 1
     * @expect array(-1, '123') when input '123', array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 1
     * @expect array(-1, 'null') when input 'null', array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0)), 1
     */
    protected static function getExpression($v, &$context, $pos) {
        $asis = ($pos === 0);

        // handle number
        if (is_numeric($v)) {
            return static::getLiteral(strval(1 * $v), $asis);
        }

        // handle double quoted string
        if (preg_match('/^"(.*)"$/', $v, $matched)) {
            return static::getLiteral(preg_replace('/([^\\\\])\\\\\\\\"/', '$1"', preg_replace('/^\\\\\\\\"/', '"', $matched[1])), $asis, true);
        }

        // handle single quoted string
        if (preg_match('/^\\\\\'(.*)\\\\\'$/', $v, $matched)) {
            return static::getLiteral($matched[1], $asis, true);
        }

        // handle boolean, null and undefined
        if (preg_match('/^(true|false|null|undefined)$/', $v)) {
            return static::getLiteral($v, $asis);
        }

        $ret = array();
        $levels = 0;

        // handle ..
        if ($v === '..') {
            $v = '../';
        }

        // Trace to parent for ../ N times
        $v = preg_replace_callback('/\\.\\.\\//', function() use (&$levels) {
            $levels++;
            return '';
        }, trim($v));

        // remove ./ in path
        $v = preg_replace('/\\.\\//', '', $v, -1, $scoped);

        $strp = (($pos !== 0) && $context['flags']['strpar']);
        if ($levels && !$strp) {
            $ret[] = $levels;
            if (!$context['flags']['parent']) {
                $context['error'][] = 'Do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag';
            }
            $context['usedFeature']['parent'] ++;
        }

        if ($context['flags']['advar'] && preg_match('/\\]/', $v)) {
            preg_match_all(static::VARNAME_SEARCH, $v, $matchedall);
        } else {
            preg_match_all('/([^\\.\\/]+)/', $v, $matchedall);
        }

        foreach ($matchedall[1] as $m) {
            if ($context['flags']['advar'] && substr($m, 0, 1) === '[') {
                $ret[] = substr($m, 1, -1);
            } else if ((!$context['flags']['this'] || ($m !== 'this')) && ($m !== '.')) {
                $ret[] = $m;
            } else {
                $scoped++;
            }
        }

        if ($strp) {
            return array(static::LITERAL, "'" . implode('.', $ret) . "'");
        }

        if (($scoped > 0) && ($levels === 0) && (count($ret) > 0)) {
            array_unshift($ret, 0);
        }

        return $ret;
    }

    /**
     * Parse the token and return parsed result.
     *
     * @param array<string> $token preg_match results
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return array<boolean|integer|array> Return parsed result
     *
     * @expect array(false, array(array())) when input array(0,0,0,0,0,0,0,''), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(true, array(array())) when input array(0,0,0,'{{',0,'{',0,''), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(true, array(array())) when input array(0,0,0,0,0,0,0,''), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 0, 'noesc' => 1), 'rawblock' => false)
     * @expect array(false, array(array('a'))) when input array(0,0,0,0,0,0,0,'a'), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array('b'))) when input array(0,0,0,0,0,0,0,'a  b'), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array('"b'), array('c"'))) when input array(0,0,0,0,0,0,0,'a "b c"'), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array(-1, '\'b c\''))) when input array(0,0,0,0,0,0,0,'a "b c"'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array('[b'), array('c]'))) when input array(0,0,0,0,0,0,0,'a [b c]'), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array('[b'), array('c]'))) when input array(0,0,0,0,0,0,0,'a [b c]'), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array('b c'))) when input array(0,0,0,0,0,0,0,'a [b c]'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array('b c'))) when input array(0,0,0,0,0,0,0,'a [b c]'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), 'q' => array('b c'))) when input array(0,0,0,0,0,0,0,'a q=[b c]'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array('q=[b c'))) when input array(0,0,0,0,0,0,0,'a [q=[b c]'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), 'q' => array('[b'), array('c]'))) when input array(0,0,0,0,0,0,0,'a q=[b c]'), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), 'q' => array('b'), array('c'))) when input array(0,0,0,0,0,0,0,'a [q]=b c'), array('flags' => array('strpar' => 0, 'advar' => 0, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), 'q' => array(-1, '\'b c\''))) when input array(0,0,0,0,0,0,0,'a q="b c"'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array(-2, array(array('foo'), array('bar')), '(foo bar)'))) when input array(0,0,0,0,0,0,0,'(foo bar)'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0, 'exhlp' => 1, 'lambda' => 0), 'ops' => array('seperator' => ''), 'usedFeature' => array('subexp' => 0), 'rawblock' => false)
     * @expect array(false, array(array('foo'), array("'=='"), array('bar'))) when input array(0,0,0,0,0,0,0,"foo '==' bar"), array('flags' => array('strpar' => 0, 'advar' => 1, 'namev' => 1, 'noesc' => 0, 'this' => 0), 'rawblock' => false)
     * @expect array(false, array(array(-2, array(array('foo'), array('bar')), '( foo bar)'))) when input array(0,0,0,0,0,0,0,'( foo bar)'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0, 'exhlp' => 1, 'lambda' => 0), 'ops' => array('seperator' => ''), 'usedFeature' => array('subexp' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array(-1, '\' b c\''))) when input array(0,0,0,0,0,0,0,'a " b c"'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 0, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), 'q' => array(-1, '\' b c\''))) when input array(0,0,0,0,0,0,0,'a q=" b c"'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('foo'), array(-1, "' =='"), array('bar'))) when input array(0,0,0,0,0,0,0,"foo \' ==\' bar"), array('flags' => array('strpar' => 0, 'advar' => 1, 'namev' => 1, 'noesc' => 0, 'this' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), array(' b c'))) when input array(0,0,0,0,0,0,0,'a [ b c]'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array(array('a'), 'q' => array(-1, "' d e'"))) when input array(0,0,0,0,0,0,0,"a q=\' d e\'"), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0), 'rawblock' => false)
     * @expect array(false, array('q' => array(-2, array(array('foo'), array('bar')), '( foo bar)'))) when input array(0,0,0,0,0,0,0,'q=( foo bar)'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0, 'exhlp' => 0, 'lambda' => 0), 'usedFeature' => array('subexp' => 0), 'ops' => array('seperator' => 0), 'rawblock' => false)
     * @expect array(false, array(array('foo'))) when input array(0,0,0,0,0,0,'>','foo'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0, 'exhlp' => 0, 'lambda' => 0), 'usedFeature' => array('subexp' => 0), 'ops' => array('seperator' => 0), 'rawblock' => false)
     * @expect array(false, array(array('foo'))) when input array(0,0,0,0,0,0,'>','"foo"'), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0, 'exhlp' => 0, 'lambda' => 0), 'usedFeature' => array('subexp' => 0), 'ops' => array('seperator' => 0), 'rawblock' => false)
     * @expect array(false, array(array('foo'))) when input array(0,0,0,0,0,0,'>','[foo] '), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0, 'exhlp' => 0, 'lambda' => 0), 'usedFeature' => array('subexp' => 0), 'ops' => array('seperator' => 0), 'rawblock' => false)
     * @expect array(false, array(array('foo'))) when input array(0,0,0,0,0,0,'>','\\\'foo\\\''), array('flags' => array('strpar' => 0, 'advar' => 1, 'this' => 1, 'namev' => 1, 'noesc' => 0, 'exhlp' => 0, 'lambda' => 0), 'usedFeature' => array('subexp' => 0), 'ops' => array('seperator' => 0), 'rawblock' => false)
     */
    public static function parse(&$token, &$context) {
        $vars = static::analyze($token[static::POS_INNERTAG], $context);
        if ($token[static::POS_OP] === '>') {
            $fn = static::getPartialName($vars);
        } else if ($token[static::POS_OP] === '#*') {
            $fn = static::getPartialName($vars, 1);
        }

        $avars = static::advancedVariable($vars, $context, static::toString($token));

        if (isset($fn) && ($fn !== null)) {
            if ($token[static::POS_OP] === '>') {
                $avars[0] = $fn;
            } else if ($token[static::POS_OP] === '#*') {
                $avars[1] = $fn;
            }
        }

        return array(($token[static::POS_BEGINRAW] === '{') || ($token[static::POS_OP] === '&') || $context['flags']['noesc'] || $context['rawblock'], $avars);
    }

    /**
     * Get block params and fix the variable list
     *
     * @param array<boolean|integer|array> $vars parsed token
     * @param integer $pos position of partial name
     *
     * @return array<string>|null Return list of block params or null
     *
     */
    public static function getPartialName(&$vars, $pos = 0) {
        if (!isset($vars[$pos])) {
            return;
        }
        return preg_match(SafeString::IS_SUBEXP_SEARCH, $vars[$pos]) ? null : array(preg_replace('/^("(.+)")|(\\[(.+)\\])|(\\\\\'(.+)\\\\\')$/', '$2$4$6', $vars[$pos]));
    }

    /**
     * Parse a subexpression then return parsed result.
     *
     * @param string $expression the full string of a sub expression
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return array<boolean|integer|array> Return parsed result
     */
    public static function subexpression($expression, &$context) {
        $context['usedFeature']['subexp']++;
        $vars = static::analyze(substr($expression, 1, -1), $context);
        $avars = static::advancedVariable($vars, $context, $expression);
        if (isset($avars[0][0]) && !$context['flags']['exhlp']) {
            if (!Validator::helper($context, $avars[0][0])) {
                $context['error'][] = "Can not find custom helper function defination {$avars[0][0]}() !";
            }
        }
        return array(static::SUBEXP, $avars, $expression);
    }

    /**
     * Check a parsed result is a subexpression or not
     *
     * @param array<string|integer|array> $var
     *
     * @return boolean return true when input is a subexpression
     */
    public static function isSubExp($var) {
        return is_array($var) && (count($var) === 3) && ($var[0] === static::SUBEXP) && is_string($var[2]);
    }

    /**
     * Analyze parsed token for advanved variables.
     *
     * @param array<boolean|integer|array> $vars parsed token
     * @param array<string,array|string|integer> $context current compile context
     * @param string $token original token
     *
     * @return array<boolean|integer|array> Return parsed result
     *
     */
    protected static function advancedVariable($vars, &$context, $token) {
        $ret = array();
        $i = 0;
        foreach ($vars as $idx => $var) {
            // handle (...)
            if (preg_match(SafeString::IS_SUBEXP_SEARCH, $var)) {
                $ret[$i] = static::subexpression($var, $context);
                $i++;
                continue;
            }

            // handle |...|
            if (preg_match(SafeString::IS_BLOCKPARAM_SEARCH, $var, $matched)) {
                $ret[static::BLOCKPARAM] = explode(' ', $matched[1]);
                continue;
            }

            if ($context['flags']['namev']) {
                if (preg_match('/^((\\[([^\\]]+)\\])|([^=^["\']+))=(.+)$/', $var, $m)) {
                    if (!$context['flags']['advar'] && $m[3]) {
                        $context['error'][] = "Wrong argument name as '[$m[3]]' in $token ! You should fix your template or compile with LightnCandy::FLAG_ADVARNAME flag.";
                    }
                    $idx = $m[3] ? $m[3] : $m[4];
                    $var = $m[5];
                    // handle foo=(...)
                    if (preg_match(SafeString::IS_SUBEXP_SEARCH, $var)) {
                        $ret[$idx] = static::subexpression($var, $context);
                        continue;
                    }
                }
            }

            if ($context['flags']['advar'] && !preg_match("/^(\"|\\\\')(.*)(\"|\\\\')$/", $var)) {
                    // foo]  Rule 1: no starting [ or [ not start from head
                if (preg_match('/^[^\\[\\.]+[\\]\\[]/', $var)
                    // [bar  Rule 2: no ending ] or ] not in the end
                    || preg_match('/[\\[\\]][^\\]\\.]+$/', $var)
                    // ]bar. Rule 3: middle ] not before .
                    || preg_match('/\\][^\\]\\[\\.]+\\./', $var)
                    // .foo[ Rule 4: middle [ not after .
                    || preg_match('/\\.[^\\]\\[\\.]+\\[/', preg_replace('/^(..\\/)+/', '', preg_replace('/\\[[^\\]]+\\]/', '[XXX]', $var)))
                ) {
                    $context['error'][] = "Wrong variable naming as '$var' in $token !";
                } else {
                    $name = preg_replace('/(\\[.+?\\])/', '', $var);
                    // Scan for invalid charactors which not be protected by [ ]
                    // now make ( and ) pass, later fix
                    if (preg_match('/[!"#%\'*+,;<=>{|}~]/', $name)) {
                        $context['error'][] = "Wrong variable naming as '$var' in $token ! You should wrap ! \" # % & ' * + , ; < = > { | } ~ into [ ]";
                    }
                }
            }

            $var = static::getExpression($var, $context, $idx);

            if (is_string($idx)) {
                $ret[$idx] = $var;
            } else {
                $ret[$i] = $var;
                $i++;
            }
        }
        return $ret;
    }

    /**
     * Analyze a token string and return parsed result.
     *
     * @param string $token preg_match results
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return array<boolean|integer|array> Return parsed result
     *
     */
    protected static function analyze($token, &$context) {
        $count = preg_match_all('/(\s*)([^\s]+)/', $token, $matchedall);
        // Parse arguments and deal with "..." or [...] or (...) or \'...\' or |...|
        if (($count > 0) && $context['flags']['advar']) {
            $vars = array();
            $prev = '';
            $expect = 0;
            $stack = 0;

            foreach ($matchedall[2] as $index => $t) {
                // continue from previous match when expect something
                if ($expect) {
                    $prev .= "{$matchedall[1][$index]}$t";
                    if (($stack > 0) && (substr($t, 0, 1) === '(')) {
                        $stack++;
                    }
                    // end an argument when end with expected charactor
                    if (substr($t, -1, 1) === $expect) {
                        if ($stack > 0) {
                            preg_match('/(\\)+)$/', $t, $matchedq);
                            $stack -= isset($matchedq[0]) ? strlen($matchedq[0]) : 1;
                            if ($stack > 0) {
                                continue;
                            }
                            if ($stack < 0) {
                                $context['error'][] = "Unexcepted ')' in expression '$token' !!";
                                break;
                            }
                        }
                        $vars[] = $prev;
                        $prev = '';
                        $expect = 0;
                    }
                    continue;
                }

                // continue to next match when begin with '(' without ending ')'
                if (preg_match('/^\([^\)]*$/', $t)) {
                    $prev = $t;
                    $expect = ')';
                    $stack=1;
                    continue;
                }

                // continue to next match when begin with '"' without ending '"'
                if (preg_match('/^"[^"]*$/', $t)) {
                    $prev = $t;
                    $expect = '"';
                    continue;
                }

                // continue to next match when begin with \' without ending '
                if (preg_match('/^\\\\\'[^\']*$/', $t)) {
                    $prev = $t;
                    $expect = '\'';
                    continue;
                }

                // continue to next match when '="' exists without ending '"'
                if (preg_match('/^[^"]*="[^"]*$/', $t)) {
                    $prev = $t;
                    $expect = '"';
                    continue;
                }

                // continue to next match when '[' exists without ending ']'
                if (preg_match('/^([^"\'].+)?\\[[^\\]]*$/', $t)) {
                    $prev = $t;
                    $expect = ']';
                    continue;
                }

                // continue to next match when =\' exists without ending '
                if (preg_match('/^[^\']*=\\\\\'[^\']*$/', $t)) {
                    $prev = $t;
                    $expect = '\'';
                    continue;
                }

                // continue to next match when =( exists without ending )
                if (preg_match('/.+\([^\)]*$/', $t)) {
                    $prev = $t;
                    $expect = ')';
                    $stack=1;
                    continue;
                }

                // continue to next match when 'as' without ending '|'
                if (($t === 'as') && (count($vars) > 0)) {
                    $prev = '';
                    $expect = '|';
                    $stack=1;
                    continue;
                }

                $vars[] = $t;
            }
            return $vars;
        }
        return ($count > 0) ? $matchedall[2] : explode(' ', $token);
    }
}

