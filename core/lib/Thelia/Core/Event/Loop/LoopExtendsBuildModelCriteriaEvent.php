<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


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
     * @param ModelCriteria $modelCriteria
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
