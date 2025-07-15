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

namespace Thelia\Core\Event\Module;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Module;

/**
 * Class ModuleEvent.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleEvent extends ActionEvent
{
    protected $id;
    protected $locale;
    protected $title;
    protected $chapo;
    protected $description;
    protected $postscriptum;

    public function setChapo(?string $chapo): void
    {
        $this->chapo = $chapo;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setPostscriptum(?string $postscriptum): void
    {
        $this->postscriptum = $postscriptum;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setTitle($title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function __construct(protected ?Module $module = null)
    {
    }

    public function setModule(?Module $module): self
    {
        $this->module = $module;

        return $this;
    }

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function hasModule(): bool
    {
        return $this->module instanceof Module;
    }
}
