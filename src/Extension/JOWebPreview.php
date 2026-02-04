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

//require_once(dirname(__FILE__) . '/../../lib/simplehtmldom/simple_html_dom.php');

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Utility\Utility;
use Joomla\Event\SubscriberInterface;
use Joomla\Uri\UriInterface;
use Joomla\Utilities\ArrayHelper;
use JLTRY\Plugin\Content\JOWebPreview\Helper\JOWebPreviewHelper;

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
                            JOWebPreviewHelper::parseAttributes($inline_params, $localparams);
                        }
                        else {
                            $localparams = Utility::parseAttributes($inline_params);
                        }
                        if (!strcmp($pattern, "joomla")) {
                            $uri = Uri::root();
                            $localparams['url'] = $uri;
                            $localparams['tag'] = "div.item-page";
                        }
                        $p_content = $this->doWebPreview($pattern, $localparams);
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
    private function doWebPreview($type, $_params )
    {
        $content = "";
        if (is_array( $_params )== false)
        {
            return  "errorf:" . print_r($_params, true);
        }
        $subject = $_params['name'] ?? $_params['subject']?? '';
        $url = $_params['url'] ?? 'http://fr.wikipedia.org/wiki';
        $divclass  =  $_params['divclass'] ?? "col-md-6 well border border-primary p-3";
        $class  =  $_params['class'] ?? '';
        $tag = trim($_params['tag']?? 'p');
        $child = (bool)$_params['child']?? false;
        $no = (int)$_params['no']?? 0;
        $search = $_params['search'] ?? NULL;
        $mode = $_params['mode'] ?? "full";
        $defdescription = $_params['description'] ?? "";
        $defimage = $_params['img'] ?? "/media/plg_content_jowebpreview/images/web_link.png";
        $max = $_params['max'] ?? 500;
        if(!strcmp($type, "joomla")) {
            $uri = Uri::getInstance();
            $rooturl = $uri->toString(['scheme', 'host', 'port', 'path']);
            $url = $url ."index.php?option=com_content&view=article&tmpl=component&id=" . $subject;
        } elseif ( !strcmp($type, "wikipedia")) {
            $url = 'http://fr.wikipedia.org/wiki';
            if ($subject != '') {
                $url = $url . '/' . urlencode($subject);
            }
        } else {
            $uri = new Uri($url);
            $rooturl = $uri->toString(['scheme', 'host', 'port']);
            if ($subject != '') {
                $url = $url . '/' . urlencode($subject);
            }
        }

        $dom = JOWebPreviewHelper::loadHTML($url);
         //returns if errors
        if (!is_object($dom)) return $dom;
        if ($mode != "preview") {
            $artcontent = JOWebPreviewHelper::getDomTag(
                                            $dom,
                                            $tag,
                                            $no,
                                            $search,
                                            $class,
                                            $child);
            //returns if errors
            if (!is_object($artcontent)) return $artcontent;
        }
        switch ($type) {
            case 'webpreview':
                switch($mode) {
                    case "truncate":
                        [$title ,$description, $img, $site_name] = JOWebPreviewHelper::getDomPreview($dom, $rooturl);
                        if ($img == "") {
                            $img = $defimage;
                        }
                        $artcontent = JOWebPreviewHelper::getLimitedHtml($artcontent, $max);
                        $content = sprintf('<div class="%s"><h2>%s</h2> %s<p><a href="%s"><img src="%s" ></img>', 
                                            $divclass, $title, $artcontent, $url, $img) .
                                    " " . 
                                    Text::_('COM_CONTENT_READ_MORE') .
                                    $text .
                                    '</p></a></div>';
                        break;
                    case "preview":
                        [$title ,$description, $img, $site_name] = JOWebPreviewHelper::getDomPreview($dom, $rooturl);
                        if ($img == "") {
                            $img = $defimage;
                        }
                        if ($description == "") {
                            $description = $defdescription;
                        }
                        $content = sprintf('<div class="%s"><a href="%s" style="color: currentcolor;">' .
                                            '<img src="%s" ></img>' .
                                            '<h2 style="border-bottom:none!important;">%s</h2>' .
                                            '<br>%s<br>%s', 
                                             $divclass, $url, $img , $title, $description, $site_name) . 
                                    '<br><p style="color: var(--link-color)">' .
                                    Text::_('COM_CONTENT_READ_MORE') .
                                    '</p></a></div>';
                        break;
                    case "full":
                        $html = $dom->saveHTML($artcontent);
                        // Résoudre les chemins relatifs pour les attributs src et href
                        if ($rooturl !== '') {
                            $html = str_replace('src="/', 'src="' . rtrim($rooturl, '/') . '/', $html);
                            $html = str_replace('href="/', 'href="' . rtrim($rooturl, '/') . '/', $html);
                        }
                        $content = $html;
                        break;
                    
                }
                break;
            case 'joomla':
                $content = $dom->saveHTML($artcontent);
                break;
            case 'wikipedia':
                $artcontent = str_replace("href=\"/wiki","href=\"". $url , $dom->saveHTML($artcontent));
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
