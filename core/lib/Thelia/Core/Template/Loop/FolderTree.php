<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\FolderQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Folder tree loop, to get a folder tree from a given folder to a given depth.
 *
 * - folder is the folder id
 * - depth is the maximum depth to go, default unlimited
 * - visible if true or missing, only visible categories will be displayed. If false, all categories (visible or not) are returned.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int         getFolder()
 * @method int         getDepth()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 */
class FolderTree extends BaseI18nLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('folder', null, true),
            Argument::createIntTypeArgument('depth', \PHP_INT_MAX),
            Argument::createBooleanOrBothTypeArgument('visible', true, false),
            Argument::createIntListTypeArgument('exclude', [])
        );
    }

    // changement de rubrique
    protected function buildFolderTree($parent, $visible, $level, $maxLevel, $exclude, &$resultsList): void
    {
        if ($level > $maxLevel) {
            return;
        }

        $search = FolderQuery::create();

        $this->configureI18nProcessing($search, [
            'TITLE',
        ]);

        $search->filterByParent($parent);

        if ($visible != BooleanOrBothType::ANY) {
            $search->filterByVisible($visible);
        }

        if ($exclude != null) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $search->orderByPosition(Criteria::ASC);

        $results = $search->find();

        foreach ($results as $result) {
            $resultsList[] = [
                'ID' => $result->getId(),
                'TITLE' => $result->getVirtualColumn('i18n_TITLE'),
                'PARENT' => $result->getParent(),
                'URL' => $this->getReturnUrl() ? $result->getUrl($this->locale) : null,
                'VISIBLE' => $result->getVisible() ? '1' : '0',
                'LEVEL' => $level,
                'CHILD_COUNT' => $result->countChild(),
            ];

            $this->buildFolderTree($result->getId(), $visible, 1 + $level, $maxLevel, $exclude, $resultsList);
        }
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);
            foreach ($result as $output => $outputValue) {
                $loopResultRow->set($output, $outputValue);
            }

            $this->addOutputFields($loopResultRow, $result);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    public function buildArray()
    {
        $id = $this->getFolder();
        $depth = $this->getDepth();
        $visible = $this->getVisible();
        $exclude = $this->getExclude();

        $resultsList = [];

        $this->buildFolderTree($id, $visible, 0, $depth, $exclude, $resultsList);

        return $resultsList;
    }
}
