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

    protected $code;

    protected $namespace;

    protected $type;

    protected $logo;

    protected $languages;

    protected $descriptives;

    protected $minVersion;

    protected $maxVersion;

    protected $version;

    protected $dependencies;

    protected $documentation;

    protected $stability;

    protected $authors;

    /**
     * @param mixed $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $authors
     */
    public function setAuthors($authors)
    {
        $this->authors = $authors;
    }

    /**
     * @return mixed
     */
    public function getAuthors()
    {
        return $this->authors;
    }

    /**
     * @param mixed $descriptives
     */
    public function setDescriptives($descriptives)
    {
        $this->descriptives = $descriptives;
    }

    /**
     * @return mixed
     */
    public function getDescriptives()
    {
        return $this->descriptives;
    }

    /**
     * @param mixed $documentation
     */
    public function setDocumentation($documentation)
    {
        $this->documentation = $documentation;
    }

    /**
     * @return mixed
     */
    public function getDocumentation()
    {
        return $this->documentation;
    }

    /**
     * @param mixed $languages
     */
    public function setLanguages($languages)
    {
        $this->languages = $languages;
    }

    /**
     * @return mixed
     */
    public function getLanguages()
    {
        return $this->languages;
    }

    /**
     * @param mixed $logo
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;
    }

    /**
     * @return mixed
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * @param mixed $maxVersion
     */
    public function setMaxVersion($maxVersion)
    {
        $this->maxVersion = $maxVersion;
    }

    /**
     * @return mixed
     */
    public function getMaxVersion()
    {
        return $this->maxVersion;
    }

    /**
     * @param mixed $minVersion
     */
    public function setMinVersion($minVersion)
    {
        $this->minVersion = $minVersion;
    }

    /**
     * @return mixed
     */
    public function getMinVersion()
    {
        return $this->minVersion;
    }

    /**
     * @param mixed $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @return mixed
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param mixed $stability
     */
    public function setStability($stability)
    {
        $this->stability = $stability;
    }

    /**
     * @return mixed
     */
    public function getStability()
    {
        return $this->stability;
    }

    /**
     * @param mixed $tags
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
    }

    /**
     * @return mixed
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param mixed $dependencies
     */
    public function setDependencies($dependencies)
    {
        $this->dependencies = $dependencies;
    }

    /**
     * @return mixed
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

}
