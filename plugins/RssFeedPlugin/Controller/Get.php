<?php
/**
 * RssFeedPlugin for phplist.
 *
 * This file is a part of RssFeedPlugin.
 *
 * @category  phplist
 *
 * @author    Duncan Cameron
 * @copyright 2015 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

/**
 * This class retrieves RSS items.
 */
use PicoFeed\Config\Config;
use PicoFeed\Parser\Item;
use PicoFeed\PicoFeedException;
use PicoFeed\Reader\Reader;

class RssFeedPlugin_Controller_Get extends CommonPlugin_Controller
{
    /**
     * Get the content for custom elements.
     *
     * @param array                $customElements
     * @param PicoFeed\Parser\Item $item
     *
     * @return array
     */
    private function getCustomElementsValues(array $customElements, Item $item)
    {
        $values = array();

        foreach ($customElements as $c) {
            $parts = explode(':', $c, 2);

            if (count($parts) == 1) {
                $values[$c] = $item->getTag($c);
            } else {
                if ($item->hasNamespace($parts[0])) {
                    $tagValues = $item->getTag($c);

                    if (count($tagValues) > 0) {
                        $values[$c] = $tagValues[0];
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Get the content for an item.
     * For an RSS feed use the description element if present.
     * For an ATOM feed use the summary element if present.
     *
     * @param PicoFeed\Parser\Item $item
     *
     * @return string the content for the item
     */
    private function getItemContent(Item $item)
    {
        $content = '';
        $values = $item->getTag('description');

        if ($values === false || count($values) == 0) {
            $values = $item->getTag('summary');

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

    private function getRssFeeds(Closure $output)
    {
        $utcTimeZone = new DateTimeZone('UTC');
        $config = new Config();
        $config->setContentFiltering(true);
        $dao = new RssFeedPlugin_DAO(new CommonPlugin_DB());
        $feeds = $dao->activeFeeds();

        if (count($feeds) == 0) {
            $output('There are no active RSS feeds to fetch');

            return;
        }
        $customElementsConfig = getConfig('rss_custom_elements');
        $customElements = $customElementsConfig === ''
            ? array()
            : explode("\n", $customElementsConfig);

        foreach ($feeds as $row) {
            $feedId = $row['id'];
            $feedUrl = $row['url'];

            try {
                $output(s('Fetching') . ' ' . $feedUrl);
                $reader = new Reader($config);
                $resource = $reader->download($feedUrl, $row['lastmodified'], $row['etag']);

                if (!$resource->isModified()) {
                    $output('Not modified');
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
                    ++$itemCount;
                    $date = $item->getDate();
                    $date->setTimeZone($utcTimeZone);
                    $published = $date->format('Y-m-d H:i:s');

                    $itemId = $dao->addItem($item->getId(), $published, $feedId);

                    if ($itemId > 0) {
                        ++$newItemCount;
                        $itemContent = $this->getItemContent($item);
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
                }
                $etag = $resource->getEtag();
                $lastModified = $resource->getLastModified();
                $dao->updateFeed($feedId, $etag, $lastModified);

                $line = s('%d items, %d new items', $itemCount, $newItemCount);
                $output($line);

                if ($newItemCount > 0) {
                    logEvent("Feed $feedUrl $line");
                }
            } catch (PicoFeedException $e) {
                $output($e->getMessage());
            }
        }
    }

    protected function actionDefault()
    {
        global $commandline;

        if ($commandline) {
            $output = function ($line) {
                echo $line, "\n";
            };
            ob_end_clean();
            echo ClineSignature();
        } else {
            $output = function ($line) {
                echo "$line<br/>\n";
                @ob_flush();
                flush();
            };
            ob_end_flush();
        }
        $this->getRssFeeds($output);
        ob_start();
    }
}
