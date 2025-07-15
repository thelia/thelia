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

namespace Thelia\Module\Validator;

/**
 * Class ModuleDefinition.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleDefinition
{
    protected string $code;
    protected string $namespace;
    protected string $type;
    protected string $logo;
    protected array $languages = [];
    protected array $descriptives = [];
    protected string $theliaVersion;
    protected string $version;
    protected array $dependencies = [];
    protected string $documentation;
    protected string $stability;
    protected array $authors = [];

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function setAuthors(array $authors): void
    {
        $this->authors = $authors;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    public function getDescriptives(): array
    {
        return $this->descriptives;
    }

    public function setDescriptives(array $descriptives): void
    {
        $this->descriptives = $descriptives;
    }

    public function getDocumentation(): string
    {
        return $this->documentation;
    }

    public function setDocumentation(string $documentation): void
    {
        $this->documentation = $documentation;
    }

    public function getLanguages(): array
    {
        return $this->languages;
    }

    public function setLanguages(array $languages): void
    {
        $this->languages = $languages;
    }

    public function getLogo(): string
    {
        return $this->logo;
    }

    public function setLogo(string $logo): void
    {
        $this->logo = $logo;
    }

    public function getTheliaVersion(): string
    {
        return $this->theliaVersion;
    }

    public function setTheliaVersion(string $theliaVersion): void
    {
        $this->theliaVersion = $theliaVersion;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getStability(): string
    {
        return $this->stability;
    }

    public function setStability(string $stability): void
    {
        $this->stability = $stability;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }
}
