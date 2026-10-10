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

namespace Thelia\Core\Template\Validator;

use Symfony\Component\Finder\Finder;
use Thelia\Core\File\XmlDescriptor;
use Thelia\Core\Template\Exception\InvalidDescriptorException;
use Thelia\Log\Tlog;
use Thelia\Tools\TerminalText;

/**
 * Class TemplateDescriptorValidator.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class TemplateDescriptorValidator
{
    protected static $versions = [
        '1' => 'template-1_0.xsd',
    ];
    protected Finder $xsdFinder;

    /**
     * TemplateDescriptorValidator constructor.
     *
     * @param string $xmlDescriptorPath the path to the template XML descriprot
     */
    public function __construct(protected string $xmlDescriptorPath)
    {
        $this->xsdFinder = new Finder();
        $this->xsdFinder
            ->name('*.xsd')
            ->in(__DIR__.'/schema/template/');
    }

    /**
     * @param string|null $version the XSD version to use,, or null to use the latest version
     *
     * @return $this
     *
     * @throw InvalidDescriptorException
     */
    public function validate(?string $version = null): self
    {
        $dom = new \DOMDocument();
        $errors = XmlDescriptor::loadingErrors($dom, $this->xmlDescriptorPath);

        if ([] === $errors) {
            ['version' => $found, 'errors' => $errors] = XmlDescriptor::matchingSchemaVersion($dom, $this->xsdFinder, self::$versions, $version, $this->schemaValidate(...));

            if (null !== $found) {
                return $this;
            }
        }

        // A file of the theme, read by its developer: named by its path.
        throw new InvalidDescriptorException(TerminalText::onOneLine(\sprintf('%s file is not a valid template descriptor : %s', $this->xmlDescriptorPath, implode(', ', $errors))));
    }

    /**
     * Validate the schema of a XML file with a given xsd file.
     *
     * @param \DOMDocument $dom     The XML document
     * @param \SplFileInfo $xsdFile The XSD file
     *
     * @return array an array of errors if validation fails, otherwise an empty array
     */
    protected function schemaValidate(\DOMDocument $dom, \SplFileInfo $xsdFile): array
    {
        return array_map(
            static fn (string $error): string => 'XML error "'.$error.'"',
            XmlDescriptor::schemaErrors($dom, (string) $xsdFile->getRealPath()),
        );
    }

    public function getDescriptor(): \SimpleXMLElement|false|null
    {
        if (file_exists($this->xmlDescriptorPath)) {
            $this->validate();

            return XmlDescriptor::read($this->xmlDescriptorPath);
        }

        Tlog::getInstance()->addWarning(\sprintf('Template descriptor %s does not exists.', $this->xmlDescriptorPath));

        return null;
    }
}
