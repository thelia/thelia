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

namespace Thelia\Files;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Exception\FileException;
use Thelia\Exception\ImageException;

/**
 * File Manager.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 */
class FileManager
{
    public function __construct(
        #[Autowire(param: 'file_model.classes')]
        protected array $supportedFileModels,
    ) {
    }

    protected function getFileTypeIdentifier(string $fileType, string $parentType): string
    {
        return strtolower(\sprintf('%s.%s', $fileType, $parentType));
    }

    /**
     * @throws FileException if the file type is not supported, or if the class does not implements FileModelInterface
     */
    public function getModelInstance(string $fileType, string $parentType): FileModelInterface
    {
        if (!isset($this->supportedFileModels[$this->getFileTypeIdentifier($fileType, $parentType)])) {
            throw new FileException(\sprintf("Unsupported file type '%s' for parent type '%s'", $fileType, $parentType));
        }

        $className = $this->supportedFileModels[$this->getFileTypeIdentifier($fileType, $parentType)];

        $instance = new $className();

        if (!$instance instanceof FileModelInterface) {
            throw new FileException(\sprintf("Wrong class type for file type '%s', parent type '%s'. Class '%s' should implements FileModelInterface", $fileType, $parentType, $className));
        }

        return $instance;
    }

    public function addFileModel(string $fileType, string $parentType, string $fullyQualifiedClassName): void
    {
        $this->supportedFileModels[$this->getFileTypeIdentifier($fileType, $parentType)] = $fullyQualifiedClassName;
    }

    /**
     * @throws ImageException
     */
    public function copyUploadedFile(FileModelInterface $model, UploadedFile $uploadedFile): UploadedFile
    {
        $fileSystem = new Filesystem();

        $directory = $model->getUploadDir();

        if (!$fileSystem->exists($directory)) {
            $fileSystem->mkdir($directory);
        }

        $fileName = $this->renameFile($model->getId(), $uploadedFile);
        $filePath = $directory.DS.$fileName;

        $fileSystem->rename($uploadedFile->getPathname(), $filePath);
        $fileSystem->chmod($filePath, 0o660);

        $newUploadedFile = new UploadedFile($filePath, $fileName);
        $model->setFile($fileName);

        if (!$model->save()) {
            throw new ImageException(\sprintf('Failed to update model after copy of uploaded file %s to %s', $uploadedFile, $model->getFile()));
        }

        return $newUploadedFile;
    }

    /**
     * @throws ImageException
     */
    protected function saveFile(int $parentId, FileModelInterface $fileModel): int
    {
        $nbModifiedLines = 0;

        if (null !== $fileModel->getFile()) {
            $fileModel->setParentId($parentId);

            $nbModifiedLines = $fileModel->save();

            if (!$nbModifiedLines) {
                throw new ImageException(\sprintf('Failed to update %s file model', $fileModel->getFile()));
            }
        }

        return $nbModifiedLines;
    }

    public function saveImage(FileCreateOrUpdateEvent $event, FileModelInterface $imageModel): int
    {
        return $this->saveFile($event->getParentId(), $imageModel);
    }

    public function saveDocument(FileCreateOrUpdateEvent $event, FileModelInterface $documentModel): int
    {
        return $this->saveFile($event->getParentId(), $documentModel);
    }

    public function sanitizeFileName(string $string): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9-_\.]/', '', $string));
    }

    public function deleteFile(FileModelInterface $model): void
    {
        $url = $model->getUploadDir().DS.$model->getFile();

        @unlink(str_replace('..', '', $url));

        $model->delete();
    }

    public function renameFile(int $modelId, UploadedFile $uploadedFile): string
    {
        $extension = $uploadedFile->getClientOriginalExtension();

        if ('' !== $extension && '0' !== $extension) {
            $extension = '.'.strtolower($extension);
        }

        return $this->sanitizeFileName(
            str_replace(
                $extension,
                '',
                $uploadedFile->getClientOriginalName(),
            ).'-'.$modelId.$extension,
        );
    }

    public function isImage(string $mimeType): bool
    {
        $isValid = false;

        $allowedType = ['image/jpeg', 'image/png', 'image/gif'];

        if (\in_array($mimeType, $allowedType, true)) {
            $isValid = true;
        }

        return $isValid;
    }
}
