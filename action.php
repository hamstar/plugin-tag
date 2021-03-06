<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

/**
 * Action part of the tag plugin, handles tag display, technorati ping and index updates
 */
class action_plugin_tag extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     *
     * @param Doku_Event_Handler $contr
     */
    function register(&$contr) {
        $contr->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'ping', array());
        $contr->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_act', array());
        $contr->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, '_handle_tpl_act', array());
        $contr->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, '_handle_keywords', array());
        if($this->getConf('toolbar_icon'))	$contr->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_toolbar_button', array ());
        $contr->register_hook('INDEXER_VERSION_GET', 'BEFORE', $this, '_indexer_version', array());
        $contr->register_hook('INDEXER_PAGE_ADD', 'BEFORE', $this, '_indexer_index_tags', array());
    }

    /**
     * Add a version string to the index so it is rebuilt
     * whenever the stored data format changes.
     */
    function _indexer_version($event, $param) {
        global $conf;
        $event->data['plugin_tag'] = '0.2.deaccent='.$conf['deaccent'];
    }

    /**
     * Add all data of the subject metadata to the metadata index.
     */
    function _indexer_index_tags($event, $param) {
        /* @var helper_plugin_tag $helper */
        if ($helper =& plugin_load('helper', 'tag')) {
            // make sure the tags are cleaned and no duplicate tags are added to the index
            $tags = p_get_metadata($event->data['page'], 'subject');
            if (!is_array($tags)) {
                $event->data['metadata']['subject'] = array();
            } else {
                $event->data['metadata']['subject'] = $helper->_cleanTagList($tags);
            }
        }
    }

    /**
     * Ping Technorati
     *
     * @author  Rui Carmo       <http://the.taoofmac.com/space/blog/2005-08-07>
     * @author  Esther Brunner  <wikidesign@gmail.com>
     */
    function ping(&$event, $param) {
        if (!$this->getConf('pingtechnorati')) return false; // config: don't ping
        if ($event->data[3]) return false;                   // old revision saved
        if (@file_exists($event->data[0][0])) return false;  // file not new
        if (!$event->data[0][1]) return false;               // file is empty

        // okay, then let's do it!
        global $conf;

        $request = '<?xml version="1.0"?><methodCall>'.
            '<methodName>weblogUpdates.ping</methodName>'.
            '<params>'.
            '<param><value>'.$conf['title'].'</value></param>'.
            '<param><value>'.DOKU_URL.'</value></param>'.
            '</params>'.
            '</methodCall>';
        $url = 'http://rpc.technorati.com:80/rpc/ping';
        $header[] = 'Host: rpc.technorati.com';
        $header[] = 'Content-type: text/xml';
        $header[] = 'Content-length: '.strlen($request);

        $http = new DokuHTTPClient();
        // $http->headers = $header;
        return $http->post($url, $request);
    }

    /**
     * catch tag action
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _handle_act(Doku_Event &$event, $param) {
        if($event->data != 'showtag') return;
        $event->preventDefault();
    }

    /**
     * Display the tag page
     *
     * @param Doku_Event $event The TPL_ACT_UNKNOWN event
     * @param array      $param optional parameters (unused)
     */
    function _handle_tpl_act(Doku_Event &$event, $param) {
        global $lang;

        if($event->data != 'showtag') return;
        $event->preventDefault();

        $tagns = $this->getConf('namespace');
        $flags = explode(',', str_replace(" ", "", $this->getConf('pagelist_flags')));

        $tag   = trim(str_replace($this->getConf('namespace').':', '', $_REQUEST['tag']));
        $ns    = trim($_REQUEST['ns']);

        /* @var helper_plugin_tag $helper */
        if ($helper =& plugin_load('helper', 'tag')) $pages = $helper->getTopic($ns, '', $tag);

        if(!empty($pages)) {

            // let Pagelist Plugin do the work for us
            if (plugin_isdisabled('pagelist') || (!$pagelist = plugin_load('helper', 'pagelist'))) {
                msg($this->getLang('missing_pagelistplugin'), -1);
                return;
            }

            /* @var helper_plugin_pagelist $pagelist */
            $pagelist->setFlags($flags);
            $pagelist->startList();
            foreach ($pages as $page) {
                $pagelist->addPage($page);
            }

            print '<h1>TAG: ' . str_replace('_', ' ', $_REQUEST['tag']) . '</h1>' . DOKU_LF;
            print '<div class="level1">' . DOKU_LF;
            print $pagelist->finishList();      
            print '</div>' . DOKU_LF;

        } else {
            print '<div class="level1"><p>' . $lang['nothingfound'] . '</p></div>';
        }
    }
    
	/**
	 * Inserts the tag toolbar button
	 */
	function insert_toolbar_button(Doku_Event &$event, $param) {
	    $event->data[] = array (
	        'type' => 'format',
	        'title' => $this->getLang('toolbar_icon'),
	        'icon' => '../../plugins/tag/images/tag-toolbar.png',
	        'open' => '{{tag>',
	    	'close' => '}}'
	    );
	}
	
	/**
	 * Prevent displaying underscores instead of blanks inside the page keywords
	 */
	function _handle_keywords(Doku_Event &$event) {
	    global $ID;

	    // Fetch tags for the page; stop proceeding when no tags specified
	    $tags = p_get_metadata($ID, 'subject', METADATA_DONT_RENDER);
	    if(is_null($tags)) true;

	    // Replace underscores with blanks
	    foreach($event->data['meta'] as &$meta) {
	        if($meta['name'] == 'keywords') {
	            $meta['content'] = str_replace('_', ' ', $meta['content']);
	        }
	    }	    
	}
}

// vim:ts=4:sw=4:et:
