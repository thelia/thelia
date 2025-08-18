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

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class NormalizerService
{
    public function __construct(private NormalizerInterface $normalizer)
    {
    }

    public function normalizeData(object|array $data, array $context, ?string $format = null): array
    {
        return $this->normalizer->normalize($data, $format, $context);
    }
}
