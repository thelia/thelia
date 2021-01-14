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
use Thelia\Model\ModuleQuery;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class Module extends AbstractSmartyPlugin
{
    /** @var bool application debug mode */
    protected $debug;

    /** @var RequestStack */
    protected $requestStack;

    public function __construct($kernelDebug, RequestStack $requestStack)
    {
        $this->debug = $kernelDebug;
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
     * @param \Smarty_Internal_Template $parser
     * @internal param \Thelia\Core\Template\Smarty\Plugins\unknown $smarty
     *
     * @return string
     *
     * @throws \Exception
     * @throws \SmartyException
     */
    public function theliaModule($params, \Smarty_Internal_Template $parser)
    {
        $content = null;
        $count = 0;
        if (false !== $location = $this->getParam($params, 'location', false)) {
            if ($this->debug === true && $this->requestStack->getCurrentRequest()->get('SHOW_INCLUDE')) {
                echo sprintf('<div style="background-color: #C82D26; color: #fff; border: solid #000000;">%s</div>', $location);
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
            $parser->assign($countvarname, $count);
        }

        if (! empty($content)) {
            return $parser->fetch(sprintf("string:%s", $content));
        }

        return "";
    }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'module_include', $this, 'theliaModule'),
        );
    }
}
