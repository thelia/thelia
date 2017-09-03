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

namespace Tinymce\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Exception\TheliaProcessException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;
use Tinymce\Form\ConfigurationForm;
use Tinymce\Tinymce;

/**
 * Class SetTransferConfig
 * @package WireTransfer\Controller
 * @author Thelia <info@thelia.net>
 */
class ConfigureController extends BaseAdminController
{
    public function configure()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'Tinymce', AccessManager::UPDATE)) {
            return $response;
        }

        // Initialize the potential exception
        $ex = null;

        // Create the Form from the request
        $configurationForm = new ConfigurationForm($this->getRequest());

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($configurationForm, "POST");

            // Get the form field values
            $data = $form->getData();

            Tinymce::setConfigValue('product_summary', $data['product_summary']);
            Tinymce::setConfigValue('product_conclusion', $data['product_conclusion']);

            Tinymce::setConfigValue('content_summary', $data['content_summary']);
            Tinymce::setConfigValue('content_conclusion', $data['content_conclusion']);

            Tinymce::setConfigValue('category_summary', $data['category_summary']);
            Tinymce::setConfigValue('category_conclusion', $data['category_conclusion']);

            Tinymce::setConfigValue('folder_summary', $data['folder_summary']);
            Tinymce::setConfigValue('folder_conclusion', $data['folder_conclusion']);

            Tinymce::setConfigValue('brand_summary', $data['brand_summary']);
            Tinymce::setConfigValue('brand_conclusion', $data['brand_conclusion']);

            Tinymce::setConfigValue('show_menu_bar', $data['show_menu_bar']);
            Tinymce::setConfigValue('force_pasting_as_text', $data['force_pasting_as_text']);
            Tinymce::setConfigValue('editor_height', $data['editor_height']);
            Tinymce::setConfigValue('custom_css', $data['custom_css']);

            // Save Custom CSS in default assets
            $customCss = __DIR__ .DS.'..'.DS.'templates'.DS.'backOffice'.DS.'default'.DS.'assets'.DS.'css'.DS.'custom-css.less';

            if (false === file_put_contents($customCss, $data['custom_css'])) {
                throw new TheliaProcessException(
                    $this->getTranslator()->trans(
                        "Failed to update custom CSS file \"%file\". Please check this file or parent folder write permissions.",
                        [ '%file' => $customCss ]
                    )
                );
            }

            // Log configuration modification
            $this->adminLogAppend(
                "tinymce.configuration.message",
                AccessManager::UPDATE,
                sprintf("Tinymce configuration updated")
            );

            // Everything is OK.
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/Tinymce'));

        } catch (FormValidationException $ex) {
            // Form cannot be validated. Create the error message using
            // the BaseAdminController helper method.
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        }
        catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        // At this point, the form has errors, and should be redisplayed. We don not redirect,
        // just redisplay the same template.
        // Setup the Form error context, to make error information available in the template.
        $this->setupFormErrorContext(
            $this->getTranslator()->trans("Tinymce configuration", [], Tinymce::MODULE_DOMAIN),
            $error_msg,
            $configurationForm,
            $ex
        );

        // Do not redirect at this point, or the error context will be lost.
        // Just redisplay the current template.
        return $this->render('module-configure', array('module_code' => 'Tinymce'));
    }
}
