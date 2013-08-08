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

use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Model\LangQuery;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * Language loop, to get a list of available languages
 *
 * - id is the language id
 * - exclude is a comma separated list of lang IDs that will be excluded from output
 * - default if 1, the loop return only default lang. If 0, return all but the default language
 *
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Lang extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id', null),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanTypeArgument('default_only', false)
        );
     }

    /**
     * @param $pagination (ignored)
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
		$id      = $this->getId();
		$exclude = $this->getExclude();
		$default_only = $this->getDefaultOnly();

        $search = LangQuery::create();

        if (! is_null($id))
			$search->filterById($id);

        if ($default_only)
        	$search->filterByByDefault(true);

        if (! is_null($exclude)) {
        	$search->filterById($exclude, Criteria::NOT_IN);
        }

        $search->orderByPosition(Criteria::ASC);

        $results = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($results as $result) {

        	$loopResultRow = new LoopResultRow();

        	$loopResultRow
	        	->set("ID", $result->getId())
	        	->set("TITLE",$result->getTitle())
	        	->set("CODE", $result->getCode())
	        	->set("LOCALE", $result->getLocale())
	        	->set("URL", $result->getUrl())
	        	->set("IS_DEFAULT", $result->getByDefault())
	        	->set("URL", $result->getUrl())
	        	->set("POSITION", $result->getPosition())

	        	->set("CREATE_DATE", $result->getCreatedAt())
	        	->set("UPDATE_DATE", $result->getUpdatedAt())
	        ;

        	$loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}