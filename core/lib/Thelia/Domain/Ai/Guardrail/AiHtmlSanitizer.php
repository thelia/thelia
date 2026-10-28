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

namespace Thelia\Domain\Ai\Guardrail;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Thelia\Domain\Ai\Internal\AiSchemaReference;

/**
 * Guardrail `html_sanitizer`: what the model wrote in a field the schema marks as HTML is reduced to the
 * elements a page can safely carry before anyone reads it (OWASP LLM05).
 *
 * A field is HTML when its schema says `"contentMediaType": "text/html"`, at any depth: nested objects, items
 * of an array, branches of `allOf`, `anyOf` and `oneOf`, local `$ref`. Free text (AiResponse::$text, a string
 * the schema does not mark) is not touched: the module that renders it escapes it.
 *
 * The profile is fixed and strict: the safe elements and attributes of the Symfony sanitizer, no `script`, `style`,
 * `iframe`, `object`, `embed` or `form` (dropped with their content), no `on*` attribute, no `style` attribute,
 * links and media over `http` and `https` (and `mailto` for links) or relative, every link with
 * `rel="noopener noreferrer"`.
 *
 * @internal
 */
final class AiHtmlSanitizer
{
    public const string MEDIA_TYPE = 'text/html';

    private const int MAXIMUM_DEPTH = 64;

    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(string $html): string
    {
        $this->sanitizer ??= new HtmlSanitizer($this->profile());

        return $this->sanitizer->sanitizeFor('body', $html);
    }

    /**
     * Whether the schema marks at least one field as HTML.
     *
     * @param array<string, mixed> $schema
     */
    public function hasHtmlFields(array $schema): bool
    {
        return self::containsMark($schema, $schema, 0);
    }

    /**
     * The data with its HTML fields sanitized.
     *
     * @param array<string, mixed> $schema
     */
    public function sanitizeData(array $schema, mixed $data): mixed
    {
        return $this->walk($schema, $schema, $data, 0);
    }

    private function profile(): HtmlSanitizerConfig
    {
        return (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('form')
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            // The length of what the model wrote is bounded by its output limit: never cut a page short.
            ->withMaxInputLength(-1);
    }

    /**
     * @param array<mixed> $root
     */
    private function walk(mixed $schema, array $root, mixed $data, int $depth): mixed
    {
        if (!\is_array($schema) || $depth > self::MAXIMUM_DEPTH) {
            return $data;
        }

        if (isset($schema['$ref']) && \is_string($schema['$ref'])) {
            $data = $this->walk(AiSchemaReference::resolve($root, $schema['$ref']), $root, $data, $depth + 1);
        }

        if (self::MEDIA_TYPE === ($schema['contentMediaType'] ?? null) && \is_string($data)) {
            $data = $this->sanitize($data);
        }

        if (\is_array($data)) {
            $data = array_is_list($data) && [] !== $data
                ? $this->walkItems($schema, $root, $data, $depth)
                : $this->walkProperties($schema, $root, $data, $depth);
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $keyword) {
            foreach (\is_array($schema[$keyword] ?? null) ? $schema[$keyword] : [] as $branch) {
                $data = $this->walk($branch, $root, $data, $depth + 1);
            }
        }

        return $data;
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $root
     * @param array<mixed> $items
     *
     * @return array<mixed>
     */
    private function walkItems(array $schema, array $root, array $items, int $depth): array
    {
        $prefix = \is_array($schema['prefixItems'] ?? null) ? array_values($schema['prefixItems']) : [];

        foreach ($items as $position => $item) {
            $items[$position] = $this->walk($prefix[$position] ?? ($schema['items'] ?? true), $root, $item, $depth + 1);
        }

        return $items;
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $root
     * @param array<mixed> $object
     *
     * @return array<mixed>
     */
    private function walkProperties(array $schema, array $root, array $object, int $depth): array
    {
        $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        foreach ($object as $name => $value) {
            if (\array_key_exists($name, $properties)) {
                $object[$name] = $this->walk($properties[$name], $root, $value, $depth + 1);
            } elseif (\array_key_exists('additionalProperties', $schema)) {
                $object[$name] = $this->walk($schema['additionalProperties'], $root, $value, $depth + 1);
            }
        }

        return $object;
    }

    /**
     * @param array<mixed> $root
     */
    private static function containsMark(mixed $schema, array $root, int $depth): bool
    {
        if (!\is_array($schema) || $depth > self::MAXIMUM_DEPTH) {
            return false;
        }

        if (self::MEDIA_TYPE === ($schema['contentMediaType'] ?? null)) {
            return true;
        }

        if (isset($schema['$ref']) && \is_string($schema['$ref']) && self::containsMark(AiSchemaReference::resolve($root, $schema['$ref']), $root, $depth + 1)) {
            return true;
        }

        $children = [$schema['items'] ?? null, $schema['additionalProperties'] ?? null, ...array_values(\is_array($schema['properties'] ?? null) ? $schema['properties'] : []), ...array_values(\is_array($schema['prefixItems'] ?? null) ? $schema['prefixItems'] : [])];

        foreach (['allOf', 'anyOf', 'oneOf'] as $keyword) {
            $children = [...$children, ...array_values(\is_array($schema[$keyword] ?? null) ? $schema[$keyword] : [])];
        }

        foreach ($children as $child) {
            if (self::containsMark($child, $root, $depth + 1)) {
                return true;
            }
        }

        return false;
    }
}
