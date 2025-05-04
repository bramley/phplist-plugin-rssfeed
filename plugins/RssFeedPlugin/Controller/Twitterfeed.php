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

use DateTime;
use phpList\plugin\Common\Controller;
use phpList\plugin\RssFeedPlugin\TwitterParser;

class TwitterFeed extends Controller
{
    private $parser;

    protected function actionDefault()
    {
        if (!(isset($_GET['id']) && ctype_digit($_GET['id']))) {
            echo 'widget id must be numeric';

            return;
        }
        $result = $this->parser->createFeed($_GET['id'], isset($_GET['format']) ? $_GET['format'] : '');
        ob_end_clean();

        switch ($result->code) {
            case 404:
                header('HTTP/1.0 404 Not found');
                break;
            case 304:
                header('HTTP/1.0 304 Not modified');
                break;
            default:
                $now = new DateTime();
                header("Content-Type: $result->contentType");
                header('Last-Modified: ' . $now->format(DateTime::RFC1123));
                echo $result->content;
        }
        exit;
    }

    public function __construct(TwitterParser $parser)
    {
        parent::__construct();
        $this->parser = $parser;
    }
}
