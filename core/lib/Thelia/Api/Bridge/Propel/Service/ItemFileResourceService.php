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
use Symfony\Component\Validator\Constraints as Assert;
use Thelia\Action\Image as ImageAction;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileModelInterface;
use Thelia\Model\ConfigQuery;

readonly class ItemFileResourceService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
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

        // The Propel setters are natively typed, so the raw strings of a multipart
        // body have to be converted before they reach the model.
        $fileModel->setParentId($parentId)
            ->setVisible((int) filter_var($request->request->get('visible', '1'), \FILTER_VALIDATE_BOOLEAN));

        $position = $request->request->get('position');

        if (null !== $position && '' !== $position) {
            $fileModel->setPosition((int) $position);
        }

        $i18ns = json_decode((string) $request->request->get('i18ns', '{}'), true);

        foreach (\is_array($i18ns) ? $i18ns : [] as $locale => $i18n) {
            $fileModel->setLocale($locale)
                ->setTitle($i18n['title'] ?? '')
                ->setDescription($i18n['description'] ?? '')
                ->setChapo($i18n['chapo'] ?? '')
                ->setPostscriptum($i18n['postscriptum'] ?? '');
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
