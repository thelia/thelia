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

namespace Thelia\Domain\Media\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class ImageUpdateDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $locale,
        public ?string $title = null,
        public ?string $chapo = null,
        public ?string $description = null,
        public ?string $postscriptum = null,
        public ?bool $visible = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'title' => $this->title,
            'chapo' => $this->chapo,
            'description' => $this->description,
            'postscriptum' => $this->postscriptum,
            'visible' => $this->visible,
        ];
    }
}
