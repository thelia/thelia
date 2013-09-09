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

namespace Thelia\Action;

use Thelia\Core\Event\BaseChangePositionEvent;

trait PositionManagementTrait {

    const POSITION_UP
    /**
     * Changes object position, selecting absolute ou relative change.
     *
     * @param BaseChangePositionEvent $event
     */
    public function changePosition(BaseChangePositionEvent $event)
    {
        if ($event->getMode() == BaseChangePositionEvent::POSITION_ABSOLUTE)
            return $this->changeAbsolutePosition($event);
        else
            return $this->exchangePosition($event);
    }

    /**
     * Move up or down a object
     *
     * @param BaseChangePositionEvent $event
     */
    protected function exchangePosition(BaseChangePositionEvent $event)
    {
       $object = CategoryQuery::create()->findPk($event->getCategoryId());

        if ($object !== null) {

            // The current position of the object
            $my_position = $object->getPosition();

            // Find object to exchange position with
            $search = CategoryQuery::create()
                ->filterByParent($object->getParent());

            // Up or down ?
            if ($event->getMode() == BaseChangePositionEvent::POSITION_UP) {
                // Find the object immediately before me
                $search->filterByPosition(array('max' => $my_position-1))->orderByPosition(Criteria::DESC);
            } elseif ($event->getMode() == BaseChangePositionEvent::POSITION_DOWN) {
                // Find the object immediately after me
                $search->filterByPosition(array('min' => $my_position+1))->orderByPosition(Criteria::ASC);
            } else

                return;

            $result = $search->findOne();

            // If we found the proper object, exchange their positions
            if ($result) {

                $cnx = Propel::getWriteConnection(CategoryTableMap::DATABASE_NAME);

                $cnx->beginTransaction();

                try {
                    $object
                        ->setDispatcher($this->getDispatcher())
                        ->setPosition($result->getPosition())
                        ->save()
                    ;

                    $result->setPosition($my_position)->save();

                    $cnx->commit();
                } catch (Exception $e) {
                    $cnx->rollback();
                }
            }
        }
    }

    /**
     * Changes object position
     *
     * @param BaseChangePositionEvent $event
     */
    protected function changeAbsolutePosition(BaseChangePositionEvent $event)
    {
        $object = CategoryQuery::create()->findPk($event->getCategoryId());

        if ($object !== null) {

            // The required position
            $new_position = $event->getPosition();

            // The current position
            $current_position = $object->getPosition();

            if ($new_position != null && $new_position > 0 && $new_position != $current_position) {

                 // Find categories to offset
                $search = CategoryQuery::create()->filterByParent($object->getParent());

                if ($new_position > $current_position) {
                    // The new position is after the current position -> we will offset + 1 all categories located between us and the new position
                    $search->filterByPosition(array('min' => 1+$current_position, 'max' => $new_position));

                    $delta = -1;
                } else {
                    // The new position is brefore the current position -> we will offset - 1 all categories located between us and the new position
                    $search->filterByPosition(array('min' => $new_position, 'max' => $current_position - 1));

                    $delta = 1;
                }

                $results = $search->find();

                $cnx = Propel::getWriteConnection(CategoryTableMap::DATABASE_NAME);

                $cnx->beginTransaction();

                try {
                    foreach ($results as $result) {
                        $result->setPosition($result->getPosition() + $delta)->save($cnx);
                    }

                    $object
                        ->setDispatcher($this->getDispatcher())
                        ->setPosition($new_position)
                        ->save($cnx)
                    ;

                    $cnx->commit();
                } catch (Exception $e) {
                    $cnx->rollback();
                }
            }
        }
    }
}