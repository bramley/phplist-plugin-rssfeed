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

use phpList\plugin\Common\Controller;
use phpList\plugin\Common\IPopulator;
use phpList\plugin\Common\Listing;
use phpList\plugin\RssFeedPlugin\DAO;
use WebblerListing;

/**
 * This class is a subclass of the base controller class that implements the CommonPlugin_IPopulator
 * interface to show rss items.
 */
class View extends Controller implements IPopulator
{
    private $dao;

    protected function actionDefault()
    {
        $listing = new Listing($this, $this);
        echo $listing->display();

        return;
    }

    public function __construct(DAO $dao)
    {
        parent::__construct();
        $this->dao = $dao;
    }

    public function populate(WebblerListing $w, $start, $limit)
    {
        $w->setTitle(s('Feed Items'));
        $w->setElementHeading(s('Item ID'));

        foreach ($this->dao->itemsForFeed($_GET['id'], $start, $limit, false) as $row) {
            $key = $row['id'];
            $w->addElement($key, $row['url']);
            $w->addColumn($key, s('Title'), $row['title']);
            $w->addColumn($key, s('Published'), $row['published']);
            $w->addColumn($key, s('Added'), $row['added']);
            $w->addColumn($key, s('Content'), substr($row['content'], 0, 100));
        }
    }

    public function total()
    {
        return $this->dao->totalItemsForFeed($_GET['id']);
    }
}
