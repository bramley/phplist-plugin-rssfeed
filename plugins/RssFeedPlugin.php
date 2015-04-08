<?php
/**
 * RssFeedPlugin for phplist
 * 
 * This file is a part of RssFeedPlugin.
 *
 * @category  phplist
 * @package   RssFeedPlugin
 * @author    Duncan Cameron
 * @copyright 2015 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

/**
 * Registers plugin with phplist
 * Provides hooks into message processing
 * 
 */

class RssFeedPlugin extends phplistPlugin
{
    const VERSION_FILE = 'version.txt';

    private $dao;
    private $rssHtml;
    private $rssText;

    public $name = 'RSS Feed Manager';
    public $authors = 'Duncan Cameron';

    public $commandlinePluginPages = array (
      'get'
    );

    public $topMenuLinks = array(
      'get' => array('category' => 'system'),
      'view' => array('category' => 'campaigns'),
      'delete' => array('category' => 'system'),
    );

    public $pageTitles = array(
      "get" => "Fetch RSS items",
      "view" => "View RSS items",
      "delete" => "Delete outdated RSS items",
    );

    public $DBstruct = array (
        'feed' => array (
            'id' => array ('integer not null primary key auto_increment', 'ID'),
            'url' => array ('varchar(65535) not null', ''),
            'etag' => array ('varchar(100) not null', ''),
            'lastmodified' => array ('varchar(100) not null', ''),
        ),
        'item' => array (
            'id' => array ('integer not null primary key auto_increment', 'ID'),
            'uid' => array ('varchar(100) not null', 'unique id'),
            'feedid' => array ('integer not null', 'fk to feed'),
            'published' => array ('datetime not null', 'published datetime'),
            'added' => array ('datetime not null', 'datetime added'),
            'index_1' => array('feedpublishedindex (feedid, published)', ''),
            'index_2' => array('feeduidindex (feedid, uid)', ''),
        ),
        'item_data' => array (
            'itemid' => array ('integer not null', 'fk to item'),
            'property' => array ('varchar(100) not null', ''),
            'value'  => array ('text', ''),
            'primary key'   => array ('(itemid, property)', ''),
        ),
    );

    public $settings = array(
      "rss_minimum" => array (
        'value' => 1,
        'description' => 'Minimum number of items to send in an RSS email',
        'type' => "integer",
        'allowempty' => 0,
        'min' => 1,
        'max' => 50,
        'category'=> 'RSS',
      ),
      "rss_maximum" => array (
        'value' => 30,
        'description' => 'Maximum number of items to send in an RSS email',
        'type' => "integer",
        'allowempty' => 0,
        'min' => 1,
        'max' => 50,
        'category'=> 'RSS',
      ),
      "rss_htmltemplate" => array (
        'value' => '
        <a href="[URL]"><b>[TITLE]</b></a><br/>
        [PUBLISHED]</br>
        [CONTENT]
        <hr/>',
        'description' => 'Item HTML template',
        'type' => "textarea",
        'allowempty' => 0,
        'category'=> 'RSS',
      ),
    );

    private function validateFeed($feedUrl)
    {
        $reader = new PicoFeed\Reader\Reader();
        $resource = $reader->download($feedUrl);
        $parser = $reader->getParser(
            $resource->getUrl(),
            $resource->getContent(),
            $resource->getEncoding()
        );
        $feed = $parser->execute();
    }

    private function replaceProperties($template, $properties)
    {
        foreach ($properties as $key => $value) {
            $template = str_ireplace("[$key]", $value, $template);
        }
        return $template;
    }

    private function sampleItems()
    {
        return array(
            array(
                'published' => '2015-03-28 10:41:41',
                'title' => 'Hamilton on pole after Rosberg block',
                'content' => 
'<p>Lewis Hamilton takes pole position for the Malaysian GP as Ferrari&#039;s Sebastian Vettel split the Mercedes in a rain-hit qualifying.</p>',
                'url' => 'http://www.bbc.co.uk/sport/0/formula1/32099506',
            ),
            array(
                'published' => '2015-03-28 12:16:20',
                'title' => "Labour 'would have to talk' to SNP",
                'content' => 
'<p>The deputy leader of the SNP Stewart Hosie believes a minority Labour government would have to negotiate with the nationalists ahead of its first Queen&#039;s speech.</p>',
                'url' => 'http://www.bbc.co.uk/news/uk-scotland-scotland-politics-32099155#sa-ns_mchannel=rss&ns_source=PublicRSS20-sa',
            )
        );
    }

