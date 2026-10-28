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

namespace Thelia\Domain\Ai\Catalog;

use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiDecimal;

/**
 * What a call costs, from the tokens and the prices of the catalogue (USD per million tokens), or as the
 * provider announced it.
 *
 * A cached token the catalogue gives no price for is counted at the price of an input token: the amount is
 * never below what was paid. A call is rounded half up to the 8 decimals of the log.
 *
 * @internal
 */
final readonly class AiCostCalculator
{
    /** The characters per token the layer assumes before a provider has counted them. */
    public const int CHARACTERS_PER_TOKEN = 3;

    private const int PRICE_SCALE = 6;

    /** One token at the price of a million, in units of the price scale, to the units of the log scale. */
    private const int PRICE_TO_COST = 10 ** (2 * self::PRICE_SCALE - AiDecimal::SCALE);

    /**
     * @param string|null $announcedCost what the provider says the call cost: kept as it is when it is a decimal
     *
     * @return string decimal string with the 8 decimals of the log
     */
    public function cost(AiModelSpecification $model, AiTokenUsage $tokens, ?string $announcedCost = null): string
    {
        if (null !== $announcedCost) {
            try {
                return AiDecimal::format(AiDecimal::parse($announcedCost));
            } catch (InvalidAiArgumentException) {
                // A figure the layer cannot read is not a cost: the catalogue prices the call.
            }
        }

        $input = AiDecimal::parse($model->inputPrice, self::PRICE_SCALE);
        $cacheRead = null === $model->cacheReadPrice ? $input : AiDecimal::parse($model->cacheReadPrice, self::PRICE_SCALE);
        $cacheWrite = null === $model->cacheWritePrice ? $input : AiDecimal::parse($model->cacheWritePrice, self::PRICE_SCALE);

        $total = $tokens->inputTokens * $input
            + $tokens->outputTokens * AiDecimal::parse($model->outputPrice, self::PRICE_SCALE)
            + $tokens->cacheReadTokens * $cacheRead
            + $tokens->cacheWriteTokens * $cacheWrite;

        return AiDecimal::format(intdiv($total + intdiv(self::PRICE_TO_COST, 2), self::PRICE_TO_COST));
    }

    /**
     * The highest a call can cost: what is held in the budget before the call runs. The input is counted from
     * the characters (three per token), the output at its limit.
     */
    public function reservation(AiModelSpecification $model, int $inputCharacters, int $maximumOutputTokens): string
    {
        return $this->cost($model, new AiTokenUsage(self::tokensOf($inputCharacters), $maximumOutputTokens));
    }

    public static function tokensOf(int $characters): int
    {
        return intdiv($characters + self::CHARACTERS_PER_TOKEN - 1, self::CHARACTERS_PER_TOKEN);
    }
}
