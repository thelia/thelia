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
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\CategoryQuery;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 *
 * Category path loop, to get the path to a given category.
 *
 * - category is the category id
 * - depth is the maximum depth to go, default unlimited
 * - level is the exact level to return. Example: if level = 2 and the path is c1 -> c2 -> c3 -> c4, the loop will return c2
 * - visible if true or missing, only visible categories will be displayed. If false, all categories (visible or not) are returned.
 *
 * Class CategoryPath
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * {@inheritdoc}
 * @method int getCategory()
 * @method int getDepth()
 * @method bool|string getVisible()
 */
class CategoryPath extends BaseI18nLoop implements ArraySearchLoopInterface
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('category', null, true),
            Argument::createIntTypeArgument('depth', PHP_INT_MAX),
            Argument::createBooleanOrBothTypeArgument('visible', true, false)
        );
    }

    public function buildArray()
    {
        $originalId = $currentId = $this->getCategory();
        $visible = $this->getVisible();
        $depth = $this->getDepth();
    
        $results = array();
    
        $ids = array();
    
        do {
            $search = CategoryQuery::create();
        
            $this->configureI18nProcessing($search, array('TITLE'));
        
            $search->filterById($currentId);
        
            if ($visible !== BooleanOrBothType::ANY) {
                $search->filterByVisible($visible);
            }
        
            $category = $search->findOne();
        
            if ($category != null) {
                $results[] = array(
                    "ID" => $category->getId(),
                    "TITLE" => $category->getVirtualColumn('i18n_TITLE'),
                    "URL" => $category->getUrl($this->locale),
                    "LOCALE" => $this->locale,
                );
            
                $currentId = $category->getParent();
            
                if ($currentId > 0) {
                    // Prevent circular refererences
                    if (in_array($currentId, $ids)) {
                        throw new \LogicException(
                            sprintf(
                                "Circular reference detected in category ID=%d hierarchy (category ID=%d appears more than one times in path)",
                                $originalId,
                                $currentId
                            )
                        );
                    }
                
                    $ids[] = $currentId;
                }
            }
        } while ($category != null && $currentId > 0 && --$depth > 0);
    
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
