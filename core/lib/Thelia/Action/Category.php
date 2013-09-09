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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Category as CategoryModel;
use Thelia\Model\CategoryQuery;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\Map\CategoryTableMap;
use Propel\Runtime\Exception\PropelException;

use Thelia\Core\Event\CategoryCreateEvent;
use Thelia\Core\Event\CategoryDeleteEvent;
use Thelia\Core\Event\CategoryToggleVisibilityEvent;
use Thelia\Core\Event\CategoryChangePositionEvent;

class Category extends BaseAction implements EventSubscriberInterface
{
    public function create(CategoryCreateEvent $event)
    {
        $category = new CategoryModel();

        $category
            ->setDispatcher($this->getDispatcher())
            ->create(
               $event->getTitle(),
               $event->getParent(),
               $event->getLocale()
         );

        $event->setCategory($category);
    }

    public function update(CategoryChangeEvent $event)
    {
    }

    /**
     * Delete a category
     *
     * @param ActionEvent $event
     */
    public function delete(CategoryDeleteEvent $event)
    {
        $category = CategoryQuery::create()->findPk($event->getCategoryId());

        if ($category !== null) {

            $category->setDispatcher($this->getDispatcher())->delete();
        }
    }

    /**
     * Toggle category visibility. No form used here
     *
     * @param ActionEvent $event
     */
    public function toggleVisibility(CategoryToggleVisibilityEvent $event)
    {
        $category = CategoryQuery::create()->findPk($event->getCategoryId());

        if ($category !== null) {

            $category
                ->setDispatcher($this->getDispatcher())
                ->setVisible($category->getVisible() ? false : true)
                ->save()
            ;
        }
    }

    /**
     * Changes category position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function changePosition(CategoryChangePositionEvent $event)
    {
        if ($event->getMode() == CategoryChangePositionEvent::POSITION_ABSOLUTE)
            return $this->changeAbsolutePosition($event);
        else
            return $this->exchangePosition($event);
    }

    /**
     * Move up or down a category
     *
     * @param CategoryChangePositionEvent $event
     */
    protected function exchangePosition(CategoryChangePositionEvent $event)
    {
       $category = CategoryQuery::create()->findPk($event->getCategoryId());

        if ($category !== null) {

            // The current position of the category
            $my_position = $category->getPosition();

            // Find category to exchange position with
            $search = CategoryQuery::create()
                ->filterByParent($category->getParent());

            // Up or down ?
            if ($event->getMode() == CategoryChangePositionEvent::POSITION_UP) {
                // Find the category immediately before me
                $search->filterByPosition(array('max' => $my_position-1))->orderByPosition(Criteria::DESC);
            } elseif ($event->getMode() == CategoryChangePositionEvent::POSITION_DOWN) {
                // Find the category immediately after me
                $search->filterByPosition(array('min' => $my_position+1))->orderByPosition(Criteria::ASC);
            } else

                return;

            $result = $search->findOne();

            // If we found the proper category, exchange their positions
            if ($result) {

                $cnx = Propel::getWriteConnection(CategoryTableMap::DATABASE_NAME);

                $cnx->beginTransaction();

                try {
                    $category
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
     * Changes category position
     *
     * @param CategoryChangePositionEvent $event
     */
    protected function changeAbsolutePosition(CategoryChangePositionEvent $event)
    {
        $category = CategoryQuery::create()->findPk($event->getCategoryId());

        if ($category !== null) {

            // The required position
            $new_position = $event->getPosition();

            // The current position
            $current_position = $category->getPosition();

            if ($new_position != null && $new_position > 0 && $new_position != $current_position) {

                 // Find categories to offset
                $search = CategoryQuery::create()->filterByParent($category->getParent());

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

                    $category
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

    /**
     * Returns an array of event names this subscriber listens to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CATEGORY_CREATE => array("create", 128),
            TheliaEvents::CATEGORY_UPDATE => array("update", 128),
            TheliaEvents::CATEGORY_DELETE => array("delete", 128),

            TheliaEvents::CATEGORY_TOGGLE_VISIBILITY => array("toggleVisibility", 128),
            TheliaEvents::CATEGORY_CHANGE_POSITION   => array("changePosition", 128),

            "action.updateCategoryPositionU" 	=> array("changePositionUp", 128),
            "action.updateCategoryPositionDown" => array("changePositionDown", 128),
            "action.updateCategoryPosition" 	=> array("changePosition", 128),
        );
    }
}
