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
class RssFeedPlugin_Controller_View
    extends RssFeedPlugin_Controller
    implements CommonPlugin_IPopulator
{
    /*
     * Implementation of CommonPlugin_IPopulator
     */

    function populate(WebblerListing $w, $start, $limit)
    {
        $loginId = $_SESSION['logindetails']['superuser'] ? '' : $_SESSION['logindetails']['id'];

        $w->setTitle($this->i18n->get('RSS'));

        foreach ($this->dao->feedItems($start, $limit, $loginId) as $row) {
            $key = $row['id'];
            $w->addElement($key, $row['url']);
            $w->addColumn($key, 'Title', $row['title']);
            $w->addColumn($key, 'Published', $row['published']);
            $w->addColumn($key, 'Content', substr($row['content'], 0, 100));
        }
    }

    function total()
    {
        $loginId = $_SESSION['logindetails']['superuser'] ? '' : $_SESSION['logindetails']['id'];
        return $this->dao->totalFeedItems($loginId);
    }
}
