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
 * This class is a subclass of the base controller class that implements the CommonPlugin_IPopulator
 * interface to show rss items
 */

use PicoFeed\Reader\Reader;
use PicoFeed\Config\Config;
use PicoFeed\PicoFeedException;

class RssFeedPlugin_Controller_Get
{
    protected function actionDefault()
    {
    }
}
