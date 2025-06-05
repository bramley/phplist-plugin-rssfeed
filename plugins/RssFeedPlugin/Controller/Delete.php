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

use phpList\plugin\Common\Context;
use phpList\plugin\Common\Controller;
use phpList\plugin\RssFeedPlugin\DAO;

class Delete extends Controller
{
    private $dao;

    protected function actionDefault()
    {
        global $commandline, $inRemoteCall;

        if ($commandline) {
            $options = getopt('c:p:m:d:u');
            $this->context->start();

            if (isset($options['d']) && ctype_digit($options['d'])) {
                $count = $this->dao->deleteItems($options['d']);
                $this->context->output(s('%d items deleted', $count));

                if (isset($options['u'])) {
                    $count = $this->dao->deleteUnusedFeeds();
                    $this->context->output(s('%d unused feeds deleted', $count));
                }
            } else {
                $this->context->output(s('option d not found'));
            }
            $this->context->finish();

            return;
        }

        if ($inRemoteCall) {
            $this->context->start();

            if (isset($_GET['days']) && ctype_digit($_GET['days'])) {
                $count = $this->dao->deleteItems($_GET['days']);
                $this->context->output(s('%d items deleted', $count));

                if (isset($_GET['unusedfeeds'])) {
                    $count = $this->dao->deleteUnusedFeeds();
                    $this->context->output(s('%d unused feeds deleted', $count));
                }
            } else {
                $this->context->output(s('days parameter not found'));
            }
            $this->context->finish();

            return;
        }
        // No need to use context for the browser

        if (!$_SESSION['logindetails']['superuser']) {
            echo '<p>' . s('Sorry, only super users can delete RSS items from the database') . '</p>';

            return;
        }
        $deleteNote = '';
        $resetNote = '';

        if (isset($_POST['submit'])) {
            if ($_POST['submit'] == 'reset') {
                $count = $this->dao->deleteItems(0);
                $count = $this->dao->resetAllFeeds();
                $resetNote = sprintf('<div class="note">%s</div>', s('%d feeds reset', $count));
            } elseif ($_POST['submit'] == 'delete') {
                if (isset($_POST['days']) && ctype_digit($_POST['days'])) {
                    $count = $this->dao->deleteItems($_POST['days']);
                    $deleteNote = s('%d items deleted', $count);

                    if (isset($_POST['unusedfeeds'])) {
                        $count = $this->dao->deleteUnusedFeeds();
                        $deleteNote .= '<br/>' . s('%d unused feeds deleted', $count);
                    }
                    $deleteNote = sprintf('<div class="note">%s</div>', $deleteNote);
                }
            }
        }
        $prompt1 = s('Enter the number of days to be kept');
        $prompt2 = s('All items whose published date is earlier will be deleted.');
        $prompt3 = s('Delete RSS feeds that are not used by any campaigns');
        $button = s('Delete');
        echo <<<END
$deleteNote
<form method="post" action="">
    <label>$prompt1<br />$prompt2
        <input type=text name="days" value="30" size=7>
    </label>
    <br/>
    <label>$prompt3
        <input type="checkbox" id="unusedfeeds" name="unusedfeeds">
    </label>
    <br/>
    <button name="submit" value="delete" type="submit">$button</button>
</form>
END;
        $prompt = s('For each feed, all items will be deleted and the etag and lastmodified fields reset.');
        $button = s('Reset');
        echo <<<END
<h4>Reset feeds</h4>
<div>
$resetNote
    <form method="post" action="">
        <div>$prompt</div>
        <button name="submit" value="reset" type="submit">$button</button>
    </form>
</div>
END;
    }

    public function __construct(DAO $dao, Context $context)
    {
        parent::__construct();
        $this->dao = $dao;
        $this->context = $context;
    }
}
