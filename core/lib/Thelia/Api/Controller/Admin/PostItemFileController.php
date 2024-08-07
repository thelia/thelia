<?php

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

use ApiPlatform\Metadata\Post;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\Service\ItemFileResourceService;
use Thelia\Api\Resource\ItemFileResourceInterface;
use Thelia\Api\Resource\PropelResourceInterface;

#[AsController]
class PostItemFileController
{
    public function __invoke(
        Request $request,
        ItemFileResourceService $itemDocumentResourceService,
        ApiResourcePropelTransformerService $apiResourceService
    ) {
        /** @var ItemFileResourceInterface|PropelResourceInterface $resourceClass */
        $resourceClass = $request->get('_api_resource_class');

        if (!\in_array(ItemFileResourceInterface::class, class_implements($resourceClass))) {
            throw new \Exception('Resource must implements ItemFileResourceInterface to use the PostItemFileController');
        }

        $itemType = $resourceClass::getItemType();
        $fileType = $resourceClass::getFileType();
        $itemId = $request->get($itemType);
        $modelTableMap = $resourceClass::getPropelRelatedTableMap();
        $modelClassName = $modelTableMap->getClassName();
        $propelModel = new $modelClassName();
        $itemDocumentResourceService->createItemFile(
            $itemId,
            $propelModel,
            $itemType,
            $fileType,
        );

        /** @var Post $operation */
        $operation = $request->get('_api_operation');

        return $apiResourceService->modelToResource(
            $resourceClass,
            $propelModel,
            $operation->getDenormalizationContext(),
        );
    }
}
