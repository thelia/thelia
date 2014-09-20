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

namespace Thelia\Core\Template\Smarty\Plugins;

use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Symfony\Component\Translation\TranslatorInterface;

class Translation extends AbstractSmartyPlugin
{
    protected $translator;
    protected $defaultTranslationDomain = '';

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Set the default translation domain
     *
     * @param  array                     $params
     * @param  \Smarty_Internal_Template $smarty
     * @return string
     */
    public function setDefaultTranslationDomain($params, &$smarty)
    {
        $this->defaultTranslationDomain = $this->getParam($params, 'domain');
    }

    /**
     * Process translate function
     *
     * @param  array                     $params
     * @param  \Smarty_Internal_Template $smarty
     * @return string
     */
    public function translate($params, &$smarty)
    {
        // All parameters other than 'l' and 'd' and 'js' are supposed to be variables. Build an array of var => value pairs
        // and pass it to the translator
        $vars = array();

        foreach ($params as $name => $value) {
            if ($name != 'l' && $name != 'd' && $name != 'js') {
                $vars["%$name"] = $value;
            }
        }

        $str = $this->translator->trans(
            $this->getParam($params, 'l'),
            $vars,
            $this->getParam($params, 'd', $this->defaultTranslationDomain)
        );

        if ($this->getParam($params, 'js', 0)) {
            $str = preg_replace("/(['\"])/", "\\\\$1", $str);
        }

        return $str;
    }

    /**
     * Define the various smarty plugins handled by this class
     *
     * @return SmartyPluginDescriptor[] an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'intl', $this, 'translate'),
            new SmartyPluginDescriptor('function', 'default_translation_domain', $this, 'setDefaultTranslationDomain'),
        );
    }
}
