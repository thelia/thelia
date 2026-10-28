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
 * The request asks for something its usage did not declare: tools, a schema, a stream, a provider tool.
 */
#[Exclude]
final class AiUsageViolationException extends \LogicException implements AiExceptionInterface
{
    public function __construct(public readonly string $usageId, string $reason)
    {
        parent::__construct(\sprintf('The AI usage "%s" does not allow this request: %s', $usageId, $reason));
    }
}
