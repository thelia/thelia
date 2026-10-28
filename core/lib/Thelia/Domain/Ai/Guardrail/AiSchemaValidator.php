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

use Thelia\Domain\Ai\Internal\AiSchemaReference;

/**
 * Checks the JSON an answer decoded to against the schema of the request (guardrail `output_schema`).
 *
 * It implements the part of JSON Schema that structured outputs use, and nothing else: `type`, `enum`, `const`,
 * `properties`, `required`, `additionalProperties`, `items`, `prefixItems`, the size and range limits, `pattern`,
 * `uniqueItems`, `allOf`, `anyOf`, `oneOf`, `not`, and `$ref` to a place of the same schema (`#/$defs/…`).
 * `format` and the keywords it does not know are annotations: they never fail an answer. A `$ref` that points
 * anywhere else fails the answer, never silently passes it.
 *
 * It is not a dependency on `justinrainbow/json-schema` on purpose: that package reaches the core only through
 * the development tools, and the subset above is small enough to own and to test.
 *
 * @internal
 */
final readonly class AiSchemaValidator
{
    private const int MAXIMUM_DEPTH = 64;

    private const int MAXIMUM_ERRORS = 5;

    /** The keywords that check(): any other keyword of a schema is either an annotation or ignored. */
    private const array ENFORCED_KEYWORDS = [
        'type', 'enum', 'const', 'properties', 'required', 'additionalProperties', 'items', 'prefixItems',
        'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'minLength', 'maxLength', 'pattern',
        'minItems', 'maxItems', 'minProperties', 'maxProperties', 'uniqueItems', 'allOf', 'anyOf', 'oneOf', 'not', '$ref', '$defs',
    ];

    /** The keywords that describe a schema and never fail a value. */
    private const array ANNOTATION_KEYWORDS = ['$schema', '$id', '$comment', 'title', 'description', 'default', 'examples', 'deprecated', 'readOnly', 'writeOnly'];

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<string> what does not match, as `<path>: <keyword>` (the path is made of the keys of the schema and
     *                      the position of the items, never of a value); empty when the data matches
     */
    public function validate(array $schema, mixed $data): array
    {
        $schema = self::asArrays($schema);
        $errors = [];
        $this->check($schema, $schema, $data, '', 0, $errors);

        return $errors;
    }

    /**
     * A schema decoded with `json_decode($json)` holds `stdClass` objects where one decoded with `true` holds arrays:
     * both are the same schema, so objects become arrays before anything reads them.
     */
    private static function asArrays(mixed $value): mixed
    {
        if (\is_object($value)) {
            $value = get_object_vars($value);
        }

        return \is_array($value) ? array_map(self::asArrays(...), $value) : $value;
    }

    /**
     * The keywords of a schema that validate() would ignore without a word (`multipleOf`, `format`, `contains`,
     * `patternProperties`... and any keyword it does not know): a schema that relies on them must be refused, not trusted.
     *
     * @param array<mixed> $schema
     *
     * @return list<string> `<path>: <keyword>`, empty when every keyword is enforced or an annotation
     */
    public static function unsupportedKeywords(array $schema): array
    {
        $found = [];
        self::collectUnsupported($schema, '', 0, $found);

        return $found;
    }

    /**
     * @param list<string> $found
     */
    private static function collectUnsupported(mixed $schema, string $path, int $depth, array &$found): void
    {
        if (!\is_array($schema) || $depth > self::MAXIMUM_DEPTH) {
            return;
        }

        foreach ($schema as $keyword => $value) {
            $keyword = (string) $keyword;

            if (\in_array($keyword, self::ANNOTATION_KEYWORDS, true)) {
                continue;
            }

            if (!\in_array($keyword, self::ENFORCED_KEYWORDS, true)) {
                $found[] = self::at($path, $keyword);

                continue;
            }

            if (\in_array($keyword, ['exclusiveMinimum', 'exclusiveMaximum'], true) && !\is_int($value) && !\is_float($value)) {
                $found[] = self::at($path, $keyword);

                continue;
            }

            match ($keyword) {
                'properties', '$defs' => self::collectUnsupportedIn($value, $path.'/'.$keyword, $depth, $found),
                'items', 'additionalProperties', 'not' => self::collectUnsupported($value, $path.'/'.$keyword, $depth + 1, $found),
                'prefixItems', 'allOf', 'anyOf', 'oneOf' => self::collectUnsupportedIn($value, $path.'/'.$keyword, $depth, $found),
                default => null,
            };
        }
    }

    /**
     * @param list<string> $found
     */
    private static function collectUnsupportedIn(mixed $schemas, string $path, int $depth, array &$found): void
    {
        if (!\is_array($schemas)) {
            return;
        }

        foreach ($schemas as $key => $schema) {
            self::collectUnsupported($schema, $path.'/'.$key, $depth + 1, $found);
        }
    }

    /**
     * @param array<string, mixed> $root
     * @param list<string>         $errors
     */
    private function check(mixed $schema, array $root, mixed $data, string $path, int $depth, array &$errors): void
    {
        if (\count($errors) >= self::MAXIMUM_ERRORS) {
            return;
        }

        if (true === $schema || [] === $schema) {
            return;
        }

        if (false === $schema || !\is_array($schema)) {
            $errors[] = self::at($path, 'not allowed');

            return;
        }

        if ($depth > self::MAXIMUM_DEPTH) {
            $errors[] = self::at($path, 'too deep');

            return;
        }

        if (isset($schema['$ref'])) {
            $target = \is_string($schema['$ref']) ? AiSchemaReference::resolve($root, $schema['$ref']) : null;

            if (null === $target) {
                $errors[] = self::at($path, 'unresolved $ref');

                return;
            }

            $this->check($target, $root, $data, $path, $depth + 1, $errors);
        }

        if (isset($schema['type']) && !self::typeMatches($schema['type'], $data)) {
            $errors[] = self::at($path, 'type');

            return;
        }

        if (isset($schema['enum']) && \is_array($schema['enum']) && !self::isOneOf($data, $schema['enum'])) {
            $errors[] = self::at($path, 'enum');
        }

        if (\array_key_exists('const', $schema) && !self::equals($data, $schema['const'])) {
            $errors[] = self::at($path, 'const');
        }

        $this->checkNumber($schema, $data, $path, $errors);
        $this->checkString($schema, $data, $path, $errors);

        if (\is_array($data)) {
            if ([] === $data || array_is_list($data)) {
                $this->checkArray($schema, $root, $data, $path, $depth, $errors);
            }

            if ([] === $data || !array_is_list($data)) {
                $this->checkObject($schema, $root, $data, $path, $depth, $errors);
            }
        }

        $this->checkCombinations($schema, $root, $data, $path, $depth, $errors);
    }

    /**
     * @param array<mixed> $schema
     * @param list<string> $errors
     */
    private function checkNumber(array $schema, mixed $data, string $path, array &$errors): void
    {
        if (!\is_int($data) && !\is_float($data)) {
            return;
        }

        foreach (['minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum'] as $keyword) {
            $limit = $schema[$keyword] ?? null;

            if (!\is_int($limit) && !\is_float($limit)) {
                continue;
            }

            $violates = match ($keyword) {
                'minimum' => $data < $limit,
                'maximum' => $data > $limit,
                'exclusiveMinimum' => $data <= $limit,
                default => $data >= $limit,
            };

            if ($violates) {
                $errors[] = self::at($path, $keyword);
            }
        }
    }

    /**
     * @param array<mixed> $schema
     * @param list<string> $errors
     */
    private function checkString(array $schema, mixed $data, string $path, array &$errors): void
    {
        if (!\is_string($data)) {
            return;
        }

        $length = mb_strlen($data);

        if (isset($schema['minLength']) && \is_int($schema['minLength']) && $length < $schema['minLength']) {
            $errors[] = self::at($path, 'minLength');
        }

        if (isset($schema['maxLength']) && \is_int($schema['maxLength']) && $length > $schema['maxLength']) {
            $errors[] = self::at($path, 'maxLength');
        }

        if (isset($schema['pattern']) && \is_string($schema['pattern'])) {
            $matched = @preg_match('~'.str_replace('~', '\~', $schema['pattern']).'~u', $data);

            if (1 !== $matched) {
                $errors[] = self::at($path, 'pattern');
            }
        }
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $root
     * @param array<mixed> $data
     * @param list<string> $errors
     */
    private function checkArray(array $schema, array $root, array $data, string $path, int $depth, array &$errors): void
    {
        $count = \count($data);

        if (isset($schema['minItems']) && \is_int($schema['minItems']) && $count < $schema['minItems']) {
            $errors[] = self::at($path, 'minItems');
        }

        if (isset($schema['maxItems']) && \is_int($schema['maxItems']) && $count > $schema['maxItems']) {
            $errors[] = self::at($path, 'maxItems');
        }

        if (true === ($schema['uniqueItems'] ?? false)) {
            foreach ($data as $position => $item) {
                if (self::isOneOf($item, \array_slice($data, 0, $position))) {
                    $errors[] = self::at($path, 'uniqueItems');

                    break;
                }
            }
        }

        $prefix = \is_array($schema['prefixItems'] ?? null) ? array_values($schema['prefixItems']) : [];

        foreach ($data as $position => $item) {
            $itemSchema = $prefix[$position] ?? ($schema['items'] ?? true);

            $this->check($itemSchema, $root, $item, $path.'/'.$position, $depth + 1, $errors);
        }
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $root
     * @param array<mixed> $data
     * @param list<string> $errors
     */
    private function checkObject(array $schema, array $root, array $data, string $path, int $depth, array &$errors): void
    {
        $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        foreach (\is_array($schema['required'] ?? null) ? $schema['required'] : [] as $name) {
            if (\is_string($name) && !\array_key_exists($name, $data)) {
                $errors[] = self::at($path.'/'.$name, 'required');
            }
        }

        $count = \count($data);

        if (isset($schema['minProperties']) && \is_int($schema['minProperties']) && $count < $schema['minProperties']) {
            $errors[] = self::at($path, 'minProperties');
        }

        if (isset($schema['maxProperties']) && \is_int($schema['maxProperties']) && $count > $schema['maxProperties']) {
            $errors[] = self::at($path, 'maxProperties');
        }

        foreach ($data as $name => $value) {
            $name = (string) $name;

            if (\array_key_exists($name, $properties)) {
                $this->check($properties[$name], $root, $value, $path.'/'.$name, $depth + 1, $errors);
            } elseif (\array_key_exists('additionalProperties', $schema)) {
                $this->check($schema['additionalProperties'], $root, $value, $path.'/'.$name, $depth + 1, $errors);
            }
        }
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $root
     * @param list<string> $errors
     */
    private function checkCombinations(array $schema, array $root, mixed $data, string $path, int $depth, array &$errors): void
    {
        foreach (\is_array($schema['allOf'] ?? null) ? $schema['allOf'] : [] as $branch) {
            $this->check($branch, $root, $data, $path, $depth + 1, $errors);
        }

        foreach (['anyOf' => 1, 'oneOf' => 1] as $keyword => $unused) {
            if (!\is_array($schema[$keyword] ?? null)) {
                continue;
            }

            $matching = 0;

            foreach ($schema[$keyword] as $branch) {
                $branchErrors = [];
                $this->check($branch, $root, $data, $path, $depth + 1, $branchErrors);

                if ([] === $branchErrors) {
                    ++$matching;
                }
            }

            if ('anyOf' === $keyword ? 0 === $matching : 1 !== $matching) {
                $errors[] = self::at($path, $keyword);
            }
        }

        if (\array_key_exists('not', $schema)) {
            $negated = [];
            $this->check($schema['not'], $root, $data, $path, $depth + 1, $negated);

            if ([] === $negated) {
                $errors[] = self::at($path, 'not');
            }
        }
    }

    private static function typeMatches(mixed $type, mixed $data): bool
    {
        foreach (\is_array($type) ? $type : [$type] as $name) {
            $matches = match ($name) {
                'string' => \is_string($data),
                'boolean' => \is_bool($data),
                'null' => null === $data,
                'number' => \is_int($data) || (\is_float($data) && is_finite($data)),
                'integer' => \is_int($data) || (\is_float($data) && is_finite($data) && floor($data) === $data),
                // A decoded empty object and an empty array are the same PHP value: it counts as both.
                'array' => \is_array($data) && ([] === $data || array_is_list($data)),
                'object' => \is_array($data) && ([] === $data || !array_is_list($data)),
                default => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $candidates
     */
    private static function isOneOf(mixed $value, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (self::equals($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function equals(mixed $left, mixed $right): bool
    {
        if (\is_array($left) && \is_array($right)) {
            if (\count($left) !== \count($right)) {
                return false;
            }

            foreach ($left as $key => $value) {
                if (!\array_key_exists($key, $right) || !self::equals($value, $right[$key])) {
                    return false;
                }
            }

            return true;
        }

        if ((\is_int($left) || \is_float($left)) && (\is_int($right) || \is_float($right))) {
            return $left == $right;
        }

        return $left === $right;
    }

    private static function at(string $path, string $keyword): string
    {
        return ('' === $path ? '/' : $path).': '.$keyword;
    }
}
