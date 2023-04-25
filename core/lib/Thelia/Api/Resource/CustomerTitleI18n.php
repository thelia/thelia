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
    #[Groups([CustomerTitle::GROUP_READ])]
    protected string $locale;

    #[Groups([CustomerTitle::GROUP_READ, Customer::GROUP_READ_SINGLE, CustomerTitle::GROUP_READ_SINGLE, CustomerTitle::GROUP_WRITE, Address::GROUP_READ])]
    protected ?string $short;

    #[Groups([CustomerTitle::GROUP_READ, Customer::GROUP_READ_SINGLE, CustomerTitle::GROUP_READ_SINGLE, CustomerTitle::GROUP_WRITE])]
    protected ?string $long;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

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
