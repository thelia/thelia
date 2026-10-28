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
 * Data the model must read but never obey: a web page, a review, a competitor offer. Never paste it into
 * the system prompt or a user message: the gateway wraps it in delimiters naming its source and tells the
 * model that what is inside is data, not instructions.
 */
#[Exclude]
final readonly class UntrustedContent
{
    /**
     * @param string|null $reference where it comes from, for the model and the log of the prompt: a URL,
     *                               an offer identifier; never a person's name or email
     */
    public function __construct(
        public UntrustedSource $source,
        public string $content,
        public ?string $reference = null,
    ) {
        AiAssert::notBlank($content, 'Untrusted content');

        if (null !== $reference && ('' === $reference || \strlen($reference) > 2048)) {
            throw new Exception\InvalidAiArgumentException('The reference of untrusted content holds 1 to 2048 bytes.');
        }
    }
}
