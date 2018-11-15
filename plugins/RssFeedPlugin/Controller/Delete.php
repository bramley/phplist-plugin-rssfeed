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
use phpList\plugin\RssFeedPlugin\DAO;

class Delete extends Controller
{
    private $dao;

    protected function actionDefault()
    {
        if (isset($_POST['daysago']) && ctype_digit($_POST['daysago'])) {
            if (!$_SESSION['logindetails']['superuser']) {
                echo '<p>' . s('Sorry, only super users can delete RSS items from the database') . '</p>';

                return;
            }
            $count = $this->dao->deleteItems($_POST['daysago']);
            $note = s('%d items deleted', $count);
            echo "<div class='note'>$note</div>";
        }

        $prompt1 = s('Enter the number of days to be kept.');
        $prompt2 = s('All items whose published date is earlier will be deleted.');
        $button = s('Delete');
        echo <<<"END"
<form method="post" action="">
<caption>$prompt1<br />
$prompt2
    <input type=text name="daysago" value="30" size=7></caption>
    <input type=submit name="submit" value="$button">
</form>
END;
    }

    public function __construct(DAO $dao)
    {
        parent::__construct();
        $this->dao = $dao;
    }
}
