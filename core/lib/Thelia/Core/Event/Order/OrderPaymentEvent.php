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
namespace Thelia\Core\Event\Order;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;

/**
 * Class PaymentEvent.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class OrderPaymentEvent extends ActionEvent
{
    /**
     * @var \Symfony\Component\HttpFoundation\Response
     */
    protected $response;

    public function __construct(protected Order $order)
    {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * @return $this
     */
    public function setResponse(Response $response): self
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    public function hasResponse(): bool
    {
        return null !== $this->response;
    }
}
