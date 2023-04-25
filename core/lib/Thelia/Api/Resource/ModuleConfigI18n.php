<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ModuleConfigI18n extends I18n
{
    #[Groups([ModuleConfig::GROUP_READ, ModuleConfig::GROUP_WRITE])]
    protected ?string $value;

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): ModuleConfigI18n
    {
        $this->value = $value;
        return $this;
    }
}
