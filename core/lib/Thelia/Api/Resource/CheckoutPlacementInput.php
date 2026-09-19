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

namespace Thelia\Api\Resource;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * What a placement may carry, which is what the operations before it had nowhere to put:
 * the answers the buyer gave to the consents the shop asks for.
 *
 * Everything else the order is built from is on the cart, and stays there — an amount, a
 * carrier or an address stated at the last moment is exactly what must not be takeable.
 * A consent is the one thing that cannot be: the shop keeps no record of what a cart
 * abandoned at the payment step agreed to, so there is nowhere to write the answer down
 * before the order exists, and the request that writes the order is the one that carries
 * it.
 *
 * The body stays optional, because a shop asking for nothing has nothing to answer: a
 * placement posted without one is the placement this endpoint has always taken.
 */
final readonly class CheckoutPlacementInput
{
    /**
     * @param array<string, bool> $consentAnswers by consent code
     */
    private function __construct(
        public array $consentAnswers,
    ) {
    }

    /**
     * @throws BadRequestHttpException          when the body is not JSON at all
     * @throws UnprocessableEntityHttpException when it is JSON that does not say what a consent answer says
     */
    public static function ofRequest(?Request $request): self
    {
        $body = trim((string) $request?->getContent());

        if ('' === $body) {
            return new self([]);
        }

        try {
            $decoded = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $malformed) {
            throw new BadRequestHttpException('The body of the request is not valid JSON.', $malformed);
        }

        if (!\is_array($decoded) || (array_is_list($decoded) && [] !== $decoded)) {
            throw new BadRequestHttpException('The body of the request must be a JSON object.');
        }

        return new self(self::consentAnswersOf($decoded['consents'] ?? []));
    }

    /**
     * A code answered twice is refused rather than settled by taking the last one: two
     * contradictory answers to the same box are not something to guess the meaning of
     * when the answer is going to be frozen on the order as a proof.
     *
     * @return array<string, bool> by consent code
     *
     * @throws UnprocessableEntityHttpException
     */
    private static function consentAnswersOf(mixed $consents): array
    {
        if (null === $consents) {
            return [];
        }

        if (!\is_array($consents) || !array_is_list($consents)) {
            throw new UnprocessableEntityHttpException('"consents" must be a list of {"code", "accepted"} objects.');
        }

        $answers = [];

        foreach ($consents as $consent) {
            if (!\is_array($consent) || !\is_string($consent['code'] ?? null) || '' === $consent['code'] || !\is_bool($consent['accepted'] ?? null)) {
                throw new UnprocessableEntityHttpException('Every consent answer must carry a non-empty "code" and a boolean "accepted".');
            }

            if (\array_key_exists($consent['code'], $answers)) {
                throw new UnprocessableEntityHttpException('A consent must be answered once and only once.');
            }

            $answers[$consent['code']] = $consent['accepted'];
        }

        return $answers;
    }
}
