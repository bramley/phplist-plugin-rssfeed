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
 * This is the entry code invoked by phplist.
 *
 * @category  phplist
 */
if (isset($_GET['p'])) {
    // invoked as a public page
    if (empty($_GET['secret']) || $_GET['secret'] != getConfig('remote_processing_secret')) {
        http_response_code(403);
        echo 'Not allowed';
        exit;
    }
}phpList\plugin\Common\Main::run(new phpList\plugin\RssFeedPlugin\ControllerFactory());
