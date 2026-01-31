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

namespace Thelia\Domain\Catalog\Category\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class CategorySeoDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $locale,
        public ?string $url = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $metaKeywords = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'url' => $this->url,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'meta_keywords' => $this->metaKeywords,
        ];
    }
}
