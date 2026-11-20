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

namespace Thelia\Api\Service\Company;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The JSON object a request carries, read by hand for the operations that declare no input.
 */
final class JsonRequestBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(?Request $request): array
    {
        $content = trim((string) $request?->getContent());

        if ('' === $content) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('The body is not valid JSON.');
        }

        if (!\is_array($decoded) || array_is_list($decoded) && [] !== $decoded) {
            throw new BadRequestHttpException('The body is a JSON object.');
        }

        return $decoded;
    }

    /**
     * A field that is a string, or null when it is absent or null.
     *
     * @param array<string, mixed> $body
     */
    public static function optionalString(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is a string.', $field));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function requiredString(array $body, string $field): string
    {
        $value = self::optionalString($body, $field);

        if (null === $value || '' === trim($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is required.', $field));
        }

        return $value;
    }
}
