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

namespace Thelia\Admin\Controller;

class CategoryController extends BaseAdminController {

	public function indexAction()
	{
		// Show top level categories and products
		$args = array(
				'action' => 'browse',
				'current_category_id' => 0
		);

		return $this->render('categories', $args);
	}

    public function processAction($action)
    {
    	list($action, $id) = explode('/', $action);

    	$args = array(
    		'action' 			  => $action,
    		'current_category_id' => $id
    	);

    	// Browe categories
    	if ($action == 'browse') {
    		return $this->render('categories', $args);
    	}
    	// Create a new category
    	else if ($action = 'create') {
    		return $this->render('edit_category', $args);
    	}
    	// Edit an existing category
    	else if ($action = 'edit') {
    		return $this->render('edit_category', $args);
    	}

    	//return $this->render("categories");
    }
}