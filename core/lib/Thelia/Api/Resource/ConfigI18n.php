<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ConfigI18n extends I18n
{
    #[Groups([Config::GROUP_READ, Config::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([Config::GROUP_READ, Config::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([Config::GROUP_READ, Config::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([Config::GROUP_READ, Config::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): ConfigI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): ConfigI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): ConfigI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): ConfigI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
