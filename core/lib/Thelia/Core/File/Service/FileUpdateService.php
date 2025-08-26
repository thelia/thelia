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

namespace Thelia\Core\File\Service;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\File\FileManager;
use Thelia\Core\File\FileModelInterface;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;

readonly class FileUpdateService
{
    public function __construct(
        private FileManager $fileManager,
        private Request $request,
    ) {
    }

    /**
     * @throws FormValidationException If form validation fails
     * @throws PropelException         If database operation fails
     * @throws \Exception              If any other error occurs
     */
    public function updateFile(
        EventDispatcherInterface $eventDispatcher,
        int $fileId,
        string $parentType,
        string $objectType,
        string $eventName,
        BaseForm $fileUpdateForm,
    ): FileModelInterface {
        $fileModelInstance = $this->fileManager->getModelInstance($objectType, $parentType);

        /** @var FileModelInterface $file */
        $file = $fileModelInstance->getQueryInstance()->findPk($fileId);

        if (null === $file) {
            throw new \InvalidArgumentException(\sprintf('%d %s id does not exist', $fileId, $objectType));
        }

        $oldFile = clone $file;
        $data = $fileUpdateForm->getForm()->getData();

        $event = new FileCreateOrUpdateEvent(0);

        if (\array_key_exists('visible', $data)) {
            $file->setVisible($data['visible'] ? 1 : 0);
        }

        $file->setLocale($data['locale']);

        if (\array_key_exists('title', $data)) {
            $file->setTitle($data['title']);
        }

        if (\array_key_exists('chapo', $data)) {
            $file->setChapo($data['chapo']);
        }

        if (\array_key_exists('description', $data)) {
            $file->setDescription($data['description']);
        }

        if (\array_key_exists('postscriptum', $data)) {
            $file->setPostscriptum($data['postscriptum']);
        }

        if (isset($data['file'])) {
            $file->setFile($data['file']);
        }

        $event->setModel($file);
        $event->setOldModel($oldFile);

        $files = $this->request->files;
        $fileForm = $files->get($fileUpdateForm::getName());

        if (isset($fileForm['file'])) {
            $event->setUploadedFile($fileForm['file']);
        }

        $eventDispatcher->dispatch($event, $eventName);

        return $event->getModel();
    }

    public function updateFileTitle(
        int $fileId,
        string $parentType,
        string $objectType,
        string $title,
        string $locale,
    ): void {
        $fileModelInstance = $this->fileManager->getModelInstance($objectType, $parentType);

        /** @var FileModelInterface $file */
        $file = $fileModelInstance->getQueryInstance()->findPk($fileId);

        if ('' !== $title && '0' !== $title) {
            $file->setLocale($locale);
            $file->setTitle($title);
            $file->save();
        }
    }

    public function logUpdateError(string $objectType, string $message): void
    {
        Tlog::getInstance()->error(\sprintf('Error during %s editing : %s.', $objectType, $message));
    }
}
