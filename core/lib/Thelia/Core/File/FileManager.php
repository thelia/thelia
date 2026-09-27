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

namespace Thelia\Core\File;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\File\Exception\FileException;
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

        if ($model instanceof LocalizedFileModelInterface) {
            $fileName = $this->freeLocalizedFileName($directory, $fileName, (string) $model->getLocale());
        }

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
        $files = $model instanceof LocalizedFileModelInterface ? $model->getStoredFiles() : [$model->getFile()];

        foreach ($files as $file) {
            @unlink(str_replace('..', '', $model->getUploadDir().DS.$file));
        }

        $model->delete();
    }

    /**
     * Removes from storage the file an upload is about to replace.
     *
     * A translated file is the one of the language being edited, and it stays on disk
     * as long as another language still shows it: after an upgrade every language of
     * an image shares the same file.
     */
    public function removeReplacedFile(FileModelInterface $model, FileModelInterface $oldModel): void
    {
        if (!$model instanceof LocalizedFileModelInterface) {
            unlink(str_replace('..', '', $model->getUploadDir().'/'.$oldModel->getFile()));

            return;
        }

        $file = $model->getOwnFile();

        if (null === $file || $model->isFileUsedByAnotherLocale($file)) {
            return;
        }

        $path = str_replace('..', '', $model->getUploadDir().'/'.$file);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * A name no stored file carries yet: two languages uploading files of the same name
     * would otherwise write over each other.
     */
    private function freeLocalizedFileName(string $directory, string $fileName, string $locale): string
    {
        if (!file_exists($directory.DS.$fileName)) {
            return $fileName;
        }

        $extension = pathinfo($fileName, \PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, \PATHINFO_FILENAME).'-'.$this->sanitizeFileName($locale);
        $suffix = '' === $extension ? '' : '.'.$extension;
        $candidate = $baseName.$suffix;
        $counter = 1;

        while (file_exists($directory.DS.$candidate)) {
            $candidate = $baseName.'-'.$counter.$suffix;
            ++$counter;
        }

        return $candidate;
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
