<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Files;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Exception\FileException;
use Thelia\Exception\ImageException;

/**
 * File Manager
 *
 * @package File
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 *
 */
class FileManager
{
    protected $supportedFileModels = array();

    /**
     * Create a new FileManager instance.
     *
     * @param array $supportedFileModels The key should have form type.parent, where type is the file type (document or image) and parent is the parent object of the file, form example product, brand, folder, etc.
     */
    public function __construct($supportedFileModels)
    {
        $this->supportedFileModels = $supportedFileModels;
    }

    /**
     * Create the file type identifier, to access the related class in the supportedFileModels table.
     *
     * @param  string $fileType   the file type, e.g. document or image.
     * @param  string $parentType the parent object type, e.g. product, folder, brand, etc.
     * @return string
     */
    protected function getFileTypeIdentifier($fileType, $parentType)
    {
        return strtolower("$fileType.$parentType");
    }
    /**
     * Create a new FileModelInterface instance, from the supportedFileModels table
     *
     * @param string $fileType   the file type, such as document, image, etc.
     * @param string $parentType the parent type, such as product, category, etc.
     *
     * @return FileModelInterface a file model interface instance
     *
     * @throws FileException if the file type is not supported, or if the class does not implements FileModelInterface
     */
    public function getModelInstance($fileType, $parentType)
    {
        if (! isset($this->supportedFileModels[$this->getFileTypeIdentifier($fileType, $parentType)])) {
            throw new FileException(
                sprintf("Unsupported file type '%s' for parent type '%s'", $fileType, $parentType)
            );
        }

        $className = $this->supportedFileModels[$this->getFileTypeIdentifier($fileType, $parentType)];

        $instance = new $className;

        if (! $instance instanceof FileModelInterface) {
            throw new FileException(
                sprintf(
                    "Wrong class type for file type '%s', parent type '%s'. Class '%s' should implements FileModelInterface",
                    $fileType,
                    $parentType,
                    $className
                )
            );
        }

        return $instance;
    }

    /**
     * A a new FileModelInterface class name to the supported class list.
     *
     * @param string $fileType                the file type, such as document, image, etc.
     * @param string $parentType              the parent type, such as Product, Category, etc.
     * @param string $fullyQualifiedClassName the fully qualified class name
     */
    public function addFileModel($fileType, $parentType, $fullyQualifiedClassName)
    {
        $this->supportedFileModels[$this->getFileTypeIdentifier($fileType, $parentType)] = $fullyQualifiedClassName;
    }

     /**
     * Copy UploadedFile into the server storage directory
     *
     * @param FileModelInterface $model        Model saved
     * @param UploadedFile       $uploadedFile Ready to be uploaded file
     * @param ConnectionInterface $con         current transaction with database
     *
     * @throws \Thelia\Exception\ImageException
     * @return UploadedFile|null
     */
    public function copyUploadedFile(FileModelInterface $model, UploadedFile $uploadedFile, ConnectionInterface $con = null)
    {
        $newUploadedFile = null;

        if ($uploadedFile !== null) {
            $directory = $model->getUploadDir();

            $fileName = $this->renameFile($model->getId(), $uploadedFile);

            $newUploadedFile = $uploadedFile->move($directory, $fileName);
            $model->setFile($fileName);

            if (!$model->save($con)) {
                throw new ImageException(
                    sprintf(
                        'Failed to update model after copy of uploaded file %s to %s',
                        $uploadedFile,
                        $model->getFile()
                    )
                );
            }
        }

        return $newUploadedFile;
    }
    /**
     * Save file into the database
     *
     * @param int                $parentId  the parent object ID
     * @param FileModelInterface $fileModel the file model object (image or document) to save.
     *
     * @return int number of modified rows in database
     *
     * @throws \Thelia\Exception\ImageException
     */
    protected function saveFile($parentId, FileModelInterface $fileModel)
    {
        $nbModifiedLines = 0;

        if ($fileModel->getFile() !== null) {
            $fileModel->setParentId($parentId);

            $nbModifiedLines = $fileModel->save();

            if (!$nbModifiedLines) {
                throw new ImageException(
                    sprintf(
                        'Failed to update %s file model',
                        $fileModel->getFile()
                    )
                );
            }
        }

        return $nbModifiedLines;
    }

    /**
     * Save file into the database
     *
     * @param FileCreateOrUpdateEvent $event      the event
     * @param FileModelInterface      $imageModel the file model object (image or document) to save.
     *
     * @return int number of modified rows in database
     */
    public function saveImage(FileCreateOrUpdateEvent $event, FileModelInterface $imageModel)
    {
        return $this->saveFile($event->getParentId(), $imageModel);
    }

    /**
     * Save file into the database
     *
     * @param FileCreateOrUpdateEvent $event         the event
     * @param FileModelInterface      $documentModel the file model object (image or document) to save.
     *
     * @return int number of modified rows in database
     */
    public function saveDocument(FileCreateOrUpdateEvent $event, FileModelInterface $documentModel)
    {
        return $this->saveFile($event->getParentId(), $documentModel);
    }

    /**
     * Sanitizes a filename replacing whitespace with dashes
     *
     * Removes special characters that are illegal in filenames on certain
     * operating systems and special characters requiring special escaping
     * to manipulate at the command line.
     *
     * @param string $string The filename to be sanitized
     *
     * @return string The sanitized filename
     */
    public function sanitizeFileName($string)
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9-_\.]/', '', $string));
    }

    /**
     * Delete image from file storage and database
     *
     * @param FileModelInterface $model File being deleted
     */
    public function deleteFile(FileModelInterface $model)
    {
        $url = $model->getUploadDir() . DS . $model->getFile();

        @unlink(str_replace('..', '', $url));

        $model->delete();
    }

    /**
     * Rename file with image model id
     *
     * @param int          $modelId      Model id
     * @param UploadedFile $uploadedFile File being saved
     *
     * @return string
     */
    public function renameFile($modelId, UploadedFile $uploadedFile)
    {
        $extension = $uploadedFile->getClientOriginalExtension();
        if (!empty($extension)) {
            $extension = '.' . strtolower($extension);
        }
        $fileName = $this->sanitizeFileName(
            str_replace(
                $extension,
                '',
                $uploadedFile->getClientOriginalName()
            ) . '-' . $modelId . $extension
        );

        return $fileName;
    }

    /**
     * Check if a file is an image
     * Check based on mime type
     *
     * @param string $mimeType File mime type
     *
     * @return bool
     */
    public function isImage($mimeType)
    {
        $isValid = false;

        $allowedType = array('image/jpeg' , 'image/png' ,'image/gif');

        if (in_array($mimeType, $allowedType)) {
            $isValid = true;
        }

        return $isValid;
    }
}
