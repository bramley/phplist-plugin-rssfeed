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

/**
 * Registers plugin with phplist
 * Provides hooks into message processing.
 */
class RssFeedPlugin extends phplistPlugin
{
    const VERSION_FILE = 'version.txt';
    const OLDEST_FIRST = 1;
    const LATEST_FIRST = 2;
    const TWITTER_PAGE = 'twitterfeed';

    private $dao;
    private $rssHtml;
    private $rssText;
    private $errorLevel;

    public $name = 'RSS Feed Manager';
    public $authors = 'Duncan Cameron';
    public $description = 'Send campaigns that contain RSS feed items';
    public $documentationUrl = 'https://resources.phplist.com/plugin/rssfeed';

    public $commandlinePluginPages = array('get', 'delete');

    public $remotePages = array(self::TWITTER_PAGE, 'get', 'delete');

    public $topMenuLinks = array(
        'feeds' => array('category' => 'campaigns'),
        'get' => array('category' => 'campaigns'),
        'delete' => array('category' => 'campaigns'),
    );

    public $DBstruct = array(
        'feed' => array(
            'id' => array('integer not null primary key auto_increment', 'ID'),
            'url' => array('text not null', ''),
            'etag' => array('varchar(100) not null', ''),
            'lastmodified' => array('varchar(100) not null', ''),
        ),
        'item' => array(
            'id' => array('integer not null primary key auto_increment', 'ID'),
            'uid' => array('varchar(100) not null', 'unique id'),
            'feedid' => array('integer not null', 'fk to feed'),
            'published' => array('datetime not null', 'published datetime'),
            'added' => array('datetime not null', 'datetime added'),
            'index_1' => array('feedpublishedindex (feedid, published)', ''),
            'index_2' => array('feeduidindex (feedid, uid)', ''),
        ),
        'item_data' => array(
            'itemid' => array('integer not null', 'fk to item'),
            'property' => array('varchar(100) not null', ''),
            'value' => array('text', ''),
            'primary key' => array('(itemid, property)', ''),
        ),
    );

