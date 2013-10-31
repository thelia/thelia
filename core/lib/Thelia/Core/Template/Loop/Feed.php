<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Tools\DateTimeFormat;

/**
 *
 * @package Thelia\Core\Template\Loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Feed extends BaseLoop
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument('url', null, true),
            Argument::createIntTypeArgument('timeout', 10)
        );
    }

    /**
     *
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $cachedir = THELIA_ROOT . 'cache/feeds';

        if (! is_dir($cachedir)) {
            if (! mkdir($cachedir)) {
                throw new \Exception(sprintf("Failed to create cache directory '%s'", $cachedir));
            }
        }

        $feed = new \SimplePie($this->getUrl(), THELIA_ROOT . 'cache/feeds');

        $feed->init();

        $feed->handle_content_type();

        $feed->set_timeout($this->getTimeout());

        $items = $feed->get_items();

        $limit = min(count($items), $this->getLimit());

        $indexes = array();
        for ($idx = 0; $idx < $limit; $idx++) {
            $indexes[] = $idx;
        }

        $loopResult = new LoopResult($indexes);

        foreach ($indexes as $idx) {

            $item = $items[$idx];

            $link = $item->get_permalink();

            $title = $item->get_title();
            $author = $item->get_author();
            $description = $item->get_description();

            $loopResultRow = new LoopResultRow($loopResult, null, $this->versionable, $this->timestampable, $this->countable);

            $loopResultRow
                ->set("URL"         , $item->get_permalink())
                ->set("TITLE"       , $item->get_title())
                ->set("AUTHOR"      , $item->get_author())
                ->set("DESCRIPTION" , $item->get_description())
                ->set("DATE"        , $item->get_date('U')) // FIXME - date format should be an intl parameter
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
