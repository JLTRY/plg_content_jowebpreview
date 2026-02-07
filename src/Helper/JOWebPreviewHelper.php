<?php
/**
 * 
* @copyright Copyright (C) 2012 Jean-Luc TRYOEN. All rights reserved.
* @license GNU/GPL
*
* Version 1.0
*
*/
namespace JLTRY\Plugin\Content\JOWebPreview\Helper;

//require_once(dirname(__FILE__) . '/../../lib/simplehtmldom/simple_html_dom.php');

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


/**
 * Helper for plg_content_jowebpreview
 *
 * @since  1.0.0
 */
class JOWebPreviewHelper
{
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
    * Retourne le contenu d’un tag limité à $maxChars caractères,
    * en conservant la structure HTML valide.
    *
    * @param string $dom      dom à analyser
    * @param int    $maxChars  Nombre maximal de caractères visibles (texte uniquement)
    * @return string            Fragment HTML tronqué mais bien formé
    */
    public static function getLimitedHtml(\DOMElement $node, int $maxChars): string
    {

        $output = '';
        $length = 0;

        $copyNode = function (\DOMNode $src) use (&$copyNode, &$output, &$length, $maxChars) {
            if ($length >= $maxChars) {
                return;
            }

            if ($src instanceof \DOMText) {
                $remaining = $maxChars - $length;
                $text = $src->nodeValue;
                if (mb_strlen($text) > $remaining) {
                    $text = mb_substr($text, 0, $remaining);
                }
                $output .= htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $length += mb_strlen($text);
            } elseif ($src instanceof \DOMElement) {
                $output .= '<' . $src->tagName;
                foreach ($src->attributes as $attr) {
                    $output .= ' ' . $attr->name . '="' .
                               htmlspecialchars($attr->value, ENT_QUOTES, 'UTF-8') . '"';
                }
                $output .= '>';

                foreach ($src->childNodes as $child) {
                    $copyNode($child);
                    if ($length >= $maxChars) {
                        break;
                    }
                }

                $output .= '</' . $src->tagName . '>';
            }
        };

        $copyNode($node);
        return $output;
    }

    /**
     * Récupère un aperçu (titre, description, image) d'une page HTML à partir d'un objet DOMDocument.
     *
     * @param DOMDocument $dom L'objet DOMDocument chargé avec le HTML de la page.
     * @param string $url L'URL de la page (pour résoudre les chemins relatifs des images).
     * @return array Tableau contenant le titre, la description l'URL de l'image et le nom du site.
     */
    public static function getDomPreview(\DOMDocument $dom, string $url): array
    {
        $title = "";
        $description = "";
        $img = "";
        $site_name = "";

        // Récupérer toutes les balises meta
        $metas = $dom->getElementsByTagName('meta');

        foreach ($metas as $meta) {
            $name = $meta->getAttribute('name');
            $property = $meta->getAttribute('property');

            // Récupérer la description
            if ($description == "" && (($name == "description") || ($property === "og:description"))) {
                $description = $meta->getAttribute('content');
            }

            // Récupérer le titre
            if ($title == "" && (($name == "title") || (strpos($property, "title") !== false))) {
                $title = $meta->getAttribute('content');
            }

            // Récupérer l'image
            if ($img == "" && (($name == "image") || (strpos($property, "image") !== false))) {
                $img = $meta->getAttribute('content');
            }
            
            // Récupérer le site "og:site_name"
            if ($site_name == "" && (($name == "site_name") || (strpos($property, "site_name") !== false))) {
                $site_name = $meta->getAttribute('content');
            }
        }

        // Si aucune image n'est trouvée dans les métadonnées, chercher dans les balises img
        if (empty($img)) {
            $images = $dom->getElementsByTagName('img');
            foreach ($images as $image) {
                $src = $image->getAttribute('src');
                if (filter_var($src, FILTER_VALIDATE_URL)) {
                    $img = $src;
                    if (strpos($img, "http") === false) {
                        // Résoudre les chemins relatifs
                        $parsedUrl = parse_url($url);
                        $urlRoot = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                        $img = $urlRoot . '/' . ltrim($src, '/');
                        break;
                    }
                }
            }
        }

        // Retourner les résultats
        return [$title, $description, $img, $site_name];
    }



    /**
     * Récupère le contenu d'une balise spécifique dans une page HTML, avec gestion des erreurs et des attributs.
     *
     * @param string $url L'URL de la page à analyser.
     * @return DOMNode Le contenu de l'élément ou un message d'erreur.
     */
    public static function loadHTML(string $url): string|\DOMDocument {
        // Créer un contexte pour gérer les options de stream (si nécessaire)
        $context = stream_context_create([
            'http' => [
                'user_agent' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36",
                'follow_location' => true,
            ],
        ]);

        // Charger le contenu HTML
        $htmlContent = @file_get_contents($url, false, $context);
        if ($htmlContent === false) {
            return "Error: Unable to load URL: $url";
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Désactiver les erreurs de parsing
        $dom->loadHTML($htmlContent);
        libxml_clear_errors();
        return $dom;
    }

    /**
     * Récupère le tag du document HTML
     *
     * @param \DOMDocument $dom Le document DOM à analyser.
     * @param string $tag le tag a rechercher.
     * @return DOMNode Le contenu de l'élément ou un message d'erreur.
     */
    public static function getDomTag(\DOMDocument $dom, string $tag, $no, $search, $class, $child): string|\DOMElement
    {
        // Sélectionner les éléments par leur nom de balise
        $xpath = new \DOMXPath($dom);
        if (strpos($tag, ".") === false)
        {
            $query = sprintf('//%s', $tag);
        } else {
            $ar = preg_split('/\./', $tag);
            $query = sprintf("//%s[contains(concat(' ', normalize-space(@class), ' '), ' %s ')]", $ar[0], $ar[1]);
        }
        $elements = $xpath->query($query);
        if (!is_object($elements)  || $elements->length == 0) {
             return array(null, "Error: No '$tag' elements found in $url");
        }
        // Parcourir les éléments pour trouver celui qui contient $search
        $artcontent = null;
        $currentNo = $no;
        while ($currentNo < min($elements->length, 100)) {
            $artcontent = $elements->item($currentNo);
            if ($search === null || strpos($artcontent->nodeValue, $search) !== false) {
                break;
            }
            $currentNo++;
        }

        if (!$artcontent) {
            return array(null, "Error retrieving $tag no:$currentNo in $url\n", null) ;
        }

        // Ajouter une classe si demandée
        if ($class && $artcontent instanceof DOMElement) {
            $artcontent->setAttribute('class', $class);
        }

        // Retourner uniquement le texte si $child est vrai
        if ($child) {
            return array($dom, $artcontent->nodeValue);
        }

        // Retourner le contenu complet de l'élément
        return $artcontent;
    }
};