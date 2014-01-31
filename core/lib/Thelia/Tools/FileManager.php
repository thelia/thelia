<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/
namespace Thelia\Tools;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\Document\DocumentCreateOrUpdateEvent;
use Thelia\Core\Event\Image\ImageCreateOrUpdateEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\ImageException;
use Thelia\Form\CategoryDocumentModification;
use Thelia\Form\CategoryImageModification;
use Thelia\Form\ContentDocumentModification;
use Thelia\Form\ContentImageModification;
use Thelia\Form\FolderDocumentModification;
use Thelia\Form\FolderImageModification;
use Thelia\Form\ProductDocumentModification;
use Thelia\Form\ProductImageModification;
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryDocumentQuery;
use Thelia\Model\CategoryImage;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentDocument;
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\ContentImage;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Exception\InvalidArgumentException;
use Thelia\Model\FolderDocument;
use Thelia\Model\FolderDocumentQuery;
use Thelia\Model\FolderImage;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductQuery;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/19/13
 * Time: 3:24 PM
 *
 * File Manager
 *
 * @package File
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class FileManager
{
    CONST TYPE_PRODUCT  = 'product';
    CONST TYPE_CATEGORY = 'category';
    CONST TYPE_CONTENT  = 'content';
    CONST TYPE_FOLDER   = 'folder';
    CONST TYPE_MODULE   = 'module';

    CONST FILE_TYPE_IMAGES   = 'images';
    CONST FILE_TYPE_DOCUMENTS   = 'documents';

    /**
     * Copy UploadedFile into the server storage directory
     *
     * @param int                                                                                                                 $parentId     Parent id
     * @param string                                                                                                              $parentType   Image type
     * @param FolderImage|ContentImage|CategoryImage|ProductImage|FolderDocument|ContentDocument|CategoryDocument|ProductDocument $model        Model saved
     * @param UploadedFile                                                                                                        $uploadedFile Ready to be uploaded file
     * @param string                                                                                                              $fileType     File type ex FileManager::FILE_TYPE_IMAGES
     *
     * @throws \Thelia\Exception\ImageException
     * @return UploadedFile
     */
    public function copyUploadedFile($parentId, $parentType, $model, $uploadedFile, $fileType)
    {
        $newUploadedFile = null;
        if ($uploadedFile !== null) {
            $directory = $this->getUploadDir($parentType, $fileType);
            $fileName = $this->renameFile($model->getId(), $uploadedFile);

            $newUploadedFile = $uploadedFile->move($directory, $fileName);
            $model->setFile($fileName);

            if (!$model->save()) {
                throw new ImageException(
                    sprintf(
                        '%s %s (%s) failed to be saved (image file)',
                        ucfirst($parentType),
                        $model->getFile(),
                        $fileType
                    )
                );
            }
        }

        return $newUploadedFile;
    }

    /**
     * Save image into the database
     *
     * @param ImageCreateOrUpdateEvent                            $event      Image event
     * @param FolderImage|ContentImage|CategoryImage|ProductImage $modelImage Image to save
     *
     * @return int                              Nb lines modified
     * @throws \Thelia\Exception\ImageException
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function saveImage(ImageCreateOrUpdateEvent $event, $modelImage)
    {
        $nbModifiedLines = 0;

        if ($modelImage->getFile() !== null) {
            switch ($event->getImageType()) {
                case self::TYPE_PRODUCT:
                    /** @var ProductImage $modelImage */
                    $modelImage->setProductId($event->getParentId());
                    break;
                case self::TYPE_CATEGORY:
                    /** @var CategoryImage $modelImage */
                    $modelImage->setCategoryId($event->getParentId());
                    break;
                case self::TYPE_CONTENT:
                    /** @var ContentImage $modelImage */
                    $modelImage->setContentId($event->getParentId());
                    break;
                case self::TYPE_FOLDER:
                    /** @var FolderImage $modelImage */
                    $modelImage->setFolderId($event->getParentId());
                    break;
                default:
                    throw new ImageException(
                        sprintf(
                            'Picture parent type is unknown (available types : %s)',
                            implode(
                                ',',
                                self::getAvailableTypes()
                            )
                        )
                    );
            }

            $nbModifiedLines = $modelImage->save();
            if (!$nbModifiedLines) {
                throw new ImageException(
                    sprintf(
                        'Image %s failed to be saved (image content)',
                        $modelImage->getFile()
                    )
                );
            }
        }

        return $nbModifiedLines;
    }

    /**
     * Save document into the database
     *
     * @param DocumentCreateOrUpdateEvent                                     $event         Image event
     * @param FolderDocument|ContentDocument|CategoryDocument|ProductDocument $modelDocument Document to save
     *
     * @throws \Thelia\Model\Exception\InvalidArgumentException
     * @return int                                              Nb lines modified
     * @todo refactor make all documents using propel inheritance and factorise image behaviour into one single clean action
     */
    public function saveDocument(DocumentCreateOrUpdateEvent $event, $modelDocument)
    {
        $nbModifiedLines = 0;

        if ($modelDocument->getFile() !== null) {
            switch ($event->getDocumentType()) {
                case self::TYPE_PRODUCT:
                    /** @var ProductImage $modelImage */
                    $modelDocument->setProductId($event->getParentId());
                    break;
                case self::TYPE_CATEGORY:
                    /** @var CategoryImage $modelImage */
                    $modelDocument->setCategoryId($event->getParentId());
                    break;
                case self::TYPE_CONTENT:
                    /** @var ContentImage $modelImage */
                    $modelDocument->setContentId($event->getParentId());
                    break;
                case self::TYPE_FOLDER:
                    /** @var FolderImage $modelImage */
                    $modelDocument->setFolderId($event->getParentId());
                    break;
                default:
                    throw new InvalidArgumentException(
                        sprintf(
                            'Document parent type is unknown (available types : %s)',
                            implode(
                                ',',
                                self::getAvailableTypes()
                            )
                        )
                    );
            }

            $nbModifiedLines = $modelDocument->save();
            if (!$nbModifiedLines) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Document %s failed to be saved (document content)',
                        $modelDocument->getFile()
                    )
                );
            }
        }

        return $nbModifiedLines;
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
     * @param CategoryImage|ProductImage|ContentImage|FolderImage|CategoryDocument|ProductDocument|ContentDocument|FolderDocument $model      File being deleted
     * @param string                                                                                                              $parentType Parent type ex : self::TYPE_PRODUCT
     * @param string                                                                                                              $fileType   File type ex FileManager::FILE_TYPE_DOCUMENTS
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function deleteFile($model, $parentType, $fileType)
    {
        $url = $this->getUploadDir($parentType, $fileType) . '/' . $model->getFile();
        unlink(str_replace('..', '', $url));
        $model->delete();
    }

    /**
     * Get image model from type
     *
     * @param string $parentType Parent type ex : self::TYPE_PRODUCT
     *
     * @return null|\Thelia\Model\CategoryImage|\Thelia\Model\ContentImage|\Thelia\Model\FolderImage|\Thelia\Model\ProductImage
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getImageModel($parentType)
    {
        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $model = new ProductImage();
                break;
            case self::TYPE_CATEGORY:
                $model = new CategoryImage();
                break;
            case self::TYPE_CONTENT:
                $model = new ContentImage();
                break;
            case self::TYPE_FOLDER:
                $model = new FolderImage();
                break;
            default:
                $model = null;
        }

        return $model;
    }

    /**
     * Get document model from type
     *
     * @param string $parentType Parent type ex : self::TYPE_PRODUCT
     *
     * @return null|ProductDocument|CategoryDocument|ContentDocument|FolderDocument
     *
     * @todo refactor make all documents using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getDocumentModel($parentType)
    {
        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $model = new ProductDocument();
                break;
            case self::TYPE_CATEGORY:
                $model = new CategoryDocument();
                break;
            case self::TYPE_CONTENT:
                $model = new ContentDocument();
                break;
            case self::TYPE_FOLDER:
                $model = new FolderDocument();
                break;
            default:
                $model = null;
        }

        return $model;
    }

    /**
     * Get image model query from type
     *
     * @param string $parentType Parent type ex : self::TYPE_PRODUCT
     *
     * @return null|\Thelia\Model\CategoryImageQuery|\Thelia\Model\ContentImageQuery|\Thelia\Model\FolderImageQuery|\Thelia\Model\ProductImageQuery
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getImageModelQuery($parentType)
    {
        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $model = new ProductImageQuery();
                break;
            case self::TYPE_CATEGORY:
                $model = new CategoryImageQuery();
                break;
            case self::TYPE_CONTENT:
                $model = new ContentImageQuery();
                break;
            case self::TYPE_FOLDER:
                $model = new FolderImageQuery();
                break;
            default:
                $model = null;
        }

        return $model;
    }

    /**
     * Get document model query from type
     *
     * @param string $parentType Parent type ex : self::TYPE_PRODUCT
     *
     * @return null|ProductDocumentQuery|CategoryDocumentQuery|ContentDocumentQuery|FolderDocumentQuery
     *
     * @todo refactor make all documents using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getDocumentModelQuery($parentType)
    {
        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $model = new ProductDocumentQuery();
                break;
            case self::TYPE_CATEGORY:
                $model = new CategoryDocumentQuery();
                break;
            case self::TYPE_CONTENT:
                $model = new ContentDocumentQuery();
                break;
            case self::TYPE_FOLDER:
                $model = new FolderDocumentQuery();
                break;
            default:
                $model = null;
        }

        return $model;
    }

    /**
     * Get form service id from type
     *
     * @param string $parentType Parent type ex : self::TYPE_PRODUCT
     * @param string $fileType   Parent id
     *
     * @return string
     *
     * @todo refactor make all documents using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getFormId($parentType, $fileType)
    {
        switch ($fileType) {
            case self::FILE_TYPE_IMAGES:
                $type = 'image';
                break;
            case self::FILE_TYPE_DOCUMENTS:
                $type = 'document';
                break;
            default:
                return false;
        }

        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $formId = 'thelia.admin.product.' . $type . '.modification';
                break;
            case self::TYPE_CATEGORY:
                $formId = 'thelia.admin.category.' . $type . '.modification';
                break;
            case self::TYPE_CONTENT:
                $formId = 'thelia.admin.content.' . $type . '.modification';
                break;
            case self::TYPE_FOLDER:
                $formId = 'thelia.admin.folder.' . $type . '.modification';
                break;
            default:
                $formId = false;
        }

        return $formId;
    }

    /**
     * Get image parent model from type
     *
     * @param string $parentType Parent type ex : self::TYPE_PRODUCT
     * @param int    $parentId   Parent Id
     *
     * @return null|\Thelia\Model\Category|\Thelia\Model\Content|\Thelia\Model\Folder|\Thelia\Model\Product
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getParentFileModel($parentType, $parentId)
    {
        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $model = ProductQuery::create()->findPk($parentId);
                break;
            case self::TYPE_CATEGORY:
                $model = CategoryQuery::create()->findPk($parentId);
                break;
            case self::TYPE_CONTENT:
                $model = ContentQuery::create()->findPk($parentId);
                break;
            case self::TYPE_FOLDER:
                $model = FolderQuery::create()->findPk($parentId);
                break;
            default:
                $model = null;
        }

        return $model;
    }

    /**
     * Get image parent model from type
     *
     * @param string  $parentType Parent type ex : self::TYPE_PRODUCT
     * @param Request $request    Request service
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     * @return ProductImageModification|CategoryImageModification|ContentImageModification|FolderImageModification
     */
    public function getImageForm($parentType, Request $request)
    {
        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $form = new ProductImageModification($request);
                break;
            case self::TYPE_CATEGORY:
                $form = new CategoryImageModification($request);
                break;
            case self::TYPE_CONTENT:
                $form = new ContentImageModification($request);
                break;
            case self::TYPE_FOLDER:
                $form = new FolderImageModification($request);
                break;
            default:
                $form = null;
        }

        return $form;

    }

    /**
     * Get document parent model from type
     *
     * @param string  $parentType Parent type ex : self::TYPE_PRODUCT
     * @param Request $request    Request service
     *
     * @todo refactor make all document using propel inheritance and factorise image behaviour into one single clean action
     * @return ProductDocumentModification|CategoryDocumentModification|ContentDocumentModification|FolderDocumentModification
     */
    public function getDocumentForm($parentType, Request $request)
    {
        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $form = new ProductDocumentModification($request);
                break;
            case self::TYPE_CATEGORY:
                $form = new CategoryDocumentModification($request);
                break;
            case self::TYPE_CONTENT:
                $form = new ContentDocumentModification($request);
                break;
            case self::TYPE_FOLDER:
                $form = new FolderDocumentModification($request);
                break;
            default:
                $form = null;
        }

        return $form;

    }

    /**
     * Get image upload dir
     *
     * @param string $parentType Parent type ex FileManager::TYPE_PRODUCT
     * @param string $fileType   File type ex : self::FILE_TYPE_DOCUMENTS
     *
     * @return string Uri
     */
    public function getUploadDir($parentType, $fileType)
    {
        if (!in_array($fileType, self::$availableFileType)) {
            return false;
        }

        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $uri = THELIA_LOCAL_DIR . 'media/' . $fileType . '/' . self::TYPE_PRODUCT;
                break;
            case self::TYPE_CATEGORY:
                $uri = THELIA_LOCAL_DIR . 'media/' . $fileType . '/' . self::TYPE_CATEGORY;
                break;
            case self::TYPE_CONTENT:
                $uri = THELIA_LOCAL_DIR . 'media/' . $fileType . '/' . self::TYPE_CONTENT;
                break;
            case self::TYPE_FOLDER:
                $uri = THELIA_LOCAL_DIR . 'media/' . $fileType . '/' . self::TYPE_FOLDER;
                break;
            default:
                $uri = false;
        }

        return $uri;

    }

    /**
     * Deduce image redirecting URL from parent type
     *
     * @param string $parentType Parent type ex : self::TYPE_PRODUCT
     * @param int    $parentId   Parent id
     * @param string $fileType   File type ex : self::FILE_TYPE_DOCUMENTS
     *
     * @return string
     */
    public function getRedirectionUrl($parentType, $parentId, $fileType)
    {
        if (!in_array($fileType, self::$availableFileType)) {
            return false;
        }

        switch ($parentType) {
            case self::TYPE_PRODUCT:
                $uri = '/admin/products/update?product_id=' . $parentId . '&current_tab=' . $fileType;
                break;
            case self::TYPE_CATEGORY:
                $uri = '/admin/categories/update?category_id=' . $parentId . '&current_tab=' . $fileType;
                break;
            case self::TYPE_CONTENT:
                $uri = '/admin/content/update/' . $parentId . '?current_tab=' . $fileType;
                break;
            case self::TYPE_FOLDER:
                $uri = '/admin/folders/update/' . $parentId . '?current_tab=' . $fileType;
                break;
            default:
                $uri = false;
        }

        return $uri;

    }

    /** @var array Available file parent type */
    public static $availableType = array(
        self::TYPE_PRODUCT,
        self::TYPE_CATEGORY,
        self::TYPE_CONTENT,
        self::TYPE_FOLDER,
        self::TYPE_MODULE
    );

    /** @var array Available file type type */
    public static $availableFileType = array(
        self::FILE_TYPE_DOCUMENTS,
        self::FILE_TYPE_IMAGES
    );

    /**
     * Rename file with image model id
     *
     * @param int          $modelId      Model id
     * @param UploadedFile $uploadedFile File being saved
     *
     * @return string
     */
    public function renameFile($modelId, $uploadedFile)
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

    /**
     * Return all document and image types
     *
     * @return array
     */
    public static function getAvailableTypes()
    {
        return array(
            self::TYPE_CATEGORY,
            self::TYPE_CONTENT,
            self::TYPE_FOLDER,
            self::TYPE_PRODUCT,
            self::TYPE_MODULE,
        );
    }
}
