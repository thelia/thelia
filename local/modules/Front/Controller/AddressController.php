<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Front\Controller;

use Front\Front;
use Symfony\Component\Form\Form;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;
use Thelia\Model\Event\AddressEvent;

/**
 * Class AddressController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AddressController extends BaseFrontController
{
    /**
     * Controller for generate modal containing update form
     * Check if request is a XmlHttpRequest and address owner is the current customer.
     *
     * @param $address_id
     */
    public function generateModalAction($address_id): void
    {
        $this->checkAuth();
        $this->checkXmlHttpRequest();
    }

    /**
     * Create controller.
     * Check if customer is logged in.
     *
     * Dispatch TheliaEvents::ADDRESS_CREATE event
     */
    public function createAction()
    {
        $this->checkAuth();

        $addressCreate = $this->createForm(FrontForm::ADDRESS_CREATE);

        try {
            /** @var Customer $customer */
            $customer = $this->getSecurityContext()->getCustomerUser();

            $form = $this->validateForm($addressCreate, 'post');
            $event = $this->createAddressEvent($form);
            $event->setCustomer($customer);

            $this->dispatch(TheliaEvents::ADDRESS_CREATE, $event);

            return $this->generateSuccessRedirect($addressCreate);
        } catch (FormValidationException $e) {
            $message = $this->getTranslator()->trans('Please check your input: %s', ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans('Sorry, an error occured: %s', ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        }

        Tlog::getInstance()->error(sprintf('Error during address creation process : %s', $message));

        $addressCreate->setErrorMessage($message);

        $this->getParserContext()
            ->addForm($addressCreate)
            ->setGeneralError($message)
        ;

        // Redirect to error URL if defined
        if ($addressCreate->hasErrorUrl()) {
            return $this->generateErrorRedirect($addressCreate);
        }
    }

    protected function createAddressEvent(Form $form)
    {
        return new AddressCreateOrUpdateEvent(
            $form->get('label')->getData(),
            $form->get('title')->getData(),
            $form->get('firstname')->getData(),
            $form->get('lastname')->getData(),
            $form->get('address1')->getData(),
            $form->get('address2')->getData(),
            $form->get('address3')->getData(),
            $form->get('zipcode')->getData(),
            $form->get('city')->getData(),
            $form->get('country')->getData(),
            $form->get('cellphone')->getData(),
            $form->get('phone')->getData(),
            $form->get('company')->getData(),
            $form->get('is_default')->getData(),
            $form->get('state')->getData()
        );
    }

    public function updateViewAction($address_id)
    {
        $this->checkAuth();

        $customer = $this->getSecurityContext()->getCustomerUser();
        $address = AddressQuery::create()->findPk($address_id);

        if (!$address || $customer->getId() != $address->getCustomerId()) {
            return $this->generateRedirectFromRoute('default');
        }

        $this->getParserContext()->set('address_id', $address_id);
    }

    public function processUpdateAction($address_id)
    {
        $this->checkAuth();

        $addressUpdate = $this->createForm(FrontForm::ADDRESS_UPDATE);

        try {
            $customer = $this->getSecurityContext()->getCustomerUser();

            $form = $this->validateForm($addressUpdate);

            $address = AddressQuery::create()->findPk($address_id);

            if (null === $address) {
                return $this->generateRedirectFromRoute('default');
            }

            if ($address->getCustomer()->getId() != $customer->getId()) {
                return $this->generateRedirectFromRoute('default');
            }

            $event = $this->createAddressEvent($form);
            $event->setAddress($address);

            $this->dispatch(TheliaEvents::ADDRESS_UPDATE, $event);

            return $this->generateSuccessRedirect($addressUpdate);
        } catch (FormValidationException $e) {
            $message = $this->getTranslator()->trans('Please check your input: %s', ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans('Sorry, an error occured: %s', ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        }

        $this->getParserContext()->set('address_id', $address_id);

        Tlog::getInstance()->error(sprintf('Error during address creation process : %s', $message));

        $addressUpdate->setErrorMessage($message);

        $this->getParserContext()
            ->addForm($addressUpdate)
            ->setGeneralError($message)
        ;

        if ($addressUpdate->hasErrorUrl()) {
            return $this->generateErrorRedirect($addressUpdate);
        }
    }

    public function deleteAction($address_id)
    {
        $this->checkAuth();
        $error_message = false;

        $customer = $this->getSecurityContext()->getCustomerUser();
        $address = AddressQuery::create()->findPk($address_id);

        if (!$address || $customer->getId() != $address->getCustomerId()) {
            // If Ajax Request
            if ($this->getRequest()->isXmlHttpRequest()) {
                return $this->jsonResponse(
                    json_encode(
                        [
                            'success' => false,
                            'message' => $this->getTranslator()->trans(
                                'Error during address deletion process',
                                [],
                                Front::MESSAGE_DOMAIN
                            ),
                        ]
                    )
                );
            }

            return $this->generateRedirectFromRoute('default');
        }

        try {
            $this->dispatch(TheliaEvents::ADDRESS_DELETE, new AddressEvent($address));
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        Tlog::getInstance()->error(sprintf('Error during address deletion : %s', $error_message));

        // If Ajax Request
        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($error_message) {
                $response = $this->jsonResponse(json_encode([
                    'success' => false,
                    'message' => $error_message,
                ]));
            } else {
                $response = $this->jsonResponse(
                    json_encode([
                        'success' => true,
                        'message' => '',
                    ])
                );
            }

            return $response;
        }

        return $this->generateRedirectFromRoute('default', ['view' => 'account']);
    }

    public function makeAddressDefaultAction($addressId)
    {
        $this->checkAuth();

        $address = AddressQuery::create()
            ->filterByCustomerId($this->getSecurityContext()->getCustomerUser()->getId())
            ->findPk($addressId)
        ;

        if (null === $address) {
            $this->pageNotFound();
        }

        try {
            $event = new AddressEvent($address);
            $this->dispatch(TheliaEvents::ADDRESS_DEFAULT, $event);
        } catch (\Exception $e) {
            $this->getParserContext()
                ->setGeneralError($e->getMessage())
            ;

            return $this->render('account');
        }

        return $this->generateRedirectFromRoute('default', ['view' => 'account']);
    }
}
