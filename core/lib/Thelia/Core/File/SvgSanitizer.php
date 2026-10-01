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

namespace Thelia\Core\File;

/**
 * Strips the active content of an SVG document, so that an SVG served from the shop
 * origin renders as an image and runs nothing, even when it is opened on its own.
 *
 * Removed: script, foreignObject, iframe, embed, object and XHTML elements, event
 * handler attributes, javascript:, vbscript: and data: URIs (but a base64 raster image)
 * whatever the namespace prefix of the attribute, processing instructions (xml-stylesheet) and the document
 * type declaration along with the entities it declares.
 */
final readonly class SvgSanitizer
{
    public const MIME_TYPE = 'image/svg+xml';

    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const XHTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

    private const REMOVED_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object'];

    private const URI_ATTRIBUTES = ['href', 'src', 'from', 'to', 'values', 'begin', 'action', 'formaction'];

    private const EMBEDDED_RASTER_IMAGE = '#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]*$#';

    private const ACTIVE_URI_SCHEMES = ['javascript:', 'vbscript:', 'data:'];

    public static function isSvg(string $fileName, ?string $mimeType = null): bool
    {
        return 'svg' === strtolower(pathinfo($fileName, \PATHINFO_EXTENSION))
            || self::MIME_TYPE === $mimeType;
    }

    /**
     * @return string|null the sanitized document, or null when the content is not a well-formed XML document
     */
    public function sanitize(string $content): ?string
    {
        if ('' === trim($content)) {
            return null;
        }

        $previousErrorState = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        // LIBXML_NONET blocks network access; entities are never substituted (no XXE).
        $loaded = $document->loadXML($content, \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorState);

        if (!$loaded || !$this->isSvgRoot($document->documentElement)) {
            return null;
        }

        $xpath = new \DOMXPath($document);

        foreach ($this->query($xpath, '//processing-instruction()') as $instruction) {
            $instruction->parentNode?->removeChild($instruction);
        }

        if ($document->doctype instanceof \DOMDocumentType) {
            $document->removeChild($document->doctype);
        }

        foreach ($this->query($xpath, '//*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            if (self::XHTML_NAMESPACE === $element->namespaceURI
                || \in_array(strtolower((string) $element->localName), self::REMOVED_ELEMENTS, true)) {
                $element->parentNode?->removeChild($element);
            }
        }

        foreach ($this->query($xpath, '//@*') as $attribute) {
            if ($attribute instanceof \DOMAttr && $this->isActiveAttribute($attribute)) {
                $attribute->ownerElement?->removeAttributeNode($attribute);
            }
        }

        // An entity reference left in the tree would be resolved again by the next parser.
        foreach ($this->entityReferences($document->documentElement) as $reference) {
            $reference->parentNode?->removeChild($reference);
        }

        $sanitized = $document->saveXML($document->documentElement);

        return false === $sanitized ? null : '<?xml version="1.0" encoding="UTF-8"?>'."\n".$sanitized;
    }

    private function isSvgRoot(?\DOMElement $root): bool
    {
        return $root instanceof \DOMElement
            && 'svg' === $root->localName
            && self::SVG_NAMESPACE === $root->namespaceURI;
    }

    private function isActiveAttribute(\DOMAttr $attribute): bool
    {
        // The local name, not the qualified one: "x:href" is an xlink:href under another prefix.
        $name = strtolower($attribute->localName ?? $attribute->nodeName);

        if (str_starts_with($name, 'on')) {
            return true;
        }

        if (!\in_array($name, self::URI_ATTRIBUTES, true)) {
            return false;
        }

        $value = strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', $attribute->value));

        // A raster image embedded in the drawing runs nothing: logos exported with one keep it.
        if (1 === preg_match(self::EMBEDDED_RASTER_IMAGE, $value)) {
            return false;
        }

        foreach (self::ACTIVE_URI_SCHEMES as $scheme) {
            if (str_contains($value, $scheme)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<\DOMNode>
     */
    private function query(\DOMXPath $xpath, string $expression): array
    {
        $nodes = $xpath->query($expression);

        return false === $nodes ? [] : array_values(iterator_to_array($nodes));
    }

    /**
     * @return list<\DOMEntityReference>
     */
    private function entityReferences(\DOMNode $node): array
    {
        $references = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMEntityReference) {
                $references[] = $child;
            } elseif ($child->hasChildNodes()) {
                array_push($references, ...$this->entityReferences($child));
            }
        }

        return $references;
    }
}
