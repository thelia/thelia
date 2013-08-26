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

use Thelia\Model\Tools\ModelCriteriaTools;

use Thelia\Model\FolderQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 * Class Folder
 *
 * @package Thelia\Core\Template\Loop
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
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createIntTypeArgument('lang'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse', 'manual', 'manual_reverse', 'random'))
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

        /* manage translations */
        ModelCriteriaTools::getI18n($search, ConfigQuery::read("default_lang_without_translation", 1), $this->request->getSession()->getLocale());

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

        $visible = $this->getVisible();

        if ($visible != BooleanOrBothType::ANY) $search->filterByVisible($visible ? 1 : 0);

        $orders  = $this->getOrder();

        foreach($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual_reverse":
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

        /* perform search */
        $folders = $this->search($search, $pagination);

        /* @todo */
        $notEmpty  = $this->getNot_empty();

        $loopResult = new LoopResult();

        foreach ($folders as $folder) {

            /*
             * no cause pagination lost :
             * if ($notEmpty && $folder->countAllProducts() == 0) continue;
             */

            $loopResultRow = new LoopResultRow();

            $loopResultRow
            	->set("ID", $folder->getId())
                ->set("TITLE",$folder->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $folder->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $folder->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $folder->getVirtualColumn('i18n_POSTSCRIPTUM'))
	            ->set("PARENT", $folder->getParent())
	            ->set("CONTENT_COUNT", $folder->countChild())
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