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

use Thelia\Type\TypeCollection;
use Thelia\Type;

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
            new Argument(
                'url',
                new TypeCollection(new Type\AnyType())
            ),
        	new Argument(
        		'timeout',
        		new TypeCollection(
        			new Type\IntType()
        		),
        		10
        	)
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

        $feed = new \SimplePie($this->getArgValue('url'), THELIA_ROOT . 'cache/feeds');

        $feed->init();

        $feed->handle_content_type();

        $feed->set_timeout($this->getArgValue('timeout'));

        $items = $feed->get_items();

        $limit = min(count($items), $this->getArgValue('limit'));

        $loopResult = new LoopResult();

        for($idx = 0; $idx < $limit; $idx++) {

        	$item = $items[$idx];

        	$link = $item->get_permalink();

        	$title = $item->get_title();
        	$author = $item->get_author();
        	$description = $item->get_description();

        	$date = $item->get_date('d/m/Y');

        	$loopResultRow = new LoopResultRow();

        	$loopResultRow->set("URL", $item->get_permalink());
        	$loopResultRow->set("TITLE", $item->get_title());
        	$loopResultRow->set("AUTHOR", $item->get_author());
        	$loopResultRow->set("DESCRIPTION", $item->get_description());
        	$loopResultRow->set("DATE", $item->get_date('d/m/Y')); // FIXME - date format should be an intl parameter

        	$loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}