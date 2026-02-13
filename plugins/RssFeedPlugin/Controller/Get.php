<?php

/**
 * RssFeedPlugin for phplist.
 *
 * This file is a part of RssFeedPlugin.
 *
 * @category  phplist
 *
 * @author    Duncan Cameron
 * @copyright 2015-2026 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

namespace phpList\plugin\RssFeedPlugin\Controller;

use DateTimeZone;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Reader as LaminasFeedReader;
use phpList\plugin\Common\Context;
use phpList\plugin\Common\Controller;
use phpList\plugin\RssFeedPlugin\DAO;
use phpList\plugin\RssFeedPlugin\FeedReader;

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
     * @param array          $customElements
     * @param EntryInterface $entry
     *
     * @return array
     */
    private function getCustomElementsValues(array $customElements, EntryInterface $entry)
    {
        $values = [];

        foreach ($customElements as $element) {
            $xpathQ = sprintf('string(%s/%s)', $entry->getXpathPrefix(), preg_replace('!/@|@!', '/@', $element));

            try {
                $value = $this->convertToEntities($entry->getXpath()->evaluate($xpathQ));
            } catch (\ErrorException $e) {
                $this->logger->debug($e->getMessage());
                $value = '';
            }
            $values[$element] = $value;
        }

        return $values;
    }

    private function getRssFeeds(DAO $dao, callable $output)
    {
        $utcTimeZone = new DateTimeZone('UTC');
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
                $response = FeedReader::get($feedUrl, $row['etag'], $row['lastmodified']);

                if ($response->getStatus() == 304) {
                    $output(s('Not modified'));
                    continue;
                }
                $feed = LaminasFeedReader::importString($response->getBody());

                if (($dom = $feed->getDomDocument())->encoding === null) {
                    $dom->encoding = 'UTF-8';
                }
                $itemCount = 0;
                $newItemCount = 0;

                foreach ($feed as $item) {
                    try {
                        ++$itemCount;
                        $date = $item->getDateCreated();
                        $date->setTimeZone($utcTimeZone);
                        $published = $date->format('Y-m-d H:i:s');

                        $itemId = $dao->addItem($item->getId(), $published, $feedId);

                        if ($itemId > 0) {
                            $authorName = is_array($author = $item->getAuthor())
                                ? $author['name']
                                : '';
                            $dao->addItemData(
                                $itemId,
                                [
                                    'title' => $item->getTitle(),
                                    'url' => $item->getLink(),
                                    'language' => $feed->getLanguage(),
                                    'author' => $authorName,
                                    'description' => $this->convertToEntities($item->getDescription()),
                                    'content' => $this->convertToEntities($item->getContent()),
                                ] + $this->getCustomElementsValues($customElements, $item)
                            );
                            ++$newItemCount;
                        }
                    } catch (\Throwable $e) {
                        $this->logger->debug($e->getMessage());
                        $message = sprintf('Unable to add item %s for feed %s', $item->getTitle(), $feedUrl);
                        logEvent($message);
                        $output($message);
                    }
                }
                $etag = $response->getHeader('etag');
                $lastModified = $response->getHeader('last-modified');
                $dao->updateFeed($feedId, $etag, $lastModified);

                $line = s('%d items, %d new items', $itemCount, $newItemCount);
                $output($line);

                if ($newItemCount > 0) {
                    logEvent(s('Feed') . " $feedUrl $line");
                }
            } catch (\Throwable $e) {
                $output($e->getMessage());
            }
        }
    }

    protected function actionDefault()
    {
        $this->context->start();
        $this->getRssFeeds($this->dao, $this->context->output(...));
        $this->context->finish();
    }

    public function __construct(DAO $dao, Context $context)
    {
        parent::__construct();
        $this->dao = $dao;
        $this->context = $context;
    }
}
