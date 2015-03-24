<?php
error_reporting(-1);

use PicoFeed\Reader\Reader;
use PicoFeed\Config\Config;
use PicoFeed\PicoFeedException;

$pl = $plugins['RssFeedPlugin'];

if (!$GLOBALS['commandline']) {
  ob_end_flush();
} else {
  ob_end_clean();
  print ClineSignature();
  print s('Getting and Parsing the RSS sources') . "\n";
  ob_start();
}

function output($line) {
  if ($GLOBALS['commandline']) {
    ob_end_clean();
    print strip_tags($line) . "\n";
    ob_start();
  } else {
    print "$line<br/>\n";
  }
  flush();
}

$dao = new RssFeedPlugin_DAO(new CommonPlugin_DB);
$conf = new Config;
$conf->setContentFiltering(true);

foreach ($dao->feeds() as $row) {
    $feedId = $row['id'];
    $feedUrl = $row['url'];

    try {
        $reader = new Reader($conf);
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

        $feed = $parser->execute();

        output('<hr/>' . $GLOBALS['I18N']->get('Parsing') . ' ' . $feedUrl . '..');
        cl_output(s('Parsing') . ' ' . $feedUrl . '..');
        flushBrowser();
        $report = $GLOBALS['I18N']->get('Parsing') . " $feedUrl\n";
        $itemcount = 0;
        $newitemcount = 0;

        foreach ($feed->getItems() as $item) {
            $itemcount++;
            $date = $item->getDate();
            $date->setTimeZone(new DateTimeZone(date_default_timezone_get()));
            $published = $date->format('Y-m-d H:i:s');

            $itemId = $dao->addItem($item->getId(), $published, $feedId);

            if ($itemId > 0) {
                $newitemcount++;
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
        
        output(sprintf('<br/>%d %s, %d %s', $itemcount, s('items'), $newitemcount, s('new items')));
        $report .= sprintf('%d items, %d new items' . "\n", $itemcount, $newitemcount);
        cl_output(sprintf('%d items, %d new items' . "\n", $itemcount, $newitemcount));
    } catch (PicoFeedException $e) {
        echo $e->getMessage(), "\n";
    }
    flush();
    logEvent($report);
}

