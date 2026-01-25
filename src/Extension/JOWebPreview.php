<?php
/**
 * 
* @copyright Copyright (C) 2012 Jean-Luc TRYOEN. All rights reserved.
* @license GNU/GPL
*
* Version 1.0
*
*/
namespace JLTRY\Plugin\Content\JOWebPreview\Extension;

require_once(dirname(__FILE__) . '/../../lib/simplehtmldom/simple_html_dom.php');

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Utility\Utility;
use Joomla\Event\SubscriberInterface;
use Joomla\Uri\UriInterface;
use Joomla\Utilities\ArrayHelper;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

define('PF_REGEX_MEDIAWIKI_PATTERN', "#{%s ([^}]*?)}#s");
/**
* JOWebPreview Content Plugin
*
*/
class JOWebPreview extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
                'onContentPrepare' => 'onContentPrepare'
                ];
    }

    public static function parseAttributes($string, &$retarray)
    {
        $pairs = explode(';', trim($string));
        foreach ($pairs as $pair) {
            if ($pair == "") {
                continue;
            }
            $pos = strpos($pair, "=");
            $key = substr($pair, 0, $pos);
            $value = substr($pair, $pos + 1);
            $retarray[$key] = $value;
        }
    }

    /**
    * Example prepare content method in Joomla 1.6/1.7/2.5
    *
     * @param  ContentPrepareEvent The context for content prepare
    */
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        //Escape fast
        if (!$this->params->get('enabled', 1)) {
            return;
        }
        if (!$this->getApplication()->isClient('site')) {
            return;
        }
        // use this format to get the arguments for both Joomla 4 and Joomla 5
        // In Joomla 4 a generic Event is passed
        // In Joomla 5 a concrete ContentPrepareEvent is passed
        [$context, $row, $params, $page] = array_values($event->getArguments());
        if ( strpos($context, 'com_content') === false ) return true;
        $patterns = array("webpreview", "wikipedia", "joomla");
        if ( !isset($row) )
        {
            return true;
        }
        if ( array_filter($patterns, function($key) { return strpos( $row->text, sprintf('{%s', $key)) !== false; }) === false ) {
            return true;
        }
        foreach ($patterns as $pattern) {
            preg_match_all(sprintf(PF_REGEX_MEDIAWIKI_PATTERN, $pattern), $row->text, $matches);
            // Number of plugins
            $count = count($matches[0]);
             // plugin only processes if there are any instances of the plugin in the text
            if ($count) {
                $_result = array();
                for ($i = 0; $i < $count; $i++)
                {
                    if (@$matches[1][$i]) {
                        $inline_params = $matches[1][$i];
                        if ( strpos( $inline_params, "\"") === false ) {
                            $localparams = array();
                            self::parseAttributes($inline_params, $localparams);
                        }
                        else {
                            $localparams = Utility::parseAttributes($inline_params);
                        }
                        if (!strcmp($pattern, "joomla")) {
                            $uri = Uri::root();
                            $localparams['url'] = $uri;
                            $localparams['tag'] = "div.item-page";
                        }
                        $p_content = $this->webpreview($pattern, $localparams);
                        $row->text = str_replace($matches[0][$i], $p_content, $row->text);
                    }
                }
            }
        }
        return true; 
    }



     /**
    * Function to insert Web PReview (introduction)
    *
    * Method is called by the onContentPrepare
    *
    * @param type : joomla wikipedia or webpreview
    * @param _params : parameters
    */       
    function webpreview($type, $_params )
    {
        $content = "";
        if (is_array( $_params )== false)
        {
            return  "errorf:" . print_r($_params, true);
        }
        if (array_key_exists('name', $_params))
        {
            $subject = $_params['name'];
        }
        elseif (array_key_exists('subject', $_params))
        {
            $subject = $_params['subject'];
        }
        else {
            $subject = '';
        }
        if (!array_key_exists('url', $_params))
        {
            $url = 'http://fr.wikipedia.org/wiki';
        } else {
            $url = rtrim($_params['url']);
        }
        if (array_key_exists('divclass', $_params)) {
            $divclass  =  $_params['divclass'];
        }
        else {
            $divclass  = "col-md-4 well border border-primary";
        }
        if (array_key_exists('class', $_params)) {
            $class  =  $_params['class'];
        } else {
            $class = null;
        }
        if(!strcmp($type, "joomla")) {
            $rooturl = $url;
            $url = $url ."index.php?option=com_content&view=article&tmpl=component&id=" . $subject;
        } else {
            $rooturl = $url ;
            if ($subject != '') {
                $url = $url . '/' . $subject;
            }
            $url = str_replace(" ", "%20", $url);
        }
        if (array_key_exists('tag', $_params)) {
            $tag = trim($_params['tag']);
        } else {
            $tag = 'p';
        }
        if (array_key_exists('child', $_params)) {
            $child = (bool)$_params['child'];
        } else {
            $child = false;
        }
        if (array_key_exists('no', $_params)) {
            $no = (int)$_params['no'];
        } else {
            $no = 0;
        }
        if (array_key_exists('search', $_params)) {
            $search = $_params['search'];
        } else {
            $search = NULL;
        }
        if (array_key_exists('img', $_params)) {
            $simage = $_params['img'];
        } else {
            $simage = "/images/web_link.png";
        }
        if (array_key_exists('full', $_params)) {
            $full = (bool)$_params['full'];
        } else {
            $full = true;
        }
        if (array_key_exists('text', $_params)) {
            $text = $_params['text'];
        } else {
            $text = "";
        }
        $context = stream_context_create(array(
            "http" => array(
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
            )
        ));
        $dom = file_get_html($url, false, $context);
        if (!$dom) {
            $dom = str_get_html(file_get_contents($url));
        }
        if (is_object($dom)) {
            $artcontent = $dom->find($tag, $no);
            if ($search != NULL) {
                while ((strpos($artcontent, $search) == false )&&($no != 100)) {
                    $artcontent = $dom->find($tag, $no++);
                }
            }
            if (!$artcontent) {
                $artcontent = "Error retrieving " . $tag . "no:" . $no . "in " . $url ;
                $artcontent .= $dom->innertext;
                return $artcontent;
            }
            if ($full && $class && $artcontent && is_object($artcontent)) {
                $artcontent->setAttribute('class', $class);
            }
            if ($artcontent && $child) {
                $artcontent = $artcontent->text();  
            }
            $artcontent = str_replace("src=\"/", "src=\"". $rooturl . '/' , $artcontent);
            $artcontent = str_replace("href=\"/", "href=\"" . $rooturl . '/', $artcontent);
        }
        else {
            $artcontent = "Error retrieving " . $url .":" . $errno . ":error" . $dom;
        }
        switch ($type) {
            case 'mediawiki':
                if ($full == false) {
                    $content = sprintf('<div class="%s">%s<p><a href="%s"><img src="%s" ></img>', 
                                            $divclass, $artcontent, $url, $simage)  .
                                            " " . 
                                            (is_object($dom)?Text::_('COM_CONTENT_READ_MORE'):$url) .
                                            $text .
                                            '</p></a></div>';
                } else {
                    $content = $artcontent ;
                }
                break;
            case 'joomla':
                $content = $artcontent;
                break;
            case 'wikipedia':
                $artcontent = str_replace("href=\"/wiki","href=\"". $url , $artcontent);
                $content = sprintf('<div class="%s">%s<p><a href="%s">' .
                            '<img src="/images/wikipedia.png" width="40"></img>' .
                            " " . Text::_('COM_CONTENT_READ_MORE') . 
                            $article . 
                            ' ... </a></p></div>', $divclass, $artcontent, $url);
                break;
        }
        return $content;
    }
}
