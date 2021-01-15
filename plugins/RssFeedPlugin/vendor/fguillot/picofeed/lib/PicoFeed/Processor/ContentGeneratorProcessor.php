<?php

namespace PicoFeed\Processor;

use PicoFeed\Base;
use PicoFeed\Logging\Logger;
use PicoFeed\Parser\Feed;
use PicoFeed\Parser\Item;

/**
 * Item Content Generator
 *
 * @package PicoFeed\Processor
 * @author  Frederic Guillot
 */
class ContentGeneratorProcessor extends Base implements ItemProcessorInterface
{
    /**
     * List of generators
     *
     * @access protected
     * @var array
     */
    protected $generators = array(
        'youtube',
        'file',
    );

    /**
     * Execute Item Processor
     *
     * @access public
     * @param  Feed $feed
     * @param  Item $item
     * @return bool
     */
    public function execute(Feed $feed, Item $item)
    {
        if ($this->config->getContentGenerating(true)) {
            foreach ($this->generators as $generator) {
                $className = '\PicoFeed\Generator\\'.ucfirst($generator).'ContentGenerator';
                $object = new $className($this->config);

                if ($object->execute($item)) {
                    return true;
                }
            }
        } else {
            Logger::setMessage(get_called_class().': Content generating disabled');
        }

        return false;
    }
}
