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
    protected $template;

    public function __construct(ParserInterface $parser)
    {
        $this->parser = $parser;
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

        $path = 'components'.DS.'smarty'.DS.$name.DS.$name.'.html';

        if (!$repeat && file_exists(THELIA_TEMPLATE_DIR.$this->template->getPath().DS.$path)) {
            $render = $this->parser->render($path, array_merge($params, ['children' => $content]));

            return htmlspecialchars_decode($render);
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
