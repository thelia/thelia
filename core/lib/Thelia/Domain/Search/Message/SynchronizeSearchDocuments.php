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

namespace Thelia\Domain\Search\Message;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Messenger\Message\DescribedJob;

#[Exclude]
final readonly class SynchronizeSearchDocuments implements DescribedJob
{
    /**
     * @param list<int>         $ids
     * @param list<string>|null $locales
     */
    public function __construct(
        public string $family,
        public array $ids,
        public ?array $locales = null,
    ) {
    }

    public function describe(): string
    {
        return \sprintf('Search index of %d %s document(s)', \count($this->ids), $this->family);
    }
}