    private function generateItemHtml(array $items)
    {
        $htmltemplate = getConfig('rss_htmltemplate');
        $html = '';

        foreach ($items as $item) {
            $d = new DateTime($item['published']);
            $html .= $this->replaceProperties(
                $htmltemplate,
                array(
                    'published' => $d->format('d/m/Y H:i'),
                    'title' => htmlspecialchars($item['title'])
                ) + $item
            );
        }
        return $html;
    }

    public function __construct()
    {
        $this->coderoot = dirname(__FILE__) . '/' . __CLASS__ . '/';
        $this->version = (is_file($f = $this->coderoot . self::VERSION_FILE))
            ? file_get_contents($f)
            : '';

        parent::__construct();
    }

    public function adminmenu()
    {
        return $this->pageTitles;
    }


    public function initialise()
    {
        parent::initialise();
        return $this->name . ' '. s('initialised');
    }
/*
 *  Methods for composing a campaign
 * 
 */ 
    public function sendMessageTab($messageid = 0, $data = array ())
    {
        $feedUrl = isset($data['rss_feed']) ? $data['rss_feed'] : '';
        $html = <<<END
    <label>RSS feed URL
    <input type="text" name="rss_feed" value="$feedUrl" /></label>
END;
        return $html;
    }

    public function sendMessageTabTitle($messageid = 0)
    {
        return 'RSS';
    }

    public function sendTestAllowed ($messageData)
    {
        if (!isset($messageData['rss_feed']) || $messageData['rss_feed'] == '') {
            $this->rssHtml = null;
            return true;
        }

        $this->rssHtml = $this->generateItemHtml($this->sampleItems());
        $this->rssText = HTML2Text($this->rssHtml);
        return true;
    }

    public function allowMessageToBeQueued($messageData = array())
    {
        if (!isset($messageData['rss_feed']) || $messageData['rss_feed'] == '') {
            return '';
        }
        $feedUrl = $messageData['rss_feed'];

        if (!preg_match('/^http/i', $feedUrl)) {
            return "Invalid URL $feedUrl for RSS feed";
        }

        try {
            $this->validateFeed($feedUrl);
        } catch (PicoFeed\PicoFeedException $e) {
            return "Failed to fetch URL $feedUrl " . $e->getMessage();
        }

        if (stripos($messageData['message'], '[RSS]') === false) {
            return 'Must have [RSS] placeholder in an RSS message';
        }

        if ($messageData['repeatinterval'] == 0) {
            return 'Repeat interval must be selected for an RSS campaign';
        }
        $this->dao = new RssFeedPlugin_DAO(new CommonPlugin_DB);
        $this->dao->addFeed($feedUrl);
        return '';
    }

/*
 *  Methods for processing the queue and messages
 * 
 */ 
    public function processQueueStart()
    {
        $level = error_reporting(-1);
        $this->dao = new RssFeedPlugin_DAO(new CommonPlugin_DB);

        foreach ($this->dao->readyRssMessages() as $mid) {
            $items = iterator_to_array($this->dao->latestFeedContent($mid, getConfig('rss_maximum')));

            if (count($items) < getConfig('rss_minimum')) {
                $count = $this->dao->reEmbargoMessage($mid);

                If ($count > 0) {
                    logEvent("Embargo advanced for RSS message $mid");
                } else {
                    $count = $this->dao->setMessageSent($mid);
                    logEvent("RSS message $mid marked as 'sent' because it has finished repeating");
                }
            }
        error_reporting($level);
        }
    }

    public function campaignStarted($data = array())
    {
        if (!isset($data['rss_feed']) || stripos($data['message'], '[RSS]') === false || $data['repeatinterval'] == 0) {
            $this->rssHtml = null;
            return;
        }
        $this->dao = new RssFeedPlugin_DAO(new CommonPlugin_DB);
        $items = iterator_to_array($this->dao->latestFeedContent($data['id'], getConfig('rss_maximum')));
        $this->rssHtml = $this->generateItemHtml($items);
        $this->rssText = HTML2Text($this->rssHtml);
    }

    public function parseOutgoingHTMLMessage($messageid, $content, $destination = '', $userdata = array())
    {
        if ($this->rssHtml === null) {
             return $content;
        }

        return str_ireplace('[RSS]', $this->rssHtml, $content);
    }

    public function parseOutgoingTextMessage($messageid, $content, $destination = '', $userdata = array())
    {
        if ($this->rssHtml === null) {
             return $content;
        }

        return str_ireplace('[RSS]', $this->rssText, $content);
    }
}
