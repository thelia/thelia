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
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Form\TheliaFormValidator;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TheliaTemplateHelper;
use Thelia\Core\Translation\Translator;
use Thelia\Tests\ContainerAwareTestCase;
use TheliaSmarty\Template\SmartyParser;

/**
 * Class SmartyPluginTestCase
 * @package TheliaSmarty\Tests\Template\Plugin
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class SmartyPluginTestCase extends ContainerAwareTestCase
{
    /** @var SmartyParser */
    protected $smarty;

    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        /** @var Request $request */
        $request = $container->get("request");
        if (null === $request->getSession()) {
            $request->setSession(new Session());
        }

        $container->set("thelia.parser.forms", [
            "thelia.empty" => "Thelia\\Form\\EmptyForm",
            "thelia.empty.2" => "Thelia\\Form\\EmptyForm",
            "thelia.api.empty" => "Thelia\\Form\\Api\\ApiEmptyForm",
        ]);

        $container->set("thelia.form_factory_builder", (new FormFactoryBuilder())->addExtension(new CoreExtension()));
        $container->set("thelia.forms.validator_builder", new ValidatorBuilder());

        $container->set(
            "thelia.form_factory",
            new TheliaFormFactory($request, $container, $container->get("thelia.parser.forms"))
        );

        $container->set("thelia.parser.context", new ParserContext(
            $request,
            $container->get("thelia.form_factory"),
            new TheliaFormValidator(new Translator($container), 'dev')
        ));

        $this->smarty = new SmartyParser(
            $request,
            $container->get("event_dispatcher"),
            $container->get("thelia.parser.context"),
            $templateHelper = new TheliaTemplateHelper()
        );

        $container->set("thelia.parser", $this->smarty);

        $this->smarty->addPlugins($this->getPlugin($container));
        $this->smarty->registerPlugins();
    }

    protected function render($template)
    {
        return $this->smarty->fetch(__DIR__.DS."fixtures".DS.$template);
    }

    /**
     * @return \TheliaSmarty\Template\AbstractSmartyPlugin
     */
    abstract protected function getPlugin(ContainerBuilder $container);
}
