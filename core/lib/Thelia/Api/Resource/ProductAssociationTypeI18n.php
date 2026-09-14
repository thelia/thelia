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

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ProductAssociationTypeI18n extends I18n
{
    /**
     * The heading a theme prints above the block of that type — "Accessories",
     * "Complementary products" — as opposed to its code, which is what the core
     * and the front-office blocks address the type by and which never changes.
     */
    #[Groups([
        ProductAssociationType::GROUP_ADMIN_READ,
        ProductAssociationType::GROUP_FRONT_READ,
        ProductAssociationType::GROUP_ADMIN_WRITE,
        ProductAssociation::GROUP_ADMIN_READ,
        ProductAssociation::GROUP_FRONT_READ,
    ])]
    protected ?string $title = null;

    #[Groups([
        ProductAssociationType::GROUP_ADMIN_READ,
        ProductAssociationType::GROUP_FRONT_READ,
        ProductAssociationType::GROUP_ADMIN_WRITE,
        ProductAssociation::GROUP_ADMIN_READ,
        ProductAssociation::GROUP_FRONT_READ,
    ])]
    protected ?string $description = null;

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
