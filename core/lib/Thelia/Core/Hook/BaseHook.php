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
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Module\BaseModule;


/**
 * Class BaseHook
 * @package Thelia\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
abstract class BaseHook {


    /**
     * @var BaseModule
     */
    public $module = null;

    /**
     * @var SmartyParser
     */
    public $parser = null;


    public function render($templateName, array $parameters = array())
    {
        $templatePath = null;

        // retrieve the template
        $smartyParser = $this->parser;

        // First look into the current template in the right scope : frontOffice, backOffice, ...
        // template should be overrided in : {template_path}/modules/{module_code}/{template_name}
        /** @var \Thelia\Core\Template\Smarty\SmartyParser $templateDefinition */
        $templateDefinition = $smartyParser->getTemplateDefinition(false);
        $templateDirectories = $smartyParser->getTemplateDirectories($templateDefinition->getType());
        if (isset($templateDirectories[$templateDefinition->getName()]["0"])) {
            $templatePath = $templateDirectories[$templateDefinition->getName()]["0"]
                . DS . TemplateDefinition::HOOK_OVERRIDE_SUBDIR
                . DS . $this->module->getCode()
                . DS . $templateName;
            if (! file_exists($templatePath)) {
                $templatePath = null;
            }
        }

        // If the smarty template doesn't exist, we take the template in the module directory
        if (null === $templatePath){
            if (isset($templateDirectories[$templateDefinition->getName()][$this->module->getCode()])) {
                $templatePath = $templateDirectories[$templateDefinition->getName()][$this->module->getCode()]
                    . DS . $templateName;
                if (! file_exists($templatePath)) {
                    $templatePath = null;
                }
            }
        }

        $content = "";
        if (null !== $templatePath){
            $content = $smartyParser->render($templatePath, $parameters);
        } else {
            $content = sprintf("ERR: Unknow template %s for module %s", $templateName, $this->module->getCode());
        }

        return $content;
    }



    /**
     * @param \Thelia\Module\BaseModule $module
     */
    public function setModule($module)
    {
        $this->module = $module;
    }

    /**
     * @return \Thelia\Module\BaseModule
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param \Thelia\Core\Template\Smarty\SmartyParser $parser
     */
    public function setParser($parser)
    {
        $this->parser = $parser;
    }

    /**
     * @return \Thelia\Core\Template\Smarty\SmartyParser
     */
    public function getParser()
    {
        return $this->parser;
    }




} 