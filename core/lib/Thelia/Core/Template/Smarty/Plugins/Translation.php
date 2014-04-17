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

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Process translate function
     *
     * @param  unknown $params
     * @param  unknown $smarty
     * @return string
     */
    public function translate($params, &$smarty)
    {
        // All parameters other than 'l' are supposed to be variables. Build an array of var => value pairs
        // and pass it to the translator
        $vars = array();

        foreach ($params as $name => $value) {
            if ($name != 'l') $vars["%$name"] = $value;
        }

        return $this->translator->trans($this->getParam($params, 'l'), $vars);
    }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'intl', $this, 'translate'),
        );
    }
}
