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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\Service\ItemFileResourceService;
use Thelia\Api\Resource\ItemFileResourceInterface;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Core\File\Exception\ProcessFileException;
use Thelia\Core\File\LocalizedFileModelInterface;

/**
 * Sets the file of one language of an existing image: multipart `fileToUpload`, and
 * `locale` naming the language (the default language when left out).
 */
#[AsController]
class PostItemFileTranslationController
{
    public function __invoke(
        Request $request,
        ItemFileResourceService $itemFileResourceService,
        ApiResourcePropelTransformerService $apiResourceService,
        ValidatorInterface $validator,
    ): PropelResourceInterface {
        /** @var class-string<ItemFileResourceInterface&PropelResourceInterface> $resourceClass */
        $resourceClass = $request->attributes->get('_api_resource_class');

        if (!\in_array(ItemFileResourceInterface::class, class_implements($resourceClass), true)) {
            throw new \LogicException('Resource must implement ItemFileResourceInterface to use the PostItemFileTranslationController');
        }

        $modelClassName = $resourceClass::getPropelRelatedTableMap()->getClassName();
        $id = (int) $request->attributes->get('id');
        $propelModel = (new $modelClassName())->getQueryInstance()->findPk($id);

        if (!$propelModel instanceof LocalizedFileModelInterface) {
            throw new NotFoundHttpException(\sprintf('No %s %d with a file per language.', $resourceClass::getFileType(), $id));
        }

        $file = $request->files->get('fileToUpload');

        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('The "fileToUpload" file part is required.');
        }

        $violations = $validator->validate($file, $itemFileResourceService->getPropertyFileConstraints($resourceClass, 'fileToUpload'));

        if (\count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }

            throw new UnprocessableEntityHttpException('Validation error: '.implode(', ', $errors));
        }

        try {
            $locale = $itemFileResourceService->replaceItemFileTranslation($propelModel, $resourceClass::getFileType(), $request);
        } catch (ProcessFileException $exception) {
            throw new HttpException($exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $exception->getMessage(), $exception);
        }

        /** @var Post $operation */
        $operation = $request->attributes->get('_api_operation');
        $context = $operation->getNormalizationContext() ?? [];
        // The answer reads the file in the language that was just written.
        $context['filters']['locale'] = $locale;

        return $apiResourceService->modelToResource($resourceClass, $propelModel, $context);
    }
}
