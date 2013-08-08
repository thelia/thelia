<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Log\Tlog;

use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 *
 * Category tree loop, to get a category tree from a given category to a given depth.
 *
 * - category is the category id
 * - depth is the maximum depth to go, default unlimited
 * - visible if true or missing, only visible categories will be displayed. If false, all categories (visible or not) are returned.
 *
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class CategoryTree extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('category', null, true),
            Argument::createIntTypeArgument('depth', PHP_INT_MAX),
        	Argument::createBooleanOrBothTypeArgument('visible', true, false),
        	Argument::createIntListTypeArgument('exclude', array())
        );
    }

    // changement de rubrique
    protected function buildCategoryTree($parent, $visible, $level, $max_level, array $exclude, LoopResult &$loopResult) {

    	if ($level > $max_level) return;

     	$search = CategoryQuery::create();

    	$search->filterByParent($parent);

    	if ($visible != BooleanOrBothType::ANY) $search->filterByVisible($visible);

    	$search->filterById($exclude, Criteria::NOT_IN);

    	$search->orderByPosition(Criteria::ASC);

    	$results = $search->find();

    	foreach($results as $result) {

    		$loopResultRow = new LoopResultRow();

           	$loopResultRow
           		->set("ID", $result->getId())
            	->set("TITLE",$result->getTitle())
	            ->set("PARENT", $result->getParent())
	            ->set("URL", $result->getUrl())
	            ->set("VISIBLE", $result->getVisible() ? "1" : "0")
	            ->set("LEVEL", $level)
	        ;

           	$loopResult->addRow($loopResultRow);

           	$this->buildCategoryTree($result->getId(), $visible,  1 + $level, $max_level, $exclude, $loopResult);
    	}
    }

    /**
     * @param $pagination (ignored)
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
		$id = $this->getCategory();
		$depth   = $this->getDepth();
		$visible = $this->getVisible();
		$exclude = $this->getExclude();

		//echo "exclude=".print_r($exclude);

		$loopResult = new LoopResult();

		$this->buildCategoryTree($id, $visible, 0, $depth, $exclude, $loopResult);

        return $loopResult;
    }
}