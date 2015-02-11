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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;


/**
 * Class ArgDefinitionOverride
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
interface ArgDefinitionOverrideInterface
{
    /**
     *
     *
     * @param BaseLoop  $loop      the current loop
     *
     * @return ArgumentCollection|null      new argument collection that will be added to the loop argument collection
     */
    public function getDefinitions(BaseLoop $loop);
}
