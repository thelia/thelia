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

namespace Thelia\Domain\Ai\Tool;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * What a write tool proposes, as the administrator will read it.
 *
 * The summary is written by the tool from a request of the model: the back office shows it next to the full
 * arguments and the `after` state, always escaped, because it is not enough to decide on.
 */
#[Exclude]
final readonly class AiChangeProposal
{
    public const int MAXIMUM_STATE_BYTES = 32768;

    /**
     * @param string               $target  what the change touches, at most 128 bytes: `product_sale_elements:42`
     * @param string               $summary plain text, at most 512 characters: `Price of MUG-THELIA: 16.500000 → 14.90 EUR excl. tax`
     * @param array<string, mixed> $before  the state the change starts from, JSON; apply() checks it still holds
     * @param array<string, mixed> $after   the state the change leads to, JSON
     */
    public function __construct(
        public string $target,
        public string $summary,
        public array $before,
        public array $after,
    ) {
        AiAssert::notBlank($target, 'The target of a proposal');
        AiAssert::notBlank($summary, 'The summary of a proposal');

        if (\strlen($target) > 128) {
            throw new InvalidAiArgumentException('The target of a proposal holds at most 128 bytes.');
        }

        if (mb_strlen($summary) > 512) {
            throw new InvalidAiArgumentException('The summary of a proposal holds at most 512 characters.');
        }

        foreach (['before' => $before, 'after' => $after] as $name => $state) {
            $encoded = json_encode($state, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

            if (false === $encoded || \strlen($encoded) > self::MAXIMUM_STATE_BYTES) {
                throw new InvalidAiArgumentException(\sprintf('The "%s" state of a proposal must be JSON of at most %d bytes.', $name, self::MAXIMUM_STATE_BYTES));
            }
        }
    }
}
