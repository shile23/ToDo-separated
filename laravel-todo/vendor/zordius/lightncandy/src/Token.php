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
 * file to handle LightnCandy token
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;

/**
 * LightnCandy Token handler
 */
class Token
{
    // RegExps
    const VARNAME_SEARCH = '/(\\[[^\\]]+\\]|[^\\[\\]\\.]+)/';

    // Positions of matched token
    const POS_LOTHER = 1;
    const POS_LSPACE = 2;
    const POS_BEGINTAG = 3;
    const POS_LSPACECTL = 4;
    const POS_BEGINRAW = 5;
    const POS_OP = 6;
    const POS_INNERTAG = 7;
    const POS_ENDRAW = 8;
    const POS_RSPACECTL = 9;
    const POS_ENDTAG = 10;
    const POS_RSPACE = 11;
    const POS_ROTHER = 12;

    /**
     * Setup delimiter by default or provided string
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string $left left string of a token
     * @param string $right right string of a token
     */
    public static function setDelimiter(&$context, $left = '{{', $right = '}}') {
        if (preg_match('/=/', "$left$right")) {
            $context['error'][] = "Can not set delimiter contains '=' , you try to set delimiter as '$left' and '$right'.";
            return;
        }

        $context['tokens']['startchar'] = substr($left, 0, 1);
        $context['tokens']['left'] = $left;
        $context['tokens']['right'] = $right;
        $rawcount = $context['rawblock'] ? '{2}' : ($context['flags']['rawblock'] ? '{0,2}' : '?');
        $left = preg_quote($left);
        $right = preg_quote($right);

        $context['tokens']['search'] = "/^(.*?)(\\s*)($left)(~?)(\\{{$rawcount})([\\^#\\/!&>\\*]{0,2})(.*?)(\\}{$rawcount})(~?)($right)(\\s*)(.*)\$/s";
    }

    /**
     * return token string
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param string[]|null $merge list of token strings to be merged
     *
     * @return string Return whole token
     *
     * @expect 'c' when input array(0, 'a', 'b', 'c', 'd', 'e')
     * @expect 'cd' when input array(0, 'a', 'b', 'c', 'd', 'e', 'f')
     * @expect 'qd' when input array(0, 'a', 'b', 'c', 'd', 'e', 'f'), array(3 => 'q')
     */
    public static function toString($token, $merge = null) {
        if (is_array($merge)) {
            $token = array_replace($token, $merge);
        }
        return implode('', array_slice($token, 3, -2));
    }
}

