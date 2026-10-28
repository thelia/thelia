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

namespace Thelia\Domain\Ai;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * A cost a module paid outside the gateway (a scoring API, a search API), written to the usage log so that
 * the budget of the usage and the dashboard count it. See AiCostRecorderInterface.
 */
#[Exclude]
final readonly class AiExternalCost
{
    /** The size of the column `ai_usage_log.provider`. */
    public const int PROVIDER_MAXIMUM_LENGTH = 32;

    /**
     * @param string $provider  the service paid, lower case, 32 characters at most: `typesafe`, `dataforseo`
     * @param string $operation what was bought: a model, an endpoint, like `jev-latest`
     * @param string $cost      decimal string in $currency, like `0.00009870`; print a float with sprintf('%.8F', $amount),
     *                          `(string) 1.2E-7` is not a decimal and is refused
     */
    public function __construct(
        public string $usageId,
        public string $provider,
        public string $operation,
        public string $cost,
        public string $currency = 'USD',
        public AiUsageLogStatus $status = AiUsageLogStatus::Succeeded,
        public ?AiTokenUsage $tokenUsage = null,
        public ?int $latencyMilliseconds = null,
        public ?string $correlationId = null,
        public ?AiActor $actor = null,
    ) {
        AiAssert::usageId($usageId);
        self::assertProvider($provider);
        AiAssert::notBlank($operation, 'The operation of an external cost');
        AiAssert::decimal($cost, 'The cost');
        AiAssert::currency($currency);

        if (\in_array($status, [AiUsageLogStatus::Blocked, AiUsageLogStatus::Rejected, AiUsageLogStatus::Aborted], true)) {
            throw new Exception\InvalidAiArgumentException('An external cost succeeded or failed: the other statuses belong to the gateway.');
        }

        if (null !== $latencyMilliseconds) {
            AiAssert::notNegative($latencyMilliseconds, 'The latency');
        }

        if (null !== $correlationId) {
            AiAssert::token($correlationId, 'A correlation identifier');
        }
    }

    /**
     * The rule of a provider name, shared with the hold taken before the call: a hold whose provider the cost
     * would refuse could never be settled.
     *
     * @throws Exception\InvalidAiArgumentException
     */
    public static function assertProvider(string $provider): void
    {
        AiAssert::token($provider, 'The provider of an external cost');

        if (mb_strlen($provider) > self::PROVIDER_MAXIMUM_LENGTH) {
            throw new Exception\InvalidAiArgumentException(\sprintf('The provider of an external cost holds at most %d characters.', self::PROVIDER_MAXIMUM_LENGTH));
        }
    }
}
