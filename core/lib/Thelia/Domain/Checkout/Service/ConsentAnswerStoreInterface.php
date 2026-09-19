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
 * The answers as they were given, for whoever has to write them down as proof.
 *
 * ConsentAcceptanceReaderInterface answers the only question a guard asks — was this
 * ticked — and that is deliberately all a module rebinding it has to implement. Freezing
 * the proof onto an order needs more: the wording the buyer had in front of them and the
 * moment they answered, because an order stating that a buyer agreed to a sentence they
 * never read proves nothing. Once the proof is written the answers are forgotten, so
 * that the next order placed asks again.
 *
 * @phpstan-type ConsentAnswer array{accepted: bool, title: string, description: string, answeredAt: \DateTimeImmutable}
 */
interface ConsentAnswerStoreInterface extends ConsentAcceptanceReaderInterface
{
    /**
     * @return array<string, ConsentAnswer> by consent code
     */
    public function answers(): array;

    public function clear(): void;
}
