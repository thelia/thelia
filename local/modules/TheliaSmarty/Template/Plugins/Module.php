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

namespace TheliaSmarty\Template\Plugins;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\Smarty\Plugins\an;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use Thelia\Model\ModuleQuery;

class Module extends AbstractSmartyPlugin
{
    /** @var bool application debug mode */
    protected $debug;

    /** @var RequestStack */
    protected $requestStack;

    public function __construct($debug, RequestStack $requestStack)
    {
        $this->debug = $debug;
        $this->requestStack = $requestStack;
    }
    /**
     * Process theliaModule template inclusion function
     *
     * This function accepts two parameters:
     *
     * - location : this is the location in the admin template. Example: folder-edit'. The function will search for
     *   AdminIncludes/<location>.html file, and fetch it as a Smarty template.
     * - countvar : this is the name of a template variable where the number of found modules includes will be assigned.
     *
     * @param array                     $params
     * @param \Smarty_Internal_Template $template
     * @internal param \Thelia\Core\Template\Smarty\Plugins\unknown $smarty
     *
     * @return string
     */
    public function theliaModule($params, \Smarty_Internal_Template $template)
    {
        $content = null;
        $count = 0;
        if (false !== $location = $this->getParam($params, 'location', false)) {
            if ($this->debug === true && $this->requestStack->getCurrentRequest()->get('SHOW_INCLUDE')) {
                echo sprintf('<div style="background-color: #C82D26; color: #fff; border-color: #000000; border: solid;">%s</div>', $location);
            }

            $moduleLimit = $this->getParam($params, 'module', null);

            $modules = ModuleQuery::getActivated();

            /** @var \Thelia\Model\Module $module */
            foreach ($modules as $module) {
                if (null !== $moduleLimit && $moduleLimit != $module->getCode()) {
                    continue;
                }

                $file = $module->getAbsoluteAdminIncludesPath() . DS . $location . '.html';

                if (file_exists($file)) {
                    $output = trim(file_get_contents($file));

                    if (! empty($output)) {
                        $content .= $output;

                        $count++;
                    }
                }
            }
        }

        if (false !== $countvarname = $this->getParam($params, 'countvar', false)) {
            $template->assign($countvarname, $count);
        }

        if (! empty($content)) {
            return $template->fetch(sprintf("string:%s", $content));
        }

        return "";
    }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'module_include', $this, 'theliaModule'),
        );
    }
}
