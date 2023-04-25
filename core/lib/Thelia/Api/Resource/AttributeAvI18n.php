<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class AttributeAvI18n extends I18n
{
    #[Groups([AttributeAv::GROUP_READ, AttributeAv::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([AttributeAv::GROUP_READ, AttributeAv::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([AttributeAv::GROUP_READ, AttributeAv::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([AttributeAv::GROUP_READ, AttributeAv::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): AttributeAvI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): AttributeAvI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): AttributeAvI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): AttributeAvI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
