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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 * Class Folder
 *
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Folder extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;
    protected $versionable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('parent'),
            Argument::createIntTypeArgument('content'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('not_empty', 0),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createAnyTypeArgument('title'),
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

    public function buildModelCriteria()
    {
        $search = FolderQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

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

        $content = $this->getContent();

        if (null !== $content) {
            $obj = ContentQuery::create()->findPk($content);

            if ($obj) {
                $search->filterByContent($obj, Criteria::IN);
            }
        }

        $title = $this->getTitle();

        if (!is_null($title)) {
            $search->where("CASE WHEN NOT ISNULL(`requested_locale_i18n`.ID) THEN `requested_locale_i18n`.`TITLE` ELSE `default_locale_i18n`.`TITLE` END ".Criteria::LIKE." ?", "%".$title."%", \PDO::PARAM_STR);
        }

        $visible = $this->getVisible();

        if ($visible !== BooleanOrBothType::ANY) $search->filterByVisible($visible ? 1 : 0);


        $orders  = $this->getOrder();

        foreach ($orders as $order) {
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

        /* @todo */
        $notEmpty  = $this->getNot_empty();

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $folder) {
            $loopResultRow = new LoopResultRow($folder);

            $loopResultRow
                ->set("ID", $folder->getId())
                ->set("IS_TRANSLATED",$folder->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$this->locale)
                ->set("TITLE",$folder->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $folder->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $folder->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $folder->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("PARENT", $folder->getParent())
                ->set("URL", $folder->getUrl($this->locale))
                ->set("CHILD_COUNT", $folder->countChild())
                ->set("CONTENT_COUNT", $folder->countAllContents())
                ->set("VISIBLE", $folder->getVisible() ? "1" : "0")
                ->set("POSITION", $folder->getPosition())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
