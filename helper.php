<?php
/**
 * DokuWiki Plugin html; Helper Component
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_html extends DokuWiki_Plugin {
    /* ---------------------------------------------------------
     * get each named/non-named arguments as array variable
     *
     * Named arguments is to be given as key="value" (quoted).
     * Non-named arguments is assumed as boolean.
     *
     * @param (string) $args        argument list
     * @param (string) $defaultkey  key name if single numeric value was given
     * @return (array) parsed arguments in $arg['key']=value
     * ---------------------------------------------------------
     */
    function getArguments($args='', $defaultkey='height') {
        $arg = array();
        // get named arguments (key="value"), ex: width="100"
        // value must be quoted in argument string.
        $val = "([\"'`])(?:[^\\\\\"'`]|\\\\.)*\g{-1}";
        $pattern = "/(\w+)\s*=\s*($val)/";
        preg_match_all($pattern, $args, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $arg[$match[1]] = substr($match[2], 1, -1); // drop quates from value string
            $args = str_replace($match[0], '', $args); // remove parsed substring
        }

        // get named numeric value argument, ex width=100
        // numeric value may not be quoted in argument string.
        $val = '\d+';
        $pattern = "/(\w+)\s*=\s*($val)/";
        preg_match_all($pattern, $args, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $arg[$match[1]] = (int)$match[2];
            $args = str_replace($match[0], '', $args); // remove parsed substring
        }

        // get width and/or height, specified as non-named arguments
        $pattern = '/(?:^| )(\d+(?:%|em|pt|px)?)\s*([,xX]?(\d+(?:%|em|pt|px)?))?(?: |$)/';
        if (preg_match($pattern, $args, $matches)) {
            if ($matches[3]) {
                // width and height were given
                $arg['width'] = $matches[1];
                $arg['height'] = $matches[3];
            } elseif ($matches[1]) {
                // width or height(=assumed as default) was given
                // preferred key name given as second parameter of this function
                if (!empty($defaultkey)) $arg[$defaultkey] = $matches[1];
            }
            $args = str_replace($matches[0], '', $args); // remove parsed substring
        }

        // get flags or non-named arguments, ex: showdate, no-showfooter
        $tokens = preg_split('/\s+/', $args);
        foreach ($tokens as $token) {
            if (preg_match('/^(?:!|not?)(.+)/',$token, $matches)) {
                // denyed/negative prefixed token
                $arg[$matches[1]] = false;
            } elseif (preg_match('/^[A-Za-z]/',$token)) {
                $arg[$token] = true;
            }
        }
        return $arg;
    }

    /* ---------------------------------------------------------
     * Exclude non-acceptable argument list
     * @param (array) $tokens          hashed argement
     * @param (array) $acceptablekeys  key array of acceptable key
     * @return (array) filtered argument hash
     * ---------------------------------------------------------
     */
    function cleanArguments($tokens, $acceptablekeys) {
        if (!is_array($acceptablekeys)) return $tokens;
        $args = array();
        foreach($tokens as $key => $value) {
            if (in_array($key, $acceptablekeys)) {
                $args[$key] = $value;
                // msg('key='.$key.' => '.$value.' added', 0);
            }
        }
        return $args;
    }

    /* ---------------------------------------------------------
     * build open tag with attributes
     * @param (string) $tag      open tag name
     * @param (array)  $attr     hashed attibutes for the tag
     * @return (string)          html of open tag
     * ---------------------------------------------------------
     */
    private function buildHtmlTag($tag, $attrs) {
        $html = '<'.$tag;
        foreach ($attrs as $key => $value) {
            $html .= ' '.$key.'="'.$value.'"';
        }
        $html .= '>';
        return $html;
    }

}
