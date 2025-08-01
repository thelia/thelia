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

namespace Thelia\Core\Event\Hook;

/**
 * Class HookCreateEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookCreateAllEvent extends HookEvent
{
    protected string $locale;
    protected string $code;
    protected int $type;
    protected bool $native;
    protected bool $active;
    protected bool $by_module;
    protected bool $block;
    protected string $title;
    protected ?string $chapo;
    protected ?string $description;

    /**
     * @return $this
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return $this
     */
    public function setNative(bool $native): static
    {
        $this->native = $native;

        return $this;
    }

    public function getNative(): bool
    {
        return $this->native;
    }

    /**
     * @return $this
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return $this
     */
    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return $this
     */
    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    /**
     * @return $this
     */
    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return $this
     */
    public function setBlock(bool $block): static
    {
        $this->block = $block;

        return $this;
    }

    public function getBlock(): bool
    {
        return $this->block;
    }

    /**
     * @return $this
     */
    public function setByModule(bool $by_module): static
    {
        $this->by_module = $by_module;

        return $this;
    }

    public function getByModule(): bool
    {
        return $this->by_module;
    }

    /**
     * @return $this
     */
    public function setChapo(?string $chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    /**
     * @return $this
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
