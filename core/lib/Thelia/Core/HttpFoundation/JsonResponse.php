<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse as BaseJsonResponse;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;

/**
 * Class JsonResponse
 * @package Thelia\Core\HttpFoundation
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class JsonResponse extends BaseJsonResponse
{
    public static function createError($errorMessage, $statusCode = 500)
    {
        return new static(["error" => $errorMessage], $statusCode);
    }

    public static function createAuthError($access)
    {
        switch ($access) {
            case AccessManager::VIEW:
                $errorMessage = "You don't have the right to view this content";
                break;

            case AccessManager::UPDATE:
                $errorMessage = "You don't have the right to edit this content";
                break;

            case AccessManager::CREATE:
                $errorMessage = "You don't have the right to create this content";
                break;

            case AccessManager::DELETE:
                $errorMessage = "You don't have the right to delete this content";
                break;

            default:
                $errorMessage = "You don't have the right to do this";
        }

        $errorMessage = Translator::getInstance()->trans($errorMessage);

        return static::createError($errorMessage);
    }

    public static function createNotFoundError($resource)
    {
        $errorMessage = Translator::getInstance()
            ->trans("The resource %res has not been found", ["%res" => $resource])
        ;

        return static::createError($errorMessage, 404);
    }
}
