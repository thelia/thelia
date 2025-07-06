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

use LogicException;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\FolderQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Class FolderPath.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @method int         getFolder()
 * @method bool|string getVisible()
 * @method int         getDepth()
 * @method string[]    getOrder()
 */
class FolderPath extends BaseI18nLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('folder', null, true),
            Argument::createIntTypeArgument('depth', \PHP_INT_MAX),
            Argument::createBooleanOrBothTypeArgument('visible', true, false)
        );
    }

    public function buildArray(): array
    {
        $originalId = $this->getFolder();
        $currentId = $originalId;
        $visible = $this->getVisible();
        $depth = $this->getDepth();

        $results = [];

        $ids = [];

        do {
            $search = FolderQuery::create();

            $this->configureI18nProcessing($search, ['TITLE']);

            $search->filterById($currentId);

            if ($visible !== BooleanOrBothType::ANY) {
                $search->filterByVisible($visible);
            }

            $folder = $search->findOne();

            if ($folder != null) {
                $results[] = [
                    'ID' => $folder->getId(),
                    'TITLE' => $folder->getVirtualColumn('i18n_TITLE'),
                    'URL' => $folder->getUrl($this->locale),
                    'LOCALE' => $this->locale,
                ];

                $currentId = $folder->getParent();

                if ($currentId > 0) {
                    // Prevent circular refererences
                    if (\in_array($currentId, $ids)) {
                        throw new LogicException(
                            sprintf(
                                'Circular reference detected in folder ID=%d hierarchy (folder ID=%d appears more than one times in path)',
                                $originalId,
                                $currentId
                            )
                        );
                    }

                    $ids[] = $currentId;
                }
            }
        } while ($folder != null && $currentId > 0 && --$depth > 0);

        // Reverse list and build the final result
        return array_reverse($results);
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
}
