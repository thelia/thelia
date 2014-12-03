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

namespace VirtualProductDelivery;

use Propel\Runtime\Connection\ConnectionInterface;
use SimpleXMLElement;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Country;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\Exception\DeliveryException;

class VirtualProductDelivery extends AbstractDeliveryModule
{
    /**
     * The module is valid if the cart contains only virtual products.
     *
     * @param Country $country
     *
     * @return bool true if there is only virtual products in cart elsewhere false
     */
    public function isValidDelivery(Country $country)
    {
        return $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->isVirtual();
    }

    public function getPostage(Country $country)
    {
        if (!$this->isValidDelivery($country)) {
            throw new DeliveryException(
                Translator::getInstance()->trans("This module cannot be used on the current cart.")
            );
        }

        return 0.0;
    }

    /**
     * This module manages virtual product delivery
     *
     * @return bool
     */
    public function handleVirtualProductDelivery()
    {
        return true;
    }


    public function postActivation(ConnectionInterface $con = null)
    {
        // delete existing message
        $message = MessageQuery::create()
            ->filterByName('mail_virtualproduct')
            ->findOne($con);

        if (null !== $message) {
            $message->delete($con);
        }

        // create new message
        $message = new Message();
        $message
            ->setName('mail_virtualproduct')
            ->setSecured(0);

        $basePath = __DIR__ . '/Config/message/%s.xml';
        $languages = LangQuery::create()->find();

        foreach ($languages as $language) {
            $locale = $language->getLocale();

            $message->setLocale($locale);

            $path = sprintf($basePath, $language->getLocale());
            if (file_exists($path) && is_readable($path)) {
                $dom = new SimpleXMLElement(file_get_contents($path));
                if ($dom) {
                    $message->setTitle((string) $dom->title);
                    $message->setSubject((string) $dom->subject);
                    $message->setTextMessage((string) $dom->text);
                    $message->setHtmlMessage((string) $dom->html);
                }
            }
        }

        $message->save();
    }
}

