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


namespace VirtualProductDelivery\EventListeners;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Thelia\Core\Event\Product\VirtualProductOrderDownloadResponseEvent;
use Thelia\Core\Event\Product\VirtualProductOrderHandleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\MetaData as MetaDataModel;
use Thelia\Model\ProductDocumentQuery;
use VirtualProductDelivery\VirtualProductDelivery;

/**
 * Class VirtualProductEvents
 * @package VirtualProductDelivery\EventListeners
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductEvents implements EventSubscriberInterface
{

    public function handleOrder(VirtualProductOrderHandleEvent $event)
    {
        $documentId = MetaDataQuery::getVal(
            'virtual',
            MetaDataModel::PSE_KEY,
            $event->getPseId()
        );

        if (null !== $documentId) {
            $productDocument = ProductDocumentQuery::create()->findPk($documentId);
            if (null !== $productDocument) {
                $event->setPath($productDocument->getFile());
            }
        }

    }

    public function download(VirtualProductOrderDownloadResponseEvent $event)
    {
        $orderProduct = $event->getOrderProduct();

        if ($orderProduct->getVirtualDocument()) {
            $baseSourceFilePath = ConfigQuery::read('documents_library_path');
            if ($baseSourceFilePath === null) {
                $baseSourceFilePath = THELIA_LOCAL_DIR . 'media' . DS . 'documents';
            } else {
                $baseSourceFilePath = THELIA_ROOT . $baseSourceFilePath;
            }

            // try to get the file
            $path = $baseSourceFilePath . DS . 'product' . DS . $orderProduct->getVirtualDocument();

            if (!is_file($path) || !is_readable($path)) {
                throw new \ErrorException(
                    Translator::getInstance()->trans(
                        "The file [%file] does not exist",
                        [
                            "%file" => $orderProduct->getId()
                        ],
                        VirtualProductDelivery::MESSAGE_DOMAIN
                    )
                );
            }

            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);
            $event->setResponse($response);

        }
    }



    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::VIRTUAL_PRODUCT_ORDER_HANDLE => ['handleOrder', 128],
            TheliaEvents::VIRTUAL_PRODUCT_ORDER_DOWNLOAD_RESPONSE => ['download', 128]
        ];
    }
}
