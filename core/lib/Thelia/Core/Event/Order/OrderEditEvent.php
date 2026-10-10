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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\Order\Edition\OrderEdit;
use Thelia\Domain\Order\Edition\OrderEditOutcome;
use Thelia\Model\Order;

/**
 * Dispatched as ORDER_BEFORE_EDIT before the lines of an order change (a listener that
 * throws refuses the edit, nothing is written) and as ORDER_AFTER_EDIT once they did, with
 * the outcome.
 */
#[Exclude]
class OrderEditEvent extends ActionEvent
{
    public function __construct(
        protected Order $order,
        protected OrderEdit $edit,
        protected ?OrderEditOutcome $outcome = null,
    ) {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getEdit(): OrderEdit
    {
        return $this->edit;
    }

    public function getOutcome(): ?OrderEditOutcome
    {
        return $this->outcome;
    }
}
