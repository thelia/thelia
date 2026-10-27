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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The only reader and writer of what the session remembers about the visitor country.
 * Without a session (a console command, the stateless API) it reads nothing and writes
 * nothing.
 */
final readonly class VisitorCountrySession
{
    public const DETECTED_KEY = 'thelia.visitor_country.detected';
    public const ID_KEY = 'thelia.visitor_country.id';
    public const ANSWERED_KEY = 'thelia.visitor_country.answered';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function isAvailable(): bool
    {
        return null !== $this->session();
    }

    /**
     * Null when the detection has not run yet, 0 when it ran and found nothing.
     */
    public function detectedCountryId(): ?int
    {
        $value = $this->session()?->get(self::DETECTED_KEY);

        return \is_int($value) ? $value : null;
    }

    public function rememberDetected(int $countryId): void
    {
        $this->session()?->set(self::DETECTED_KEY, $countryId);
    }

    public function retainedCountryId(): ?int
    {
        $value = $this->session()?->get(self::ID_KEY);

        return \is_int($value) && $value > 0 ? $value : null;
    }

    public function retain(int $countryId): void
    {
        $this->session()?->set(self::ID_KEY, $countryId);
    }

    public function forget(): void
    {
        $this->session()?->remove(self::ID_KEY);
    }

    public function isAnswered(): bool
    {
        return true === $this->session()?->get(self::ANSWERED_KEY);
    }

    public function markAnswered(): void
    {
        $this->session()?->set(self::ANSWERED_KEY, true);
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }
}
