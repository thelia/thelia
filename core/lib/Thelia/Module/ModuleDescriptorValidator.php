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

namespace Thelia\Module;

use Symfony\Component\Finder\Finder;
use Thelia\Core\File\XmlDescriptor;
use Thelia\Module\Exception\InvalidXmlDocumentException;

/**
 * Class ModuleDescriptorValidator.
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class ModuleDescriptorValidator
{
    protected static $versions = [
        '1' => 'module.xsd',
        '2' => 'module-2_1.xsd',
        '3' => 'module-2_2.xsd',
    ];
    protected Finder $xsdFinder;
    protected $moduleVersion;

    public function __construct()
    {
        $this->xsdFinder = new Finder();
        $this->xsdFinder
            ->name('*.xsd')
            ->in(__DIR__.'/schema/module/');
    }

    public function getModuleVersion()
    {
        return $this->moduleVersion;
    }

    public function validate($xml_file, $version = null): bool
    {
        $this->moduleVersion = null;
        $dom = new \DOMDocument();
        $notLoaded = XmlDescriptor::loadingErrors($dom, (string) $xml_file);

        if ([] !== $notLoaded) {
            $reason = implode(', ', array_map(static fn (string $said): string => self::withoutPath($said, (string) $xml_file), $notLoaded));
        } else {
            ['version' => $this->moduleVersion, 'errors' => $errors] = XmlDescriptor::matchingSchemaVersion($dom, $this->xsdFinder, self::$versions, null === $version ? null : (string) $version, $this->schemaValidate(...));

            if (null !== $this->moduleVersion) {
                return true;
            }

            $reason = implode(', ', $errors);
        }

        // Shown to the administrator who uploads the module: the module it is about, never
        // where the server unpacked it, and nothing a value of the descriptor would make a
        // log or a page obey.
        throw new InvalidXmlDocumentException(XmlDescriptor::printable(\sprintf('The %s is not a valid file: %s', self::describe((string) $xml_file), $reason)));
    }

    /**
     * The descriptor as the administrator knows it: "module.xml of <module>" for one at its
     * place in a module (<module>/Config/module.xml), the file name alone anywhere else.
     */
    private static function describe(string $xmlFile): string
    {
        $file = trim(basename($xmlFile));

        if ('' === $file) {
            return 'descriptor';
        }

        $configFolder = \dirname($xmlFile);

        if ('Config' !== basename($configFolder) || \in_array(basename(\dirname($configFolder)), ['', '.', '..'], true)) {
            return $file;
        }

        return \sprintf('%s of %s', $file, basename(\dirname($configFolder)));
    }

    /**
     * A message of libxml about the file, without a path of the server. libxml quotes the
     * path of a file it could not open, in full, resolved and normalised: when it is the
     * descriptor, whatever was quoted becomes the name of the file (the whole of it, as
     * the path may hold a quote). Then the path as given and as resolved, wherever they
     * stand, and last any other absolute path quoted; a value quoted (an entity, a
     * namespace) is left as it is.
     */
    private static function withoutPath(string $message, string $xmlFile): string
    {
        $file = basename($xmlFile);

        if ('' === $file) {
            return $message;
        }

        // The name is given back as it is: never read for the references of a replacement.
        $message = (string) preg_replace_callback(
            '#(failed to load external entity )"(.*)"#s',
            static fn (array $found): string => basename(rawurldecode($found[2])) === $file ? $found[1].'"'.$file.'"' : $found[0],
            $message,
        );
        $message = strtr($message, self::namesOf($xmlFile));

        return (string) preg_replace('#(["\'])/[^"\']*/([^"\'/]+)\1#', '$1$2$1', $message);
    }

    /**
     * The path as given, as the file system resolves it and as libxml encodes it (a URI,
     * "%20" for a space), each standing for the name of the file (strtr() takes the
     * longest first, so that the shorter one never eats a part of the longer).
     *
     * @return array<string, string>
     */
    private static function namesOf(string $path): array
    {
        // A path with a NUL byte is no path: the file system refuses to resolve it.
        if ('' === $path || str_contains($path, "\0")) {
            return [];
        }

        // The path as given stands only when absolute: a short relative one ("a/b") would
        // be found inside unrelated text.
        $resolvedFolder = realpath(\dirname($path));
        $forms = str_starts_with($path, '/') ? [$path, rawurldecode($path)] : [];

        if (false !== $resolvedFolder) {
            $forms[] = $resolvedFolder.\DIRECTORY_SEPARATOR.basename($path);
        }

        $names = [];

        foreach ($forms as $form) {
            $names[$form] = basename(rawurldecode($form));
            $names[implode('/', array_map('rawurlencode', explode('/', $form)))] = basename(rawurldecode($form));
        }

        return array_filter($names, static fn (string $form): bool => '' !== $form, \ARRAY_FILTER_USE_KEY);
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

    public function getDescriptor($xml_file): \SimpleXMLElement|false
    {
        $this->validate($xml_file);

        return XmlDescriptor::read((string) $xml_file);
    }
}
