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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
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
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int getParent()
 * @method int getContent()
 * @method bool getCurrent()
 * @method bool|string getVisible()
 * @method int[] getExclude()
 * @method string getTitle()
 * @method string[] getOrder()
 */
class Folder extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
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
                    new Type\EnumListType([
                        'alpha',
                        'alpha_reverse',
                        'manual',
                        'manual_reverse',
                        'random',
                        'created',
                        'created_reverse',
                        'updated',
                        'updated_reverse'
                        ])
                ),
                'manual'
            ),
            Argument::createIntListTypeArgument('exclude')
        );
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn()
    {
        return [
            "title"
        ];
    }

    /**
     * @param FolderQuery $search
     * @param string $searchTerm
     * @param string $searchIn
     * @param string $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria)
    {
        $search->_and();

        $this->addTitleSearchWhereClause($search, $searchCriteria, $searchTerm);
    }

    /**
     * @param FolderQuery $search
     * @param string $criteria
     * @param string $value
     */
    protected function addTitleSearchWhereClause($search, $criteria, $value)
    {
        $search->where(
            "CASE WHEN NOT ISNULL(`requested_locale_i18n`.ID) THEN `requested_locale_i18n`.`TITLE` ELSE `default_locale_i18n`.`TITLE` END ".$criteria." ?",
            $value,
            \PDO::PARAM_STR
        );
    }

    public function buildModelCriteria()
    {
        $search = FolderQuery::create();

        /* manage translations */
        $this->configureI18nProcessing(
            $search,
            [ 'TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS']
        );

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
            $this->addTitleSearchWhereClause($search, Criteria::LIKE, '%'.$title.'%');
        }

        $visible = $this->getVisible();

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

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
                case "created":
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case "created_reverse":
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case "updated":
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case "updated_reverse":
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Folder $folder */
        foreach ($loopResult->getResultDataCollection() as $folder) {
            $loopResultRow = new LoopResultRow($folder);

            $loopResultRow
                ->set("ID", $folder->getId())
                ->set("IS_TRANSLATED", $folder->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $folder->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $folder->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $folder->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $folder->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("PARENT", $folder->getParent())
                ->set("ROOT", $folder->getRoot($folder->getId()))
                ->set("URL", $folder->getUrl($this->locale))
                ->set("META_TITLE", $folder->getVirtualColumn('i18n_META_TITLE'))
                ->set("META_DESCRIPTION", $folder->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set("META_KEYWORDS", $folder->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set("CHILD_COUNT", $folder->countChild())
                ->set("CONTENT_COUNT", $folder->countAllContents())
                ->set("VISIBLE", $folder->getVisible() ? "1" : "0")
                ->set("POSITION", $folder->getPosition())
            ;
            $this->addOutputFields($loopResultRow, $folder);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
