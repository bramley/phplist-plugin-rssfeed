<?php

/**
 * RssFeedPlugin for phplist.
 *
 * This file is a part of RssFeedPlugin.
 *
 * @category  phplist
 *
 * @author    Duncan Cameron
 * @copyright 2018 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

namespace phpList\plugin\RssFeedPlugin\Controller;

use phpList\plugin\Common\Controller;
use phpList\plugin\Common\ImageTag;
use phpList\plugin\Common\IPopulator;
use phpList\plugin\Common\Listing;
use phpList\plugin\Common\PageURL;
use phpList\plugin\RssFeedPlugin\DAO;
use WebblerListing;

class Feeds extends Controller implements IPopulator
{
    private $dao;

    protected function actionDefault()
    {
        $listing = new Listing($this, $this);
        $listing->pager->setItemsPerPage([]);
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
        $w->setTitle(s('RSS feeds'));
        $w->setElementHeading(s('Feed ID'));

        foreach ($this->dao->feeds() as $row) {
            $key = $row['id'];
            $w->addElement($key);
            $w->addColumn($key, 'URL', $row['url'], '', 'wrap');
            $active = $row['active'] ? new ImageTag('yes.png', s('active')) : '';
            $w->addColumnHtml($key, s('Active'), $active);
            $totalItems = $this->dao->totalItemsForFeed($row['id']);
            $url = $totalItems > 0 ? new PageURL('view', ['pi' => $_GET['pi'], 'id' => $row['id']]) : '';
            $w->addColumn($key, s('Items'), $totalItems, $url);
            $w->addColumn($key, s('Active campaigns'), $row['total_active']);
            $w->addColumn($key, s('Sent campaigns'), $row['total_sent']);
            $w->addRow($key, s('ETag'), $row['etag'], '', 'left');
            $w->addRow($key, s('Last modified'), $row['lastmodified'], '', 'left');
        }
    }

    public function total()
    {
        return count($this->dao->feeds());
    }
}
