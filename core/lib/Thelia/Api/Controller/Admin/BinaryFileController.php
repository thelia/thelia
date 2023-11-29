<?php

namespace Thelia\Api\Controller\Admin;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Thelia\Api\Resource\ItemFileResourceInterface;

#[AsController]
class BinaryFileController
{
    public function __invoke(
        Request $request
    )
    {
        $resource = $request->get('data');

        if (!$resource instanceof ItemFileResourceInterface) {
            throw new \Exception("Resource must implements ItemFileResourceInterface to use the BinaryFileController");
        }

        $propelModel = $resource->getPropelModel();
        $filePath = $propelModel->getUploadDir().DS.$propelModel->getFile();

        return new BinaryFileResponse($filePath);
    }
}
