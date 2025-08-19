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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class AccessCheckerService
{
    public function __construct(
        private MetadataService $metadataService,
    ) {
    }

    public function checkUserAccess(
        string $resourceClass,
        string $path,
        Operation $operation,
        array $context,
    ): void {
        if (!$this->metadataService->canUserAccessResource($resourceClass, $path, Request::METHOD_GET, $operation, $context)) {
            throw new AccessDeniedHttpException('Access Denied on '.$path);
        }
    }
}
