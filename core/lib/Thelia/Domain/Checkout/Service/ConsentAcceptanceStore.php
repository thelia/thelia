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

namespace Thelia\Domain\Checkout\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * What the buyer answered to each consent, while the order is still being placed.
 *
 * The answers live in the session rather than in a table: until the order exists there
 * is nothing to attach them to, and a cart abandoned at the payment step must not
 * leave a record of consents behind. The proof is written once, on the order itself,
 * by OrderFacade — from here.
 *
 * Every read tolerates the absence of a session, because an order can be created
 * without one: the back office does it, and so does a command line.
 */
final readonly class ConsentAcceptanceStore
{
    public const SESSION_KEY = 'thelia.checkout.consent_acceptances';

    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * @return array<string, bool> the answer given to each consent, by consent code
     */
    public function all(): array
    {
        $acceptances = $this->session()?->get(self::SESSION_KEY, []);

        if (!\is_array($acceptances)) {
            return [];
        }

        $answers = [];

        foreach ($acceptances as $code => $accepted) {
            $answers[(string) $code] = (bool) $accepted;
        }

        return $answers;
    }

    public function isAccepted(string $code): bool
    {
        return $this->all()[$code] ?? false;
    }

    /**
     * Records the answers of one submission, replacing the previous ones.
     *
     * A box left unticked is an answer too — the buyer declined — so the caller passes
     * every consent it displayed, not only the ones that came back ticked.
     *
     * @param array<string, bool> $acceptances the answer given to each consent, by consent code
     */
    public function replace(array $acceptances): void
    {
        $answers = [];

        foreach ($acceptances as $code => $accepted) {
            $answers[(string) $code] = (bool) $accepted;
        }

        $this->session()?->set(self::SESSION_KEY, $answers);
    }

    public function accept(string $code, bool $accepted = true): void
    {
        $this->replace([...$this->all(), $code => $accepted]);
    }

    public function clear(): void
    {
        $this->session()?->remove(self::SESSION_KEY);
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
