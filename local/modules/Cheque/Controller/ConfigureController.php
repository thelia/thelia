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

namespace Cheque\Controller;

use Cheque\Cheque;
use Cheque\Form\ConfigurationForm;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

/**
 * Class SetTransferConfig
 * @package WireTransfer\Controller
 * @author Thelia <info@thelia.net>
 */
class ConfigureController extends BaseAdminController
{
    public function configure()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'Cheque', AccessManager::UPDATE)) {
            return $response;
        }

        // Initialize the potential exception
        $ex = null;

        // Create the Form from the request
        $configurationForm = $this->createForm('cheque.instructions.configure');

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($configurationForm, "POST");

            // Get the form field values
            $data = $form->getData();

            Cheque::setConfigValue('instructions', $data['instructions'], $this->getCurrentEditionLocale());
            Cheque::setConfigValue('payable_to', $data['payable_to']);

            // Log configuration modification
            $this->adminLogAppend(
                "cheque.configuration.message",
                AccessManager::UPDATE,
                sprintf("Cheque instructions configuration updated")
            );

            // Everything is OK.
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/Cheque'));
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
            $this->getTranslator()->trans("Cheque instructions configuration", [], Cheque::MESSAGE_DOMAIN),
            $error_msg,
            $configurationForm,
            $ex
        );

        // Do not redirect at this point, or the error context will be lost.
        // Just redisplay the current template.
        return $this->render('module-configure', ['module_code' => 'Cheque']);
    }
}
