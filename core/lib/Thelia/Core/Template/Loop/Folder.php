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

use Thelia\Model\FolderQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * Folder loop, all params available :
 *
 * - id : can be an id (eq : 3) or a "string list" (eg: 3, 4, 5)
 * - parent : categories having this parent id
 * - current : current id is used if you are on a folder page
 * - not_empty : if value is 1, folder and subcategories must have at least 1 product
 * - visible : default 1, if you want folder not visible put 0
 * - order : all value available :  'alpha', 'alpha_reverse', 'manual' (default), 'manual-reverse', 'random'
 * - exclude : all folder id you want to exclude (as for id, an integer or a "string list" can be used)
 *
 * example :
 *
 * <THELIA_cat type="folder" parent="3" limit="4">
 *      #TITLE : #ID
 * </THELIA_cat>
 *
 *
 * Class Folder
 * @package Thelia\Core\Template\Loop
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Folder extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('parent'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('not_empty', 0),
            Argument::createBooleanTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse', 'manual', 'manual-reverse', 'random'))
                ),
                'manual'
            ),
            Argument::createIntListTypeArgument('exclude')
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = FolderQuery::create();

		$id = $this->getId();

        if (!is_null($id)) {
            $search->filterById($id, Criteria::IN);
        }

        $parent = $this->getParent();

        if (!is_null($parent)) {
            $search->filterByParent($parent);
        }


		$current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->request->get("folder_id"));
        } elseif ($current === false) {
            $search->filterById($this->request->get("folder_id"), Criteria::NOT_IN);
        }


         $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $search->filterByVisible($this->getVisible() ? 1 : 0);

        $orders  = $this->getOrder();

        foreach($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn(\Thelia\Model\Map\FolderI18nTableMap::TITLE);
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn(\Thelia\Model\Map\FolderI18nTableMap::TITLE);
                    break;
                case "manual-reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case "manual":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "random":
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break(2);
                    break;
            }
        }

        /**
         * \Criteria::INNER_JOIN in second parameter for joinWithI18n  exclude query without translation.
         *
         * @todo : verify here if we want results for row without translations.
         */

        $search->joinWithI18n(
            $this->request->getSession()->getLocale(),
            (ConfigQuery::read("default_lang_without_translation", 1)) ? Criteria::LEFT_JOIN : Criteria::INNER_JOIN
        );

        $categories = $this->search($search, $pagination);

        $notEmpty  = $this->getNot_empty();

        $loopResult = new LoopResult();

        foreach ($categories as $folder) {

            if ($notEmpty && $folder->countAllProducts() == 0) continue;

            $loopResultRow = new LoopResultRow();

            $loopResultRow
            	->set("ID", $folder->getId())
            	->set("TITLE",$folder->getTitle())
	            ->set("CHAPO", $folder->getChapo())
	            ->set("DESCRIPTION", $folder->getDescription())
	            ->set("POSTSCRIPTUM", $folder->getPostscriptum())
	            ->set("PARENT", $folder->getParent())
	            ->set("URL", $folder->getUrl())
	            ->set("PRODUCT_COUNT", $folder->countChild())
	            ->set("VISIBLE", $folder->getVisible() ? "1" : "0")
	            ->set("POSITION", $folder->getPosition())

	            ->set("CREATE_DATE", $folder->getCreatedAt())
	            ->set("UPDATE_DATE", $folder->getUpdatedAt())
	            ->set("VERSION", $folder->getVersion())
	            ->set("VERSION_DATE", $folder->getVersionCreatedAt())
	            ->set("VERSION_AUTHOR", $folder->getVersionCreatedBy())
			;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}