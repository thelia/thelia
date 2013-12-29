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
            $this->redirectToRoute("admin.configuration.mailing-system.view");
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans("mailing system modification", array()),
            $error_msg,
            $form,
            $ex
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->render('mailing-system');
    }
}
