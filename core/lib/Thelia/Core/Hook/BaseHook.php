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
use Thelia\Log\Tlog;
use Thelia\Module\BaseModule;


/**
 * Class BaseHook
 * @package Thelia\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
abstract class BaseHook implements BaseHookInterface {


    /**
     * @var BaseModule
     */
    public $module = null;

    /**
     * @var SmartyParser
     */
    public $parser = null;


    public function render($templateName)
    {
        // retrieve the template
        $smartyParser = $this->parser;

        /** @var \Thelia\Core\Template\Smarty\SmartyParser $templateDefinition */
        $templateDefinition = $smartyParser->getTemplateDefinition(false);
        $templateDirectories = $smartyParser->getTemplateDirectories($templateDefinition->getType());

        Tlog::getInstance()->addDebug("_HOOK_ render :: " . print_r($templateDirectories, true) );


        // TODO: Implement render() method.
    }

    public function assign($name, $value)
    {
        // TODO: Implement assign() method.
    }

} 