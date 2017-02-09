<?php

error_reporting(-1);

if (isset($_POST['daysago']) && ctype_digit($_POST['daysago'])) {
    if (!$_SESSION['logindetails']['superuser']) {
        echo '<p>'.s('Sorry, only super users can delete RSS items from the database').'</p>';

        return;
    }
    $dao = new RssFeedPlugin_DAO(new CommonPlugin_DB());
    $count = $dao->deleteItems($_POST['daysago']);
    echo "<div class='note'>$count items deleted</div>";
}

echo <<<'END'
<form method="post" action="">
<caption>Enter the number of days to be kept.<br />
All items whose published date is earlier will be deleted.
    <input type=text name="daysago" value="30" size=7></caption>
    <input type=submit name="submit" value="Purge">
</form>
END;
