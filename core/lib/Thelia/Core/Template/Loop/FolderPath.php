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

use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\FolderQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Class FolderPath
 * @package Thelia\Core\Template\Loop
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * {@inheritdoc}
 * @method int getFolder()
 * @method bool|string getVisible()
 * @method int getDepth()
 * @method string[] getOrder()
 */
class FolderPath extends BaseI18nLoop implements ArraySearchLoopInterface
{
    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('folder', null, true),
            Argument::createIntTypeArgument('depth', PHP_INT_MAX),
            Argument::createBooleanOrBothTypeArgument('visible', true, false)
        );
    }

    public function buildArray()
    {
        $originalId = $currentId = $this->getFolder();
        $visible = $this->getVisible();
        $depth = $this->getDepth();
    
        $results = array();

        $ids = array();

        do {
            $search = FolderQuery::create();
    
            $this->configureI18nProcessing($search, array('TITLE'));
    
            $search->filterById($currentId);
            
            if ($visible !== BooleanOrBothType::ANY) {
                $search->filterByVisible($visible);
            }
    
            $folder = $search->findOne();

            if ($folder != null) {
                $results[] = array(
                    "ID" => $folder->getId(),
                    "TITLE" => $folder->getVirtualColumn('i18n_TITLE'),
                    "URL" => $folder->getUrl($this->locale),
                    "LOCALE" => $this->locale,
                );
    
                $currentId = $folder->getParent();

                if ($currentId > 0) {
                    // Prevent circular refererences
                    if (in_array($currentId, $ids)) {
                        throw new \LogicException(
                            sprintf(
                                "Circular reference detected in folder ID=%d hierarchy (folder ID=%d appears more than one times in path)",
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

    public function parseResults(LoopResult $loopResult)
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
