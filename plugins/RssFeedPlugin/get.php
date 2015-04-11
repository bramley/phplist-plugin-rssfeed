<?php
error_reporting(-1);

use PicoFeed\Reader\Reader;
use PicoFeed\Config\Config;
use PicoFeed\PicoFeedException;

if ($commandline) {
    ob_end_clean();
    echo ClineSignature();
} else {
    ob_end_flush();
}
getRssFeeds();

function output($line)
{
    global $commandline;

    if ($commandline) {
        echo $line, "\n";
    } else {
        echo "$line<br/>\n";
        ob_flush();
        flush();
    }
}

function getRssFeeds()
{
    $utcTimeZone = new DateTimeZone('UTC');
    $config = new Config;
    $config->setContentFiltering(true);
    $dao = new RssFeedPlugin_DAO(new CommonPlugin_DB);
    $feeds = $dao->activeFeeds();

    if (count($feeds) == 0) {
        output('There are no active RSS feeds to fetch');
        return;
    }

    foreach ($feeds as $row) {
        $feedId = $row['id'];
        $feedUrl = $row['url'];

        try {
            $reader = new Reader($config);
            $resource = $reader->download($feedUrl, $row['lastmodified'], $row['etag']);

            if (!$resource->isModified()) {
                output("Feed '$feedUrl' not modified");
                continue;   
            }

            $parser = $reader->getParser(
                $resource->getUrl(),
                $resource->getContent(),
                $resource->getEncoding()
            );
            $line = s('Parsing') . ' ' . $feedUrl;
            output($line);

            $feed = $parser->execute();
            $itemCount = 0;
            $newItemCount = 0;

            foreach ($feed->getItems() as $item) {
                $itemCount++;
                $date = $item->getDate();
                $date->setTimeZone($utcTimeZone);
                $published = $date->format('Y-m-d H:i:s');

                $itemId = $dao->addItem($item->getId(), $published, $feedId);

                if ($itemId > 0) {
                    $newItemCount++;
                    $dao->addItemData(
                        $itemId,
                        array(
                            'title' => $item->getTitle(),
                            'url' => $item->getUrl(),
                            'language' => $item->getLanguage(),
                            'author' => $item->getAuthor(),
                            'enclosureurl' => $item->getEnclosureUrl(),
                            'enclosuretype' => $item->getEnclosureType(),
                            'content' => $item->getContent(),
                            'rtl' => $item->isRTL()
                        )
                    );
                }
            }
            $etag = $resource->getEtag();
            $lastModified = $resource->getLastModified();
            $dao->updateFeed($feedId, $etag, $lastModified);
            
            $line = s('%d items, %d new items', $itemCount, $newItemCount);
            output($line);

            if ($newItemCount > 0) {
                logEvent("Feed $feedUrl $line");
            }
        } catch (PicoFeedException $e) {
            output($e->getMessage());
        }
    }
}
