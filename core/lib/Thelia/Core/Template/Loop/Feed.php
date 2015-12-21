<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

/**
 *
 * @package Thelia\Core\Template\Loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * {@inheritdoc}
 * @method string getUrl()
 * @method int getTimeout()
 */
class Feed extends BaseLoop implements ArraySearchLoopInterface
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument('url', null, true),
            Argument::createIntTypeArgument('timeout', 10)
        );
    }

    public function buildArray()
    {
        $cachedir = THELIA_ROOT . 'cache/feeds';

        if (! is_dir($cachedir)) {
            if (! mkdir($cachedir)) {
                throw new \Exception(sprintf("Failed to create cache directory '%s'", $cachedir));
            }
        }

        $feed = new \SimplePie();
        $feed->set_feed_url($this->getUrl());
        $feed->set_cache_location(THELIA_ROOT . 'cache/feeds');

        $feed->init();

        $feed->handle_content_type();

        $feed->set_timeout($this->getTimeout());

        $items = $feed->get_items();

        return $items;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \SimplePie_Item $item */
        foreach ($loopResult->getResultDataCollection() as $item) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set("URL", $item->get_permalink())
                ->set("TITLE", $item->get_title())
                ->set("AUTHOR", $item->get_author())
                ->set("DESCRIPTION", $item->get_description())
                ->set("DATE", $item->get_date('U'))
            ;
            $this->addOutputFields($loopResultRow, $item);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
