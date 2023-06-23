<?php
/**
 * 
* @copyright Copyright (C) 2012 Jean-Luc TRYOEN. All rights reserved.
* @license GNU/GPL
*
* Version 1.0
*
*/

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.plugin.plugin' );
define('PF_REGEX_MEDIAWIKI_PATTERN', "#{%s (.*?)}#s");
require_once(dirname(__FILE__) . '/simple_html_dom.php');
use Joomla\CMS\Uri\Uri;

/**
* WikipediaArticle Content Plugin
*
*/
class PlgContentMediaWiki extends JPlugin
{


 	/**
	* Example prepare content method in Joomla 1.6/1.7/2.5
	*
	* Method is called by the view
	*
	* @param object The article object. Note $article->text is also available
	* @param object The article params
	*/   
	function onContentPrepare($context, &$row, &$params, $page = 0)
	{
		return $this->OnPrepareRow($row);
	}
	
	function onPrepareRow(&$row) 
	{  
		//Escape fast
        if (!$this->params->get('enabled', 1)) {
            return true;
        }

 		if ( (strpos( $row->text, '{wikipedia' ) === false ) && 
             (strpos( $row->text, '{mediawiki' ) === false ) && 
             (strpos( $row->text, '{joomla' ) === false )){
            return true;
		}
		$patterns = array("mediawiki", "wikipedia", "joomla");
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
						$pairs = explode(';', trim($inline_params));
						foreach ($pairs as $pair) {
							$pos = strpos($pair, "=");
							$key = substr($pair, 0, $pos);
							$value = substr($pair, $pos + 1);
							$_result[$key] = $value;
						}
                        if (!strcmp($pattern, "joomla")) {
                            $uri = Uri::root();
                            $_result['url'] = $uri;
                            $_result['tag'] = "div.item-page";
                        }
						$p_content = $this->mediawikiarticle($pattern, $_result);
						$row->text = str_replace(sprintf("{%s " . $matches[1][$i] . "}", $pattern), $p_content, $row->text);
					}
				}
			}
		}
		return true; 
	}
    
	
	protected function file_get_contents($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);		
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,120);
		curl_setopt($ch, CURLOPT_TIMEOUT,120);
		curl_setopt($ch, CURLOPT_MAXREDIRS,10);		
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
    
    
 	/**
	* Function to insert MediaWiki introduction
	*
	* Method is called by the onContentPrepare or onPrepareContent
	*
	* @param string The text string to find and replace
	*/       
	function mediawikiarticle($type, $_params )
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
		}		
        if(!strcmp($type, "joomla")) {
            $rooturl = $url;
            $url = $url ."index.php?option=com_content&view=article&tmpl=component&id=" . $subject;            
        } else {
            $rooturl = $url . '/';
            $url = $url . '/' . $subject;
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
		
		
		// Get the first paragraph
		//$html = file_get_contents($url);
        //if ( ! $html) {
            if (($type == 'mediawiki') || ($type == 'joomla')) {
                $dom = str_get_html($this->file_get_contents($url));
            }else {
                $dom = file_get_html($url);
            }    
        if (!$dom) {
           $dom = str_get_html( file_get_contents($url));
        }
        if ($dom) {            
            $artcontent = $dom->find($tag, $no);
            if ($search != NULL) {                
                while ((strpos($artcontent, $search) == false )&&($no != 100)) {
                    $artcontent = $dom->find($tag, $no++);
                }
            }
            if ($full && $class && $artcontent) {
                $artcontent->setAttribute('class', $class);
            }
            if ($artcontent && $child) {            
                $artcontent = $artcontent->text();  
            }    
            $artcontent = str_replace("src=\"/", "src=\"". $rooturl, $artcontent);
            $artcontent = str_replace("href=\"/", "href=\"" . $rooturl .'/', $artcontent);            
		}
        else {
            $artcontent = "Error retrieving " . $url;
        }
		switch ($type) {
			case 'mediawiki':			
				if ($full == false) {                                
					$content = sprintf('<div class="%s">%s<p><a href="%s"><img src="%s" ></img>', 
											$divclass, $artcontent, $url, $simage)  .
											" " . 
											JText::_('COM_CONTENT_READ_MORE') .
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
							" " . JText::_('COM_CONTENT_READ_MORE') . 
							$article . 
							' ... </a></p></div>', $divclass, $artcontent, $url);
				break;
		}
		return $content;
	}
}
