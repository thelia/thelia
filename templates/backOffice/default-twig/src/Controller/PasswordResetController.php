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

namespace BackOfficeDefaultTwigBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminQuery;
use Thelia\Model\ConfigQuery;
use Twig\Environment;

final class PasswordResetController
{
    private const TOKEN_KEY = 'admin.password_renew_token';

    public function __construct(
        private readonly Environment $twig,
        private readonly EventDispatcherInterface $events,
        private readonly SecurityContext $securityContext,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/admin/lost-password', name: 'admin.lost-password', methods: ['GET'])]
    public function showLostPassword(Request $request): Response
    {
        if (($denied = $this->guardRecovery()) !== null || ($denied = $this->guardLoggedOut()) !== null) {
            return $denied;
        }

        return $this->renderLostPassword(['form_error_message' => $request->getSession() instanceof TheliaSession ? '' : '']);
    }

    #[Route('/admin/password-create-request', name: 'admin.password-create', methods: ['POST'])]
    public function passwordCreateRequest(Request $request): Response
    {
        if (($denied = $this->guardRecovery()) !== null || ($denied = $this->guardLoggedOut()) !== null) {
            return $denied;
        }

        $usernameOrEmail = trim((string) $request->request->get('username_or_email', ''));
        if (\strlen($usernameOrEmail) < 3) {
            return $this->renderLostPassword(['error' => $this->translator->trans('Please enter a username or email address.')]);
        }

        $admin = AdminQuery::create()->findOneByEmail($usernameOrEmail) ?? AdminQuery::create()->findOneByLogin($usernameOrEmail);
        if ($admin === null) {
            AdminLog::append('admin', 'ADMIN_LOST_PASSWORD', 'Invalid username or email', $request);

            return $this->renderLostPassword(['error' => $this->translator->trans('Invalid username or email.')]);
        }

        $email = (string) $admin->getEmail();
        if ($email === '') {
            return $this->renderLostPassword(['error' => $this->translator->trans('Sorry, no email defined for this administrator.')]);
        }

        $this->events->dispatch(new AdministratorEvent($admin), TheliaEvents::ADMINISTRATOR_CREATEPASSWORD);

        return new RedirectResponse($this->urls->generate('admin.password-create-success'));
    }

    #[Route('/admin/password-create-request-success', name: 'admin.password-create-success', methods: ['GET'])]
    public function passwordCreateSuccess(): Response
    {
        if (($denied = $this->guardRecovery()) !== null || ($denied = $this->guardLoggedOut()) !== null) {
            return $denied;
        }

        return $this->renderLostPassword(['create_request_success' => true]);
    }

    #[Route('/admin/password-create/{token}', name: 'admin.password-create-form', methods: ['GET'], requirements: ['token' => '.*'])]
    public function displayCreateForm(Request $request, string $token): Response
    {
        if (($denied = $this->guardRecovery()) !== null || ($denied = $this->guardLoggedOut()) !== null) {
            return $denied;
        }

        $admin = AdminQuery::create()->findOneByPasswordRenewToken($token);
        if ($admin === null) {
            return $this->renderLostPassword(['token_error' => true]);
        }

        $session = $request->getSession();
        if ($session instanceof TheliaSession) {
            $session->set(self::TOKEN_KEY, $token);
        }

        return $this->renderCreatePassword();
    }

    #[Route('/admin/password-created', name: 'admin.password-renewed', methods: ['POST'])]
    public function passwordCreated(Request $request): Response
    {
        if (($denied = $this->guardRecovery()) !== null || ($denied = $this->guardLoggedOut()) !== null) {
            return $denied;
        }

        $password = (string) $request->request->get('password', '');
        $confirm = (string) $request->request->get('password_confirm', '');
        if ($password === '' || $password !== $confirm) {
            return $this->renderCreatePassword(['error' => $this->translator->trans('The two passwords do not match or are empty.')]);
        }

        $session = $request->getSession();
        $token = '';
        if ($session instanceof TheliaSession) {
            $token = (string) ($session->get(self::TOKEN_KEY) ?? '');
        }

        if ($token === '' || ($admin = AdminQuery::create()->findOneByPasswordRenewToken($token)) === null) {
            return $this->renderCreatePassword(['error' => $this->translator->trans('An invalid token was provided, your password cannot be changed')]);
        }

        $event = new AdministratorUpdatePasswordEvent($admin);
        $event->setPassword($password);
        $this->events->dispatch($event, TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD);

        if ($session instanceof TheliaSession) {
            $session->set(self::TOKEN_KEY, null);
        }

        return new RedirectResponse($this->urls->generate('admin.password-renewed-success'));
    }

    #[Route('/admin/password-create-success', name: 'admin.password-renewed-success', methods: ['GET'])]
    public function passwordRenewedSuccess(): Response
    {
        if (($denied = $this->guardRecovery()) !== null || ($denied = $this->guardLoggedOut()) !== null) {
            return $denied;
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/auth/create-password-success.html.twig'));
    }

    #[Route('/admin/set-email-address', name: 'admin.set-email-address', methods: ['GET', 'POST'])]
    public function setEmailAddress(): Response
    {
        return new RedirectResponse($this->urls->generate('admin.configuration.administrators.view', [
            'show_email_change_notice' => 1,
        ]));
    }

    /** @param array<string, mixed> $extra */
    private function renderLostPassword(array $extra = []): Response
    {
        return new Response($this->twig->render(
            '@BackOfficeDefaultTwig/auth/lost-password.html.twig',
            $extra + ['create_request_success' => false, 'token_error' => false, 'error' => null],
        ));
    }

    /** @param array<string, mixed> $extra */
    private function renderCreatePassword(array $extra = []): Response
    {
        return new Response($this->twig->render(
            '@BackOfficeDefaultTwig/auth/create-password.html.twig',
            $extra + ['error' => null],
        ));
    }

    private function guardRecovery(): ?Response
    {
        if (!ConfigQuery::read('enable_lost_admin_password_recovery', false)) {
            return new Response(
                $this->twig->render('@BackOfficeDefaultTwig/general_error.html.twig', [
                    'error_message' => $this->translator->trans('The lost admin password recovery feature is disabled.'),
                ]),
                Response::HTTP_FORBIDDEN,
            );
        }

        return null;
    }

    private function guardLoggedOut(): ?Response
    {
        if ($this->securityContext->getAdminUser() !== null) {
            return new RedirectResponse('/admin');
        }

        return null;
    }
}
