<?php

$pluginsDir = dirname(__DIR__);

return [
    'phpList\plugin\RssFeedPlugin\ControllerFactory' => $pluginsDir . '/RssFeedPlugin/ControllerFactory.php',
    'phpList\plugin\RssFeedPlugin\Controller\Delete' => $pluginsDir . '/RssFeedPlugin/Controller/Delete.php',
    'phpList\plugin\RssFeedPlugin\Controller\Feeds' => $pluginsDir . '/RssFeedPlugin/Controller/Feeds.php',
    'phpList\plugin\RssFeedPlugin\Controller\Get' => $pluginsDir . '/RssFeedPlugin/Controller/Get.php',
    'phpList\plugin\RssFeedPlugin\Controller\View' => $pluginsDir . '/RssFeedPlugin/Controller/View.php',
    'phpList\plugin\RssFeedPlugin\DAO' => $pluginsDir . '/RssFeedPlugin/DAO.php',
    'phpList\plugin\RssFeedPlugin\FeedReader' => $pluginsDir . '/RssFeedPlugin/FeedReader.php',
];
