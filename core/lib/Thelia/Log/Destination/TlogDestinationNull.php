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
namespace Thelia\Log\Destination;

use Thelia\Log\AbstractTlogDestination;

class TlogDestinationNull extends AbstractTlogDestination
{
    public function getTitle(): string
    {
        return 'Black hole';
    }

    public function getDescription(): string
    {
        return "This destinations consumes the logs but don't display them";
    }

    public function add($string): void
    {
        // Rien
    }

    public function write(&$res): void
    {
        // Rien
    }
}
