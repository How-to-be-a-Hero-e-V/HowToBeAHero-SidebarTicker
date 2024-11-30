<?php

namespace SidebarTicker;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;

class Hooks
{
    /**
     * Prüft, ob eine Seite existiert.
     *
     * @param string $titleText Titel der Seite als Text
     * @param \Title $title Instanz des Titles
     * @return bool Gibt true zurück, wenn die Seite existiert, andernfalls false
     */
    public static function pageExists(string $titleText, \Title $title)
    {
        if ($title) {
            if ($title->getNamespace() === NS_SPECIAL) {
                return \SpecialPageFactory::exists($title->getDBkey());
            } elseif ($title->isExternal()) {
                return false;
            } else {
                // LinkCache über MediaWikiServices beziehen
                $linkCache = MediaWikiServices::getInstance()->getLinkCache();
                $prefixedDBKey = $title->getPrefixedDBkey();

                if ($linkCache->getGoodLinkID($prefixedDBKey)) {
                    return true;
                } elseif ($linkCache->isBadLink($prefixedDBKey)) {
                    return false;
                }

                return $title->exists();
            }
        }
        return false;
    }

    /**
     * Hook für den Aufbau der Sidebar.
     *
     * @param \Skin $skin Skin-Objekt
     * @param array &$sidebar Referenz zur Sidebar, die angepasst wird
     * @return bool
     */
    public static function onSkinBuildSidebar($skin, &$sidebar)
    {
        // Aktuelle Sprache des Benutzers ermitteln
        $langCode = RequestContext::getMain()->getLanguage()->getCode();
        $tickerTitle = "SidebarTicker/" . $langCode;
        $title = \Title::newFromText($tickerTitle);

        if (!$title || !self::pageExists($tickerTitle, $title)) {
            return true;
        }

        // API-Request vorbereiten
        $apiRequest = new \DerivativeRequest(
            RequestContext::getMain()->getRequest(),
            [
                'action' => 'parse',
                'page' => $tickerTitle
            ]
        );

        $api = new \ApiMain($apiRequest, true);
        $api->execute();
        $result = $api->getResult();

        if (!isset($result->getResultData()["parse"]["text"])) {
            return true; // Abbruch, wenn keine Daten zurückgegeben werden
        }

        $parsedContent = $result->getResultData()["parse"]["text"];

        // CSS für den Ticker
        $css = <<<CSS
<style type="text/css">
.marqueeContainer {
    position: relative;
    width: 100%;
    overflow: hidden;
}
.marqueeContainer .content {
    position: relative;
    margin: 0;
    transform: translateX(100%);
    animation: marquee 15s linear infinite;
}
@keyframes marquee {
    0% {
        transform: translateX(100%);
    }
    100% {
        transform: translateX(-100%);
    }
}
</style>
CSS;

        // HTML-Wrapper für den Ticker
        $htmlTemplate = <<<HTML
<div class="marqueeContainer">
    <div class="content">%s</div>
</div>
HTML;

        // Füge den Ticker zur Sidebar hinzu
        $sidebar['ticker'] = $css . sprintf($htmlTemplate, $parsedContent);
        return true;
    }
}
