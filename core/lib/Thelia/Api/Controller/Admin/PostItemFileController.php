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

use ApiPlatform\Metadata\Post;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\Service\ItemFileResourceService;
use Thelia\Api\Resource\ItemFileResourceInterface;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Core\File\Exception\ProcessFileException;

#[AsController]
class PostItemFileController
{
    public function __invoke(
        Request $request,
        ItemFileResourceService $itemDocumentResourceService,
        ApiResourcePropelTransformerService $apiResourceService,
        ValidatorInterface $validator,
    ): PropelResourceInterface {
        /** @var ItemFileResourceInterface|PropelResourceInterface $resourceClass */
        $resourceClass = $request->attributes->get('_api_resource_class');

        if (!\in_array(ItemFileResourceInterface::class, class_implements($resourceClass), true)) {
            throw new \Exception('Resource must implements ItemFileResourceInterface to use the PostItemFileController');
        }

        $file = $request->files->get('fileToUpload');

        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('The "fileToUpload" file part is required.');
        }

        $constraints = $itemDocumentResourceService->getPropertyFileConstraints($resourceClass, 'fileToUpload');
        $violations = $validator->validate($file, $constraints);

        if (\count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }

            throw new UnprocessableEntityHttpException('Validation error: '.implode(', ', $errors));
        }

        $itemType = $resourceClass::getItemType();
        $fileType = $resourceClass::getFileType();
        $itemId = self::resolveItemId($request, $itemType);
        $modelTableMap = $resourceClass::getPropelRelatedTableMap();
        $modelClassName = $modelTableMap->getClassName();
        $propelModel = new $modelClassName();

        try {
            $itemDocumentResourceService->createItemFile(
                $itemId,
                $propelModel,
                $itemType,
                $fileType,
                $request,
            );
        } catch (ProcessFileException $exception) {
            // The upload policy speaks in HTTP terms already: 415 for a type that is
            // not accepted, 403 for a file above the server limit.
            throw new HttpException($exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $exception->getMessage(), $exception);
        }

        /** @var Post $operation */
        $operation = $request->attributes->get('_api_operation');

        return $apiResourceService->modelToResource(
            $resourceClass,
            $propelModel,
            $operation->getNormalizationContext(),
        );
    }

    /**
     * Which item the uploaded file belongs to.
     *
     * A route placeholder wins, so an operation may put the item in its uri
     * template. Otherwise the multipart body carries it under the item type
     * ("product", "category", …), as a plain identifier or as an IRI, the two
     * forms every other relation of this API accepts.
     */
    private static function resolveItemId(Request $request, string $itemType): int
    {
        $raw = $request->attributes->get($itemType) ?? $request->request->get($itemType);

        if (\is_int($raw)) {
            return $raw;
        }

        if (\is_string($raw)) {
            if (ctype_digit($raw)) {
                return (int) $raw;
            }

            // An IRI, for instance /api/admin/products/42
            if (1 === preg_match('#/(\d+)/?$#', $raw, $matches)) {
                return (int) $matches[1];
            }
        }

        throw new UnprocessableEntityHttpException(\sprintf('The "%s" field is required and must be an id or an IRI: it tells which %s the file belongs to.', $itemType, $itemType));
    }
}
