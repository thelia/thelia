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

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use Thelia\Api\Controller\Front\VisitorCountryController;

/**
 * The country the shop detects for the request, so that a headless front can offer it
 * or pre-fill a form with it. Public, like the countries it names.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/visitor_country',
            controller: VisitorCountryController::class,
            openapi: new OpenApiOperation(
                summary: 'Detect the country of the visitor',
                description: 'Detected from the request alone, nothing is stored: the configured header or the client IP address, depending on the shop settings. `mode` is `off`, `suggest` or `apply`; `detected` is an ISO 3166-1 alpha-2 code of a visible country, null when the mode is `off` or nothing was found.',
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'The detection mode and the detected country.',
                        content: new \ArrayObject(['application/json' => ['example' => ['mode' => 'suggest', 'detected' => 'BE']]]),
                    ),
                ],
            ),
            // Public on purpose: the answer is the detection of the request, nothing of the visitor.
            security: 'true',
            read: false,
            write: false,
        ),
    ],
)]
final class VisitorCountry
{
    #[ApiProperty(identifier: true)]
    public string $mode = 'off';

    public ?string $detected = null;
}
