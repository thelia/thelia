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

namespace Thelia\Domain\Localization\VisitorCountry;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\EventListener\SessionGuardTrait;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\RequestPath;

final readonly class VisitorCountryListener
{
    use SessionGuardTrait;

    public function __construct(
        private VisitorCountrySettings $settings,
        private VisitorCountryDetector $detector,
        private VisitorCountrySession $session,
    ) {
    }

    // Below the language, which KernelListener resolves at 128: the country of a visitor
    // never decides the language.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->isSessionUsable($request) || TheliaRequest::$isAdminEnv || $this->isBackendPath(RequestPath::decoded($request))) {
            return;
        }

        $mode = $this->settings->mode();

        if (VisitorCountryMode::Off === $mode || null !== $this->session->detectedCountryId()) {
            return;
        }

        $country = $this->detector->detect($request);

        // Remembered even when nothing was found: the chain, and the log line of a
        // database that cannot be read, run once per session.
        $this->session->rememberDetected($country?->getId() ?? 0);

        if (null !== $country && VisitorCountryMode::Apply === $mode) {
            $this->session->retain($country->getId());
        }
    }

    private function isBackendPath(string $path): bool
    {
        return 1 === preg_match('#^/(admin|api)(/|$)#', $path);
    }
}
