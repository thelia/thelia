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
namespace Thelia\Core\Template;

use ArrayIterator;
use Exception;
use Thelia\Core\Template\Exception\TemplateException;
use Thelia\Core\Template\Validator\TemplateDescriptor;
use Thelia\Core\Template\Validator\TemplateValidator;

class TemplateDefinition
{
    public const FRONT_OFFICE = 1;

    public const BACK_OFFICE = 2;

    public const PDF = 3;

    public const EMAIL = 4;

    public const FRONT_OFFICE_SUBDIR = 'frontOffice';

    public const BACK_OFFICE_SUBDIR = 'backOffice';

    public const PDF_SUBDIR = 'pdf';

    public const EMAIL_SUBDIR = 'email';

    public const FRONT_OFFICE_CONFIG_NAME = 'active-front-template';

    public const BACK_OFFICE_CONFIG_NAME = 'active-admin-template';

    public const PDF_CONFIG_NAME = 'active-pdf-template';

    public const EMAIL_CONFIG_NAME = 'active-email-template';

    public const CONFIG_NAMES = [
        self::FRONT_OFFICE_SUBDIR => self::FRONT_OFFICE_CONFIG_NAME,
        self::BACK_OFFICE_SUBDIR => self::BACK_OFFICE_CONFIG_NAME,
        self::PDF_SUBDIR => self::PDF_CONFIG_NAME,
        self::EMAIL_SUBDIR => self::EMAIL_CONFIG_NAME,
    ];

    protected ?string $path = null;

    protected ?TemplateDescriptor $templateDescriptor = null;

    protected ?string $translationDomainPrefix = null;

    public static array $standardTemplatesSubdirs = [
        self::FRONT_OFFICE => self::FRONT_OFFICE_SUBDIR,
        self::BACK_OFFICE => self::BACK_OFFICE_SUBDIR,
        self::PDF => self::PDF_SUBDIR,
        self::EMAIL => self::EMAIL_SUBDIR,
    ];

    protected ?array $parentList = null;

    /**
     * @param string $name the template name (= directory name)
     * @param int $type the remplate type (see $standardTemplatesSubdirs)
     *
     * @throws Exception
     */
    public function __construct(string $name, int $type)
    {
        switch ($type) {
            case self::FRONT_OFFICE:
                $this->path = self::FRONT_OFFICE_SUBDIR.DS.$name;
                $this->translationDomainPrefix = 'fo.';
                break;
            case self::BACK_OFFICE:
                $this->path = self::BACK_OFFICE_SUBDIR.DS.$name;
                $this->translationDomainPrefix = 'bo.';
                break;
            case self::PDF:
                $this->path = self::PDF_SUBDIR.DS.$name;
                $this->translationDomainPrefix = 'pdf.';
                break;
            case self::EMAIL:
                $this->path = self::EMAIL_SUBDIR.DS.$name;
                $this->translationDomainPrefix = 'email.';
                break;
            default:
                $this->path = $name;
                $this->translationDomainPrefix = 'generic.';
                break;
        }

        $this->templateDescriptor = (new TemplateValidator($this->getAbsolutePath()))->getTemplateDefinition($name, $type);
    }


    public function getParentList(): ?array
    {
        if (null === $this->parentList) {
            $this->parentList = [];

            $parent = $this->getDescriptor()?->getParent();

            for ($index = 1; null !== $parent; ++$index) {
                $this->parentList[$parent->getName().'-'] = $parent;

                $parent = $parent->getDescriptor()?->getParent();
            }
        }

        return $this->parentList;
    }

    public function getTemplateFilePath(string $templateName): string
    {
        $templateList = array_merge(
            [$this],
            $this->getParentList()
        );

        /** @var TemplateDefinition $templateDefinition */
        foreach ($templateList as $templateDefinition) {
            $templateFilePath = \sprintf(
                '%s%s/%s',
                THELIA_TEMPLATE_DIR,
                $templateDefinition->getPath(),
                $templateName
            );

            if (file_exists($templateFilePath)) {
                return $templateFilePath;
            }
        }

        throw new TemplateException('Template file not found: ' . $templateName);
    }

    public function getAssetsPath(): string
    {
        return $this->templateDescriptor->getAssets();
    }

    public function setAssetsPath($assets): static
    {
        $this->$this->templateDescriptor->setAssets($assets);

        return $this;
    }

    public function getAbsoluteAssetsPath(): string
    {
        return $this->getAbsolutePath().DS.$this->templateDescriptor->getAssets();
    }


    public function getTranslationDomain(): string
    {
        return $this->translationDomainPrefix.strtolower($this->getName());
    }

    public function getName(): string
    {
        return $this->templateDescriptor->getName();
    }

    public function setName($name): static
    {
        $this->templateDescriptor->setName($name);

        return $this;
    }

    public function getI18nPath(): string
    {
        return $this->getPath().DS.'I18n';
    }

    public function getAbsoluteI18nPath(): string
    {
        return THELIA_TEMPLATE_DIR.$this->getI18nPath();
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getAbsolutePath(): string
    {
        return THELIA_TEMPLATE_DIR.$this->getPath();
    }

    public function getConfigPath(): string
    {
        return $this->getPath().DS.'configs';
    }

    public function getAbsoluteConfigPath(): string
    {
        return THELIA_TEMPLATE_DIR.$this->getConfigPath();
    }

    public function setPath(?string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getType(): int
    {
        return $this->templateDescriptor->getType();
    }

    public function setType($type): static
    {
        $this->$this->templateDescriptor->setType($type);

        return $this;
    }

    public function getDescriptor(): ?TemplateDescriptor
    {
        return $this->templateDescriptor;
    }

    public static function getStandardTemplatesSubdirsIterator(): ArrayIterator
    {
        return new ArrayIterator(self::$standardTemplatesSubdirs);
    }
}
