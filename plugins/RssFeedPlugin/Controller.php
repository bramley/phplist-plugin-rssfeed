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
 * This is the controller class that provides the actionX() methods
 */

class RssFeedPlugin_Controller
    extends CommonPlugin_Controller
{
    protected $model;

    /*
     *    Protected methods
     */
    protected function actionDefault()
    {
        $toolbar = new CommonPlugin_Toolbar($this);
        $toolbar->addExportButton();
        $toolbar->addHelpButton('viewrss');
        $listing = new CommonPlugin_Listing($this, $this);
        echo $listing->display();
        return;

        $params = array(
            'toolbar' => $toolbar->display(),
            'listing' => $listing->display()
        );
        print $this->render(dirname(__FILE__) . '/view.tpl.php', $params);
    }

    /*
     *    Public methods
     */
    public function __construct()
    {
        parent::__construct();
        $this->dao = new RssFeedPlugin_DAO(new CommonPlugin_DB());
    }
}
