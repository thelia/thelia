<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

use Thelia\Form\BaseForm;
use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\SmartyPluginInterface;

class Form implements SmartyPluginInterface
{

    protected $request;
    protected $form;
    protected $formDefinition = array();

    public function __construct(Request $request)
    {
        $this->request = $request;

    }

    public function setFormDefinition($formDefinition)
    {
        foreach ($formDefinition as $name => $className) {
            if (array_key_exists($name, $this->formDefinition)) {
                throw new \InvalidArgumentException(sprintf("%s form name already exists for %s class", $name,
                    $className));
            }

            $this->formDefinition[$name] = $className;
        }
    }

    public function generateForm($params, $content, $template, &$repeat)
    {
        if (empty($params['name'])) {
            throw new \InvalidArgumentException("Missing 'name' parameter in form arguments");
        }

        $form = new BaseForm($this->request);
        $formBuilder = $form->getFormBuilder()->createBuilder();

        $instance = $this->getInstance($params['name']);
        $instance = $instance->buildForm($formBuilder, array());

        var_dump($instance->getForm()->createView()); exit;
        $template->assign("form", $instance->getForm()->createView());

    }

    public function getInstance($name)
    {
        if (!isset($this->formDefinition[$name])) {
            throw new ElementNotFoundException(sprintf("%s form does not exists", $name));
        }


        return new $this->formDefinition[$name];
    }

    /**
     * @return an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("block", "form", $this, "generateForm")
        );
    }
}
