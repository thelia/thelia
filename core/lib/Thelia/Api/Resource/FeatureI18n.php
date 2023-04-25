<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class FeatureI18n extends I18n
{
    #[Groups([Feature::GROUP_READ, Feature::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([Feature::GROUP_READ, Feature::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([Feature::GROUP_READ, Feature::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([Feature::GROUP_READ, Feature::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): FeatureI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): FeatureI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): FeatureI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): FeatureI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
