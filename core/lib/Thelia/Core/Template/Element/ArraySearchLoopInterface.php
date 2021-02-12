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

namespace Thelia\Core\Template\Element;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
interface ArraySearchLoopInterface
{
    /**
     * this method returns an array.
     *
     * @return array
     */
    public function buildArray();
}
