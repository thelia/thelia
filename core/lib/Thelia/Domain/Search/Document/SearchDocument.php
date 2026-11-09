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
use Thelia\Domain\Search\Contract\SearchScope;

#[Exclude]
final readonly class SearchDocument
{
    /**
     * @param array<string, string> $fields
     * @param array<string, int>    $weights
     */
    public function __construct(
        public string $family,
        public int $id,
        public ?string $locale,
        public SearchScope $scope,
        public bool $visible,
        public array $fields,
        public array $weights = [],
    ) {
    }

    public function weightOf(string $field): int
    {
        return $this->weights[$field] ?? 1;
    }
}
