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

    protected $pluginMode, $spec_keys, $spec_default;
    public function __construct() {
        $this->pluginMode = implode('_', array('plugin',
            $this->getPluginName(),$this->getPluginComponent(),));

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
        $this->Lexer->addSpecialPattern($this->special_pattern, $mode, $this->pluginMode);
        $this->Lexer->addEntryPattern($this->entry_pattern, $mode, $this->pluginMode);
    }
    function postConnect() {
        $this->Lexer->addExitPattern($this->exit_pattern, $this->pluginMode);
    }


    /**
     * handle syntax
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler){
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
    public function render($format, Doku_Renderer &$renderer, $indata) {
        $util =& plugin_load('helper', $this->getPluginName());

        if (empty($indata)) return false;
        list($state, $data) = $indata;
        if ($format != 'xhtml') return false;

        switch ($state) {
            case DOKU_LEXER_SPECIAL:
            case DOKU_LEXER_ENTER:
                $linkId = $data['src'];
                $this->_checkAttributes($data);
                if ($data['src'] == false) {
                    $message = $this->getPluginName().'_'.$this->getPluginComponent().
                        ': page not exists ('.$linkId.')';
                    $renderer->doc .= $util->msg($message, -1);
                    return false;
                    break;
                } else {
                    $renderer->doc .= $util->buildHtmlTag('iframe', $data);
                }
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
     * verify attribute of iframe tags
     */
    private function _checkAttributes(&$tokens) {

        // check css vulnerability, not allow JavaScript insertion
        if (array_key_exists('style', $tokens)) {
            if ((stristr($tokens['style'], 'url') !== false) ||
                (stristr($tokens['style'], 'import') !== false) ||
                (stristr($tokens['style'], 'javascript:') !== false)) {
                unset($tokens['style']);
            }
        }
        // resolve src attribute
        if (array_key_exists('src', $tokens)) {
            $tokens['src'] = $this->_resolveSrcUrl($tokens['src']);
        }
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
            } elseif ($exists) { //pageID
                list($id, $section) = explode('#', $linkId, 2);
                $url = wl($id);
                $url.= ((strpos($url,'?')!==false) ? '&':'?').'do=export_xhtml';
                $url.= $section ? '#'.$section : '';
            } else {
                //msg('page not exists ('.$linkId.')',-1);
                $url = false;
            }
            return $url;
        }
    }

/*
    function _buildHtmlTag($tag, $attrs) {
        $html = '<'.$tag;
        foreach ($attrs as $key => $value) {
            $html .= ' '.$key.'="'.$value.'"';
        }
        $html .= '>';
        return $html;
    }
*/

}
