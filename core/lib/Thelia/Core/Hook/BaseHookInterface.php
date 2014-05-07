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

namespace Thelia\Core\Hook;


use Thelia\Core\Template\Smarty\SmartyParser;
use Thelia\Module\BaseModuleInterface;

interface BaseHookInterface
{
    /*
    public function setModule(BaseModuleInterface $module);

    public function getModule();

    public function setParser(SmartyParser $parser);

    public function getParser();
    */

    public function render($templateName);

    public function assign($name, $value);

}