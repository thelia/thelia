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
 * The usage cannot run now: turned off by the merchant or by the global switch, public without a budget,
 * or no model with the capabilities it needs. Hide the feature; asking again will not help.
 */
#[Exclude]
final class InactiveAiUsageException extends \RuntimeException implements AiExceptionInterface
{
    public function __construct(public readonly string $usageId)
    {
        parent::__construct(\sprintf('The AI usage "%s" is not active.', $usageId));
    }
}
