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
use Thelia\Core\Template\ParserInterface;
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

    protected $template;

    public function __construct(ParserInterface $parser, $kernelDebug)
    {
        $this->parser = $parser;
        $this->kernelDebug = $kernelDebug;
        $this->template = $this->parser->getTemplateHelper()->getActiveFrontTemplate();
    }

    public function component(array $params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        $name = $this->getParam($params, 'name');
        if (null === $name || empty($name)) {
            throw new \InvalidArgumentException(
                "Missing 'name' parameter"
            );
        }

        $path = $template->getConfigVariable('component_path') ?: 'components'.DS.'smarty';
        $path .= DS.$name.DS.$name.'.html';
        $componentFile = THELIA_TEMPLATE_DIR.$this->template->getPath().DS.$path;

        if ($this->kernelDebug && !file_exists($componentFile)) {
            throw new Exception('no component at'.$componentFile);
        } elseif (!file_exists($componentFile)) {
            return '';
        }

        if (!$repeat) {
            $render = $this->parser->render($path, array_merge($params, ['children' => $content]));

            return $render;
        }

        return '';
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('block', 'component', $this, 'component'),
        ];
    }
}
