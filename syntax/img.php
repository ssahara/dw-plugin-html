<?php
/**
 * DokuWiki Plugin html img
 * Google Drawing の 埋め込みHTML をそのまま使えるようにする。
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * SYNTAX: <img src=... >
 *
 * SYNTAX: {{img> url}}
 *         {{img width> url|title}}
 *         {{img width,height> url|title}}
 *         {{img width,height> id?nolink|title}}
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';

class syntax_plugin_html_img extends DokuWiki_Syntax_Plugin {

    protected $spec_keys, $spec_default;
    function __construct() {

        // attibutes acceptable for <img> tag
        $this->spec_keys = array(
            'id', 'class', 'style', 'width', 'height',
            'src', 'alt', 'title', 
            'usemap', 'ismap',
            //deplicated in html5
            'align', 'border', 'hspace', 'vspace', 'longdesc',
            );

        $this->spec_default = array(
            //'width'  => 400,
            //'height' => 300,
        );
    }

    public function getType()  { return 'substition'; }
    public function getPType() { return 'normal'; }
    public function getSort()  { return 305; }
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{img\b.*?\>.*?}}',$mode,
            implode('_', array('plugin',$this->getPluginName(),$this->getPluginComponent(),))
        );
        $this->Lexer->addSpecialPattern('<img\b.*?>',$mode,
            implode('_', array('plugin',$this->getPluginName(),$this->getPluginComponent(),))
        );
    }

    /**
     * handle syntax
     */
    public function handle($match, $state, $pos, &$handler){
        global $ACT;
        $util =& plugin_load('helper', $this->getPluginName());

        $params = substr(trim($match, '{}<>'), strlen('img'));
        list($params, $url) = explode('>', $params, 2);
        list($url, $title)  = explode('|', $url, 2);

        // handle parameters
        $opts = array_merge($this->spec_default, $util->getArguments($params,'width'));
        $opts = $util->cleanArguments($opts, $this->spec_keys);
        if (!empty($url))   $opts['src'] = trim($url);
        if (!empty($title)) $opts['title'] = trim($title);

        // Check css vulnerability, not allow JavaScript insertion
        if (array_key_exists('style', $opts)) {
            if ((stristr($opts['style'], 'url') !== false) ||
                (stristr($opts['style'], 'import') !== false) ||
                (stristr($opts['style'], 'javascript:') !== false)) {
                unset($opts['style']);
            }
        }

        // Check alignment (wiki-style)
        if (!array_key_exists('align', $opts)) {
            $ralign = (bool)preg_match('/^ /',$url);
            $lalign = (bool)preg_match('/ $/',$url);
            if ( $lalign && $ralign ) { $opts['align'] = 'center';
            } else if ( $ralign ) {     $opts['align'] = 'right';
            } else if ( $lalign ) {     $opts['align'] = 'left';
            } else { $align = null; }
        }

        // Check src and title attribute
        if (array_key_exists('src', $opts)) {
            if ($ACT='preview') { //debug
                msg($this->getPluginName().': img src="'.$opts['src'].'"' ,0);
            }
            if (preg_match('/^https?:\/\//', $opts['src'])) {
                $type = 'externalmedia';
                if (empty($opts['title'])) {
                    $opts['title'] = is_null($title) ? $opts['src'] : trim($title);
                }
            } else {
                $type = 'internalmedia';
                $opts['title'] = is_null($title) ? '' : trim($title);
            }
        }
        return array($state, $opts);
    }

    /**
     * Render output
     */
    public function render($format, &$renderer, $indata) {

        if (empty($indata)) return false;
        list($state, $data) = $indata;
        if ($format != 'xhtml') return false;

        $data['src'] = $this->_resolveSrcUrl($data['src']);
        $renderer->doc .= $this->_buildHtmlTag($data);
        return true;
    }


    /**
     * resolve src attribute of img tag
     */
    private function _resolveSrcUrl($linkId) {
        global $ID;
        if (preg_match('/^https?:\/\//', $linkId)) {
            // Google Drawing?
            return $linkId;
        } else { // assume resource as linkid, which may include section
            resolve_pageid(getNS($ID), $linkId, $exists);
            list($ext, $mime) = mimetype($linkId);
            if (substr($mime, 0, 5) == 'image') { // mediaID
                $url = ml($linkId);
                return $url;
            } else { //pageID?
                msg($this->getPluginName().': not image src="'.$linkId.'"' , -1);
                return false;
            }
        }
    }

    /**
     * build img tag
     */
    private function _buildHtmlTag($attrs) {
        $html = '<img';
        foreach ($attrs as $key => $value) {
            $html .= ' '.$key.'="'.$value.'"';
        }
        $html .= '>';
        return $html;
    }

}
