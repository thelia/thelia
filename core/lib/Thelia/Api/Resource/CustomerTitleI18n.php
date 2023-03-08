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

class CustomerTitleI18n extends I18n
{
    #[Groups([I18n::GROUP_READ, CustomerTitle::GROUP_READ, CustomerTitle::GROUP_WRITE, Address::GROUP_READ])]
    private ?string $short;

    #[Groups([CustomerTitle::GROUP_READ, CustomerTitle::GROUP_WRITE])]
    private ?string $long;

    public function getShort(): ?string
    {
        return $this->short;
    }

    public function setShort(?string $short): CustomerTitleI18n
    {
        $this->short = $short;
        return $this;
    }

    public function getLong(): ?string
    {
        return $this->long;
    }

    public function setLong(?string $long): CustomerTitleI18n
    {
        $this->long = $long;
        return $this;
    }
}

