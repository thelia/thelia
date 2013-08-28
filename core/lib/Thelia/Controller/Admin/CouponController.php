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

namespace Thelia\Controller\Admin;

use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;

class CouponController extends BaseAdminController
{

    protected function browseCoupon($args)
    {
        $this->checkAuth("ADMIN", "admin.coupon.view");

        return $this->render('coupons', $args);
    }

    public function indexAction()
    {
        return $this->processAction();
    }

    public function processAction()
    {
        // Get the current action
        $action = $this->getRequest()->get('action', 'browse');

        // Get the category ID
        $id = $this->getRequest()->get('id', 0);

        $args = array(
            'action' 			  => $action,
            'current_coupon_id'   => $id
        );

        try {
            switch ($action) {
                case 'browse' : // Browse coupon

                    return $this->browseCoupons($args);

                case 'create' : // Create a new category

//                    return $this->createNewCategory($args);

                case 'edit' : // Edit an existing category

//                    return $this->editCategory($args);

                case 'delete' : // Delete an existing category

//                    return $this->deleteCategory($args);

                case 'visibilityToggle' : // Toggle visibility

//                    return $this->visibilityToggle($id);

                case 'changePosition' : // Change position

//                    return $this->changePosition($args);

                case 'positionUp' : // Move up category

//                    return $this->positionUp($args);

                case 'positionDown' : // Move down category

//                    return $this->positionDown($args);
            }
        } catch (AuthorizationException $ex) {
            return $this->errorPage($ex->getMessage());
        } catch (AuthenticationException $ex) {
            return $this->errorPage($ex->getMessage());
        }

        // We did not recognized the action -> return a 404 page
        return $this->pageNotFound();
    }
}
