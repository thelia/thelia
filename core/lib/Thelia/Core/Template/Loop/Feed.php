<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method string getUrl()
 * @method int    getTimeout()
 */
class Feed extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument('url', null, true),
            Argument::createIntTypeArgument('timeout', 10),
        );
    }

    public function buildArray(): array
    {
        /** @var AdapterInterface $cacheAdapter */
        $cacheAdapter = $this->container->get('thelia.cache');

        $cacheItem = $cacheAdapter->getItem('feed_' . md5($this->getUrl()));

        if (!$cacheItem->isHit()) {
            $feed = new \SimplePie();
            $feed->set_feed_url($this->getUrl());

            $feed->init();

            $feed->handle_content_type();

            $cacheItem->expiresAfter($this->getTimeout() * 60);
            $cacheItem->set($feed->get_items());
            $cacheAdapter->save($cacheItem);
        }

        /** @var array $itemAsArray */
        return $cacheItem->get();
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \SimplePie_Item $item */
        foreach ($loopResult->getResultDataCollection() as $item) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set('URL', $item->get_permalink())
                ->set('TITLE', $item->get_title())
                ->set('AUTHOR', $item->get_author())
                ->set('DESCRIPTION', $item->get_description())
                ->set('DATE', $item->get_date('U'));
            $this->addOutputFields($loopResultRow, $item);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
