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

namespace Thelia\Core\Event\Currency;

use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Currency;

/**
 * Class CurrencyChangeEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CurrencyChangeEvent extends CurrencyEvent
{
    /** @var Request */
    protected $request;

    public function __construct(?Currency $currency = null, ?Request $request = null)
    {
        parent::__construct($currency);
        $this->setRequest($request);
    }

    /**
     * @return $this
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}
