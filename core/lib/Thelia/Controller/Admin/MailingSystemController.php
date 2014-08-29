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

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\MailingSystem\MailingSystemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\MailingSystemModificationForm;
use Thelia\Model\ConfigQuery;

class MailingSystemController extends BaseAdminController
{
    const RESOURCE_CODE = "admin.mailing-system";

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) return $response;

        // Hydrate the form abd pass it to the parser
        $data = array(
            'enabled'       => ConfigQuery::isSmtpEnable() ? 1 : 0,
            'host'          => ConfigQuery::getSmtpHost(),
            'port'          => ConfigQuery::getSmtpPort(),
            'encryption'    => ConfigQuery::getSmtpEncryption(),
            'username'      => ConfigQuery::getSmtpUsername(),
            'password'      => ConfigQuery::getSmtpPassword(),
            'authmode'      => ConfigQuery::getSmtpAuthMode(),
            'timeout'       => ConfigQuery::getSmtpTimeout(),
            'sourceip'      => ConfigQuery::getSmtpSourceIp(),
        );

        // Setup the object form
        $form = new MailingSystemModificationForm($this->getRequest(), "form", $data);

        // Pass it to the parser
        $this->getParserContext()->addForm($form);

        // Render the edition template.
        return $this->render('mailing-system');
    }

    public function updateAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::UPDATE)) return $response;

        $error_msg = false;

        // Create the form from the request
        $form = new MailingSystemModificationForm($this->getRequest());

        try {

            // Check the form against constraints violations
            $formData = $this->validateForm($form, "POST");

            // Get the form field values
            $event = new MailingSystemEvent();
            $event->setEnabled($formData->get('enabled')->getData());
            $event->setHost($formData->get('host')->getData());
            $event->setPort($formData->get('port')->getData());
            $event->setEncryption($formData->get('encryption')->getData());
            $event->setUsername($formData->get('username')->getData());
            $event->setPassword($formData->get('password')->getData());
            $event->setAuthMode($formData->get('authmode')->getData());
            $event->setTimeout($formData->get('timeout')->getData());
            $event->setSourceIp($formData->get('sourceip')->getData());

            $this->dispatch(TheliaEvents::MAILING_SYSTEM_UPDATE, $event);

            // Redirect to the success URL
            $response = $this->generateRedirectFromRoute("admin.configuration.mailing-system.view");
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("mailing system modification", array()),
                $error_msg,
                $form,
                $ex
            );

            // At this point, the form has errors, and should be redisplayed.
            $response = $this->render('mailing-system');
        }

        return $response;
    }
}
