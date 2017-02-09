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
 * This is the entry code invoked by phplist.
 *
 * @category  phplist
 */
if (!(phplistPlugin::isEnabled('CommonPlugin'))) {
    echo 'phplist-plugin-common must be installed and enabled to use this plugin';

    return;
}
if (!(isset($_GET['id']) && ctype_digit($_GET['id']))) {
    echo 'widget id must be numeric';

    return;
}

error_reporting(-1);
$parser = new phpList\plugin\RssFeedPlugin\TwitterParser();
$result = $parser->createFeed($_GET['id'], isset($_GET['format']) ? $_GET['format'] : '');
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
