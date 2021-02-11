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

namespace Thelia\Tests\Module;

use PHPUnit\Framework\TestCase;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
abstract class BaseModuleTestor extends TestCase
{
    protected $instance;

    abstract public function getTestedClassName();
    abstract public function getTestedInstance();

    public function setUp(): void
    {
        $this->instance = $this->getTestedInstance();
    }
}
