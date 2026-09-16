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

namespace Thelia\Api\Controller\Admin;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Resource\ItemFileResourceInterface;

#[AsController]
class BinaryFileController
{
    public function __invoke(
        Request $request,
    ): BinaryFileResponse {
        $resource = $request->attributes->get('data');

        if (!$resource instanceof ItemFileResourceInterface) {
            throw new \Exception('Resource must implements ItemFileResourceInterface to use the BinaryFileController');
        }

        $propelModel = $resource->getPropelModel();
        $fileName = (string) $propelModel->getFile();

        // A media row does not always carry a file: a product video played from a
        // platform is a row and an identifier, nothing more. Building the path
        // anyway hands BinaryFileResponse the upload directory itself, which it
        // reports as a 500 rather than as the missing file it is.
        if ('' === $fileName) {
            throw new NotFoundHttpException('This resource carries no file.');
        }

        return new BinaryFileResponse($propelModel->getUploadDir().DS.$fileName);
    }
}
