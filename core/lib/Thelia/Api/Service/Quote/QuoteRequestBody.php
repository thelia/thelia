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

namespace Thelia\Api\Service\Quote;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The JSON body of a write on the quotes, read field by field.
 *
 * Nothing is taken that is not named: a field the operation does not know is refused, not ignored.
 * The prices and the totals are the shop's, so a body that carries one where none is expected is
 * wrong, and it is said so rather than silently dropped. Types are strict: a number is a number and
 * a string is a string.
 */
final readonly class QuoteRequestBody
{
    /**
     * @param array<string, mixed> $fields
     */
    private function __construct(private array $fields)
    {
    }

    /**
     * @param list<string> $allowed the fields the operation takes
     *
     * @throws BadRequestHttpException          when the body is not a JSON object
     * @throws UnprocessableEntityHttpException when it names a field the operation does not take
     */
    public static function of(?Request $request, array $allowed): self
    {
        $content = trim((string) $request?->getContent());
        $decoded = [];

        if ('' !== $content) {
            try {
                $decoded = json_decode($content, true, 8, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new BadRequestHttpException('The body is not valid JSON.');
            }
        }

        if (!\is_array($decoded) || ([] !== $decoded && array_is_list($decoded))) {
            throw new BadRequestHttpException('The body is a JSON object.');
        }

        return self::fromFields($decoded, $allowed);
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<string>         $allowed
     */
    public static function fromFields(array $fields, array $allowed, string $path = ''): self
    {
        $unknown = array_values(array_diff(array_map('strval', array_keys($fields)), $allowed));

        if ([] !== $unknown) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s%s" is not a field of this operation.', $path, $unknown[0]));
        }

        return new self($fields);
    }

    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->fields);
    }

    public function integer(string $field, bool $required = true): ?int
    {
        $value = $this->fields[$field] ?? null;

        if (null === $value) {
            return $required ? throw new UnprocessableEntityHttpException(\sprintf('"%s" is required.', $field)) : null;
        }

        if (!\is_int($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is an integer.', $field));
        }

        return $value;
    }

    public function number(string $field, bool $required = true): ?float
    {
        $value = $this->fields[$field] ?? null;

        if (null === $value) {
            return $required ? throw new UnprocessableEntityHttpException(\sprintf('"%s" is required.', $field)) : null;
        }

        if (!\is_int($value) && !\is_float($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is a number.', $field));
        }

        return (float) $value;
    }

    public function text(string $field): ?string
    {
        $value = $this->fields[$field] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is a string.', $field));
        }

        return $value;
    }

    /**
     * A decimal, taken as a string or as a number and handed on as a string.
     */
    public function amount(string $field, bool $required = true): ?string
    {
        $value = $this->fields[$field] ?? null;

        if (null === $value) {
            return $required ? throw new UnprocessableEntityHttpException(\sprintf('"%s" is required.', $field)) : null;
        }

        if (\is_int($value)) {
            return (string) $value;
        }

        if (\is_float($value)) {
            return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            return trim($value);
        }

        throw new UnprocessableEntityHttpException(\sprintf('"%s" is a decimal.', $field));
    }

    /**
     * A calendar day, as YYYY-MM-DD.
     */
    public function day(string $field): ?\DateTimeImmutable
    {
        $value = $this->text($field);

        if (null === $value || '' === $value) {
            return null;
        }

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$day instanceof \DateTimeImmutable || $day->format('Y-m-d') !== $value) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is a day, as YYYY-MM-DD.', $field));
        }

        return $day;
    }

    /**
     * The objects of a list, each as a body of its own.
     *
     * @param list<string> $allowed the fields of one object
     *
     * @return list<self>
     */
    public function objects(string $field, array $allowed, int $maximum): array
    {
        $value = $this->fields[$field] ?? null;

        if (null === $value) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is required.', $field));
        }

        if (!\is_array($value) || !array_is_list($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is a list.', $field));
        }

        if (\count($value) > $maximum) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" holds at most %d entries.', $field, $maximum));
        }

        $objects = [];

        foreach ($value as $index => $object) {
            if (!\is_array($object) || ([] !== $object && array_is_list($object))) {
                throw new UnprocessableEntityHttpException(\sprintf('"%s[%d]" is an object.', $field, $index));
            }

            $objects[] = self::fromFields($object, $allowed, \sprintf('%s[%d].', $field, $index));
        }

        return $objects;
    }
}
