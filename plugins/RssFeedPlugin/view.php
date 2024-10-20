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
if (!phplistPlugin::isEnabled('CommonPlugin')) {
    echo 'phplist-plugin-common must be installed and enabled to use this plugin';

    return;
}

phpList\plugin\Common\Main::run(new phpList\plugin\RssFeedPlugin\ControllerFactory());
