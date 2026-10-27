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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\EventListener\SessionGuardTrait;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\RequestPath;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;

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

        if (VisitorCountryMode::Off === $mode) {
            return;
        }

        $detectedId = $this->session->detectedCountryId();

        if (null === $detectedId) {
            $detectedId = $this->detector->detect($request)?->getId() ?? 0;

            // Remembered even when nothing was found: the chain, and the log line of a
            // database that cannot be read, run once per session. A header that is absent
            // costs nothing to read again, and may well come with the next request; a header
            // that is present is the answer of the proxy, even for a country the shop hides.
            if ($detectedId > 0 || $this->isDetectionFinal($request)) {
                $this->session->rememberDetected($detectedId);
            }
        }

        if ($detectedId < 1) {
            return;
        }

        // A session opened in another mode is brought to the current one: the mode of the
        // shop may change while the visitor browses.
        if (VisitorCountryMode::Apply === $mode) {
            if (null === $this->session->retainedCountryId()) {
                $this->session->retainApplied($detectedId);
            }

            return;
        }

        if (!$this->session->isAnswered() && $this->isOnTheDomainOfTheCountry($request, $detectedId)) {
            $this->session->retain($detectedId);
            $this->session->markAnswered();
        }
    }

    private function isDetectionFinal(Request $request): bool
    {
        return match ($this->settings->source()) {
            VisitorCountrySource::IpDatabase => true,
            VisitorCountrySource::Header => '' !== trim((string) $request->headers->get($this->settings->header(), '')),
        };
    }

    /**
     * With one domain per language, reaching the domain of a language of the detected
     * country (`nl_BE` for BE) is the answer to the suggestion.
     */
    private function isOnTheDomainOfTheCountry(Request $request, int $countryId): bool
    {
        if (!ConfigQuery::isMultiDomainActivated()) {
            return false;
        }

        $session = $request->getSession();
        $locale = (string) ($session instanceof Session ? $session->getLang()?->getLocale() : '');
        $localeCountry = IsoCountryCode::ofLocale($locale);

        return null !== $localeCountry && $localeCountry === IsoCountryCode::normalize(CountryQuery::create()->findPk($countryId)?->getIsoalpha2());
    }

    private function isBackendPath(string $path): bool
    {
        return 1 === preg_match('#^/(admin|api)(/|$)#', $path);
    }
}
