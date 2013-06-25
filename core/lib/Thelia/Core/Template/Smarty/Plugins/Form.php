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

use Symfony\Component\Form\FormView;
use Thelia\Form\BaseForm;
use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\SmartyPluginInterface;
use Thelia\Log\Tlog;

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

    public function generateForm($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        if ($repeat) {

            if (empty($params['name'])) {
                throw new \InvalidArgumentException("Missing 'name' parameter in form arguments");
            }

            $form = BaseForm::getFormFactory($this->request);
            $formBuilder = $form->createBuilder('form');

            $instance = $this->getInstance($params['name']);
            $instance = $instance->buildForm($formBuilder, array());

            $template->assign("form", $instance->getForm()->createView());
        } else {
            return $content;
        }
    }

    public function formRender($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        if ($repeat) {

            $form = $params["form"];

            if (! $form instanceof \Symfony\Component\Form\FormView) {
                throw new \InvalidArgumentException("form parameter in form_field block must be an instance of
                Symfony\Component\Form\FormView");
            }


            $template->assign("options", $form->vars);
            $template->assign("name", $form->vars["name"]);
            $template->assign("value", $form->vars["value"]);
            $template->assign("label", $form->vars["label"]);
            $attr = array();
            foreach ($form->vars["attr"] as $key => $value) {
                $attr[] = sprintf('%s="%s"', $key, $value);
            }
            $template->assign("attr", implode(" ", $attr));

            $form->setRendered();

        } else {
            return $content;
        }
    }

    public function formRenderHidden($params, \Smarty_Internal_Template $template)
    {
        $form = $params["form"];

        $field = '<input type="hidden" name="%s" value="%s">';

        if (! $form instanceof \Symfony\Component\Form\FormView) {
            throw new \InvalidArgumentException("form parameter in form_field_hidden function must be an instance of
                Symfony\Component\Form\FormView");
        }

        $return = "";

        foreach ($form->getIterator() as $row) {
            if ($this->isHidden($row) && $row->isRendered() === false) {
                $return .= sprintf($field, $row->vars["name"], $row->vars["value"]);
            }
        }

        return $return;
    }

    protected function isHidden(FormView $formView)
    {
        return array_search("hidden", $formView->vars["block_prefixes"]);
    }

    public function formEnctype($params, \Smarty_Internal_Template $template)
    {
        $form = $params["form"];

        if (! $form instanceof \Symfony\Component\Form\FormView) {
            throw new \InvalidArgumentException("form parameter in form_field block must be an instance of
                Symfony\Component\Form\FormView");
        }

        if ($form->vars["multipart"]) {
            return sprintf('%s="%s"',"enctype", "multipart/form-data");
        }
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
            new SmartyPluginDescriptor("block", "form", $this, "generateForm"),
            new SmartyPluginDescriptor("block", "form_field", $this, "formRender"),
            new SmartyPluginDescriptor("function", "form_field_hidden", $this, "formRenderHidden"),
            new SmartyPluginDescriptor("function", "form_enctype", $this, "formEnctype")
        );
    }
}
