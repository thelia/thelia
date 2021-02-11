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

namespace Thelia\Core\Event\Loop;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseLoop;

/**
 * Class LoopExtendsBuildModelCriteriaEvent
 * @package Thelia\Core\Event\Loop
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsBuildModelCriteriaEvent extends LoopExtendsEvent
{
    /** @var ModelCriteria $modelCriteria */
    protected $modelCriteria;

    /**
     * LoopExtendsBuildModelCriteria constructor.
     */
    public function __construct(BaseLoop $loop, ModelCriteria $modelCriteria)
    {
        parent::__construct($loop);
        $this->modelCriteria = $modelCriteria;
    }

    /**
     * @return ModelCriteria
     */
    public function getModelCriteria()
    {
        return $this->modelCriteria;
    }
}
