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
define('PF_REGEX_MEDIAWIKI_PATTERN', "#{mediawiki (.*?)}#s");
require_once(dirname(__FILE__) . '/../wikipedia/simple_html_dom.php');


/**
* WikipediaArticle Content Plugin
*
*/
class plgContentMediaWiki extends JPlugin
{

	/**
	* Constructor
	*
	* @param object $subject The object to observe
	* @param object $params The object that holds the plugin parameters
	*/
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}

	/**
	* Example prepare content method in Joomla 1.5
	*
	* Method is called by the view
	*
	* @param object The article object. Note $article->text is also available
	* @param object The article params
	* @param int The 'page' number
	*/
	function onPrepareContent( &$article, &$params, $limitstart )
	{
		return $this->OnPrepareRow($article);
	}

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

 		if ( strpos( $row->text, '{mediawiki' ) === false ) {
            return true;
		}
		preg_match_all(PF_REGEX_MEDIAWIKI_PATTERN, $row->text, $matches);
		// Number of plugins
		$count = count($matches[0]);
		 // plugin only processes if there are any instances of the plugin in the text
		if ($count) {
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
					$p_content = $this->mediawikiarticle($_result);								
					$row->text = str_replace("{mediawiki " . $matches[1][$i] . "}", $p_content, $row->text);
				}
			}
		}
		return true; 
	}
    
	
	protected function get_file_contents($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);		
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0");
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch,CURLOPT_CONNECTTIMEOUT,120);
		curl_setopt ($ch,CURLOPT_TIMEOUT,120);
		curl_setopt ($ch,CURLOPT_MAXREDIRS,10);		
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
	function mediawikiarticle( $_params )
	{
		$content = "";
		if (is_array( $_params )== false)
		{
			return  "errorf:" . print_r($_params, true);
		}
		if (! array_key_exists('url', $_params))
		{
			return  "errorf: url unknown" . print_r($_params, true);
		}
		if (! array_key_exists('subject', $_params))
		{
			return  "errorf: subject unknown" . print_r($_params, true);
		}
		$url = $_params['url'];
		//$url = 'https://fr.wikipedia.org/wiki/Seconds_Out'; 
		$subject = $_params['subject'];//'http://www.jltryoen.fr/wiki/Half_ChtriMan_2010';
		$html = $this->get_file_contents($url . '/' . $subject); //file_get_html($url); //
		if (array_key_exists('tag', $_params)) {
			$tag = $_params['tag'];
		} else {
			$tag = '#mw-content-text';
		}
		if (array_key_exists('no', $_params)) {
			$no = (int)$_params['no'];
		} else {
			$no = 0;
		}
		if (array_key_exists('img', $_params)) {
			$img = (bool)$_params['img'];
		} else {
			$img = true;
		}
		
		// Get the first paragraph
		$dom = str_get_html($html);//
		$content = str_replace("src=\"/","src=\"". $url, $dom->find($tag, $no));
		if ($img) {
			$content .= sprintf('<a class="readmore-link" href="%s/%s"><img src="%s/favicon.ico"></img>', $url, $subject, $url )  .
					" " . JText::_('COM_CONTENT_READ_MORE') . '</a>';
		} else {
			$content .= sprintf('<a href="%s/%s"></a>',$url, $subject) ;
		}
		return $content;
	}
}
