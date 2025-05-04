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

use phpList\plugin\Common\ControllerFactoryBase;

/**
 * This class is a concrete implementation of ControllerFactoryBase.
 */
class ControllerFactory extends ControllerFactoryBase
{
    /**
     * Custom implementation to create a controller using plugin and page.
     *
     * @param string $pi     the plugin
     * @param array  $params further parameters from the URL
     *
     * @return phpList\plugin\Common\Controller
     */
    public function createController($pi, array $params)
    {
        $depends = include __DIR__ . '/depends.php';
        $container = new \phpList\plugin\Common\Container($depends);
        $page = isset($params['page']) ? $params['page'] : $params['p'];
        $class = 'phpList\plugin\\' . $pi . '\\Controller\\' . ucfirst($page);

        return $container->get($class);
    }
}
