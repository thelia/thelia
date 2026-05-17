<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\MailingSystem\MailingSystemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;

class MailingSystemController extends BaseAdminController
{
    public const RESOURCE_CODE = 'admin.configuration.mailing-system';

    public function defaultAction()
    {
        if (($response = $this->checkAuth(self::RESOURCE_CODE, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        // Hydrate the form abd pass it to the parser
        $data = [
            'enabled' => (bool) ConfigQuery::isSmtpEnable(),
            'host' => ConfigQuery::getSmtpHost(),
            'port' => ConfigQuery::getSmtpPort(),
            'encryption' => ConfigQuery::getSmtpEncryption(),
            'username' => ConfigQuery::getSmtpUsername(),
            'password' => ConfigQuery::getSmtpPassword(),
            'authmode' => ConfigQuery::getSmtpAuthMode(),
            'timeout' => ConfigQuery::getSmtpTimeout(),
            'sourceip' => ConfigQuery::getSmtpSourceIp(),
        ];

        // Setup the object form
        $form = $this->createForm(AdminForm::MAILING_SYSTEM_MODIFICATION, FormType::class, $data);

        // Pass it to the parser
        $this->getParserContext()->addForm($form);

        // Render the edition template.
        return $this->render('mailing-system', ['editDisabled' => ConfigQuery::isSmtpInEnv()]);
    }

    public function updateAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        // Check current user authorization
        if (($response = $this->checkAuth(self::RESOURCE_CODE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $error_msg = false;
        $ex = null;

        // Create the form from the request
        $form = $this->createForm(AdminForm::MAILING_SYSTEM_MODIFICATION);

        try {
            // Check the form against constraints violations
            $formData = $this->validateForm($form, 'POST');

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

            $eventDispatcher->dispatch($event, TheliaEvents::MAILING_SYSTEM_UPDATE);

            // Redirect to the success URL
            $response = $this->generateRedirectFromRoute('admin.configuration.mailing-system.view');
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans('mailing system modification'),
                $error_msg,
                $form,
                $ex,
            );

            // At this point, the form has errors, and should be redisplayed.
            $response = $this->render('mailing-system');
        }

        return $response;
    }

    public function testAction(Request $request, MailerFactory $mailer): Response|JsonResponse
    {
        $translator = Translator::getInstance();

        // Check current user authorization
        if (($response = $this->checkAuth(self::RESOURCE_CODE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $contactEmail = ConfigQuery::read('store_email');
        $storeName = ConfigQuery::read('store_name', 'Thelia');

        $json_data = [
            'success' => false,
            'message' => '',
        ];

        if ($contactEmail) {
            $emailTest = $request->get('email', $contactEmail);

            $message = $translator->trans('Email test from : %store%', ['%store%' => $storeName]);

            $htmlMessage = \sprintf('<p>%s</p>', $message);

            try {
                $mailer->sendSimpleEmailMessage(
                    [$contactEmail => $storeName],
                    [$emailTest => $storeName],
                    $message,
                    $message,
                    $htmlMessage,
                );
                $json_data['success'] = true;
                $json_data['message'] = $translator->trans('Your configuration seems to be ok. Checked out your mailbox : %email%', ['%email%' => $emailTest]);
            } catch (\Exception $ex) {
                $json_data['message'] = $ex->getMessage();
            }
        } else {
            $json_data['message'] = $translator->trans('You have to configure your store email first !');
        }

        return new JsonResponse($json_data);
    }
}
