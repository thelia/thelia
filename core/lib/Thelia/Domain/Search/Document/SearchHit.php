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

namespace Thelia\Domain\Search\Document;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class SearchHit
{
    public function __construct(
        public string $family,
        public int $id,
        public float $score,
    ) {
    }
}
