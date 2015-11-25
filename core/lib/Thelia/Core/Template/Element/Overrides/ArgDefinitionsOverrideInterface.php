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
 * Class ArgDefinitionsOverride
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
interface ArgDefinitionsOverrideInterface
{
    /**
     * Manipulate argument definitions
     *
     * For instance:
     *
     * $loop->getArgs()->addArgument(
     *     Argument::createEnumListTypeArgument(
     *         'export',
     *         ['JSON', 'json', 'csv', 'xml'],
     *     )
     * );
     *
     * @param BaseLoop  $loop      the current loop
     *
     */
    public function getArgDefinitions(BaseLoop $loop);
}
