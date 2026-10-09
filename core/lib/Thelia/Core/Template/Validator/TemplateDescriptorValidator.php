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
use Thelia\Core\Template\Exception\InvalidDescriptorException;
use Thelia\Log\Tlog;

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
        $errors = [];

        if ($dom->load($this->xmlDescriptorPath)) {
            /** @var \SplFileInfo $xsdFile */
            foreach ($this->xsdFinder as $xsdFile) {
                $xsdVersion = array_search($xsdFile->getBasename(), self::$versions, true);

                // The keys of the table are read back as integers: a version is compared as text.
                if (false === $xsdVersion || (null !== $version && $version !== (string) $xsdVersion)) {
                    continue;
                }

                $errors = $this->schemaValidate($dom, $xsdFile);

                if ([] === $errors) {
                    return $this;
                }
            }
        }

        throw new InvalidDescriptorException(\sprintf('%s file is not a valid template descriptor : %s', $this->xmlDescriptorPath, implode(', ', $errors)));
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
        $errorMessages = [];
        $previousErrorHandling = libxml_use_internal_errors(true);
        // A schema that cannot be read is a warning of PHP: an exception here, whatever the
        // environment does with a warning.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            if (!$dom->schemaValidate($xsdFile->getRealPath())) {
                $errors = libxml_get_errors();

                foreach ($errors as $error) {
                    $errorMessages[] = \sprintf(
                        'XML error "%s" [%d] (Code %d) in %s on line %d column %d'."\n",
                        $error->message,
                        $error->level,
                        $error->code,
                        $error->file,
                        $error->line,
                        $error->column,
                    );
                }
            }
        } catch (\Exception $notChecked) {
            // A schema that could not be read checks nothing: a descriptor it could not check
            // is not a valid one.
            $errorMessages[] = \sprintf('the descriptor could not be checked against %s (%s)', $xsdFile->getBasename(), $notChecked->getMessage());
        } finally {
            restore_error_handler();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }

        return $errorMessages;
    }

    /**
     * @return object|null
     */
    public function getDescriptor(): \SimpleXMLElement|false|null
    {
        if (file_exists($this->xmlDescriptorPath)) {
            $this->validate();

            return @simplexml_load_file($this->xmlDescriptorPath);
        }

        Tlog::getInstance()->addWarning(\sprintf('Template descriptor %s does not exists.', $this->xmlDescriptorPath));

        return null;
    }
}
