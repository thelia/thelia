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
 * No service declares this usage: the module is off, or the identifier is misspelt.
 */
#[Exclude]
final class UnknownAiUsageException extends \LogicException implements AiExceptionInterface
{
    public function __construct(public readonly string $usageId)
    {
        parent::__construct(\sprintf('No AI usage "%s" is declared.', $usageId));
    }
}
