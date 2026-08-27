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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Utility\Utility;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Http\HttpFactory;
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
    
        /**
     * Constructor
     *
     * @param   object  &$subject  The object to observe
     * @param   array   $config    An array that holds the plugin configuration
     *
     * @access  protected
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->httpclient = HttpFactory::getHttp();
    }

    /**
     * Récupère le contenu d'une balise spécifique dans une page HTML, avec gestion des erreurs et des attributs.
     *
     * @param string $url L'URL de la page à analyser.
     * @return DOMNode Le contenu de l'élément ou un message d'erreur.
     */
    public function get(string $url): string | \DOMDocument{
        $response = $this->httpclient->get($url);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
            return sprintf(
                    'Error code %s received requesting data from url :%s ',
                    $response->getStatusCode(),
                    $url
                );
        }
        return JOWebPreviewHelper::stringTODOM($response->getBody());
    }


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
        if (!isset($row) || ($row == null))
        {
            Log::add('row is null:', Log::WARNING, 'jowebpreview');
        }
        if (!isset($row) || !is_object($row) || !property_exists($row, 'text')){
            Log::add('row has no text:', Log::WARNING, 'jowebpreview');
            return false;
        }
        //Log::add('row :' . print_r($row, true), Log::WARNING, 'jowebpreview');
        $patterns = array("webpreview", "wikipedia", "joomla");
        if ( array_filter($patterns, function($key)use ($row) { return strpos( $row->text, sprintf('{%s', $key)) !== false; }) === false ) {
            return false;
        }
        foreach ($patterns as $pattern) {
            preg_match_all(sprintf(PF_REGEX_MEDIAWIKI_PATTERN, $pattern), $row->text, $matches);
            // Number of plugins
            $count = count($matches[0]);
             // plugin only processes if there are any instances of the plugin in the text
            if ($count) {
                $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
                $wa->getRegistry()->addRegistryFile('media/plg_content_jowebpreview/joomla.asset.json');
                $wa->useStyle("plugin.jowebpreview");
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
                        //Log::add('OnContentPrepare: ' . print_r($localparams, true) , Log::WARNING, 'jowebpreview');
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
    private function doWebPreview($type, $params )
    {
        $content = "";
        if (is_array( $params )== false)
        {
            return  "errorf:" . print_r($params, true);
        }
        $subject = $params['name'] ?? $params['subject']?? '';
        $url = $params['url'] ?? 'http://fr.wikipedia.org/wiki';
        $divclass  =  $params['divclass'] ?? "col-md-6 well border border-primary p-3";
        $class  =  $params['class'] ?? '';
        $tag = trim($params['tag']?? 'html');
        $child = (bool)$params['child']?? false;
        $no = (int)$params['no']?? 0;
        $search = $params['search'] ?? NULL;
        $mode = $params['mode'] ?? "full";
        $defdescription = $params['description'] ?? "";
        $defsite_name = $params['site_name'] ?? "";
        $defimage = $params['img'] ?? "/media/plg_content_jowebpreview/images/web_link.png";
        $truncate_max = $params['max'] ?? 500;
        $iframe_width = $params['width'] ?? "100%";
        $iframe_height = $params['height'] ?? 2148;
        if(!strcmp($type, "joomla")) {
            $uri = Uri::getInstance();
            $url = $url ."index.php?option=com_content&view=article&tmpl=component&id=" . $subject;
        } elseif ( !strcmp($type, "wikipedia")) {
            $url = 'http://fr.wikipedia.org/wiki';
            $uri = new Uri($url);
            if ($subject != '') {
                $url = $url . '/' . urlencode($subject);
            }
        } else {
            $uri = new Uri($url);
            if ($subject != '') {
                $url = $url . '/' . $subject;
                $url = str_replace(" ", "%20", $url);
            }
        }
        $rooturl = $uri->toString(['scheme', 'host', 'port', 'path']);
        $host_name =  $uri->toString(['host']);
        $icon = sprintf("http://www.google.com/s2/favicons?domain=%s", $host_name);
        if (($mode != "preview")&& ($mode != "iframe")){
            $dom = $this->get($url);
             //returns if errors
            if (!is_object($dom)){
                return $dom . "<br><a class=\"external\" href=\"". $url ."\">" . $url ."</a>";
            }
            $artcontent = $dom;
            if ($mode != "iframe"){
                $artcontent = JOWebPreviewHelper::getDomTag(
                                                $dom,
                                                $tag,
                                                $no,
                                                $search,
                                                $class,
                                                $child);
            }
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
                        $artcontent = JOWebPreviewHelper::getLimitedHtml($artcontent, $truncate_max);
                        $content = sprintf('<div class="%s"><h2>%s</h2> %s<p>' .
                                           '<a class="external" href="%s"><img src="%s" ></img><br>' .
                                           '<span style="color: var(--link-color)">' .
                                            '<img src="%s" ></img>&nbsp;%s</span></a></div>', 
                                            $divclass, $title, $artcontent, $url, $img, $icon, $site_name);
                        break;
                    case "preview":
                        foreach (array("site_name", "title", "description", "image") as $meta)
                        {
                            $$meta = $params["og:{$meta}"] ?? null;
                        }
                        if ($title == null && $description == null) {
                            $dom = $this->get($url);
                            if (!is_object($dom)) return $dom;
                            [$title ,$description, $image, $site_name] = JOWebPreviewHelper::getDomPreview($dom, $rooturl);
                        }
                        if ($image == "") {
                            $image = $defimage;
                        }
                        if ($description == "") {
                            $description = $defdescription;
                        }
                        if ($site_name == "") {
                            $site_name = $defsite_name;
                        }
                        $content = sprintf('<div class="%s"><a class="external" href="%s" style="color: currentcolor;">' .
                                            '<img src="%s" ></img>' .
                                            '<h2 style="border-bottom:none!important;">%s</h2>' .
                                            '%s<br>' .
                                            '<span style="color: var(--link-color)">' .
                                            '<img src="%s"></img>&nbsp;%s&nbsp;' .
                                            '</span></a></div>', 
                                             $divclass, $url, $image , $title, $description, $icon, $site_name);
                        break;
                    case "full":
                    case "fullreadmore":
                        $html = $dom->saveHTML($artcontent);
                        // Résoudre les chemins relatifs pour les attributs src et href
                        if ($rooturl !== '') {
                            $html = str_replace('src="', 'src="' . rtrim($rooturl, '/') . '/', $html);
                            $html = str_replace('href="', 'href="' . rtrim($rooturl, '/') . '/', $html);
                        }
                        if ($mode != "fullreadmore") {
                            $content = $html;
                        } else {
                            $content = sprintf('<div class="%s">%s</div>', $divclass, $html) .
                                       sprintf('<p class="readmore">
                                                  <a class="btn btn-secondary" href="%s">
                                                      <span class="icon-chevron-right" aria-hidden="true"></span>' . " " .
                                                      Text::_('COM_CONTENT_READ_MORE') . 
                                                  '</a>
                                                </p>', $url);
                        }
                        break;
                    case "iframe":
                    default:
                        $content = '<iframe src="'.$url.'" frameborder="0" scrolling="auto" width="'. $iframe_width .'" height="'. $iframe_height . '"></iframe>';
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
