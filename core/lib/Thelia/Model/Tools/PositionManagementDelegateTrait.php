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

namespace Thelia\Model\Tools;

use Propel\Runtime\ActiveQuery\PropelQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

trait PositionManagementDelegateTrait
{
    /**
     * Create an instancer of this object query
     */
    private function createQuery()
    {
        return PropelQuery::from(__CLASS__);
    }

    /**
     * Return the database name from this object's table map.
     */
    private function getDatabaseNameFromMap()
    {
        $class = new \ReflectionClass(self::TABLE_MAP);

        return $class->getConstant('DATABASE_NAME');
    }

    /**
     * Implementors may add some search criteria (e.g., parent id) to the queries
     * used to change/get position by overloading this method.
     */
    protected function addCriteriaToPositionQuery($query)
    {
        // Add required criteria here...
    }


    /**
     * Get the position of the next inserted object
     */
    public function getNextPosition(){
        $next = $this->getNext();
        if($next){
            return $next->getRank();
        }
        return 1;
    }

    /**
     * Get the position of the last inserted object
     */
    public function getMaxRank(){
        $maxRank = $this->createQuery();
        $this->addCriteriaToPositionQuery($maxRank);
        $maxRank->orderByPosition(Criteria::DESC);
        $result = $maxRank->findOne();

        if($result)
            return $result->getPosition();

        return null;
    }

    /**
     * Move up a object
     */
    public function movePositionUp(){
        $rank = $this->getRank();
        /*
         * Min rank is 1
         */
        if($rank>1){
            $previous = $this->getPrevious();
            if($previous){
                $this->moveUp();
            }
            else{
                $rank--;
                /*
                 * Allow to reorder with a discontinuous sequence
                 * 1,2,4,5 => 1,3,4,5
                 */
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
            /*
             * Allow to reorder with a discontinuous sequence
             * 1,2,4,5 => 1,3,4,5
             */
            if($maxRank && $rank<$maxRank){
                $rank++;
                $this->setPosition($rank);
                $this->save();
            }
        }
    }

    /**
     * Simply return the database name, from the constant in the MAP class.
     */
    protected function getDatabaseName()
    {
        // Find DATABASE_NAME constant
        $mapClassName = self::TABLE_MAP;

        return $mapClassName::DATABASE_NAME;
    }

    /**
     * Changes object position
     *
     * @param newPosition
     */
    public function changeAbsolutePosition($positionValue){
        if($positionValue>0){
            $search = $this->createQuery();
            $this->addCriteriaToPositionQuery($search);
            $search->filterByPosition($positionValue);
            $resul = $search->findOne();

            if($resul){
                $this->moveToRank($positionValue);
            }
            else{
                $maxRank = $this->getMaxRank();
                /*
                 * Allow to reorder with a discontinuous sequence
                 * 1,2,4,5 => 1,3,4,5
                 */
                if($positionValue<$maxRank){
                    $this->setPosition($positionValue);
                    $this->save();
                }
            }
        }
    }

}
