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

use Thelia\Form\TestForm;
/**
 * Manages variables
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class TestController extends BaseAdminController
{
    /**
     * Load a object for modification, and display the edit template.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function updateAction()
    {
        // Prepare the data that will hydrate the form
        $data = array(
                'title'           => "test title",
                'test'         => array('a', 'b', 'toto' => 'c')
        );

        // Setup the object form
        $changeForm = new TestForm($this->getRequest(), "form", $data);

        // Pass it to the parser
        $this->getParserContext()->addForm($changeForm);

        return $this->render('test-form');
    }

    /**
     * Save changes on a modified object, and either go back to the object list, or stay on the edition page.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function processUpdateAction()
    {
        $error_msg = false;

        // Create the form from the request
        $changeForm = new TestForm($this->getRequest());

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            echo "data=";

            var_dump($data);
        }
        catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        }
        catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        echo "Error = $error_msg";

        exit;
    }
}