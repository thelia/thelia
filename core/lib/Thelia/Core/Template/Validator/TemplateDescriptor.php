<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Validator;

use Thelia\Core\Template\TemplateDefinition;

/**
 * Class TemplateDescriptor.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class TemplateDescriptor
{
    /** @var string the template directory name (e.g. 'default') */
    protected $name;

    /** @var int the template type (front, back, pdf) */
    protected $type;

    /** @var array */
    protected $languages = [];

    /** @var array */
    protected $descriptives = [];

    /** @var string */
    protected $theliaVersion;

    /** @var string */
    protected $version;

    /** @var TemplateDefinition */
    protected $parent;

    /** @var string */
    protected $documentation;

    /** @var string */
    protected $stability;

    /** @var array */
    protected $authors = [];

    /**
     * TemplateDescriptor constructor.
     *
     * @param string $name
     * @param int    $type
     */
    public function __construct($name, $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
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
     *
     * @return $this
     */
    public function setLanguages($languages)
    {
        $this->languages = $languages;

        return $this;
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
     *
     * @return $this
     */
    public function setDescriptives($descriptives)
    {
        $this->descriptives = $descriptives;

        return $this;
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
     *
     * @return $this
     */
    public function setTheliaVersion($theliaVersion)
    {
        $this->theliaVersion = $theliaVersion;

        return $this;
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
     *
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return TemplateDefinition
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param TemplateDefinition $parent
     *
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    public function hasParent()
    {
        return null !== $this->parent;
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
     *
     * @return $this
     */
    public function setDocumentation($documentation)
    {
        $this->documentation = $documentation;

        return $this;
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
     *
     * @return $this
     */
    public function setStability($stability)
    {
        $this->stability = $stability;

        return $this;
    }

    /**
     * @return array
     */
    public function getAuthors()
    {
        return $this->authors;
    }

    /**
     * @param array $authors
     *
     * @return $this
     */
    public function setAuthors($authors)
    {
        $this->authors = $authors;

        return $this;
    }
}
