<?php
/**
 * DokuWiki Plugin html iframe
 * Google Drive や OneDrive 上の公開ドキュメントの埋め込みHTML をそのまま使えるようにする。
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * SYNTAX: <iframe src=... > ... </iframe>
 *
 * SYNTAX: {{iframe> id}} or {{iframe> url}}
 *         {{iframe width,height> id|title}}
 *         {{iframe width,height> url|title}}
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';

class syntax_plugin_html_iframe extends DokuWiki_Syntax_Plugin {

    protected $entry_pattern   = '<iframe\b.*?>(?=.*?</iframe>)';
    protected $exit_pattern    = '</iframe>';
    protected $special_pattern = '{{iframe\b.*?\>.*?}}';

    protected $spec_keys, $spec_default;
    function __construct() {

        // attibutes acceptable for <iframe> tag
        $this->spec_keys = array(
            'id', 'class', 'style', 'src', 'width', 'height', 'name',
            'seamless', 'srcdoc', 'sandbox',
            //deplicated in html5
            'longdesc', 'scrolling', 'frameborder',
            'marginwidth', 'marginheight', 'align',
            // wiki 
            'border', 'title',
            );

        $this->spec_default = array(
            'width'  => 400,
            'height' => 300,
            'frameborder' => 0,
        );
    }

    public function getType()  { return 'substition'; }
    public function getPType() { return 'normal'; }
    public function getSort()  { return 305; }
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern($this->special_pattern,$mode,
            implode('_', array('plugin',$this->getPluginName(),$this->getPluginComponent(),))
        );
        $this->Lexer->addEntryPattern($this->entry_pattern,$mode,
            implode('_', array('plugin',$this->getPluginName(),$this->getPluginComponent(),))
        );
    }
    function postConnect() {
        $this->Lexer->addExitPattern($this->exit_pattern,
            implode('_', array('plugin',$this->getPluginName(),$this->getPluginComponent(),))
        );
    }


    /**
     * handle syntax
     */
    public function handle($match, $state, $pos, &$handler){
        global $ACT;
        $util =& plugin_load('helper', $this->getPluginName());

        switch ($state) {
            case DOKU_LEXER_SPECIAL:
            case DOKU_LEXER_ENTER:
                $params = substr(trim($match, '{}<>'), strlen('iframe'));
                list($params, $resource) = explode('>', $params, 2);
                list($resource, $title)  = explode('|', $resource, 2);

                // handle parameters
                $opts = array_merge($this->spec_default, $util->getArguments($params,''));
                $opts = $util->cleanArguments($opts, $this->spec_keys);
                if (!empty($resource)) $opts['src'] = trim($resource);
                if (!empty($title))    $opts['title'] = trim($title);

                // check css vulnerability, not allow JavaScript insertion
                if (array_key_exists('style', $opts)) {
                    if ((stristr($opts['style'], 'url') !== false) ||
                        (stristr($opts['style'], 'import') !== false) ||
                        (stristr($opts['style'], 'javascript:') !== false)) {
                        unset($opts['style']);
                    }
                }
                if (array_key_exists('src', $opts)) {
                    if (preg_match('/^https?:\/\//', $opts['src'])) {
                        if ($ACT='preview') {
                            msg($this->getPluginName().': iframe src="'.$opts['src'].'"' ,0);
                        }
                    } else {
                        $opts['src'] = $this->_resolveSrcUrl($opts['src']);
                    }
                }
                return array($state, $opts);
            case DOKU_LEXER_UNMATCHED:
                return array($state, '');
            case DOKU_LEXER_EXIT:
                return array($state, '');
        }
    }

    /**
     * Render output
     */
    public function render($format, &$renderer, $indata) {

        if (empty($indata)) return false;
        list($state, $data) = $indata;
        if ($format != 'xhtml') return false;

        switch ($state) {
            case DOKU_LEXER_SPECIAL:
            case DOKU_LEXER_ENTER:
                $renderer->doc .= $this->_buildHtmlTag('iframe', $data);
                if ($state == DOKU_LEXER_SPECIAL) {
                    $renderer->doc .= '</iframe>'.NL;
                }
                break;
            case DOKU_LEXER_UNMATCHED:
                break;
            case DOKU_LEXER_EXIT:
                $renderer->doc .= NL.'</iframe>'.NL;
                break;
        }
        return true;
    }


    /**
     * resolve src attribute of iframe tags
     */
    private function _resolveSrcUrl($url) {
        global $ID;
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        } else { // assume resource as linkid, which may include section
            $linkId = $url;
            resolve_pageid(getNS($ID), $linkId, $exists);
            list($ext, $mime) = mimetype($linkId);
            if (substr($mime, 0, 5) == 'image') { // mediaID
                $url = ml($linkId);
            } else { //pageID
                if (!$exists) msg('page not exists ('.$linkId.')',-1);
                list($id, $section) = explode('#', $linkId, 2);
                $url = wl($id);
                $url.= ((strpos($url,'?')!==false) ? '&':'?').'do=export_xhtml';
                $url.= $section ? '#'.$section : '';
            }
            return $url;
        }
    }

    /**
     * build iframe tag with attributes
     */
    private function _buildHtmlTag($tag, $attrs) {
        $html = '<'.$tag;
        foreach ($attrs as $key => $value) {
            $html .= ' '.$key.'="'.$value.'"';
        }
        $html .= '>';
        return $html;
    }

}
