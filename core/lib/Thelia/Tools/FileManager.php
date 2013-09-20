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
use Thelia\Core\Event\ImageCreateOrUpdateEvent;
use Thelia\Core\Event\ImageDeleteEvent;
use Thelia\Exception\ImageException;
use Thelia\Model\AdminLog;
use Thelia\Model\Base\CategoryImage;
use Thelia\Model\ContentImage;
use Thelia\Model\FolderImage;
use Thelia\Model\ProductImage;

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
     * @param array                                               $newUploadedFiles UploadedFile array to update
     *
     * @throws \Thelia\Exception\ImageException
     * @return array Updated UploadedFile array
     */
    public function copyUploadedFile($parentId, $imageType, $modelImage, $uploadedFile, $newUploadedFiles)
    {
        if ($uploadedFile !== null) {
            $directory = $modelImage->getUploadDir();
            $fileName = $this->sanitizeFileName(
                $uploadedFile->getClientOriginalName() . "-" . $modelImage->getId() . "." . strtolower(
                    $uploadedFile->getClientOriginalExtension()
                )
            );

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

            $newUploadedFiles[] = array('file' => $uploadedFile->move($directory, $fileName));
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

        return $newUploadedFiles;
    }

    /**
     * Save image into the database
     *
     * @param ImageCreateOrUpdateEvent                            $event      Image event
     * @param FolderImage|ContentImage|CategoryImage|ProductImage $modelImage Image to save
     *
     * @return int Nb lines modified
     * @throws \Thelia\Exception\ImageException
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function saveImage(ImageCreateOrUpdateEvent $event, $modelImage)
    {
        $nbModifiedLines = 0;

        if ($modelImage->getFile() !== null) {
            switch ($event->getImageType()) {
                case ImageCreateOrUpdateEvent::TYPE_PRODUCT:
                    /** @var ProductImage $modelImage */
                    $modelImage->setProductId($event->getParentId());
                    break;
                case ImageCreateOrUpdateEvent::TYPE_CATEGORY:
                    /** @var CategoryImage $modelImage */
                    $modelImage->setCategoryId($event->getParentId());
                    break;
                case ImageCreateOrUpdateEvent::TYPE_CONTENT:
                    /** @var ContentImage $modelImage */
                    $modelImage->setContentId($event->getParentId());
                    break;
                case ImageCreateOrUpdateEvent::TYPE_FOLDER:
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
     */
    public function deleteImage($imageModel)
    {
        unlink($imageModel->getAbsolutePath());
        $imageModel->delete();
    }
}