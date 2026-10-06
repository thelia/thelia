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
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
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
        $filePath = $propelModel->getUploadDir().DS.$fileName;

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // A document is whatever the shop accepted as one, and this answer comes from the
        // shop origin: it is handed over as a download, never opened as a page of the shop.
        if ('document' === $resource::getFileType()) {
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                basename($fileName),
                // A name stored by an earlier version may hold characters the plain header refuses.
                (string) preg_replace('/[^\x20-\x7e]|[%"\\\\\/]/', '_', basename($fileName)),
            );
        }

        return $response;
    }
}
