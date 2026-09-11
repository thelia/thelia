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

namespace Thelia\Core\Event\CheckoutStep;

/**
 * Creates the rows missing for the steps the installed code declares, so that a module
 * shipping a step gets its line in the back office without a migration of its own.
 */
class CheckoutStepSynchronizeEvent extends CheckoutStepEvent
{
    /** @var list<string> */
    protected array $createdCodes = [];

    /**
     * @return list<string>
     */
    public function getCreatedCodes(): array
    {
        return $this->createdCodes;
    }

    /**
     * @param list<string> $createdCodes
     */
    public function setCreatedCodes(array $createdCodes): static
    {
        $this->createdCodes = $createdCodes;

        return $this;
    }
}
