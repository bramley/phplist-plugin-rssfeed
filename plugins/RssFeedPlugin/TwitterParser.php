<?php

namespace phpList\plugin\RssFeedPlugin;

use DateTime;
use DOMDocument;
use DOMElement;
use DOMXpath;
use PicoFeed\Syndication\AtomFeedBuilder;
use PicoFeed\Syndication\AtomItemBuilder;
use PicoFeed\Syndication\Rss20FeedBuilder;
use PicoFeed\Syndication\Rss20ItemBuilder;
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
        list($src, $width) = explode(' ', $srcs[count($srcs) - 1], 2);
        preg_match('/\d+/', $width, $w);
        $image->setAttribute('src', $src);
        $image->setAttribute('width', $w[0]);
        $image->removeAttribute('height');

        $this->removeAttributes($image->parentNode);

        return $e->ownerDocument->saveXML($image->parentNode);
    }

    private function getContent(DOMElement $e)
    {
        $nl = $this->xpath->query("p[@class='timeline-Tweet-text']", $e);
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
            $feedBuilder = AtomFeedBuilder::create();
            $result->contentType = 'application/atom+xml; charset=utf-8';
        } else {
            $feedBuilder = Rss20FeedBuilder::create();
            $result->contentType = 'application/rss+xml; charset=utf-8';
        }
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $o->body);
        $this->xpath = new DOMXpath($dom);

        $feedBuilder
            ->withTitle($this->xpath->evaluate("string(//h1[@class='summary']/a/@title)"))
            ->withSiteUrl($this->xpath->evaluate("string(//h1[@class='summary']/a/@href)"))
            ->withFeedUrl(
                (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                . htmlspecialchars_decode("://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")
            )
            ->withDate(new DateTime());

        $previousTweetTime = new DateTime();

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $since = new DateTime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        } else {
            $since = null;
        }
        $itemCount = 0;

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

            $itemBuilder = ($format == 'atom')
                ? AtomItemBuilder::create($feedBuilder)
                : Rss20ItemBuilder::create($feedBuilder);

            $feedBuilder->withItem(
                $itemBuilder
                    ->withId($id)
                    ->withTitle(($isRetweet ? 'RT ' : '') . $nickname . ' - ' . $textContent)
                    ->withUpdatedDate($tweetTime)
                    ->withPublishedDate($tweetTime)
                    ->withUrl($url)
                    ->withContent($avatar . $content . $image)
            );
            ++$itemCount;
        }

        if ($itemCount === 0) {
            $result->code = 304;

            return $result;
        }
        $result->content = $feedBuilder->build();
        $result->code = '';

        return $result;
    }
}
