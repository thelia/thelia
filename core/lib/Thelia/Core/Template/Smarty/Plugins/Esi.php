<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
