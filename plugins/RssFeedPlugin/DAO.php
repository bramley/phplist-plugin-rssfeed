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

namespace phpList\plugin\RssFeedPlugin;

use phpList\plugin\Common\DAO as CommonDAO;

/**
 * Data access class.
 */
class DAO extends CommonDAO
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
            (url, etag, lastmodified)
            SELECT '$url', '', ''
            FROM (SELECT 1) AS a
            WHERE NOT EXISTS(
                SELECT url
                FROM {$this->tables['feed']}
                WHERE url = '$url'
                LIMIT 1
            )";

        return $this->dbCommand->queryInsertId($sql);
    }

    public function feedExists($url)
    {
        $url = sql_escape($url);
        $sql =
            "SELECT id
            FROM {$this->tables['feed']}
            WHERE url = '$url'
            LIMIT 1";

        return $this->dbCommand->queryOne($sql);
    }

    public function addItem($uid, $published, $feedId)
    {
        $uid = sql_escape($uid);

        $sql =
            "INSERT INTO {$this->tables['item']}
            (uid, published, feedid, added)
            SELECT '$uid', CONVERT_TZ('$published', '+00:00', @@session.time_zone), $feedId, current_timestamp
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

    /**
     * Builds an array of items for a message's feed in ascending order of
     * published date.
     *
     * @param int  $mid        message id
     * @param int  $limit      maximum number of items to return
     * @param bool $useEmbargo whether to select only items published within
     *                         the repeat period
     *
     * @return array 0-indexed array of items
     */
    public function messageFeedItems($mid, $limit, $useEmbargo = true)
    {
        $published = $useEmbargo
            ? 'AND it.published >= m.embargo - INTERVAL m.repeatinterval MINUTE AND it.published < m.embargo'
            : '';
        $subquery =
            "SELECT id
            FROM (
                SELECT DISTINCT it.id, it.published
                FROM {$this->tables['message']} m
                JOIN {$this->tables['messagedata']} md ON m.id = md.id AND md.name = 'rss_feed'
                JOIN {$this->tables['feed']} fe ON fe.url = md.data
                JOIN {$this->tables['item']} it ON fe.id = it.feedid
                WHERE m.id = $mid
                $published
                ORDER BY it.published DESC
                LIMIT $limit
            ) AS t";

        $sql = "SELECT it.id, it.published, itd.property, itd.value
                FROM {$this->tables['item']} it
                JOIN {$this->tables['item_data']} itd on itd.itemid = it.id
                WHERE it.id IN ($subquery)
                ORDER BY it.published ASC, it.id ASC";

        $rows = $this->dbCommand->queryAll($sql);
        $result = array();

        foreach ($rows as $row) {
            $result[$row['id']][$row['property']] = $row['value'];
            $result[$row['id']]['published'] = $row['published'];
        }

        return array_values($result);
    }

    /**
     * Return all feeds that have been used in campaigns.
     * Also indicate whether a feed is being used in an active campaign.
     *
     * @return iterator
     */
    public function feeds()
    {
        $sql =
            "SELECT f.*, COUNT(m.id) > 0 AS active
            FROM {$this->tables['feed']} f
            LEFT JOIN {$this->tables['messagedata']} md ON f.url = md.data AND md.name = 'rss_feed'
            LEFT JOIN {$this->tables['message']} m ON md.id = m.id AND m.status NOT IN ('sent', 'prepared', 'suspended')
            GROUP BY f.id
            ";

        return $this->dbCommand->queryAll($sql);
    }

    /**
     * Return only the active feeds.
     *
     * @return array
     */
    public function activeFeeds()
    {
        return array_filter(
            iterator_to_array($this->feeds()),
            function ($current) {
                return $current['active'];
            }
        );
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

    /* Returns all items for a specific feed.
     *
     * @return iterator
     */
    public function itemsForFeed($feedId, $start = null, $maximum = null, $asc = true)
    {
        $order = $asc ? 'ASC' : 'DESC';
        $limit = $start === null ? '' : "LIMIT $start, $maximum";
        $sql =
            "SELECT it.id, itd1.value as title, itd2.value as content, itd3.value as url, it.published
            FROM {$this->tables['item']} it
            JOIN {$this->tables['item_data']} itd1 on it.id = itd1.itemid AND itd1.property = 'title'
            JOIN {$this->tables['item_data']} itd2 on it.id = itd2.itemid AND itd2.property = 'content'
            JOIN {$this->tables['item_data']} itd3 on it.id = itd3.itemid AND itd3.property = 'url'
            JOIN {$this->tables['feed']} fe ON it.feedid = fe.id
            WHERE fe.id = $feedId
            ORDER BY it.published $order
            $limit";

        return $this->dbCommand->queryAll($sql);
    }

    /* Returns the number of items for a specific feed.
     *
     * @return int
     */
    public function totalItemsForFeed($feedId)
    {
        $sql =
            "SELECT COUNT(*)
            FROM {$this->tables['item']} it
            JOIN {$this->tables['feed']} fe ON it.feedid = fe.id
            WHERE fe.id = $feedId";

        return $this->dbCommand->queryOne($sql);
    }

    /*
     * Used to avoid sending RSS messages that do not have any recent content
     */
    public function readyRssMessages()
    {
        $sql =
            "SELECT m.id
            FROM {$this->tables['message']} m
            JOIN {$this->tables['messagedata']} md ON m.id = md.id AND md.name = 'rss_feed' AND md.data != ''
            WHERE m.status IN ('submitted')
            AND m.embargo <= current_timestamp";

        return $this->dbCommand->queryColumn($sql, 'id');
    }

    public function reEmbargoMessage($id)
    {
        $sql =
            "UPDATE {$this->tables['message']}
            SET embargo = embargo +
                INTERVAL (FLOOR(TIMESTAMPDIFF(MINUTE, embargo, NOW()) / repeatinterval) + 1) * repeatinterval MINUTE
            WHERE id = $id AND now() < repeatuntil";

        if (($count = $this->dbCommand->queryAffectedRows($sql)) > 0) {
            $sql =
                "SELECT embargo
                FROM {$this->tables['message']}
                WHERE id = $id";
            $embargo = $this->dbCommand->queryOne($sql, 'embargo');

            list($e['year'], $e['month'], $e['day'], $e['hour'], $e['minute']) =
                sscanf($embargo, '%04d-%02d-%02d %02d:%02d');
            setMessageData($id, 'embargo', $e);
        }

        return $count;
    }

    public function setMessageSent($id)
    {
        $sql =
            "UPDATE {$this->tables['message']}
            SET status = 'sent'
            WHERE id = $id";

        return $this->dbCommand->queryAffectedRows($sql);
    }

    public function setMessage($id, $message)
    {
        $escaped = sql_escape($message);
        $sql =
            "UPDATE {$this->tables['message']}
            SET message = '$escaped'
            WHERE id = $id";
        $affected = $this->dbCommand->queryAffectedRows($sql);
        setMessageData($id, 'message', $message);
    }

    public function setSubject($id, $subject)
    {
        $escaped = sql_escape($subject);
        $sql =
            "UPDATE {$this->tables['message']}
            SET subject = '$escaped'
            WHERE id = $id";
        $affected = $this->dbCommand->queryAffectedRows($sql);
        setMessageData($id, 'subject', $subject);
        setMessageData($id, 'campaigntitle', $subject);
    }

    public function templateBody($id)
    {
        $sql =
            "SELECT template
            FROM {$this->tables['template']}
            WHERE id = $id";

        return stripslashes($this->dbCommand->queryOne($sql));
    }
}
