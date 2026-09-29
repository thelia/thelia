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
use Thelia\Core\File\LocalizedFileModelInterface;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

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

        // A translated file is served in the language asked for (?locale=), the default
        // language otherwise, with the fallback every translated read follows.
        if ($propelModel instanceof LocalizedFileModelInterface) {
            $propelModel->setLocale(self::requestedLocale($request));
        }

        $filePath = $propelModel->getUploadDir().DS.$propelModel->getFile();

        return new BinaryFileResponse($filePath);
    }

    private static function requestedLocale(Request $request): string
    {
        $requested = $request->query->get('locale');

        if (\is_string($requested) && '' !== $requested && null !== LangQuery::create()->findOneByLocale($requested)) {
            return $requested;
        }

        return Lang::getDefaultLanguage()->getLocale();
    }
}
