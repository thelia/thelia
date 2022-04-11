<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

abstract class I18n
{
    private int $id;

    private string $locale;

    private ?string $title;

    private ?string $chapo;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     * @return I18n
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getChapo(): string
    {
        return $this->chapo;
    }

    /**
     * @param string|null $chapo
     * @return I18n
     */
    public function setChapo(?string $chapo): self
    {
        $this->chapo = $chapo;
        return $this;
    }
}
