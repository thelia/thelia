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

namespace Thelia\Core\Template;

class TemplateDefinition
{
    const FRONT_OFFICE = 1;
    const BACK_OFFICE = 2;
    const PDF = 3;
    const EMAIL = 4;

    const FRONT_OFFICE_SUBDIR = 'frontOffice';
    const BACK_OFFICE_SUBDIR = 'backOffice';
    const PDF_SUBDIR = 'pdf';
    const EMAIL_SUBDIR = 'email';

    protected static $standardTemplatesSubdirs = array(
        self::FRONT_OFFICE => self::FRONT_OFFICE_SUBDIR,
        self::BACK_OFFICE  => self::BACK_OFFICE_SUBDIR,
        self::PDF          => self::PDF_SUBDIR,
        self::EMAIL        => self::EMAIL_SUBDIR,
    );

    /**
     * @var string the template directory name (e.g. 'default')
     */
    protected $name;

    /**
     * @var string the template directory full path
     */
    protected $path;

    /**
     * @var int the template type (front, back, pdf)
     */
    protected $type;

    protected $translationDomainPrefix;

    public function __construct($name, $type)
    {
        $this->name = $name;
        $this->type = $type;

        switch ($type) {
            case TemplateDefinition::FRONT_OFFICE:
                $this->path = self::FRONT_OFFICE_SUBDIR . DS . $name;
                $this->translationDomainPrefix = 'fo.';
                break;
            case TemplateDefinition::BACK_OFFICE:
                $this->path = self::BACK_OFFICE_SUBDIR . DS . $name;
                $this->translationDomainPrefix = 'bo.';
                break;
            case TemplateDefinition::PDF:
                $this->path = self::PDF_SUBDIR . DS . $name;
                $this->translationDomainPrefix = 'pdf.';
                break;
            case TemplateDefinition::EMAIL:
                $this->path = self::EMAIL_SUBDIR . DS . $name;
                $this->translationDomainPrefix = 'email.';
                break;
            default:
                $this->path = $name;
                $this->translationDomainPrefix = 'generic.';
                break;
        }
    }

    public function getTranslationDomain()
    {
        return $this->translationDomainPrefix . strtolower($this->getName());
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getI18nPath()
    {
        return $this->getPath() . DS . 'I18n';
    }

    public function getAbsoluteI18nPath()
    {
        return THELIA_TEMPLATE_DIR . $this->getI18nPath();
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getAbsolutePath()
    {
        return THELIA_TEMPLATE_DIR . $this->getPath();
    }

    public function getConfigPath()
    {
        return $this->getPath() . DS . 'configs';
    }

    public function getAbsoluteConfigPath()
    {
        return THELIA_TEMPLATE_DIR . $this->getConfigPath();
    }

    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Returns an iterator on the standard templates subdir names
     */
    public static function getStandardTemplatesSubdirsIterator()
    {
        return new \ArrayIterator(self::$standardTemplatesSubdirs);
    }
}
