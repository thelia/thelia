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
namespace Thelia\Core\Template\Element;

use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
interface PropelSearchLoopInterface
{
    /**
     * this method returns a Propel ModelCriteria.
     *
     * @return ModelCriteria
     */
    public function buildModelCriteria();
}
