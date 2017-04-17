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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentI18n;
use Thelia\Model\ProductDocumentI18nQuery;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageI18nQuery;
use Thelia\Model\ProductImageQuery;

/**
 * Class File
 *
 * @package Thelia\Action
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class File extends BaseAction implements EventSubscriberInterface
{
    public function cloneFile(ProductCloneEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $originalProductId = $event->getOriginalProduct()->getId();
        $clonedProduct = $event->getClonedProduct();

        foreach ($event->getTypes() as $type) {
            $originalProductFiles = [];

            switch ($type) {
                case 'images':
                    $originalProductFiles = ProductImageQuery::create()
                        ->findByProductId($originalProductId);
                    break;

                case 'documents':
                    $originalProductFiles = ProductDocumentQuery::create()
                        ->findByProductId($originalProductId);
                    break;
            }

            // Set clone's files
            /** @var ProductDocument|ProductImage $originalProductFile */
            foreach ($originalProductFiles as $originalProductFile) {
                $srcPath = $originalProductFile->getUploadDir() . DS . $originalProductFile->getFile();

                if (file_exists($srcPath)) {
                    $ext = pathinfo($srcPath, PATHINFO_EXTENSION);

                    $clonedProductFile = [];

                    switch ($type) {
                        case 'images':
                            $fileName = $clonedProduct->getRef().'.'.$ext;
                            $clonedProductFile = new ProductImage();
                            break;

                        case 'documents':
                            $fileName = pathinfo($originalProductFile->getFile(), PATHINFO_FILENAME).'-'.$clonedProduct->getRef().'.'.$ext;
                            $clonedProductFile = new ProductDocument();
                            break;
                    }

                    // Copy a temporary file of the source file as it will be deleted by IMAGE_SAVE or DOCUMENT_SAVE event
                    $srcTmp = $srcPath.'.tmp';
                    copy($srcPath, $srcTmp);

                    // Get file mimeType
                    $finfo = new \finfo();
                    $fileMimeType = $finfo->file($srcPath, FILEINFO_MIME_TYPE);

                    // Get file event's parameters
                    $clonedProductFile
                        ->setProductId($clonedProduct->getId())
                        ->setVisible($originalProductFile->getVisible())
                        ->setPosition($originalProductFile->getPosition())
                        ->setLocale($clonedProduct->getLocale())
                        ->setTitle($clonedProduct->getTitle());

                    $clonedProductCopiedFile = new UploadedFile($srcPath, $fileName, $fileMimeType, filesize($srcPath), null, true);

                    // Create and dispatch event
                    $clonedProductCreateFileEvent = new FileCreateOrUpdateEvent($clonedProduct->getId());
                    $clonedProductCreateFileEvent
                        ->setModel($clonedProductFile)
                        ->setUploadedFile($clonedProductCopiedFile)
                        ->setParentName($clonedProduct->getTitle());

                    $originalProductFileI18ns = [];

                    switch ($type) {
                        case 'images':
                            $dispatcher->dispatch(TheliaEvents::IMAGE_SAVE, $clonedProductCreateFileEvent);

                            // Get original product image I18n
                            $originalProductFileI18ns = ProductImageI18nQuery::create()
                                ->findById($originalProductFile->getId());
                            break;

                        case 'documents':
                            $dispatcher->dispatch(TheliaEvents::DOCUMENT_SAVE, $clonedProductCreateFileEvent);

                            // Get original product document I18n
                            $originalProductFileI18ns = ProductDocumentI18nQuery::create()
                                ->findById($originalProductFile->getId());
                            break;
                    }

                    // Set temporary source file as original one
                    rename($srcTmp, $srcPath);

                    // Clone file's I18n
                    $this->cloneFileI18n($originalProductFileI18ns, $clonedProductFile, $type, $event, $dispatcher);
                } else {
                    Tlog::getInstance()->addWarning("Failed to find media file $srcPath");
                }
            }
        }
    }

    public function cloneFileI18n($originalProductFileI18ns, $clonedProductFile, $type, ProductCloneEvent $event, EventDispatcherInterface $dispatcher)
    {
        // Set clone files I18n
        /** @var ProductDocumentI18n $originalProductFileI18n */
        foreach ($originalProductFileI18ns as $originalProductFileI18n) {
            // Update file with current I18n info. Update or create I18n according to existing or absent Locale in DB
            $clonedProductFile
                ->setLocale($originalProductFileI18n->getLocale())
                ->setTitle($originalProductFileI18n->getTitle())
                ->setDescription($originalProductFileI18n->getDescription())
                ->setChapo($originalProductFileI18n->getChapo())
                ->setPostscriptum($originalProductFileI18n->getPostscriptum());

            // Create and dispatch event
            $clonedProductUpdateFileEvent = new FileCreateOrUpdateEvent($event->getClonedProduct()->getId());
            $clonedProductUpdateFileEvent->setModel($clonedProductFile);

            switch ($type) {
                case 'images':
                    $dispatcher->dispatch(TheliaEvents::IMAGE_UPDATE, $clonedProductUpdateFileEvent);
                    break;

                case 'documents':
                    $dispatcher->dispatch(TheliaEvents::DOCUMENT_UPDATE, $clonedProductUpdateFileEvent);
                    break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::FILE_CLONE => array("cloneFile", 128)
        );
    }
}
