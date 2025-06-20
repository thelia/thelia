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
    /** @var string */
    protected $code;

    /** @var string */
    protected $namespace;

    /** @var string */
    protected $type;

    /** @var string */
    protected $logo;

    /** @var array */
    protected $languages = [];

    /** @var array */
    protected $descriptives = [];

    /** @var string */
    protected $theliaVersion;

    /** @var string */
    protected $version;

    /** @var array */
    protected $dependencies = [];

    /** @var string */
    protected $documentation;

    /** @var string */
    protected $stability;

    /** @var array */
    protected $authors = [];

    /**
     * @return array
     */
    public function getAuthors()
    {
        return $this->authors;
    }

    /**
     * @param array $authors
     */
    public function setAuthors($authors): void
    {
        $this->authors = $authors;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode($code): void
    {
        $this->code = $code;
    }

    /**
     * @return array
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * @param array $dependencies
     */
    public function setDependencies($dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    /**
     * @return array
     */
    public function getDescriptives()
    {
        return $this->descriptives;
    }

    /**
     * @param array $descriptives
     */
    public function setDescriptives($descriptives): void
    {
        $this->descriptives = $descriptives;
    }

    /**
     * @return string
     */
    public function getDocumentation()
    {
        return $this->documentation;
    }

    /**
     * @param string $documentation
     */
    public function setDocumentation($documentation): void
    {
        $this->documentation = $documentation;
    }

    /**
     * @return array
     */
    public function getLanguages()
    {
        return $this->languages;
    }

    /**
     * @param array $languages
     */
    public function setLanguages($languages): void
    {
        $this->languages = $languages;
    }

    /**
     * @return string
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * @param string $logo
     */
    public function setLogo($logo): void
    {
        $this->logo = $logo;
    }

    /**
     * @return string
     */
    public function getTheliaVersion()
    {
        return $this->theliaVersion;
    }

    /**
     * @param string $theliaVersion
     */
    public function setTheliaVersion($theliaVersion): void
    {
        $this->theliaVersion = $theliaVersion;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     */
    public function setNamespace($namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * @return string
     */
    public function getStability()
    {
        return $this->stability;
    }

    /**
     * @param string $stability
     */
    public function setStability($stability): void
    {
        $this->stability = $stability;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion($version): void
    {
        $this->version = $version;
    }
}
