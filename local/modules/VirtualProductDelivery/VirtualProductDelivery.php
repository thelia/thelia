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
use Thelia\Core\Translation\Translator;
use Thelia\Model\Country;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\Exception\DeliveryException;

class VirtualProductDelivery extends AbstractDeliveryModule
{
    const MESSAGE_DOMAIN = 'virtualproductdelivery';

    /** @var Translator */
    protected $translator;

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
                $this->trans("This module cannot be used on the current cart.")
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
        // create new message
        if (null === MessageQuery::create()->findOneByName('mail_virtualproduct')) {
            $message = new Message();
            $message
                ->setName('mail_virtualproduct')
                ->setHtmlTemplateFileName('virtual-product-download.html')
                ->setHtmlLayoutFileName('')
                ->setTextTemplateFileName('virtual-product-download.txt')
                ->setTextLayoutFileName('')
                ->setSecured(0);

            $languages = LangQuery::create()->find();

            foreach ($languages as $language) {
                $locale = $language->getLocale();

                $message->setLocale($locale);

                $message->setSubject(
                    $this->trans('Order {$order_ref} validated. Download your files.', [], $locale)
                );
                $message->setTitle(
                    $this->trans('Virtual product download message', [], $locale)
                );
            }

            $message->save();
        }
    }

    protected function trans($id, $parameters = [], $locale = null)
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters, self::MESSAGE_DOMAIN, $locale);
    }
}