    private function isRssMessage(array $messageData)
    {
        return isset($messageData['rss_feed']) && $messageData['rss_feed'] != '';
    }

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
                'published' => date('Y-m-d H:i:s', time() - 10000),
                'title' => 'These are just some sample entries for the test RSS message',
                'content' => '<p>The phpList manual is available online, or you can download it to your favourite device.</p>',
                'url' => 'https://www.phplist.org/manual/',
            ),
            array(
                'published' => date('Y-m-d H:i:s', time() - 8000),
                'title' => 'Adding your first Subscribers ',
                'content' => '<p>phpList Manual chapter explaining how to add subscribers.</p>',
                'url' => 'https://www.phplist.org/manual/ch006_adding-your-first-subscribers.xhtml',
            ),
            array(
                'published' => date('Y-m-d H:i:s', time() - 6000),
                'title' => 'Composing your first campaign',
                'content' => '<p>How to write your first campaign in phpList.</p>',
                'url' => 'https://www.phplist.org/manual/ch007_sending-your-first-campaign.xhtml',
            ),
            array(
                'published' => date('Y-m-d H:i:s', time() - 4000),
                'title' => 'Sending a campaign',
                'content' => '<p>The phpList manual pages, explaining how to send your campaign.</p>',
                'url' => 'https://www.phplist.org/manual/ch008_your-first-campaign.xhtml',
            ),
            array(
                'published' => date('Y-m-d H:i:s', time()),
                'title' => 'Campaign Statistics',
                'content' => '<p>Once you have sent your campaign, just sit back and watch the statistics grow.</p>',
                'url' => 'https://www.phplist.org/manual/ch009_basic-campaign-statistics.xhtml',
            ),
        );
    }

    private function generateItemHtml(array $items, $order, $customTemplate)
    {
        $htmltemplate = trim($customTemplate) === ''
            ? getConfig('rss_htmltemplate')
            : $customTemplate;
        $html = '';

        if ($order == self::LATEST_FIRST) {
            $items = array_reverse($items);
        }

        foreach ($items as $item) {
            $d = new DateTime($item['published']);
            $html .= $this->replaceProperties(
                $htmltemplate,
                array(
                    'published' => $d->format(getConfig('rss_date_format')),
                    'title' => htmlspecialchars($item['title']),
                ) + $item
            );
        }

        return $html;
    }

    private function newSubject($subject, array $items)
    {
        $size = count($items);

        if ($size == 0) {
            $titleReplace = 'No title';
        } else {
            $item = $items[$size - 1];
            $titleReplace = $item['title'];

            if ($size > 1 && ($suffix = getConfig('rss_subjectsuffix'))) {
                $titleReplace .= $suffix;
            }
        }

        return $this->replaceProperties(
            $subject,
            array('RSSITEM:TITLE' => $titleReplace)
        );
    }

    private function modifySubject(array $messageData, array $items)
    {
        global $MD;

        $MD[$messageData['id']]['subject'] = $this->newSubject($messageData['subject'], $items);
    }

    private function itemsForTestMessage($mid)
    {
        $items = $this->dao->messageFeedItems($mid, getConfig('rss_maximum'), true);

        if (count($items) > 0) {
            $warning = '';
        } else {
            $items = $this->dao->messageFeedItems($mid, getConfig('rss_maximum'), false);

            if (count($items) > 0) {
                $warning = s('There are no feed items that will be included in the first campaign. A test message will include only items with earlier published dates.');
            } else {
                $warning = s('There are no feed items. A test message will include the sample items.');
                $items = $this->sampleItems();
            }
        }

        return [$items, $warning];
    }

    public function __construct()
    {
        $this->pageTitles = array(
            'feeds' => s('RSS feeds'),
            'get' => s('Fetch RSS items'),
            'delete' => s('Delete outdated RSS items'),
        );
        $this->settings = array(
            'rss_minimum' => array(
                'description' => s('Minimum number of items to send in an RSS email'),
                'type' => 'integer',
                'value' => 1,
                'allowempty' => 0,
                'min' => 1,
                'max' => 50,
                'category' => 'RSS',
            ),
            'rss_maximum' => array(
                'description' => s('Maximum number of items to send in an RSS email'),
                'type' => 'integer',
                'value' => 30,
                'allowempty' => 0,
                'min' => 1,
                'max' => 50,
                'category' => 'RSS',
            ),
            'rss_htmltemplate' => array(
                'description' => s('Item HTML template'),
                'type' => 'textarea',
                'value' => '
                <a href="[URL]"><b>[TITLE]</b></a><br/>
                [PUBLISHED]<br/>
                [CONTENT]
                <hr/>',
                'allowempty' => 0,
                'category' => 'RSS',
            ),
            'rss_subjectsuffix' => array(
                'description' => s('Text to append when the title of the latest item is used in the subject'),
                'type' => 'text',
                'value' => '',
                'allowempty' => true,
                'category' => 'RSS',
            ),
            'rss_custom_elements' => array(
                'description' => s('Additional feed elements to be included in each item\'s data'),
                'type' => 'textarea',
                'value' => '',
                'allowempty' => true,
                'category' => 'RSS',
            ),
            'rss_date_format' => array(
                'description' => s('php date() format for the published date'),
                'type' => 'text',
                'value' => 'd/m/Y H:i',
                'allowempty' => false,
                'category' => 'RSS',
            ),
            'rss_content_filtering' => array(
                'description' => s('Allow only whitelisted tags in item content'),
                'type' => 'boolean',
                'value' => true,
                'allowempty' => true,
                'category' => 'RSS',
            ),
            'rss_content_generating' => array(
                'description' => s('Generate special content when an item URL is to YouTube or to a pdf file'),
                'type' => 'boolean',
                'value' => false,
                'allowempty' => true,
                'category' => 'RSS',
            ),
        );
        $this->errorLevel = E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_STRICT;
        $this->coderoot = dirname(__FILE__) . '/' . __CLASS__ . '/';

        parent::__construct();
        $this->version = (is_file($f = $this->coderoot . self::VERSION_FILE))
            ? file_get_contents($f)
            : '';
    }

    public function dependencyCheck()
    {
        global $plugins;

        return array(
            'Common plugin v3.11.1 or later installed' => (
                phpListPlugin::isEnabled('CommonPlugin')
                && version_compare($plugins['CommonPlugin']->version, '3.11.1') >= 0
            ),
            'View in Browser plugin v2.4.0 or later installed' => (
                !phpListPlugin::isEnabled('ViewBrowserPlugin')
                || version_compare($plugins['ViewBrowserPlugin']->version, '2.4.0') >= 0
            ),
            'phpList version 3.3.2 or later' => version_compare(VERSION, '3.3.2') >= 0,
            'PHP version 5.4.0 or later' => version_compare(PHP_VERSION, '5.4') > 0,
            'iconv extension installed' => extension_loaded('iconv'),
            'xml extension installed' => extension_loaded('xml'),
            'dom extension installed' => extension_loaded('dom'),
            'SimpleXML extension installed' => extension_loaded('SimpleXML'),
            'Multibyte String extension installed' => extension_loaded('mbstring'),
        );
    }

    /**
     * Use this method as a hook to create the dao.
     */
    public function activate()
    {
        parent::activate();
        $depends = require $this->coderoot . 'depends.php';
        $container = new \phpList\plugin\Common\Container($depends);
        $this->dao = $container->get('phpList\plugin\RssFeedPlugin\DAO');
    }

    public function adminmenu()
    {
        return $this->pageTitles;
    }

    public function cronJobs()
    {
        return array(
            array(
                'page' => 'get',
                'frequency' => 60,
            ),
        );
    }

    /*
     *  Methods for composing a campaign
     */
    public function sendMessageTab($messageid = 0, $data = array())
    {
        $feedUrl = isset($data['rss_feed']) ? htmlspecialchars($data['rss_feed']) : '';
        $order = CHtml::dropDownList(
            'rss_order',
            isset($data['rss_order']) ? $data['rss_order'] : self::OLDEST_FIRST,
            array(self::OLDEST_FIRST => s('Oldest items first'), self::LATEST_FIRST => s('Latest items first'))
        );
        $template = isset($data['rss_template']) ? htmlspecialchars($data['rss_template']) : '';
        $feedLabel = s('RSS feed URL');
        $orderLabel = s('How to order feed items');
        $templateLabel = s('Custom template');

        if ($feedUrl) {
            list($items, $testItemWarning) = $this->itemsForTestMessage($data['id']);
        } else {
            $testItemWarning = '';
        }
        $html = <<<END
    <label>$feedLabel
    <input type="text" name="rss_feed" value="$feedUrl" size="60"/></label>
    <label>$orderLabel
    $order</label>
    <label>$templateLabel</label><textarea name="rss_template" rows="10" cols="40">$template</textarea>
    <div class="note">$testItemWarning</div>
END;

        return $html;
    }

    public function sendMessageTabTitle($messageid = 0)
    {
        return 'RSS';
    }

    public function sendMessageTabInsertBefore()
    {
        return 'Format';
    }

    /**
     * Generate RSS items for a test message.
     *
     * @param array $messageData message fields
     */
    public function sendTestAllowed($messageData)
    {
        if (!$this->isRssMessage($messageData)) {
            $this->rssHtml = null;

            return true;
        }
        list($items, $warning) = $this->itemsForTestMessage($messageData['id']);

        $this->rssHtml = $this->generateItemHtml($items, $messageData['rss_order'], $messageData['rss_template']);
        $this->rssText = HTML2Text($this->rssHtml);
        $this->modifySubject($messageData, $items);

        return true;
    }

    /**
     * Provide a read-only view of the RSS fields for a campaign.
     *
     * @param int   $messageId   message id
     * @param array $messageData message fields
     */
    public function viewMessage($messageId, array $messageData)
    {
        if (!$this->isRssMessage($messageData)) {
            return false;
        }
        $html = $this->sendMessageTab($messageId, $messageData);
        $html = <<<END
    <fieldset disabled>
    $html
    </fieldset>
END;

        return array('RSS', $html);
    }

    /**
     * Validate that the RSS fields have been entered and the feed url is valid.
     *
     * @param array $messageData message fields
     *
     * @return string empty string for success otherwise an error message
     */
    public function allowMessageToBeQueued($messageData = array())
    {
        if (!$this->isRssMessage($messageData)) {
            return '';
        }
        $feedUrl = $messageData['rss_feed'];

        if (!preg_match('/^http/i', $feedUrl)) {
            return "Invalid URL $feedUrl for RSS feed";
        }
        $exists = $this->dao->feedExists($feedUrl);

        if (!$exists) {
            try {
                $this->validateFeed($feedUrl);
            } catch (PicoFeed\PicoFeedException $e) {
                return s('Failed to fetch URL %s %s', $feedUrl, $e->getMessage());
            }
            $this->dao->addFeed($feedUrl);
        }

        if (stripos($messageData['message'], '[RSS]') === false) {
            if ($messageData['template'] === 0) {
                $templateHasPlaceholder = false;
            } else {
                $templateBody = $this->dao->templateBody($messageData['template']);
                $templateHasPlaceholder = stripos($templateBody, '[RSS]') !== false;
            }

            if (!$templateHasPlaceholder) {
                return s('Must have [RSS] placeholder in an RSS message');
            }
        }

        if (!USE_REPETITION) {
            return sprintf(
                '%s <a href="%s" target="_blank">(%s)</a>',
                s('Campaign repetition must be enabled in config.php'),
                'https://resources.phplist.com/system/config/use_repetition',
                s('Documentation')
            );
        }
        $repeatInterval = $messageData['repeatinterval'] * 60;

        if ($repeatInterval == 0) {
            return s('Repeat interval must be selected for an RSS campaign');
        }

        if ($repeatInterval > DEFAULT_MESSAGEAGE) {
            $helpLink = sprintf('<a href="%s#rss_campaign_schedule" target="_blank">%s</a>', $this->documentationUrl, $this->documentationUrl);

            return s(
                'The value of the config.php setting DEFAULT_MESSAGEAGE (%d) must be greater than the repeat interval in seconds (%d). See %s.',
                DEFAULT_MESSAGEAGE,
                $repeatInterval,
                $helpLink
            );
        }

        return '';
    }

    /**
     * Use this hook to see whether any RSS messages have sufficient items to be sent.
     * If not then the embargo of the campaign is moved forward to avoid sending a message with no RSS content.
     */
    public function processQueueStart()
    {
        $level = error_reporting($this->errorLevel);

        foreach ($this->dao->readyRssMessages() as $mid) {
            $items = $this->dao->messageFeedItems($mid, getConfig('rss_maximum'));

            if (count($items) < getConfig('rss_minimum')) {
                $count = $this->dao->reEmbargoMessage($mid);

                if ($count > 0) {
                    logEvent(s('Embargo advanced for RSS message %s', $mid));
                } else {
                    $count = $this->dao->setMessageSent($mid);
                    logEvent(s('RSS message %d marked as "sent" because it has finished repeating', $mid));
                }
            }
        }
        error_reporting($level);
    }

    /**
     * Use this hook to generate the html and plain text of the RSS items and modify the subject.
     *
     * @param array $messageData message fields
     */
    public function campaignStarted($messageData = array())
    {
        if (!$this->isRssMessage($messageData)) {
            $this->rssHtml = null;

            return;
        }
        $items = $this->dao->messageFeedItems($messageData['id'], getConfig('rss_maximum'));
        $this->rssHtml = $this->generateItemHtml($items, $messageData['rss_order'], $messageData['rss_template']);
        $this->rssText = HTML2Text($this->rssHtml);
        $this->modifySubject($messageData, $items);
    }

    /**
     * When a campaign has finished sending replace the placeholder with the actual RSS content used, and modify
     * the subject to the actual subject used.
     *
     * @param int   $messageId   message id
     * @param array $messageData message fields
     */
    public function processSendingCampaignFinished($messageId, array $messageData)
    {
        global $MD;

        if (!$this->isRssMessage($messageData)) {
            return;
        }

        if (stripos($messageData['message'], '[RSS]') !== false) {
            $content = str_ireplace('[RSS]', $this->rssHtml, $messageData['message']);
            $this->dao->setMessage($messageId, $content);
        }
        $this->dao->setSubject($messageId, $MD[$messageData['id']]['subject']);
    }

    /**
     * Replace the placeholder by the html RSS content.
     *
     * @param int    $messageid   the message id
     * @param string $content     the message content
     * @param string $destination destination email address
     * @param array  $userdata    user fields
     *
     * @return string
     */
    public function parseOutgoingHTMLMessage($messageid, $content, $destination = '', $userdata = array())
    {
        if ($this->rssHtml === null) {
            return $content;
        }

        return str_ireplace('[RSS]', $this->rssHtml, $content);
    }

    /**
     * Replace the placeholder by the text RSS content.
     *
     * @param int    $messageid   the message id
     * @param string $content     the message content
     * @param string $destination destination email address
     * @param array  $userdata    user fields
     *
     * @return string
     */
    public function parseOutgoingTextMessage($messageid, $content, $destination = '', $userdata = array())
    {
        if ($this->rssHtml === null) {
            return $content;
        }

        return str_ireplace('[RSS]', $this->rssText, $content);
    }

    /**
     * Called by ViewBrowser plugin to manipulate template and message.
     * Gets the RSS HTML content and modifies the message subject.
     *
     * @param string &$templateBody the body of the template
     * @param array  &$messageData  the message data
     */
    public function viewBrowserHook(&$templateBody, array &$messageData)
    {
        if (!$this->isRssMessage($messageData)) {
            return;
        }

        if ($messageData['status'] == 'draft') {
            list($items, $warning) = $this->itemsForTestMessage($messageData['id']);
        } else {
            $items = $this->dao->messageFeedItems($messageData['id'], getConfig('rss_maximum'));
        }

        $this->rssHtml = $this->generateItemHtml($items, $messageData['rss_order'], $messageData['rss_template']);
        $messageData['subject'] = $this->newSubject($messageData['subject'], $items);
    }

    /**
     * Called when a campaign is being copied.
     * Allows this plugin to specify which rows of the messagedata table should also
     * be copied.
     *
     * @return array rows of messagedata table that should be copied
     */
    public function copyCampaignHook()
    {
        return array('rss_feed', 'rss_order', 'rss_template');
    }
}
