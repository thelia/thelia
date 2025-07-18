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
use Thelia\Api\Resource\ItemFileResourceInterface;

#[AsController]
class BinaryFileController
{
    public function __invoke(
        Request $request,
    ): BinaryFileResponse {
        $resource = $request->get('data');

        if (!$resource instanceof ItemFileResourceInterface) {
            throw new \Exception('Resource must implements ItemFileResourceInterface to use the BinaryFileController');
        }

        $propelModel = $resource->getPropelModel();
        $filePath = $propelModel->getUploadDir().DS.$propelModel->getFile();

        return new BinaryFileResponse($filePath);
    }
}
