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

namespace Thelia\Core\Event\Loop;

use Thelia\Core\Template\Element\BaseLoop;

/**
 * Class LoopExtendsBuildArrayEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsBuildArrayEvent extends LoopExtendsEvent
{
    /**
     * Class constructor.
     *
     * @param BaseLoop $loop  Loop object
     * @param array    $array Build array base results
     */
    public function __construct(BaseLoop $loop, protected array $array)
    {
        parent::__construct($loop);
    }

    /**
     * Get build array results.
     *
     * @return array Build array results
     */
    public function getArray(): array
    {
        return $this->array;
    }

    /**
     * Set build array results.
     *
     * @return $this Return $this, allow chaining
     */
    public function setArray(array $array): static
    {
        $this->array = $array;

        return $this;
    }
}
