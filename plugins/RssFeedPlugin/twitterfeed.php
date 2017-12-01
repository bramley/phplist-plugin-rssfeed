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
phpList\plugin\Common\Main::run(new phpList\plugin\RssFeedPlugin\ControllerFactory());
