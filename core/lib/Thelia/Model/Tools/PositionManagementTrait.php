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

namespace Thelia\Model\Tools;

use Propel\Runtime\ActiveQuery\PropelQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

trait PositionManagementTrait {

    /**
     * Create an instancer of this object query
     */
    private function createQuery() {
        return PropelQuery::from(__CLASS__);
    }

    /**
     * Return the database name from this object's table map.
     */
    private function getDatabaseNameFromMap() {
        $class = new \ReflectionClass(self::TABLE_MAP);

        return $class->getConstant('DATABASE_NAME');
    }

    /**
     * Implementors may add some search criteria (e.g., parent id) to the queries
     * used to change/get position by overloading this method.
     */
    protected function addCriteriaToPositionQuery($query) {
        // Add required criteria here...
    }

    /**
     * Get the position of the next inserted object
     */
    public function getNextPosition() {

         $query = $this->createQuery()
            ->orderByPosition(Criteria::DESC)
            ->limit(1);

         $this->addCriteriaToPositionQuery($query);

         $last = $query->findOne();

         return $last != null ? $last->getPosition() + 1 : 1;
    }

    /**
     * Move up a object
     */
    public function movePositionUp() {
        $this->movePositionUpOrDown(true);
    }

    /**
     * Move down a object
     */
    public function movePositionDown() {
        $this->movePositionUpOrDown(false);
    }

    /**
     * Move up or down a object
     *
     * @param the exchange mode: go up (POSITION_UP) or go down (POSITION_DOWN)
     */
    protected function movePositionUpOrDown($up = true)
    {
        // The current position of the object
        $my_position = $this->getPosition();

        // Find object to exchange position with
        $search = $this->createQuery();

        $this->addCriteriaToPositionQuery($search);

        // Up or down ?
        if ($up === true) {
            // Find the object immediately before me
            $search->filterByPosition(array('max' => $my_position-1))->orderByPosition(Criteria::DESC);
        }
        else {
            // Find the object immediately after me
            $search->filterByPosition(array('min' => $my_position+1))->orderByPosition(Criteria::ASC);
        }

        $result = $search->findOne();

        // If we found the proper object, exchange their positions
        if ($result) {

            $cnx = Propel::getWriteConnection($this->getDatabaseName());

            $cnx->beginTransaction();

            try {
                $this
                    ->setPosition($result->getPosition())
                    ->save($cnx)
                ;

                $result->setDispatcher($this->getDispatcher())->setPosition($my_position)->save($cnx);

                $cnx->commit();
            }
            catch (Exception $e) {
                $cnx->rollback();
            }
        }
    }

    /**
     * Simply return the database name, from the constant in the MAP class.
     */
    protected function getDatabaseName() {
        // Find DATABASE_NAME constant
        $mapClassName = self::TABLE_MAP;

        return $mapClassName::DATABASE_NAME;
    }

    /**
     * Changes object position
     *
     * @param newPosition
     */
    public function changeAbsolutePosition($newPosition)
    {
        // The current position
        $current_position = $this->getPosition();

        if ($newPosition != null && $newPosition > 0 && $newPosition != $current_position) {

             // Find categories to offset
            $search = $this->createQuery();

            $this->addCriteriaToPositionQuery($search);

            if ($newPosition > $current_position) {
                // The new position is after the current position -> we will offset + 1 all categories located between us and the new position
                $search->filterByPosition(array('min' => 1+$current_position, 'max' => $newPosition));

                $delta = -1;
            } else {
                // The new position is brefore the current position -> we will offset - 1 all categories located between us and the new position
                $search->filterByPosition(array('min' => $newPosition, 'max' => $current_position - 1));

                $delta = 1;
            }

            $results = $search->find();

            $cnx = Propel::getWriteConnection($this->getDatabaseName());

            $cnx->beginTransaction();

            try {
                foreach ($results as $result) {

                    $objNewPosition = $result->getPosition() + $delta;

                    $result->setDispatcher($this->getDispatcher())->setPosition($objNewPosition)->save($cnx);
                }

                $this
                    ->setPosition($newPosition)
                    ->save($cnx)
                ;

                $cnx->commit();
            }
            catch (Exception $e) {
                $cnx->rollback();
            }
        }
    }

    protected function reorderBeforeDelete($fields = array())
    {
        // Find DATABASE_NAME constant
        $mapClassName = self::TABLE_MAP;

        $data = array();
        $whereCriteria = array();

        foreach($fields as $field => $value) {
            $whereCriteria[] = $field . '=:' . $field;
            $data[':' . $field] = $value;
        }

        $data[':position'] = $this->getPosition();

        $sql = sprintf("UPDATE `%s` SET position=(position-1) WHERE " . (count($whereCriteria)>0 ? implode(" AND ", $whereCriteria) : '1') . " AND position>:position", $mapClassName::TABLE_NAME);

        $con = Propel::getConnection($mapClassName::DATABASE_NAME);
        $statement = $con->prepare($sql);

        $statement->execute($data);
    }
}