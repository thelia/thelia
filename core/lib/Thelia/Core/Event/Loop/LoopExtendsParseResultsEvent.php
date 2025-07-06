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
use Thelia\Core\Template\Element\LoopResult;

/**
 * Class LoopExtendsParseResultsEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsParseResultsEvent extends LoopExtendsEvent
{
    /**
     * LoopExtendsParseResultsEvent constructor.
     */
    public function __construct(BaseLoop $loop, protected LoopResult $loopResult)
    {
        parent::__construct($loop);
    }

    public function getLoopResult(): LoopResult
    {
        return $this->loopResult;
    }
}
