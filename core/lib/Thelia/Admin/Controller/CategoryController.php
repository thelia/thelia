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

use Thelia\Model\CategoryQuery;
use Thelia\Core\Security\Exception\AuthenticationException;
class CategoryController extends BaseAdminController {

	public function indexAction()
	{
		// Show top level categories and products
		$args = array(
				'action' => 'browse',
				'current_category_id' => 0
		);

		return $this->browseCategory($args);
	}

	public function createNewCategory($args) {

		$this->checkAuth("ADMIN", "admin.category.create");

		$this->dispatchEvent("createCategory");

		// At this point, the form has error, and should be redisplayed.
		return $this->render('categories', $args);
	}

	public function editCategory($args) {

		$this->checkAuth("AMIN", "admin.category.edit");

		return $this->render('edit_category', $args);
	}

	public function deleteCategory($category_id) {

		$this->checkAuth("AMIN", "admin.category.delete");

		$category = CategoryQuery::create()->findPk($category_id);

		$this->dispatchEvent("deleteCategory");

		// Something was wrong, category was not deleted. Display parent category list
		return $this->render(
			'categories',
			array('current_category_id' => $category->getParent())
		);
	}

	public function browseCategory($args) {

		$this->checkAuth("AMIN", "admin.catalog.view");

		return $this->render('categories', $args);
	}

    public function processAction()
    {
    	// Get the current action
    	$action = $this->getRequest()->get('action', 'browse');

    	// Get the category ID
    	$id = $this->getRequest()->get('id', 0);

    	$args = array(
    		'action' 			  => $action,
    		'current_category_id' => $id
    	);

    	try {
	    	// Browse categories
	    	if ($action == 'browse') {
	    		return $this->browseCategory($args);
	    	}
	    	// Create a new category
	    	else if ($action == 'create') {
	    		return $this->createNewCategory($args);
	    	}
	        	// Edit an existing category
	    	else if ($action == 'edit') {
	    		return $this->editCategory($args);
	    	}
	    	// Delete an existing category
	    	else if ($action == 'delete') {
	    		return $this->deleteCategory($id);
	    	}
    	}
    	catch(AuthenticationException $ex) {
    		return $this->render('general_error', array(
    			"error_message" => $ex->getMessage())
    		);
    	}
    }
}