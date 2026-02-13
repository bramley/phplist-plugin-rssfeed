<?php

/**
 * RssFeedPlugin for phplist.
 *
 * This file is a part of RssFeedPlugin.
 *
 * @category  phplist
 *
 * @author    Duncan Cameron
 * @copyright 2026 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

namespace phpList\plugin\RssFeedPlugin;

use HTTP_Request2;
use HTTP_Request2_Observer_Log;
use phpList\plugin\Common\Logger;
use phpList\plugin\Common\StringStream;

class FeedReader
{
    /**
     * Get the feed.
     *
     * @param string $uri
     * @param string $etag
     * @param string $lastModified
     *
     * @return HTTP_Request2_Response
     */
    public static function get($uri, $etag = null, $lastModified = null)
    {
        $request = new HTTP_Request2($uri, HTTP_Request2::METHOD_GET);
        $logOutput = '';
        $request->attach(new HTTP_Request2_Observer_Log(StringStream::fopen($logOutput, 'w')));

        if ($etag) {
            $request->setHeader('If-None-Match', $etag);
        }

        if ($lastModified) {
            $request->setHeader('If-Modified-Since', $lastModified);
        }
        $response = $request->send();
        Logger::instance()->debug("\n" . $logOutput);

        if ($response->getStatus() !== 200 && $response->getStatus() !== 304) {
            throw new \Exception('Feed failed to load, got response code ' . $response->getStatusCode());
        }

        return $response;
    }
}
