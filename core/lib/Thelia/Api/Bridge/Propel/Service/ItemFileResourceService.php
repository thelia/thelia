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

namespace Thelia\Api\Bridge\Propel\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Thelia\Action\Image as ImageAction;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileModelInterface;
use Thelia\Core\File\LocalizedFileModelInterface;
use Thelia\Core\File\Service\FileProcessorService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

readonly class ItemFileResourceService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FileProcessorService $fileProcessorService,
    ) {
    }

    public function createItemFile(
        int $parentId,
        FileModelInterface $fileModel,
        string $itemType,
        string $fileType,
        Request $request,
    ): void {
        /** @var UploadedFile $file */
        $file = $request->files->get('fileToUpload');

        if (!$file->isValid()) {
            throw new FileException($file->getErrorMessage());
        }

        // The shop upload policy, the one the back office applies (FileConfiguration).
        // The API used to carry its own copy as per-resource constraints, which said
        // something else and covered images only.
        $this->fileProcessorService->validateUpload($file, $fileType);
        $this->fileProcessorService->sanitizeUpload($file);

        // The Propel setters are natively typed, so the raw strings of a multipart
        // body have to be converted before they reach the model.
        $fileModel->setParentId($parentId)
            ->setVisible((int) filter_var($request->request->get('visible', '1'), \FILTER_VALIDATE_BOOLEAN));

        $position = $request->request->get('position');

        if (null !== $position && '' !== $position) {
            $fileModel->setPosition((int) $position);
        }

        // Only image models carry alt/decorative: the same service also
        // handles documents, whose model has no such setter.
        if (method_exists($fileModel, 'setDecorative') && null !== $request->request->get('decorative')) {
            $fileModel->setDecorative((int) filter_var($request->request->get('decorative'), \FILTER_VALIDATE_BOOLEAN));
        }

        $i18ns = json_decode((string) $request->request->get('i18ns', '{}'), true);

        foreach (\is_array($i18ns) ? $i18ns : [] as $locale => $i18n) {
            $fileModel->setLocale($locale)
                ->setTitle($i18n['title'] ?? '')
                ->setDescription($i18n['description'] ?? '')
                ->setChapo($i18n['chapo'] ?? '')
                ->setPostscriptum($i18n['postscriptum'] ?? '');

            if (method_exists($fileModel, 'setAlt') && isset($i18n['alt'])) {
                $fileModel->setAlt($i18n['alt']);
            }
        }

        // A translated file lands in the language the request names, the default one otherwise.
        if ($fileModel instanceof LocalizedFileModelInterface) {
            $fileModel->setLocale($this->resolveFileLocale($request));
        }

        $fileEvent = new FileCreateOrUpdateEvent($parentId);
        $fileEvent->setModel($fileModel);
        $fileEvent->setUploadedFile($file);

        $file = $this->eventDispatcher->dispatch(
            $fileEvent,
            'image' === $fileType ? TheliaEvents::IMAGE_SAVE : TheliaEvents::DOCUMENT_SAVE,
        );

        if ('image' !== $fileType) {
            return;
        }

        $event = new ImageEvent();

        $baseSourceFilePath = ConfigQuery::read('images_library_path');

        if (null === $baseSourceFilePath) {
            $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'images';
        } else {
            $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
        }

        $sourceFilePath = \sprintf(
            '%s/%s/%s',
            $baseSourceFilePath,
            $itemType,
            basename($file->getUploadedFile()->getFilename()),
        );

        $event->setSourceFilepath($sourceFilePath);
        $event->setCacheSubdirectory($fileType);
        $event->setHeight(100);
        $event->setWidth(200);
        $event->setRotation(0);
        $event->setResizeMode((string) ImageAction::EXACT_RATIO_WITH_BORDERS);

        $this->eventDispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);
    }

    /**
     * Gives one language of an image the uploaded file, replacing the one it stored.
     *
     * The previous file leaves the storage only when no other language shows it.
     *
     * @return string the locale the file was written for
     */
    public function replaceItemFileTranslation(
        LocalizedFileModelInterface $fileModel,
        string $fileType,
        Request $request,
    ): string {
        /** @var UploadedFile $file */
        $file = $request->files->get('fileToUpload');

        if (!$file->isValid()) {
            throw new FileException($file->getErrorMessage());
        }

        $this->fileProcessorService->validateUpload($file, $fileType);
        $this->fileProcessorService->sanitizeUpload($file);

        $locale = $this->resolveFileLocale($request);

        $oldModel = clone $fileModel;
        $fileModel->setLocale($locale);

        $fileEvent = new FileCreateOrUpdateEvent($fileModel->getParentId());
        $fileEvent->setModel($fileModel);
        $fileEvent->setOldModel($oldModel);
        $fileEvent->setUploadedFile($file);

        $this->eventDispatcher->dispatch(
            $fileEvent,
            'image' === $fileType ? TheliaEvents::IMAGE_UPDATE : TheliaEvents::DOCUMENT_UPDATE,
        );

        return $locale;
    }

    /**
     * The language a multipart body names under "locale", the default language when it names none.
     */
    public function resolveFileLocale(Request $request): string
    {
        $locale = $request->request->get('locale');

        if (null === $locale || '' === $locale) {
            return Lang::getDefaultLanguage()->getLocale();
        }

        if (null === LangQuery::create()->findOneByLocale((string) $locale)) {
            throw new UnprocessableEntityHttpException(\sprintf('The locale "%s" is not a language of this shop.', $locale));
        }

        return (string) $locale;
    }

    /**
     * @throws \ReflectionException
     */
    public function getPropertyFileConstraints(string $className, string $propertyName): array
    {
        $constraints = [];

        $reflectionClass = new \ReflectionClass($className);

        if ($reflectionClass->hasProperty($propertyName)) {
            $property = $reflectionClass->getProperty($propertyName);

            $attributes = $property->getAttributes(Assert\File::class);

            foreach ($attributes as $attribute) {
                $constraintInstance = $attribute->newInstance();
                $constraints[] = $constraintInstance;
            }

            $attributes = $property->getAttributes(Assert\Image::class);

            foreach ($attributes as $attribute) {
                $constraintInstance = $attribute->newInstance();
                $constraints[] = $constraintInstance;
            }
        }

        return $constraints;
    }
}
