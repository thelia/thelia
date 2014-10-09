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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Fragment\EsiFragmentRenderer;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Template\Smarty\an;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;

/**
 * Class Esi
 * @package Thelia\Core\Template\Smarty\Plugins
 * @author Manuel Raynaud <manu@thelia.net>
 */
class Esi extends AbstractSmartyPlugin
{
    protected $esiFragmentRender;
    protected $request;

    public function __construct(EsiFragmentRenderer $esiFragmentRenderer, Request $request)
    {
        $this->esiFragmentRender = $esiFragmentRenderer;
        $this->request = $request;
    }

    public function renderEsi($params, $template = null)
    {
        $path = $this->getParam($params, 'path');
        $alt = $this->getParam($params, 'alt');
        $ignore_errors = $this->getParam($params, 'ignore_errors');
        $comment = $this->getParam($params, 'comment');

        if (null === $path) {
            return;
        }

        $response = $this->esiFragmentRender->render($path, $this->request, array(
            'alt' => $alt,
            'ignore_errors' => $ignore_errors,
            'comment' => $comment
        ));

        if (!$response->isSuccessful()) {
            return null;
        }

        return $response->getContent();
    }

    /**
     * @return an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'render_esi', $this, 'renderEsi')
        );
    }
}
