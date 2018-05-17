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

use Psr\Container\ContainerInterface;

/*
 * This file provides the dependencies for a dependency injection container.
 */

return [
    'phpList\plugin\RssFeedPlugin\Controller\Feeds' => function (ContainerInterface $container) {
        return new Controller\Feeds(
            $container->get('phpList\plugin\RssFeedPlugin\DAO')
        );
    },
    'phpList\plugin\RssFeedPlugin\Controller\Get' => function (ContainerInterface $container) {
        return new Controller\Get(
            $container->get('phpList\plugin\RssFeedPlugin\DAO'),
            $container->get('phpList\plugin\Common\Context')
        );
    },
    'phpList\plugin\RssFeedPlugin\Controller\View' => function (ContainerInterface $container) {
        return new Controller\View(
            $container->get('phpList\plugin\RssFeedPlugin\DAO')
        );
    },
    'phpList\plugin\RssFeedPlugin\Controller\Delete' => function (ContainerInterface $container) {
        return new Controller\Delete(
            $container->get('phpList\plugin\RssFeedPlugin\DAO'),
            $container->get('phpList\plugin\Common\Context')
        );
    },
    'phpList\plugin\RssFeedPlugin\Controller\Twitterfeed' => function (ContainerInterface $container) {
        return new Controller\Twitterfeed(
            $container->get('phpList\plugin\RssFeedPlugin\TwitterParser')
        );
    },
    'phpList\plugin\RssFeedPlugin\TwitterParser' => function (ContainerInterface $container) {
        return new TwitterParser();
    },
    'phpList\plugin\RssFeedPlugin\DAO' => function (ContainerInterface $container) {
        return new DAO(
            $container->get('phpList\plugin\Common\DB'),
            getConfig('rss_maximum')
        );
    },
];
