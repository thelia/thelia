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

namespace Thelia\Api\Controller\Front;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Thelia\Domain\Localization\VisitorCountryFacade;

/**
 * Answers from the request alone: the API starts no session, so nothing is remembered
 * and nothing is read from one.
 */
#[AsController]
readonly class VisitorCountryController
{
    public function __invoke(Request $request, VisitorCountryFacade $facade): JsonResponse
    {
        $response = new JsonResponse([
            'mode' => $facade->mode()->value,
            'detected' => $facade->resolveFromRequest($request)?->getIsoalpha2(),
        ]);

        // Two visitors do not get the same answer: no shared cache may keep it.
        $response->setPrivate();

        return $response;
    }
}
