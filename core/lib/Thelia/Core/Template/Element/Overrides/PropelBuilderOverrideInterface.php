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


namespace Thelia\Core\Template\Element\Overrides;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * Class PropelBuilderOverrideInterface
 * @package Thelia\Core\Template\Element\Overrides
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
interface PropelBuilderOverrideInterface
{
    /**
     *
     *
     * @param BaseLoop        $loop      the current loop
     * @param ModelCriteria   $search    the search ModelCriteria of the loop
     *
     * @return ModelCriteria             the modified ModelCriteria
     */
    public function build(BaseLoop $loop, ModelCriteria $search);
}
