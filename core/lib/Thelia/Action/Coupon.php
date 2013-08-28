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
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Category as CategoryModel;
use Thelia\Form\CategoryCreationForm;
use Thelia\Core\Event\CategoryEvent;
use Thelia\Tools\Redirect;
use Thelia\Model\CategoryQuery;
use Thelia\Model\AdminLog;
use Thelia\Form\CategoryDeletionForm;
use Thelia\Action\Exception\FormValidationException;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\Map\CategoryTableMap;
use Propel\Runtime\Exception\PropelException;

class Coupon extends BaseAction implements EventSubscriberInterface
{

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
//            "action.createCategory" => array("create", 128),
//            "action.modifyCategory" => array("modify", 128),
//            "action.deleteCategory" => array("delete", 128),
//
//            "action.toggleCategoryVisibility" 	=> array("toggleVisibility", 128),
//            "action.changeCategoryPositionUp" 	=> array("changePositionUp", 128),
//            "action.changeCategoryPositionDown" => array("changePositionDown", 128),
//            "action.changeCategoryPosition" 	=> array("changePosition", 128),
        );
    }
}
