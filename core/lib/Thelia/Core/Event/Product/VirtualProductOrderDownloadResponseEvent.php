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

namespace Thelia\Core\Event\Product;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\OrderProduct;

/**
 * Class VirtualProductOrderDownloadResponseEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductOrderDownloadResponseEvent extends ActionEvent
{
    protected Response $response;

    public function __construct(protected OrderProduct $orderProduct)
    {
    }

    public function getOrderProduct(): OrderProduct
    {
        return $this->orderProduct;
    }

    /**
     * @return $this
     */
    public function setOrderProduct(OrderProduct $orderProduct): static
    {
        $this->orderProduct = $orderProduct;

        return $this;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @return $this
     */
    public function setResponse(Response $response): static
    {
        $this->response = $response;

        return $this;
    }
}
