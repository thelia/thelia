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
                            'alpha',
                            'alpha-reverse',
                            'manual',
                            'manual_reverse',
                            'random',
                            'given_id',
                            'created',
                            'created_reverse',
                            'updated',
                            'updated_reverse',
                            'position',
                            'position_reverse'
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
        return [
            "title"
        ];
    }

    /**
     * @param ContentQuery $search
     * @param string $searchTerm
     * @param string $searchIn
     * @param string $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria)
    {
        $search->_and();

        $search->where("CASE WHEN NOT ISNULL(`requested_locale_i18n`.ID) THEN `requested_locale_i18n`.`TITLE` ELSE `default_locale_i18n`.`TITLE` END ".$searchCriteria." ?", $searchTerm, \PDO::PARAM_STR);
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

        $folder = $this->getFolder();
        $folderDefault = $this->getFolderDefault();

        if (!is_null($folder) || !is_null($folderDefault)) {
            $foldersIds = array();
            if (!is_array($folder)) {
                $folder = array();
            }
            if (!is_array($folderDefault)) {
                $folderDefault = array();
            }

            $foldersIds = array_merge($foldersIds, $folder, $folderDefault);
            $folders = FolderQuery::create()->filterById($foldersIds, Criteria::IN)->find();

            $depth = $this->getDepth();

            if (null !== $depth) {
                foreach (FolderQuery::findAllChild($folder, $depth) as $subFolder) {
                    $folders->prepend($subFolder);
                }
            }

            $search->filterByFolder(
                $folders,
                Criteria::IN
            );
        }

        $current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->request->get("content_id"));
        } elseif ($current === false) {
            $search->filterById($this->request->get("content_id"), Criteria::NOT_IN);
        }

        $current_folder = $this->getCurrentFolder();

        if ($current_folder === true) {
            $current = ContentQuery::create()->findPk($this->request->get("content_id"));

            $search->filterByFolder($current->getFolders(), Criteria::IN);
        } elseif ($current_folder === false) {
            $current = ContentQuery::create()->findPk($this->request->get("content_id"));

            $search->filterByFolder($current->getFolders(), Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $title = $this->getTitle();

        if (!is_null($title)) {
            $search->where("CASE WHEN NOT ISNULL(`requested_locale_i18n`.ID) THEN `requested_locale_i18n`.`TITLE` ELSE `default_locale_i18n`.`TITLE` END ".Criteria::LIKE." ?", "%".$title."%", \PDO::PARAM_STR);
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha-reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual":
                    if (null === $foldersIds || count($foldersIds) != 1) {
                        throw new \InvalidArgumentException('Manual order cannot be set without single folder argument');
                    }
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "manual_reverse":
                    if (null === $foldersIds || count($foldersIds) != 1) {
                        throw new \InvalidArgumentException('Manual order cannot be set without single folder argument');
                    }
                    $search->orderByPosition(Criteria::DESC);
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
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "position_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
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

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var ContentModel $content */
        foreach ($loopResult->getResultDataCollection() as $content) {
            $loopResultRow = new LoopResultRow($content);
            $defaultFolderId = $content->getDefaultFolderId();
            $loopResultRow->set("ID", $content->getId())
                ->set("IS_TRANSLATED", $content->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $content->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $content->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $content->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $content->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("URL", $content->getUrl($this->locale))
                ->set("META_TITLE", $content->getVirtualColumn('i18n_META_TITLE'))
                ->set("META_DESCRIPTION", $content->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set("META_KEYWORDS", $content->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set("POSITION", $content->getPosition())
                ->set("DEFAULT_FOLDER", $defaultFolderId)
                ->set("VISIBLE", $content->getVisible())
            ;
            $this->addOutputFields($loopResultRow, $content);

            $loopResult->addRow($this->findNextPrev($loopResultRow, $content, $defaultFolderId));
        }

        return $loopResult;
    }

    /**
     * @param  LoopResultRow         $loopResultRow
     * @param  \Thelia\Model\Content $content
     * @param $defaultFolderId
     * @return LoopResultRow
     */
    private function findNextPrev(LoopResultRow $loopResultRow, \Thelia\Model\Content $content, $defaultFolderId)
    {
        $isBackendContext = $this->getBackendContext();

        if ($isBackendContext || $this->getWithPrevNextInfo()) {
            // Find previous and next category
            $previousQuery = ContentQuery::create()
                ->joinContentFolder()
                ->where('ContentFolder.folder_id = ?', $defaultFolderId)
                ->filterByPosition($content->getPosition(), Criteria::LESS_THAN)
            ;

            if (! $isBackendContext) {
                $previousQuery->filterByVisible(true);
            }

            $previous = $previousQuery
                ->orderByPosition(Criteria::DESC)
                ->findOne()
            ;

            $nextQuery = ContentQuery::create()
                ->joinContentFolder()
                ->where('ContentFolder.folder_id = ?', $defaultFolderId)
                ->filterByPosition($content->getPosition(), Criteria::GREATER_THAN)
            ;

            if (! $isBackendContext) {
                $nextQuery->filterByVisible(true);
            }

            $next = $nextQuery
                ->orderByPosition(Criteria::ASC)
                ->findOne()
            ;

            $loopResultRow
                ->set("HAS_PREVIOUS", $previous != null ? 1 : 0)
                ->set("HAS_NEXT", $next != null ? 1 : 0)
                ->set("PREVIOUS", $previous != null ? $previous->getId() : -1)
                ->set("NEXT", $next != null ? $next->getId() : -1)
            ;
        }

        return $loopResultRow;
    }
}
