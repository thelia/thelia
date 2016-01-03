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

namespace HookNavigation\Controller;

use HookNavigation\HookNavigation;
use HookNavigation\Model\Config\HookNavigationConfigValue;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Form\Exception\FormValidationException;

/**
 * Class HookNavigationConfigController.
 *
 * @author Etienne PERRIERE <eperriere@openstudio.fr> - OpenStudio
 */
class HookNavigationConfigController extends BaseAdminController
{
    public function defaultAction()
    {
        $bodyConfig = HookNavigation::getConfigValue(HookNavigationConfigValue::FOOTER_BODY_FOLDER_ID);
        $bottomConfig = HookNavigation::getConfigValue(HookNavigationConfigValue::FOOTER_BOTTOM_FOLDER_ID);

        $this->getSession()->getFlashBag()->set('bodyConfig', $bodyConfig);
        $this->getSession()->getFlashBag()->set('bottomConfig', $bottomConfig);

        return $this->render('hooknavigation-configuration');
    }

    public function saveAction()
    {
        $baseForm = $this->createForm('hooknavigation.configuration');

        $errorMessage = null;

        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            HookNavigation::setConfigValue(HookNavigationConfigValue::FOOTER_BODY_FOLDER_ID, is_bool($data['footer_body_folder_id']) ? (int) ($data['footer_body_folder_id']) : $data['footer_body_folder_id']);
            HookNavigation::setConfigValue(HookNavigationConfigValue::FOOTER_BOTTOM_FOLDER_ID, is_bool($data['footer_bottom_folder_id']) ? (int) ($data['footer_bottom_folder_id']) : $data['footer_bottom_folder_id']);
        } catch (FormValidationException $ex) {
            // Invalid data entered
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $errorMessage = $this->getTranslator()->trans('Sorry, an error occurred: %err', ['%err' => $ex->getMessage()], [], HookNavigation::MESSAGE_DOMAIN);
        }

        if (null !== $errorMessage) {
            // Mark the form as with error
            $baseForm->setErrorMessage($errorMessage);

            // Send the form and the error to the parser
            $this->getParserContext()
                ->addForm($baseForm)
                ->setGeneralError($errorMessage)
            ;
        } else {
            $this->getParserContext()
                ->set('success', true)
            ;
        }

        return $this->defaultAction();
    }
}
