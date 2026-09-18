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

/**
 * Where the checkout reads the consent answers of the request it is serving.
 *
 * Two places hold them, and only one of them holds them at a time. A buyer walking the
 * screens of a theme ticks boxes long before they pay, so their answers wait in the
 * session; a caller with no session states them in the request that places the order,
 * and they are held in memory for the length of it. Asking the second first is what makes
 * the two work side by side: a shop serves both at once, and the answers of a request
 * that carries them are the answers of that request, whatever a session elsewhere says.
 *
 * Everything the checkout asks about consents goes through here — the guard that refuses
 * an order, the step that reports what is missing, the proof frozen onto the order — so
 * that the two paths cannot end up refusing on one set of answers and recording another.
 *
 * @phpstan-import-type ConsentAnswer from ConsentAnswerStoreInterface
 */
final readonly class ConsentAnswerChain implements ConsentAnswerStoreInterface
{
    public function __construct(
        private SubmittedConsentAnswers $submittedAnswers,
        private ConsentAcceptanceStore $sessionAnswers,
    ) {
    }

    /**
     * @return array<string, bool> the answer given to each consent, by consent code
     */
    public function all(): array
    {
        return $this->answerSource()->all();
    }

    /**
     * @return array<string, ConsentAnswer> by consent code
     */
    public function answers(): array
    {
        return $this->answerSource()->answers();
    }

    /**
     * Both are forgotten, not just the one that answered: the order carries the proof
     * now, and an answer left behind in either place would be a box the next order does
     * not ask again.
     */
    public function clear(): void
    {
        $this->submittedAnswers->clear();
        $this->sessionAnswers->clear();
    }

    private function answerSource(): ConsentAnswerStoreInterface
    {
        return $this->submittedAnswers->hasAnswers()
            ? $this->submittedAnswers
            : $this->sessionAnswers;
    }
}
