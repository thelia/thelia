<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaSmarty\Template\Plugins;

use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\ParserInterface;
use TheliaSmarty\Events\ComponentRenderEvent;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * Class Component.
 *
 * @author Damien Foulhoux <dfoulhoux@openstudio.fr>
 */
class Component extends AbstractSmartyPlugin
{
    /**
     * Component constructor.
     */

    /** @var ParserInterface */
    protected $parser;

    /** @var string */
    protected $kernelDebug;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    public function __construct(ParserInterface $parser, $kernelDebug, EventDispatcherInterface $eventDispatcher)
    {
        $this->parser = $parser;
        $this->kernelDebug = $kernelDebug;
        $this->dispatcher = $eventDispatcher;
    }

    public function component(array $params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        // Do nothing at opening tag
        if ($repeat) {
            return '';
        }

        $name = $this->getParam($params, 'name');
        if (null === $name || empty($name)) {
            throw new \InvalidArgumentException(
                "Missing 'name' parameter"
            );
        }

        $componentsDir = 'components';
        $componentPrefixDir = DS.$name.DS.$name; // eg: /Tabs/Tabs
        $path = $componentsDir.$componentPrefixDir; // eg: Components/Tabs/Tabs
        $templatePath = $this->parser->getTemplateDefinition()->getPath(); // eg: templates/frontOffice/default

        $componentFile = THELIA_TEMPLATE_DIR.$templatePath.DS.$path.'.html';

        if (!file_exists($componentFile)) {
            if ($this->kernelDebug) {
                throw new Exception('no component at'.$componentFile);
            }

            return '';
        }

        $renderEvent = (new ComponentRenderEvent())
        ->setName($name)
        ->setContent($content);

        $this->dispatcher->dispatch($renderEvent, ComponentRenderEvent::COMPONENT_BEFORE_RENDER_PREFIX.$name);

        $render = $this->parser->render($path.'.html', array_merge($params, ['children' => $renderEvent->getContent()]));
        $renderEvent = (new ComponentRenderEvent())
        ->setRender($render);

        $this->dispatcher->dispatch($renderEvent, ComponentRenderEvent::COMPONENT_AFTER_RENDER_PREFIX.$name);

        return $renderEvent->getRender();
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('block', 'component', $this, 'component'),
            new SmartyPluginDescriptor('function', 'include_component', $this, 'include_component'),
        ];
    }
}
