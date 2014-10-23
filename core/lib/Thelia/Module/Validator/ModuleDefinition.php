<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Module\Validator;

/**
 * Class ModuleDefinition
 * @package Thelia\Module\Validator
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
    public function setAuthors($authors)
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
    public function setCode($code)
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
    public function setDependencies($dependencies)
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
    public function setDescriptives($descriptives)
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
    public function setDocumentation($documentation)
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
    public function setLanguages($languages)
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
    public function setLogo($logo)
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
    public function setTheliaVersion($theliaVersion)
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
    public function setNamespace($namespace)
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
    public function setStability($stability)
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
    public function setType($type)
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
    public function setVersion($version)
    {
        $this->version = $version;
    }
}
