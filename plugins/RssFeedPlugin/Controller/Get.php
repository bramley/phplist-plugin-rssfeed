<?php

/**
 * RssFeedPlugin for phplist.
 *
 * This file is a part of RssFeedPlugin.
 *
 * @category  phplist
 *
 * @author    Duncan Cameron
 * @copyright 2015-2018 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

namespace phpList\plugin\RssFeedPlugin\Controller;

use DateTimeZone;
use phpList\plugin\Common\Context;
use phpList\plugin\Common\Controller;
use phpList\plugin\Common\StringCallback;
use phpList\plugin\RssFeedPlugin\DAO;
use PicoFeed\Config\Config;
use PicoFeed\Logging\Logger as PicoFeedLogger;
use PicoFeed\Parser\Item;
use PicoFeed\Reader\Reader;

use function phpList\plugin\Common\getConfigLines;

/**
 * This class retrieves items from RSS feeds.
 */
class Get extends Controller
{
    private $context;
    private $dao;

    /**
     * Convert characters above the BMP to numeric entities to avoid a restriction of the MySQL utf8 character set to
     * only three bytes.
     *
     * @param string $content
     *
     * @return string encoded content
     */
    private function convertToEntities($content)
    {
        $convmap = array(0x10000, 0x10FFFF, 0, 0xFFFFFF);

        return mb_encode_numericentity($content, $convmap);
    }

    /**
     * Get the values of custom elements and attributes.
     *
     * @param array                $customElements
     * @param PicoFeed\Parser\Item $item
     *
     * @return array
     */
    private function getCustomElementsValues(array $customElements, Item $item)
    {
        $values = [];

        foreach ($customElements as $element) {
            $parts = explode('@', $element, 2);

            if (count($parts) == 2) {
                $tagValues = $item->getTag($parts[0], $parts[1]);
            } else {
                $tagValues = $item->getTag($element);
            }

            if ($tagValues) {
                $values[$element] = $this->convertToEntities($tagValues[0]);
            }
        }

        return $values;
    }

    /**
     * Get the content for an item.
     * Use the content element if configured to do so.
     * For an RSS feed use the description element if present.
     * For an ATOM feed use the summary element if present.
     *
     * @param PicoFeed\Parser\Item $item
     *
     * @return string the content for the item
     */
    private function getItemContent(Item $item)
    {
        $useSummary = getConfig('rss_content_use_summary');

        if (!$useSummary) {
            return $item->getContent();
        }
        $content = '';
        $values = $item->getTag('description');

        if ($values === false || count($values) == 0) {
            $item->setNamespaces($item->getNamespaces() + ['atom' => 'http://www.w3.org/2005/Atom']);
            $values = $item->getTag('atom:summary');

            if ($values === false || count($values) == 0) {
                $content = $item->getContent();
            } else {
                $content = $values[0];
            }
        } else {
            $content = $values[0];
        }

        return $content;
    }

    private function getRssFeeds(DAO $dao, callable $output)
    {
        $this->logger->debug(new StringCallback(function () {
            PicoFeedLogger::enable();

            return 'PicoFeed logging enabled';
        }));
        $utcTimeZone = new DateTimeZone('UTC');
        $config = new Config();
        $config->setMaxBodySize((int) getConfig('rss_max_body_size'));
        $config->setContentFiltering((bool) getConfig('rss_content_filtering'));
        $config->setContentGenerating((bool) getConfig('rss_content_generating'));
        $feeds = $dao->activeFeeds();

        if (count($feeds) == 0) {
            $output(s('There are no active RSS feeds to fetch'));

            return;
        }
        $customElements = getConfigLines('rss_custom_elements');

        foreach ($feeds as $row) {
            $feedId = $row['id'];
            $feedUrl = $row['url'];

            try {
                $output(s('Fetching') . ' ' . $feedUrl);
                $reader = new Reader($config);
                $resource = $reader->download($feedUrl, $row['lastmodified'], $row['etag']);

                if (!$resource->isModified()) {
                    $output(s('Not modified'));
                    continue;
                }

                $parser = $reader->getParser(
                    $resource->getUrl(),
                    $resource->getContent(),
                    $resource->getEncoding()
                );
                $feed = $parser->execute();
                $itemCount = 0;
                $newItemCount = 0;

                foreach ($feed->getItems() as $item) {
                    try {
                        ++$itemCount;
                        $date = $item->getDate();
                        $date->setTimeZone($utcTimeZone);
                        $published = $date->format('Y-m-d H:i:s');

                        $itemId = $dao->addItem($item->getId(), $published, $feedId);

                        if ($itemId > 0) {
                            ++$newItemCount;
                            $itemContent = $this->getItemContent($item);
                            $itemContent = $this->convertToEntities($itemContent);
                            $dao->addItemData(
                                $itemId,
                                array(
                                    'title' => $item->getTitle(),
                                    'url' => $item->getUrl(),
                                    'language' => $item->getLanguage(),
                                    'author' => $item->getAuthor(),
                                    'enclosureurl' => $item->getEnclosureUrl(),
                                    'enclosuretype' => $item->getEnclosureType(),
                                    'content' => $itemContent,
                                    'rtl' => $item->isRTL(),
                                ) + $this->getCustomElementsValues($customElements, $item)
                            );
                        }
                    } catch (\Throwable $e) {
                        $this->logger->debug($e->getMessage());
                        $message = sprintf('Unable to add item %s for feed %s', $item->getTitle(), $feedUrl);
                        logEvent($message);
                        $output($message);
                    }
                }
                $etag = $resource->getEtag();
                $lastModified = $resource->getLastModified();
                $dao->updateFeed($feedId, $etag, $lastModified);

                $line = s('%d items, %d new items', $itemCount, $newItemCount);
                $output($line);

                if ($newItemCount > 0) {
                    logEvent(s('Feed') . " $feedUrl $line");
                }
            } catch (\Throwable $e) {
                $output($e->getMessage());
            }
            $this->logger->debug(PicoFeedLogger::toString());
        }
    }

    protected function actionDefault()
    {
        $this->context->start();
        $this->getRssFeeds($this->dao, [$this->context, 'output']);
        $this->context->finish();
    }

    public function __construct(DAO $dao, Context $context)
    {
        parent::__construct();
        $this->dao = $dao;
        $this->context = $context;
    }
}
