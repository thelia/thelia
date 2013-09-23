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
use Thelia\Core\Event\ImagesCreateOrUpdateEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\ImageException;
use Thelia\Form\CategoryImageModification;
use Thelia\Form\ContentImageModification;
use Thelia\Form\FolderImageModification;
use Thelia\Form\ProductImageModification;
use Thelia\Model\AdminLog;
use Thelia\Model\CategoryImage;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentImage;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderImage;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\FolderQuery;
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

    /** @var ContainerInterface Service Container */
    protected $container = null;

    /** @var Translator Service Translator */
    protected $translator = null;

    /**
     * Constructor
     *
     * @param ContainerInterface $container Service container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->translator = $this->container->get('thelia.translator');
    }

    /**
     * Copy UploadedFile into the server storage directory
     *
     * @param int                                                 $parentId         Parent id
     * @param string                                              $imageType        Image type
     * @param FolderImage|ContentImage|CategoryImage|ProductImage $modelImage       Image saved
     * @param UploadedFile                                        $uploadedFile     Ready to be uploaded file
     *
     * @throws \Thelia\Exception\ImageException
     * @return UploadedFile
     */
    public function copyUploadedFile($parentId, $imageType, $modelImage, $uploadedFile)
    {
        if ($uploadedFile !== null) {
            $directory = $this->getUploadDir($imageType);
            $fileName = $this->renameFile($modelImage->getId(), $uploadedFile);

            $this->adminLogAppend(
                $this->translator->trans(
                    'Uploading picture %pictureName% to %directory% for parent_id %parentId% (%parentType%)',
                    array(
                        '%pictureName%' => $uploadedFile->getClientOriginalName(),
                        '%directory%' => $directory . '/' . $fileName,
                        '%parentId%' => $parentId,
                        '%parentType%' => $imageType
                    ),
                    'image'
                )
            );

            $newUploadedFile = $uploadedFile->move($directory, $fileName);
            $modelImage->setFile($fileName);

            if (!$modelImage->save()) {
                throw new ImageException(
                    sprintf(
                        'Image %s (%s) failed to be saved (image file)',
                        $modelImage->getFile(),
                        $imageType
                    )
                );
            }
        }

        return $newUploadedFile;
    }

    /**
     * Save image into the database
     *
     * @param ImagesCreateOrUpdateEvent                            $event      Image event
     * @param FolderImage|ContentImage|CategoryImage|ProductImage $modelImage Image to save
     *
     * @return int Nb lines modified
     * @throws \Thelia\Exception\ImageException
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function saveImage(ImagesCreateOrUpdateEvent $event, $modelImage)
    {
        $nbModifiedLines = 0;

        if ($modelImage->getFile() !== null) {
            switch ($event->getImageType()) {
                case ImagesCreateOrUpdateEvent::TYPE_PRODUCT:
                    /** @var ProductImage $modelImage */
                    $modelImage->setProductId($event->getParentId());
                    break;
                case ImagesCreateOrUpdateEvent::TYPE_CATEGORY:
                    /** @var CategoryImage $modelImage */
                    $modelImage->setCategoryId($event->getParentId());
                    break;
                case ImagesCreateOrUpdateEvent::TYPE_CONTENT:
                    /** @var ContentImage $modelImage */
                    $modelImage->setContentId($event->getParentId());
                    break;
                case ImagesCreateOrUpdateEvent::TYPE_FOLDER:
                    /** @var FolderImage $modelImage */
                    $modelImage->setFolderId($event->getParentId());
                    break;
                default:
                    throw new ImageException(
                        sprintf(
                            'Picture parent type is unknown (available types : %s)',
                            implode(
                                ',',
                                $event->getAvailableType()
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
        $cleanName = strtr($string, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
        $cleanName = strtr($cleanName, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));

        $cleanName = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $cleanName);

        return $cleanName;
    }

    /**
     * Helper to append a message to the admin log.
     *
     * @param string $message
     */
    public function adminLogAppend($message)
    {
        AdminLog::append(
            $message,
            $this->container->get('request'),
            $this->container->get('thelia.securityContext')->getAdminUser()
        );
    }


    /**
     * Delete image from file storage and database
     *
     * @param CategoryImage|ProductImage|ContentImage|FolderImage $imageModel Image being deleted
     * @param string $parentType Parent type
     */
    public function deleteImage($imageModel, $parentType)
    {
        $url = $this->getUploadDir($parentType) . '/' . $imageModel->getFile();
        unlink(str_replace('..', '', $url));
        $imageModel->delete();
    }


    /**
     * Get image model from type
     *
     * @param string $parentType Parent type
     *
     * @return null|\Thelia\Model\CategoryImage|\Thelia\Model\ContentImage|\Thelia\Model\FolderImage|\Thelia\Model\ProductImage
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getImageModel($parentType)
    {
        switch ($parentType) {
            case ImagesCreateOrUpdateEvent::TYPE_PRODUCT:
                $model = new ProductImage();
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CATEGORY:
                $model = new CategoryImage();
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CONTENT:
                $model = new ContentImage();
                break;
            case ImagesCreateOrUpdateEvent::TYPE_FOLDER:
                $model = new FolderImage();
                break;
            default:
                $model = null;
        }

        return $model;
    }

    /**
     * Get image model query from type
     *
     * @param string $parentType
     *
     * @return null|\Thelia\Model\CategoryImageQuery|\Thelia\Model\ContentImageQuery|\Thelia\Model\FolderImageQuery|\Thelia\Model\ProductImageQuery
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getImageModelQuery($parentType)
    {
        switch ($parentType) {
            case ImagesCreateOrUpdateEvent::TYPE_PRODUCT:
                $model = new ProductImageQuery();
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CATEGORY:
                $model = new CategoryImageQuery();
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CONTENT:
                $model = new ContentImageQuery();
                break;
            case ImagesCreateOrUpdateEvent::TYPE_FOLDER:
                $model = new FolderImageQuery();
                break;
            default:
                $model = null;
        }

        return $model;
    }

    /**
     * Get image parent model from type
     *
     * @param string $parentType
     * @param int    $parentId
     *
     * @return null|\Thelia\Model\Category|\Thelia\Model\Content|\Thelia\Model\Folder|\Thelia\Model\Product
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getParentImageModel($parentType, $parentId)
    {
        switch ($parentType) {
            case ImagesCreateOrUpdateEvent::TYPE_PRODUCT:
                $model = ProductQuery::create()->findPk($parentId);
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CATEGORY:
                $model = CategoryQuery::create()->findPk($parentId);
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CONTENT:
                $model = ContentQuery::create()->findPk($parentId);
                break;
            case ImagesCreateOrUpdateEvent::TYPE_FOLDER:
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
     * @param string  $parentType Parent type
     * @param Request $request    Request service
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     * @return ProductImageModification|CategoryImageModification|ContentImageModification|FolderImageModification
     */
    public function getImageForm($parentType, Request $request)
    {
        switch ($parentType) {
            case ImagesCreateOrUpdateEvent::TYPE_PRODUCT:
                $form = new ProductImageModification($request);
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CATEGORY:
                $form = new CategoryImageModification($request);
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CONTENT:
                $form = new ContentImageModification($request);
                break;
            case ImagesCreateOrUpdateEvent::TYPE_FOLDER:
                $form = new FolderImageModification($request);
                break;
            default:
                $model = null;
        }

        return $form;

    }

    /**
     * Get image upload dir
     *
     * @param string $parentType Parent type
     *
     * @return string Uri
     */
    public function getUploadDir($parentType)
    {
        switch ($parentType) {
            case ImagesCreateOrUpdateEvent::TYPE_PRODUCT:
                $uri = THELIA_LOCAL_DIR . 'media/images/' . ImagesCreateOrUpdateEvent::TYPE_PRODUCT;
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CATEGORY:
                $uri = THELIA_LOCAL_DIR . 'media/images/' . ImagesCreateOrUpdateEvent::TYPE_CATEGORY;
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CONTENT:
                $uri = THELIA_LOCAL_DIR . 'media/images/' . ImagesCreateOrUpdateEvent::TYPE_CONTENT;
                break;
            case ImagesCreateOrUpdateEvent::TYPE_FOLDER:
                $uri = THELIA_LOCAL_DIR . 'media/images/' . ImagesCreateOrUpdateEvent::TYPE_FOLDER;
                break;
            default:
                $uri = null;
        }

        return $uri;

    }

    /**
     * Deduce image redirecting URL from parent type
     *
     * @param string $parentType Parent type
     * @param int    $parentId   Parent id
     * @return string
     */
    public function getRedirectionUrl($parentType, $parentId)
    {
        switch ($parentType) {
            case ImagesCreateOrUpdateEvent::TYPE_PRODUCT:
                $uri = '/admin/products/update?product_id=' . $parentId;
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CATEGORY:
                $uri = '/admin/categories/update?category_id=' . $parentId;
                break;
            case ImagesCreateOrUpdateEvent::TYPE_CONTENT:
                $uri = '/admin/content/update/' . $parentId;
                break;
            case ImagesCreateOrUpdateEvent::TYPE_FOLDER:
                $uri = '/admin/folders/update/' . $parentId;
                break;
            default:
                $uri = false;
        }

        return $uri;

    }

    /** @var array Available image parent type */
    public static $availableType = array(
        self::TYPE_PRODUCT,
        self::TYPE_CATEGORY,
        self::TYPE_CONTENT,
        self::TYPE_FOLDER,
        self::TYPE_MODULE
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
        $fileName = $this->sanitizeFileName(
            str_replace('.' . $extension, '', $uploadedFile->getClientOriginalName()) . "-" . $modelId . "." . strtolower(
            $extension
            )
        );
        return $fileName;
    }
}