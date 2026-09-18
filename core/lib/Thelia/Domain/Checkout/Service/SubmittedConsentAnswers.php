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

use Symfony\Contracts\Service\ResetInterface;

/**
 * What the buyer answered in the very request that places the order.
 *
 * A theme spreads the checkout over several screens, so an answer has to outlive the
 * request it was given in and the session is where it waits. A caller with no session
 * has nowhere to leave it: the answers arrive with the placement, are read by the guard
 * and by the proof written on the order, and are gone when the request is.
 *
 * Which is why this holds them in memory and nowhere else. Nothing is persisted, nothing
 * is shared between requests, and an order that is never written leaves no trace of the
 * boxes somebody ticked — the same promise the session store makes.
 *
 * @phpstan-import-type ConsentAnswer from ConsentAnswerStoreInterface
 */
final class SubmittedConsentAnswers implements ConsentAnswerStoreInterface, ResetInterface
{
    /** @var array<string, ConsentAnswer> */
    private array $answers = [];

    /**
     * @return array<string, bool> the answer given to each consent, by consent code
     */
    public function all(): array
    {
        return array_map(
            static fn (array $answer): bool => $answer['accepted'],
            $this->answers,
        );
    }

    /**
     * @return array<string, ConsentAnswer> by consent code
     */
    public function answers(): array
    {
        return $this->answers;
    }

    public function hasAnswers(): bool
    {
        return [] !== $this->answers;
    }

    /**
     * Records the answers of one submission, replacing the previous ones.
     *
     * Every consent the shop was asking for is passed, not only the ones agreed to: a box
     * left unticked is a refusal, and an order with no row for it would later read as an
     * order placed before that consent existed. The wording travels with each answer,
     * because it is what the proof is made of.
     *
     * They are all given the same moment, unlike the session store which keeps the moment
     * each box was ticked: here they were all answered in one request.
     *
     * @param array<string, array{accepted: bool, title: string, description: string}> $answers by consent code
     */
    public function submit(array $answers): void
    {
        $answeredAt = new \DateTimeImmutable();
        $this->answers = [];

        foreach ($answers as $code => $answer) {
            $this->answers[(string) $code] = [
                'accepted' => $answer['accepted'],
                'title' => $answer['title'],
                'description' => $answer['description'],
                'answeredAt' => $answeredAt,
            ];
        }
    }

    public function clear(): void
    {
        $this->answers = [];
    }

    /**
     * On a persistent worker runtime the service outlives the request that filled it, and
     * an answer left behind would be an agreement given by the previous buyer. The kernel
     * asks for the memory back between two requests, and this is all of it.
     */
    public function reset(): void
    {
        $this->clear();
    }
}
