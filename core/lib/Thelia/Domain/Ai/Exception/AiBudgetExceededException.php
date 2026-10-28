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

namespace Thelia\Domain\Ai\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The cost reserved for the call does not fit in the monthly budget of the usage, or in the global
 * monthly cap of the shop when $globalCap is true. It fits again next month or when the merchant raises it.
 */
#[Exclude]
final class AiBudgetExceededException extends \RuntimeException implements AiExceptionInterface
{
    public function __construct(public readonly string $usageId, public readonly bool $globalCap = false)
    {
        parent::__construct($globalCap
            ? \sprintf('The global monthly AI cap is reached (usage "%s").', $usageId)
            : \sprintf('The monthly budget of the AI usage "%s" is reached.', $usageId));
    }
}
