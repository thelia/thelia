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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseLoop;

/**
 * Class LoopExtendsBuildModelCriteriaEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsBuildModelCriteriaEvent extends LoopExtendsEvent
{
    /**
     * LoopExtendsBuildModelCriteria constructor.
     */
    public function __construct(BaseLoop $loop, protected ModelCriteria $modelCriteria)
    {
        parent::__construct($loop);
    }

    public function getModelCriteria(): ModelCriteria
    {
        return $this->modelCriteria;
    }
}
