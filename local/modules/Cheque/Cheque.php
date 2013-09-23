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

namespace Cheque;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\ModuleImageQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;

class Cheque extends BaseModule implements PaymentModuleInterface
{
    protected $request;
    protected $dispatcher;

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function pay()
    {
        // no special process, waiting for the cheque.
    }

    public function install()
    {

    }

    public function afterActivation()
    {
        /* insert the images from image folder if first module activation */
        $module = $this->getModuleModel();
        if(ModuleImageQuery::create()->filterByModule($module)->count() == 0) {
            $this->deployImageFolder($module, sprintf('%s/images', __DIR__));
        }

        /* set module title */
        $this->setTitle(
            $module,
            array(
                "en_US" => "Cheque",
                "fr_FR" => "Cheque",
            )
        );
    }

    public function destroy()
    {
        // TODO: Implement destroy() method.
    }

    public function getCode()
    {
        return 'Cheque';
    }

}
