<?php

namespace phpList\plugin\RssFeedPlugin;

use DateTime;
use DOMDocument;
use DOMElement;
use DOMXpath;
use PicoFeed\Syndication\Atom;
use PicoFeed\Syndication\Rss20;
use stdClass;

class TwitterParser
{
    private $xpath;

    private function removeAttributes(DOMElement $element)
    {
        $atts = $this->xpath->query(
            './/@target|.//@rel|.//@data-expanded-url|.//@data-query-source|.//@data-pre-embedded|.//@data-scribe
            |.//@data-src-1x|.//@data-src-2x|.//@data-srcset|.//@data-width|.//@data-height|.//@data-crop-x|.//@data-crop-y
            |.//@data-image-index|.//@class|.//@title|.//@dir|.//@lang',
            $element
        );

        foreach ($atts as $a) {
            $a->parentNode->removeAttributeNode($a);
        }
    }

    private function removeSpan(DOMElement $element)
    {
        $spanElements = $this->xpath->query('.//a/span', $element);

        foreach ($spanElements as $e) {
            $textContent = $e->textContent;
            $textNode = $element->ownerDocument->createTextNode($textContent);
            $e->parentNode->replaceChild($textNode, $e);
        }
    }

    private function fixHttpLinks(DOMElement $element)
    {
        $aElements = $this->xpath->query('.//a', $element);

        foreach ($aElements as $e) {
            $textContent = $e->textContent;

            if (preg_match('|https?://(.*)|i', $textContent, $matches)) {
                $e->nodeValue = $matches[1];
            }
        }
    }

    private function getAvatar(DOMElement $e)
    {
        $nl = $this->xpath->query(".//img[@class='Avatar']", $e);

        if ($nl->length === 0) {
            return '';
        }
        $image = $nl->item(0);
        $image->setAttribute('src', $image->getAttribute('data-src-1x'));
        $this->removeAttributes($image);

        return $e->ownerDocument->saveXML($image);
    }

    private function getInlineImage(DOMElement $e)
    {
        $nl = $this->xpath->query(".//img[@class='NaturalImage-image']", $e);

        if ($nl->length === 0) {
            return '';
        }
        $image = $nl->item(0);
        $srcset = urldecode($image->getAttribute('data-srcset'));
        $srcs = explode(',', $srcset);
        list($src, $width) = explode(' ',  $srcs[count($srcs) - 1], 2);
        preg_match('/\d+/', $width, $w);
        $image->setAttribute('src', $src);
        $image->setAttribute('width', $w[0]);
        $image->removeAttribute('height');

        $this->removeAttributes($image->parentNode);

        return $e->ownerDocument->saveXML($image->parentNode);
    }

    private function getContent(DOMElement $e)
    {
        $nl = $this->xpath->query("div[@class='timeline-Tweet-text']", $e);
        $div = $nl->item(0);
        $this->removeAttributes($div);
        $this->removeSpan($div);
        $this->fixHttpLinks($div);

        return array($div->textContent, $e->ownerDocument->saveXML($div));
    }

    /**
     * Fetches the twitter timeline then creates a feed containing the new items.
     *
     * @param string $widgetId the 18 digit twitter widget id
     * @param string $format   the format of the feed, atom or rss
     *
     * @return stdClass instance of result object - code and content
     */
    public function createFeed($widgetId, $format)
    {
        $result = new stdClass();
        $f = file_get_contents("https://cdn.syndication.twimg.com/widgets/timelines/$widgetId");

        if ($f === false) {
            $result->code = 404;

            return $result;
        }
        $o = json_decode($f);

        if ($o === null || !isset($o->body)) {
            $result->code = 404;

            return $result;
        }

        if ($format == 'atom') {
            $writer = new Atom();
            $result->contentType = 'application/atom+xml; charset=utf-8';
        } else {
            $writer = new Rss20();
            $result->contentType = 'application/rss+xml; charset=utf-8';
        }
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $o->body);
        $this->xpath = new DOMXpath($dom);

        $writer->title = $this->xpath->evaluate("string(//h1[@class='summary']/a/@title)");
        $writer->site_url = $this->xpath->evaluate("string(//h1[@class='summary']/a/@href)");
        $writer->feed_url =
            (isset($_SERVER['HTTPS']) ? 'https' : 'http')
            . htmlspecialchars_decode("://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

        $previousTweetTime = new DateTime();

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $since = new DateTime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        } else {
            $since = null;
        }

        foreach ($this->xpath->query('.//div[@data-tweet-id]') as $e) {
            $id = $this->xpath->evaluate('string(@data-tweet-id)', $e);
            $url = $this->xpath->evaluate("string(.//div[@class='timeline-Tweet-metadata']/a/@href)", $e);
            $name = $this->xpath->evaluate("string(.//span[contains(@class,'TweetAuthor-name')])", $e);
            $nickname = $this->xpath->evaluate("string(.//span[contains(@class,'TweetAuthor-screenName')])", $e);
            $datetime = $this->xpath->evaluate("string(.//time[@class='dt-updated']/@datetime)", $e);

            if ($this->xpath->evaluate("contains(@class,'timeline-Tweet--isRetweet')", $e)) {
                $isRetweet = true;
                $tweetTime = $previousTweetTime;
            } else {
                $isRetweet = false;
                $tweetTime = new DateTime($this->xpath->evaluate("string(.//time[@class='dt-updated']/@datetime)", $e));
                $previousTweetTime = $tweetTime;
            }

            if ($since && $tweetTime < $since) {
                break;
            }

            $avatar = $this->getAvatar($e);
            $image = $this->getInlineImage($e);
            list($textContent, $content) = $this->getContent($e);

            if (strlen($textContent) > 40) {
                $textContent = substr($textContent, 0, strrpos(substr($textContent, 0, 40), ' ')) . ' …';
            }

            $writer->items[] = array(
                'id' => $id,
                'title' => ($isRetweet ? 'RT ' : '') . $nickname . ' - ' . $textContent,
                'updated' => $tweetTime->format('U'),
                'url' => $url,
                'content' => $avatar . $content . $image,
            );
        }

        if (count($writer->items) === 0) {
            $result->code = 304;

            return $result;
        }
        $result->content = $writer->execute();
        $result->code = '';

        return $result;
    }
}
