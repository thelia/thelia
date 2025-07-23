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

namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse as BaseJsonResponse;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;

/**
 * Class JsonResponse.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
final class JsonResponse extends BaseJsonResponse
{
    public static function createError($errorMessage, $statusCode = 500): self
    {
        return new self(['error' => $errorMessage], $statusCode);
    }

    public static function createAuthError($access): self
    {
        $errorMessage = match ($access) {
            AccessManager::VIEW => "You don't have the right to view this content",
            AccessManager::UPDATE => "You don't have the right to edit this content",
            AccessManager::CREATE => "You don't have the right to create this content",
            AccessManager::DELETE => "You don't have the right to delete this content",
            default => "You don't have the right to do this",
        };

        $errorMessage = Translator::getInstance()->trans($errorMessage);

        return self::createError($errorMessage);
    }

    public static function createNotFoundError($resource): self
    {
        $errorMessage = Translator::getInstance()
            ->trans('The resource %res has not been found', ['%res' => $resource]);

        return self::createError($errorMessage, 404);
    }
}
