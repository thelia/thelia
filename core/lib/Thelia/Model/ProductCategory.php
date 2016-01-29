<?php

namespace Thelia\Model;

use Thelia\Model\Base\ProductCategory as BaseProductCategory;
use Thelia\Model\Map\ProductCategoryTableMap;

/**
 * Manages products position
 *
 * Overload the methods "getNextPosition", "changeAbsolutePosition", "movePositionUp" and "movePositionDown".
 * Use the position's propel behavior's methods.
 * Prevent error on discontinous ranking.
 */
class ProductCategory extends BaseProductCategory
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Get the position of the last inserted object
     */
    public function getMaxRank(){
        $maxRank = ProductCategoryQuery::create()
            ->select(array('maxRank'))
            ->filterByCategoryId($this->getCategoryId())
            ->withColumn('MAX('.ProductCategoryTableMap::POSITION.')', 'maxRank')
            ->findOne();

        return $maxRank;
    }

    /**
     * Get the position of the next inserted object
     */
    public function getNextPosition(){
        $next = $this->getNext();
        if($next){
            return $next->getRank();
        }
        return null;
    }

    /**
     * Changes object position
     *
     * @param newPosition
     */
    public function changeAbsolutePosition($positionValue){
        $object = ProductCategoryQuery::create()
            ->filterByCategoryId($this->getCategoryId())
            ->filterByPosition($positionValue)
            ->findOne();
        if($object){
            $this->moveToRank($positionValue);
        }
        else{
            $maxRank = $this->getMaxRank();
            if($positionValue<$maxRank){
                $this->setPosition($positionValue);
                $this->save();
            }
        }
    }

    /**
     * Move up a object
     */
    public function movePositionUp(){
        $rank = $this->getRank();
        if($rank>1){
            $previous = $this->getPrevious();
            if($previous){
                $this->moveUp();
            }
            else{
                $rank--;
                $this->setPosition($rank);
                $this->save();
            }
        }
    }

    /**
     * Move down a object
     */
    public function movePositionDown(){
        $next = $this->getNext();
        if($next){
            $this->moveDown();
        }
        else{
            $rank = $this->getRank();
            $maxRank = $this->getMaxRank();
            if($rank<$maxRank){
                $rank++;
                $this->setPosition($rank);
                $this->save();
            }
        }
    }
}
