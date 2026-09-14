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
 * An answer is more than a yes or a no: it carries the wording and the long text the
 * buyer had in front of them, and the moment they answered. That is what makes the row
 * written on the order a proof rather than a claim — a merchant who rewords a consent
 * between the tick and the payment must not end up with an order saying the buyer
 * agreed to a sentence they never read. Which is also why an answer left unchanged
 * keeps the wording it was given with, whatever the shop has published since.
 *
 * Every read tolerates the absence of a session, because an order can be created
 * without one: the back office does it, and so does a command line.
 *
 * @phpstan-type ConsentAnswer array{accepted: bool, title: string, description: string, answeredAt: \DateTimeImmutable}
 */
final readonly class ConsentAcceptanceStore implements ConsentAcceptanceReaderInterface
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
        return array_map(
            static fn (array $answer): bool => $answer['accepted'],
            $this->answers(),
        );
    }

    /**
     * The answers as they were given: what was ticked, what was on screen, and when.
     *
     * @return array<string, ConsentAnswer> by consent code
     */
    public function answers(): array
    {
        $stored = $this->session()?->get(self::SESSION_KEY, []);

        if (!\is_array($stored)) {
            return [];
        }

        $answers = [];

        foreach ($stored as $code => $answer) {
            if (!\is_array($answer)) {
                continue;
            }

            $answers[(string) $code] = [
                'accepted' => (bool) ($answer['accepted'] ?? false),
                'title' => (string) ($answer['title'] ?? ''),
                'description' => (string) ($answer['description'] ?? ''),
                'answeredAt' => new \DateTimeImmutable((string) ($answer['answeredAt'] ?? 'now')),
            ];
        }

        return $answers;
    }

    public function isAccepted(string $code): bool
    {
        return $this->answers()[$code]['accepted'] ?? false;
    }

    /**
     * Records the answers of one submission, replacing the previous ones.
     *
     * A box left unticked is an answer too — the buyer declined — so the caller passes
     * every consent it displayed, not only the ones that came back ticked, along with
     * the wording it displayed them under.
     *
     * An answer whose value has not moved keeps the wording and the timestamp it was
     * first given: ticking one box must not restamp, nor re-word, the boxes around it.
     *
     * @param array<string, array{accepted: bool, title: string, description: string}> $answers by consent code
     */
    public function replace(array $answers): void
    {
        $previous = $this->answers();
        $answeredAt = new \DateTimeImmutable();
        $stored = [];

        foreach ($answers as $code => $answer) {
            $code = (string) $code;
            $accepted = (bool) $answer['accepted'];
            $unchanged = isset($previous[$code]) && $previous[$code]['accepted'] === $accepted;

            $stored[$code] = [
                'accepted' => $accepted,
                'title' => $unchanged ? $previous[$code]['title'] : $answer['title'],
                'description' => $unchanged ? $previous[$code]['description'] : $answer['description'],
                'answeredAt' => ($unchanged ? $previous[$code]['answeredAt'] : $answeredAt)->format(\DATE_ATOM),
            ];
        }

        $this->session()?->set(self::SESSION_KEY, $stored);
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
