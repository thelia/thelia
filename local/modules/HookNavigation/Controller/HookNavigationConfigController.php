<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HookNavigation\Controller;

use HookNavigation\HookNavigation;
use HookNavigation\Model\Config\HookNavigationConfigValue;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

/**
 * Class HookNavigationConfigController.
 *
 * @author Etienne PERRIERE <eperriere@openstudio.fr> - OpenStudio
 */
class HookNavigationConfigController extends BaseAdminController
{
    public function defaultAction(Session $session)
    {
        $bodyConfig = HookNavigation::getConfigValue(HookNavigationConfigValue::FOOTER_BODY_FOLDER_ID);
        $bottomConfig = HookNavigation::getConfigValue(HookNavigationConfigValue::FOOTER_BOTTOM_FOLDER_ID);

        $session->getFlashBag()->set('bodyConfig', $bodyConfig ?? '');
        $session->getFlashBag()->set('bottomConfig', $bottomConfig ?? '');

        return $this->render('hooknavigation-configuration');
    }

    public function saveAction(Request $request, Translator $translator, ParserContext $parserContext)
    {
        $baseForm = $this->createForm('hooknavigation.configuration');

        $errorMessage = null;

        $parserContext->set('success', true);

        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            HookNavigation::setConfigValue(HookNavigationConfigValue::FOOTER_BODY_FOLDER_ID, \is_bool($data['footer_body_folder_id']) ? (int) ($data['footer_body_folder_id']) : $data['footer_body_folder_id']);
            HookNavigation::setConfigValue(HookNavigationConfigValue::FOOTER_BOTTOM_FOLDER_ID, \is_bool($data['footer_bottom_folder_id']) ? (int) ($data['footer_bottom_folder_id']) : $data['footer_bottom_folder_id']);

            if ($request->get('save_mode') !== 'stay') {
                // Redirect to module list
                return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/modules'));
            }
        } catch (FormValidationException $ex) {
            // Invalid data entered
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $errorMessage = $translator->trans(
                'Sorry, an error occurred: %err',
                ['%err' => $ex->getMessage()],
                HookNavigation::MESSAGE_DOMAIN
            );
        }

        if (null !== $errorMessage) {
            // Mark the form as with error
            $baseForm->setErrorMessage($errorMessage);

            // Send the form and the error to the parser
            $parserContext
                ->addForm($baseForm)
                ->setGeneralError($errorMessage)
            ;
        }

        return $this->defaultAction($request->getSession());
    }
}
