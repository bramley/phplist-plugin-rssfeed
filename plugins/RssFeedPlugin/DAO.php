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
 * Data access class
 * 
 */

class RssFeedPlugin_DAO extends CommonPlugin_DAO
{
    public function __construct($db)
    {
        global $plugins;

        parent::__construct($db);
        $this->tables += $plugins['RssFeedPlugin']->tables;
    }

    public function addFeed($url)
    {
        $url = sql_escape($url);
        $sql = 
            "INSERT INTO {$this->tables['feed']}
            (url)
            SELECT '$url'
            FROM (SELECT 1) AS a
            WHERE NOT EXISTS(
                SELECT url
                FROM {$this->tables['feed']}
                WHERE url = '$url'
                LIMIT 1
            )";

        return $this->dbCommand->queryInsertId($sql);
    }

    public function addItem($uid, $published, $feedId)
    {
        $uid = sql_escape($uid);

        $sql = 
            "INSERT INTO {$this->tables['item']}
            (uid, published, feedid, added)
            SELECT '$uid', '$published', $feedId, current_timestamp
            FROM (SELECT 1) AS a
            WHERE NOT EXISTS(
                SELECT uid
                FROM {$this->tables['item']}
                WHERE uid = '$uid' AND feedid = $feedId
                LIMIT 1
            )";

        return $this->dbCommand->queryInsertId($sql);
    }

    public function addItemData($itemId, array $properties)
    {
        $a = array();

        foreach ($properties as $property => $value) {
            $property = sql_escape($property);
            $value = sql_escape($value);
            $a[] = "\n($itemId, '$property', '$value')";
        }
        $sql = 
            "INSERT INTO {$this->tables['item_data']}
            (itemid, property, value)
            VALUES"
            . implode(',', $a);

        return $this->dbCommand->queryInsertId($sql);
    }

    public function deleteItems($days)
    {
        $sql = 
            "DELETE itd
            FROM {$this->tables['item']} it
            JOIN {$this->tables['item_data']} itd ON itd.itemid = it.id
            WHERE it.published < now() - INTERVAL $days DAY
            ";

        $rows = $this->dbCommand->queryAffectedRows($sql);

        $sql = 
            "DELETE it
            FROM {$this->tables['item']} it
            WHERE it.published < now() - INTERVAL $days DAY
            ";

        return $this->dbCommand->queryAffectedRows($sql);
    }

    public function latestFeedContentByUrl($url, $interval, $limit = 30)
    {
        $url = sql_escape($url);
        $sql = 
            "SELECT itd1.value as title, itd2.value as content, itd3.value as url, it.published
            FROM {$this->tables['feed']} fe
            JOIN {$this->tables['item']} it ON fe.id = it.feedid
            JOIN {$this->tables['item_data']} itd1 on itd1.itemid = it.id AND itd1.property = 'title'
            JOIN {$this->tables['item_data']} itd2 on itd2.itemid = it.id AND itd2.property = 'content'
            JOIN {$this->tables['item_data']} itd3 on itd3.itemid = it.id AND itd3.property = 'url'
            WHERE fe.url = '$url' AND it.published > now() - INTERVAL $interval MINUTE
            ORDER BY it.published
            LIMIT $limit";

        return $this->dbCommand->queryAll($sql);
    }

    public function feeds()
    {
        $sql = 
            "SELECT *
            FROM {$this->tables['feed']}";

        return $this->dbCommand->queryAll($sql);
    }

    public function updateFeed($feedId, $etag, $lastModified)
    {
        $etag = sql_escape($etag);
        $lastModified = sql_escape($lastModified);
        $sql = 
            "UPDATE {$this->tables['feed']}
            SET etag = '$etag', lastmodified = '$lastModified'
            WHERE id = $feedId";

        return $this->dbCommand->queryAffectedRows($sql);
    }
    /*
     *  Used by view controller
     */
    public function feedItems($start, $maximum, $loginId)
    {
        $owner = $loginId ? "m.owner = $loginId AND" : '';
        $sql = 
            "SELECT it.id, itd1.value as title, itd2.value as content, itd3.value as url, it.published
            FROM {$this->tables['item']} it
            JOIN {$this->tables['item_data']} itd1 on it.id = itd1.itemid AND itd1.property = 'title'
            JOIN {$this->tables['item_data']} itd2 on it.id = itd2.itemid AND itd2.property = 'content'
            JOIN {$this->tables['item_data']} itd3 on it.id = itd3.itemid AND itd3.property = 'url'
            JOIN {$this->tables['feed']} fe ON it.feedid = fe.id
            WHERE fe.url IN (
                SELECT DISTINCT data
                FROM {$this->tables['messagedata']} md
                JOIN {$this->tables['message']} m ON m.id = md.id
                WHERE $owner md.name = 'rss_feed'
            )
            ORDER BY it.published
            LIMIT $start, $maximum";

        return $this->dbCommand->queryAll($sql);
    }

    public function totalFeedItems($loginId)
    {
        $owner = $loginId ? "m.owner = $loginId AND" : '';
        $sql = 
            "SELECT COUNT(*) AS t
            FROM {$this->tables['item']} it
            JOIN {$this->tables['feed']} fe ON it.feedid = fe.id
            WHERE fe.url IN (
                SELECT DISTINCT data
                FROM {$this->tables['messagedata']} md
                JOIN {$this->tables['message']} m ON m.id = md.id
                WHERE $owner md.name = 'rss_feed'
            )";

        return $this->dbCommand->queryOne($sql, 't');
    }
}
