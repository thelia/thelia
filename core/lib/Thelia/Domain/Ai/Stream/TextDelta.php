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

namespace Thelia\Domain\Ai\Stream;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A piece of the text, to show as it comes.
 */
#[Exclude]
final readonly class TextDelta implements StreamEvent
{
    public function __construct(public string $text)
    {
    }
}
