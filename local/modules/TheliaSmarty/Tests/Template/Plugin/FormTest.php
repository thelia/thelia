<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace TheliaSmarty\Tests\Template\Plugin;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Validator\ValidatorBuilder;
use TheliaSmarty\Template\Plugins\Form;

/**
 * Class FormTest
 * @package TheliaSmarty\Tests\Template\Plugin
 * @author Benjamin Perche <benjamin@thelia.net>
 */
class FormTest extends SmartyPluginTestCase
{
    /**
     * @var Form
     */
    protected $plugin;

    /**
     * @return \TheliaSmarty\Template\AbstractSmartyPlugin
     */
    protected function getPlugin(ContainerBuilder $container)
    {
        $this->plugin = new Form(
            $container->get("thelia.form_factory"),
            $container->get("thelia.parser.context"),
            $container->get("thelia.parser")
        );

        $this->plugin->setFormDefinition($container->get("thelia.parser.forms"));

        return $this->plugin;
    }

    public function testSimpleStackedForm()
    {
        $parserContext = $this->getParserContext();
        $this->assertNull($parserContext->popCurrentForm());

        // First, initialize form
        // eq: {form name="thelia.empty"}
        $repeat = true;
        $this->plugin->generateForm(
            ["name" => "thelia.empty"],
            "",
            $this->getMock("\\Smarty_Internal_Template", [], [], '', false),
            $repeat
        );

        // Here, the current form is present
        $this->assertInstanceOf("Thelia\\Form\\EmptyForm", $parserContext->getCurrentForm());
        $this->assertInstanceOf("Thelia\\Form\\EmptyForm", $form = $parserContext->popCurrentForm());
        // But not after we have pop
        $this->assertNull($parserContext->popCurrentForm());

        // So we re-push it into the stack
        $parserContext->pushCurrentForm($form);

        // And run the ending form tag
        // eq: {/form}
        $repeat = false;
        $this->plugin->generateForm(
            ["name" => "thelia.empty"],
            "",
            $this->getMock("\\Smarty_Internal_Template", [], [], '', false),
            $repeat
        );

        // There is no more form in the stack
        $this->assertNull($parserContext->popCurrentForm());

        // Let's even predict an exception
        $this->setExpectedException(
            "TheliaSmarty\\Template\\Exception\\SmartyPluginException",
            "There is currently no defined form"
        );

        $parserContext->getCurrentForm();
    }

    public function testMultipleStackedForms()
    {
        $parserContext = $this->getParserContext();
        $this->assertNull($parserContext->popCurrentForm());

        // First form:
        // eq: {form name="thelia.empty"}
        $repeat = true;
        $this->plugin->generateForm(
            ["name" => "thelia.empty"],
            "",
            $this->getMock("\\Smarty_Internal_Template", [], [], '', false),
            $repeat
        );

        $this->assertInstanceOf("Thelia\\Form\\EmptyForm", $parserContext->getCurrentForm());

        // Then next one:
        // eq: {form name="thelia.api.empty"}
        $repeat = true;
        $this->plugin->generateForm(
            ["name" => "thelia.api.empty"],
            "",
            $this->getMock("\\Smarty_Internal_Template", [], [], '', false),
            $repeat
        );

        $this->assertInstanceOf("Thelia\\Form\\Api\\ApiEmptyForm", $parserContext->getCurrentForm());

        // Third form:
        // eq: {form name="thelia.empty.2"}
        $repeat = true;
        $this->plugin->generateForm(
            ["name" => "thelia.empty.2"],
            "",
            $this->getMock("\\Smarty_Internal_Template", [], [], '', false),
            $repeat
        );

        $this->assertInstanceOf("Thelia\\Form\\EmptyForm", $parserContext->getCurrentForm());

        // Then, Let's close forms
        // eq: {/form} {* related to {form name="thelia.empty.2"} *}
        $repeat = false;
        $this->plugin->generateForm(
            ["name" => "thelia.empty.2"],
            "",
            $this->getMock("\\Smarty_Internal_Template", [], [], '', false),
            $repeat
        );

        $this->assertInstanceOf("Thelia\\Form\\Api\\ApiEmptyForm", $parserContext->getCurrentForm());

        // Then, Let's close forms
        // eq: {/form} {* related to {form name="thelia.api.empty"} *}
        $repeat = false;
        $this->plugin->generateForm(
            ["name" => "thelia.api.empty"],
            "",
            $this->getMock("\\Smarty_Internal_Template", [], [], '', false),
            $repeat
        );

        $this->assertInstanceOf("Thelia\\Form\\EmptyForm", $parserContext->getCurrentForm());

        // Then close the first form:
        // eq: {/form} {* related to {form name="thelia.empty"} *}
        $repeat = false;
        $this->plugin->generateForm(
            ["name" => "thelia.empty"],
            "",
            $this->getMock("\\Smarty_Internal_Template", [], [], '', false),
            $repeat
        );

        // The exception
        $this->setExpectedException(
            "TheliaSmarty\\Template\\Exception\\SmartyPluginException",
            "There is currently no defined form"
        );

        $parserContext->getCurrentForm();
    }

    /**
     * @return \Thelia\Core\Template\ParserContext
     */
    protected function getParserContext()
    {
        return $this->container->get("thelia.parser.context");
    }
}
