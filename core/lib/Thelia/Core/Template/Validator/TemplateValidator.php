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

use Thelia\Core\Template\Exception\TemplateException;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\Version\Version;

/**
 * Class TemplateValidator.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class TemplateValidator
{
    protected $templatePath;

    /** @var TemplateDescriptorValidator */
    protected $templateDescriptor;

    /** @var TemplateDefinition */
    protected $templateDefinition;

    protected $templateVersion;

    /** @var array array of errors */
    protected $errors = [];

    /** @var \SimpleXMLElement */
    protected $xmlDescriptorContent;

    /**
     * TemplateValidator constructor.
     *
     * @param $templatePath
     *
     * @throws \Exception
     */
    public function __construct($templatePath)
    {
        $templateValidator = new TemplateDescriptorValidator($templatePath.DS.'template.xml');

        $this->xmlDescriptorContent = $templateValidator->getDescriptor();
    }

    public function getTemplateVersion()
    {
        return $this->templateVersion;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param string $name the template directory name
     * @param int    $type the template type (front, back, etc.)
     *
     * @return TemplateDescriptor the template descriptor
     *
     * @throws \Exception
     */
    public function getTemplateDefinition(string $name, int $type)
    {
        $templateDescriptor = new TemplateDescriptor($name, $type);

        $templateDescriptor
            ->setName($name)
            ->setType($type)
        ;

        if (!empty($this->xmlDescriptorContent)) {
            $templateDescriptor
                ->setVersion((string) $this->xmlDescriptorContent->version)
                ->setLanguages($this->getTemplateLanguages())
                ->setDescriptives($this->getTemplateDescriptives())
                ->setAuthors($this->getTemplateAuthors())
                ->setTheliaVersion((string) $this->xmlDescriptorContent->thelia)
                ->setStability((string) $this->xmlDescriptorContent->stability)
                ->setDocumentation((string) $this->xmlDescriptorContent->documentation)
                ->setAssets((string) $this->xmlDescriptorContent->assets)
            ;

            $this->checkVersion($templateDescriptor);

            if (!empty($this->xmlDescriptorContent->parent)) {
                // Just try to instantiate template definition for the parent template
                // An exception will be thrown if something goes wrong.
                try {
                    $templateDescriptor->setParent(
                        new TemplateDefinition(
                            (string) $this->xmlDescriptorContent->parent,
                            $type
                        )
                    );
                } catch (\Exception $ex) {
                    // The Translator could not be initialized, take care of this.
                    try {
                        $message = Translator::getInstance()->trans(
                            'The parent template "%parent" of template "%name" could not be found',
                            [
                                '%parent' => $templateDescriptor->getParent()->getName(),
                                '%name' => $templateDescriptor->getName(),
                            ]
                        );
                    } catch (\Exception $ex) {
                        $message = sprintf(
                            'The parent template "%s" of template "%s" could not be found',
                            $templateDescriptor->getParent()->getName(),
                            $templateDescriptor->getName()
                        );
                    }

                    throw new TemplateException($message);
                }
            }
        }

        return $templateDescriptor;
    }

    /**
     * @param TemplateDescriptor $templateDescriptor
     */
    protected function checkVersion($templateDescriptor): void
    {
        if ($templateDescriptor->getTheliaVersion()) {
            if (!Version::test(Thelia::THELIA_VERSION, $templateDescriptor->getTheliaVersion(), false, '>=')) {
                // The Translator could not be initialized, take care of this.
                try {
                    $message = Translator::getInstance()->trans(
                        'The template "%name" requires Thelia %version or newer',
                        [
                            '%name' => $templateDescriptor->getName(),
                            '%version' => $templateDescriptor->getTheliaVersion(),
                        ]
                    );
                } catch (\Exception $ex) {
                    $message = sprintf(
                        'The template "%s" requires Thelia %s or newer',
                        $templateDescriptor->getName(),
                        $templateDescriptor->getTheliaVersion()
                    );
                }

                throw new TemplateException($message);
            }
        }
    }

    protected function getTemplateLanguages()
    {
        $languages = [];
        foreach ($this->xmlDescriptorContent->languages->language as $language) {
            $languages[] = (string) $language;
        }

        return $languages;
    }

    protected function getTemplateDescriptives()
    {
        $descriptives = [];
        foreach ($this->xmlDescriptorContent->descriptive as $descriptive) {
            $descriptives[(string) $descriptive['locale']] = [
                'title' => (string) $descriptive->title,
                'subtitle' => (string) $descriptive->subtitle,
                'description' => (string) $descriptive->description,
                'postscriptum' => (string) $descriptive->postscriptum,
            ];
        }

        return $descriptives;
    }

    protected function getTemplateAuthors()
    {
        $authors = [];

        if (0 !== \count($this->xmlDescriptorContent->authors->author)) {
            foreach ($this->xmlDescriptorContent->authors->author as $author) {
                $authors[] = [
                    (string) $author->name,
                    (string) $author->company,
                    (string) $author->email,
                    (string) $author->website,
                ];
            }
        }

        return $authors;
    }
}
