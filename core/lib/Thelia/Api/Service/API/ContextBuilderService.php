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

namespace Thelia\Api\Service\API;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

readonly class ContextBuilderService
{
    public function buildContext(
        string $path,
        Operation $operation,
        string $resourceClass,
        array $uriVariables,
        array $parameters,
    ): array {
        $parameters = $this->convertParameterKeysToArrays($parameters);

        return [
            'path_info' => $path,
            'operation' => $operation,
            'uri_variables' => $uriVariables,
            'resource_class' => $resourceClass,
            'filters' => $parameters,
            'groups' => $operation->getNormalizationContext()['groups'] ?? null,
            // The normalizers key their attribute cache on a serialization of the context, once for every item
            // of a collection. What ResourceService adds next to it (the result in extra_variables, the request)
            // and the operation metadata do not change which attributes are read: serialized for each item, the
            // result made a collection quadratic in its size. API Platform's SerializerContextBuilder list, plus
            // the two keys of ResourceService. Being in the context, it replaces any default_context list.
            AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY => [
                'root_operation', 'operation', 'object', 'data', 'property_metadata',
                'circular_reference_limit_counters', 'debug_trace_id', 'extra_variables', 'request',
            ],
        ];
    }

    private function convertParameterKeysToArrays(array $parameters): array
    {
        $result = [];
        foreach ($parameters as $key => $value) {
            $this->mergeNestedArray($result, $this->stringToArray($key, $value));
        }

        return $result;
    }

    private function mergeNestedArray(array &$target, array $source): void
    {
        foreach ($source as $key => $value) {
            if (\is_array($value) && isset($target[$key]) && \is_array($target[$key])) {
                $this->mergeNestedArray($target[$key], $value);
            } else {
                $target[$key] = $value;
            }
        }
    }

    private function stringToArray(string $string, $value = null): array
    {
        $keys = preg_split('/\]\[|\[|\]/', $string, -1, \PREG_SPLIT_NO_EMPTY);
        if (empty($keys)) {
            return [$string => $value];
        }

        $result = [];
        $ref = &$result;

        foreach ($keys as $key) {
            if (!isset($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }

        $ref = $value;

        return $result;
    }
}
