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

namespace Thelia\Core\Security\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\RequestPath;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Model\Admin;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 64)]
final readonly class AdminTwoFactorEnrolmentListener
{
    public const SETUP_ROUTE = 'admin.two-factor.setup';
    public const ENROLLED_SESSION_KEY = 'thelia.admin_two_factor_enrolled';

    private const ROUTES_OPEN_BEFORE_ENROLMENT = [
        self::SETUP_ROUTE,
        'admin.two-factor.confirm',
        'admin.logout',
    ];

    public function __construct(
        private SecurityContext $securityContext,
        private TokenStorageInterface $tokenStorage,
        private AdminTwoFactorManager $twoFactorManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->twoFactorManager->isRequired()) {
            return;
        }

        $request = $event->getRequest();

        if (\in_array($request->attributes->get('_route'), self::ROUTES_OPEN_BEFORE_ENROLMENT, true)) {
            return;
        }

        $path = RequestPath::decoded($request);

        if (str_starts_with($path, '/api/admin/')) {
            $admin = $this->tokenStorage->getToken()?->getUser();

            if ($admin instanceof Admin && $this->twoFactorManager->mustEnrol($admin)) {
                $event->setController(static fn (): Response => new JsonResponse(
                    ['message' => 'The second factor of this account must be enabled from the back office first.'],
                    Response::HTTP_FORBIDDEN,
                ));
            }

            return;
        }

        if (!$this->isBackOfficeRequest($path, $event->getController())) {
            return;
        }

        $admin = $this->securityContext->getAdminUser();

        if (!$admin instanceof Admin || $this->isKnownToBeEnrolled($request, $admin)) {
            return;
        }

        if (!$this->twoFactorManager->mustEnrol($admin)) {
            $this->rememberEnrolled($request, $admin);

            return;
        }

        $setupUrl = $this->urlGenerator->generate(self::SETUP_ROUTE);
        $event->setController(static fn (): Response => new RedirectResponse($setupUrl));
    }

    private function isBackOfficeRequest(string $path, callable $controller): bool
    {
        if ('/admin' === $path || str_starts_with($path, '/admin/')) {
            return true;
        }

        $controllerObject = \is_array($controller) ? $controller[0] : $controller;

        return $controllerObject instanceof BaseAdminController;
    }

    private function isKnownToBeEnrolled(Request $request, Admin $admin): bool
    {
        return $request->hasSession() && $request->getSession()->get(self::ENROLLED_SESSION_KEY) === (int) $admin->getId();
    }

    private function rememberEnrolled(Request $request, Admin $admin): void
    {
        if ($request->hasSession()) {
            $request->getSession()->set(self::ENROLLED_SESSION_KEY, (int) $admin->getId());
        }
    }
}
