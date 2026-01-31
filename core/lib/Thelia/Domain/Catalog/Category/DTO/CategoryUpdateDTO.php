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

readonly class CategoryUpdateDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $title,
        public string $locale,
        public int $parentId = 0,
        public bool $visible = true,
        public ?string $chapo = null,
        public ?string $description = null,
        public ?string $postscriptum = null,
        public ?int $defaultTemplateId = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'locale' => $this->locale,
            'parent' => $this->parentId,
            'visible' => $this->visible,
            'chapo' => $this->chapo,
            'description' => $this->description,
            'postscriptum' => $this->postscriptum,
            'default_template_id' => $this->defaultTemplateId,
        ];
    }
}
