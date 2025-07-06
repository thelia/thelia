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

class CustomerTitleI18n extends I18n
{
    #[Groups([
        CustomerTitle::GROUP_ADMIN_READ,
        Customer::GROUP_ADMIN_READ_SINGLE,
        CustomerTitle::GROUP_ADMIN_READ_SINGLE,
        CustomerTitle::GROUP_ADMIN_WRITE,
        Address::GROUP_ADMIN_READ,
        CustomerTitle::GROUP_FRONT_READ,
        Customer::GROUP_FRONT_READ_SINGLE,
    ])]
    protected ?string $short = null;

    #[Groups([
        CustomerTitle::GROUP_ADMIN_READ,
        Customer::GROUP_ADMIN_READ_SINGLE,
        CustomerTitle::GROUP_ADMIN_READ_SINGLE,
        CustomerTitle::GROUP_ADMIN_WRITE,
        CustomerTitle::GROUP_FRONT_READ,
        Customer::GROUP_FRONT_READ_SINGLE,
    ])]
    protected ?string $long = null;

    public function getShort(): ?string
    {
        return $this->short;
    }

    public function setShort(?string $short): self
    {
        $this->short = $short;

        return $this;
    }

    public function getLong(): ?string
    {
        return $this->long;
    }

    public function setLong(?string $long): self
    {
        $this->long = $long;

        return $this;
    }
}
