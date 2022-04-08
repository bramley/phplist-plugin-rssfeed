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

        if (isset($_POST['days']) && ctype_digit($_POST['days'])) {
            $count = $this->dao->deleteItems($_POST['days']);
            $note = s('%d items deleted', $count);

            if (isset($_POST['unusedfeeds'])) {
                $count = $this->dao->deleteUnusedFeeds();
                $note .= '<br/>' . s('%d unused feeds deleted', $count);
            }
            echo "<div class='note'>$note</div>";
        }

        $prompt1 = s('Enter the number of days to be kept.');
        $prompt2 = s('All items whose published date is earlier will be deleted');
        $prompt3 = s('Delete RSS feeds that are not used by any campaigns');
        $button = s('Delete');
        echo <<<END
<form method="post" action="">
    <label>$prompt1<br />
$prompt2
        <input type=text name="days" value="30" size=7>
    </label>
    <br/>
    <label>$prompt3
        <input type="checkbox" id="unusedfeeds" name="unusedfeeds">
    </label>
    <br/>
    <input type=submit name="submit" value="$button">
</form>
END;
    }

    public function __construct(DAO $dao, Context $context)
    {
        parent::__construct();
        $this->dao = $dao;
        $this->context = $context;
    }
}
