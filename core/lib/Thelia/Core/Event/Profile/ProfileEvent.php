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

namespace Thelia\Core\Event\Profile;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Profile;

class ProfileEvent extends ActionEvent
{
    protected $id;
    protected $locale;
    protected $code;
    protected $title;
    protected $chapo;
    protected $description;
    protected $postscriptum;
    protected $resourceAccess;
    protected $moduleAccess;

    public function __construct(protected ?Profile $profile = null)
    {
    }

    public function hasProfile(): bool
    {
        return $this->profile instanceof Profile;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): static
    {
        $this->profile = $profile;

        return $this;
    }

    public function setId($id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setCode($code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setChapo($chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setDescription($description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setPostscriptum($postscriptum): static
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setResourceAccess($resourceAccess): static
    {
        $this->resourceAccess = $resourceAccess;

        return $this;
    }

    public function getResourceAccess()
    {
        return $this->resourceAccess;
    }

    public function setModuleAccess($moduleAccess): static
    {
        $this->moduleAccess = $moduleAccess;

        return $this;
    }

    public function getModuleAccess()
    {
        return $this->moduleAccess;
    }
}
