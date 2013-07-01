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

/**
 *
 * Plugin for smarty defining blocks and functions for using Form display.
 *
 * blocks :
 *  - {form name="myForm"} ... {/form} => find form named myForm,
 * create an instance and assign this instanciation into smarty variable. Form must be declare into
 * config using <forms> tag
 *
 *  - {form_field form=$form.fieldName} {/form_field} This block find info into the Form field containing by
 * the form paramter. This field must be an instance of FormView. fieldName is the name of your field. This block
 * can output these info :
 *      * $name => name of yout input
 *      * $value => value for your input
 *      * $label => label for your input
 *      * $error => boolean for know if there is error for this field
 *      * $attr => all your attribute for your input (define when you construct programmatically you form)
 *
 *  - {form_error form=$form.fieldName} ... {/form_error} Display this block if there are errors on this field.
 * fieldName is the name of your field
 *
 * Class Form
 * @package Thelia\Core\Template\Smarty\Plugins
 */
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

            $instance = $this->getInstance($params['name']);
            $form = $instance->getForm();

            if (
                true === $this->request->getSession()->get("form_error", false) &&
                $this->request->getSession()->get("form_name") == $instance->getName())
            {
                $form->bind($this->request);
                $this->request->getSession()->set("form_error", false);
            }

            $template->assign("form", $form->createView());
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
            $template->assign("name", $form->vars["full_name"]);
            $template->assign("value", $form->vars["value"]);
            $template->assign("label", $form->vars["label"]);
            $template->assign("error", empty($form->vars["errors"]) ? false : true);
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
                $return .= sprintf($field, $row->vars["full_name"], $row->vars["value"]);
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
            throw new \InvalidArgumentException("form parameter in form_enctype function must be an instance of
                Symfony\Component\Form\FormView");
        }

        if ($form->vars["multipart"]) {
            return sprintf('%s="%s"',"enctype", "multipart/form-data");
        }
    }

    public function formError($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {

        $form = $params["form"];
        if (! $form instanceof \Symfony\Component\Form\FormView) {
            throw new \InvalidArgumentException("form parameter in form_error block must be an instance of
                Symfony\Component\Form\FormView");
        }

        if (empty($form->vars["errors"])) {
            return "";
        }

        if ($repeat) {

            $error = $form->vars["errors"];

            $template->assign("message", $error[0]->getMessage());
            $template->assign("parameters", $error[0]->getMessageParameters());
            $template->assign("pluralization", $error[0]->getMessagePluralization());


        } else {
            return $content;
        }
    }

    public function getInstance($name)
    {
        if (!isset($this->formDefinition[$name])) {
            throw new ElementNotFoundException(sprintf("%s form does not exists", $name));
        }

        $class = new \ReflectionClass($this->formDefinition[$name]);


        return $class->newInstance(
            $this->request,
            "form"
        );
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
            new SmartyPluginDescriptor("function", "form_enctype", $this, "formEnctype"),
            new SmartyPluginDescriptor("block", "form_error", $this, "formError")
        );
    }
}
