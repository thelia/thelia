<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class TaxI18n extends I18n
{
    #[Groups([Tax::GROUP_ADMIN_READ, Tax::GROUP_FRONT_READ, Tax::GROUP_ADMIN_WRITE])]
    protected ?string $title;

    #[Groups([Tax::GROUP_ADMIN_READ, Tax::GROUP_FRONT_READ, Tax::GROUP_ADMIN_WRITE])]
    protected ?string $description;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
