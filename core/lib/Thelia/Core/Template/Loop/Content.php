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
use Thelia\Core\Template\Element\StandardI18nFieldsSearchTrait;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\ContentQuery;
use Thelia\Model\Content as ContentModel;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 *
 * Content loop
 *
 *
 * Class Content
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getFolder()
 * @method int[] getFolderDefault()
 * @method bool getCurrent()
 * @method bool getCurrentFolder()
 * @method bool getWithPrevNextInfo()
 * @method int getDepth()
 * @method bool|string getVisible()
 * @method string getTitle()
 * @method string[] getOrder()
 * @method int[] getExclude()
 * @method int[] getExcludeFolder()
 */
class Content extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;

    protected $timestampable = true;
    protected $versionable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('folder'),
            Argument::createIntListTypeArgument('folder_default'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('current_folder'),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            Argument::createIntTypeArgument('depth', 1),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createAnyTypeArgument('title'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        array(
                            'id', 'id_reverse',
                            'alpha', 'alpha-reverse', 'alpha_reverse',
                            'manual', 'manual_reverse',
                            'visible', 'visible_reverse',
                            'random',
                            'given_id',
                            'created', 'created_reverse',
                            'updated', 'updated_reverse',
                            'position', 'position_reverse'
                        )
                    )
                ),
                'alpha'
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createIntListTypeArgument('exclude_folder')
        );
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn()
    {
        return $this->getStandardI18nSearchFields();
    }

    /**
     * @param ContentQuery $search
     * @param string $searchTerm
     * @param array $searchIn
     * @param string $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria)
    {
        $search->_and();

        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria);
    }

    public function buildModelCriteria()
    {
        $search = ContentQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS'));

        $id = $this->getId();

        if (!is_null($id)) {
            $search->filterById($id, Criteria::IN);
        }

        $manualOrderAllowed = false;
        if (null !== $folderDefault = $this->getFolderDefault()) {
            // Select the contents which have $folderDefault as the default folder.
            $search
                ->useContentFolderQuery('FolderSelect')
                    ->filterByDefaultFolder(true)
                    ->filterByFolderId($folderDefault, Criteria::IN)
                ->endUse()
            ;

            // We can only sort by position if we have a single folder ID
            $manualOrderAllowed = (1 == count($folderDefault));
        } elseif (null !== $folderIdList = $this->getFolder()) {
            // Select all content which have one of the required folders as the default one, or an associated one
            $depth = $this->getDepth();

            $allFolderIDs = FolderQuery::getFolderTreeIds($folderIdList, $depth);

            $search
                ->useContentFolderQuery('FolderSelect')
                    ->filterByFolderId($allFolderIDs, Criteria::IN)
                ->endUse()
            ;

            // We can only sort by position if we have a single folder ID, with a depth of 1
            $manualOrderAllowed = (1 == $depth && 1 == count($folderIdList));
        } else {
            $search
                ->leftJoinContentFolder('FolderSelect')
                ->addJoinCondition('FolderSelect', '`FolderSelect`.DEFAULT_FOLDER = 1')
            ;
        }

        $search->withColumn(
            'CAST(CASE WHEN ISNULL(`FolderSelect`.POSITION) THEN \'' . PHP_INT_MAX . '\' ELSE `FolderSelect`.POSITION END AS SIGNED)',
            'position_delegate'
        );
        $search->withColumn('`FolderSelect`.FOLDER_ID', 'default_folder_id');
        $search->withColumn('`FolderSelect`.DEFAULT_FOLDER', 'is_default_folder');

        $current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->getCurrentRequest()->get("content_id"));
        } elseif ($current === false) {
            $search->filterById($this->getCurrentRequest()->get("content_id"), Criteria::NOT_IN);
        }

        $current_folder = $this->getCurrentFolder();

        if ($current_folder === true) {
            $current = ContentQuery::create()->findPk($this->getCurrentRequest()->get("content_id"));

            $search->filterByFolder($current->getFolders(), Criteria::IN);
        } elseif ($current_folder === false) {
            $current = ContentQuery::create()->findPk($this->getCurrentRequest()->get("content_id"));

            $search->filterByFolder($current->getFolders(), Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $title = $this->getTitle();

        if (!is_null($title)) {
            $this->addSearchInI18nColumn($search, 'TITLE', Criteria::LIKE, "%".$title."%");
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $exclude_folder = $this->getExcludeFolder();

        if (!is_null($exclude_folder)) {
            $search->filterByFolder(
                FolderQuery::create()->filterById($exclude_folder, Criteria::IN)->find(),
                Criteria::NOT_IN
            );
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "id":
                    $search->orderById(Criteria::ASC);
                    break;
                case "id_reverse":
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha-reverse":
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual":
                    if (! $manualOrderAllowed) {
                        throw new \InvalidArgumentException('Manual order cannot be set without single folder argument');
                    }
                    $search->addAscendingOrderByColumn('position_delegate');
                    break;
                case "manual_reverse":
                    if (! $manualOrderAllowed) {
                        throw new \InvalidArgumentException('Manual order cannot be set without single folder argument');
                    }
                    $search->addDescendingOrderByColumn('position_delegate');
                    break;
                case "given_id":
                    if (null === $id) {
                        throw new \InvalidArgumentException('Given_id order cannot be set without `id` argument');
                    }
                    foreach ($id as $singleId) {
                        $givenIdMatched = 'given_id_matched_' . $singleId;
                        $search->withColumn(ContentTableMap::ID . "='$singleId'", $givenIdMatched);
                        $search->orderBy($givenIdMatched, Criteria::DESC);
                    }
                    break;
                case "visible":
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case "visible_reverse":
                    $search->orderByVisible(Criteria::DESC);
                    break;
                case "random":
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break(2);
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
                case "position":
                    $search->addAscendingOrderByColumn('position_delegate');
                    break;
                case "position_reverse":
                    $search->addDescendingOrderByColumn('position_delegate');
                    break;
            }
        }

        $search->groupById();

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var ContentModel $content */
        foreach ($loopResult->getResultDataCollection() as $content) {
            $loopResultRow = new LoopResultRow($content);

            if ((bool) $content->getVirtualColumn('is_default_folder')) {
                $defaultFolderId = $content->getVirtualColumn('default_folder_id');
            } else {
                $defaultFolderId = $content->getDefaultFolderId();
            }

            $loopResultRow->set("ID", $content->getId())
                ->set("IS_TRANSLATED", $content->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $content->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $content->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $content->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $content->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("URL", $this->getReturnUrl() ? $content->getUrl($this->locale) : null)
                ->set("META_TITLE", $content->getVirtualColumn('i18n_META_TITLE'))
                ->set("META_DESCRIPTION", $content->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set("META_KEYWORDS", $content->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set("POSITION", $content->getVirtualColumn('position_delegate'))
                ->set("DEFAULT_FOLDER", $defaultFolderId)
                ->set("VISIBLE", $content->getVisible());
            $this->addOutputFields($loopResultRow, $content);

            $this->findNextPrev($loopResultRow, $content, $defaultFolderId);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * @param LoopResultRow $loopResultRow
     * @param ContentModel $content
     * @param int $defaultFolderId
     */
    private function findNextPrev(LoopResultRow $loopResultRow, ContentModel $content, $defaultFolderId)
    {
        if ($this->getWithPrevNextInfo()) {
            $currentPosition = ContentFolderQuery::create()
                ->filterByFolderId($defaultFolderId)
                ->filterByContentId($content->getId())
                ->findOne()->getPosition();

            // Find previous and next content
            $previousQuery = ContentFolderQuery::create()
                ->filterByFolderId($defaultFolderId)
                ->filterByPosition($currentPosition, Criteria::LESS_THAN);

            $nextQuery = ContentFolderQuery::create()
                ->filterByFolderId($defaultFolderId)
                ->filterByPosition($currentPosition, Criteria::GREATER_THAN);

            if (!$this->getBackendContext()) {
                $previousQuery->useContentQuery()
                    ->filterByVisible(true)
                    ->endUse();

                $previousQuery->useContentQuery()
                    ->filterByVisible(true)
                    ->endUse();
            }

            $previous = $previousQuery
                ->orderByPosition(Criteria::DESC)
                ->findOne();

            $next = $nextQuery
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            $loopResultRow
                ->set("HAS_PREVIOUS", $previous != null ? 1 : 0)
                ->set("HAS_NEXT", $next != null ? 1 : 0)
                ->set("PREVIOUS", $previous != null ? $previous->getContentId() : -1)
                ->set("NEXT", $next != null ? $next->getContentId() : -1);
        }
    }
}
