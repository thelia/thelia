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

class TlogDestinationText extends AbstractTlogDestination
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getTitle(): string
    {
        return 'Direct text display';
    }

    public function getDescription(): string
    {
        return 'Display logs in raw text format, on top of generated pages.';
    }

    public function add($texte): void
    {
        echo trim((string) $texte)."\n";
    }

    public function write(&$res): void
    {
        // Rien
    }
}
