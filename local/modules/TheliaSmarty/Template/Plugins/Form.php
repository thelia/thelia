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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Thelia\Core\Form\TheliaFormFactoryInterface;
use Thelia\Core\Form\Type\TheliaType;
use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\ParserInterface;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use Thelia\Form\BaseForm;
use Symfony\Component\Form\Form as SymfonyForm;

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
class Form extends AbstractSmartyPlugin
{
    const COLLECTION_TYPE_NAME = "collection";

    private static $taggedFieldsStack = null;
    private static $taggedFieldsStackPosition = null;

    /** @var  ContainerInterface */
    protected $container;

    /** @var  ParserContext $parserContext */
    protected $parserContext;

    /** @var  ParserInterface $parser */
    protected $parser;

    protected $formDefinition = array();

    /** @var array|TheliaFormFactoryInterface */
    protected $formFactory = array();

    /** @var array The form collection stack */
    protected $formCollectionStack = array();

    /** @var array Counts collection loop in page */
    protected $formCollectionCount = array();

    public function __construct(
        TheliaFormFactoryInterface $formFactory,
        ParserContext $parserContext,
        ParserInterface $parser
    ) {
        $this->formFactory = $formFactory;
        $this->parserContext = $parserContext;
        $this->parser = $parser;
    }

    public function setFormDefinition($formDefinition)
    {
        foreach ($formDefinition as $name => $className) {
            if (array_key_exists($name, $this->formDefinition)) {
                throw new \InvalidArgumentException(
                    sprintf("%s form name already exists for %s class", $name, $className)
                );
            }

            $this->formDefinition[$name] = $className;
        }
    }

    public function generateForm($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        if ($repeat) {
            $name = $this->getParam($params, 'name');
            $formType = $this->getParam($params, 'type', 'form');

            if (null == $name) {
                $name = "thelia.empty";
            }

            if (!isset($this->formDefinition[$name])) {
                throw new ElementNotFoundException(sprintf("%s form does not exists", $name));
            }

            $formClass = $this->formDefinition[$name];

            // Check if parser context contains our form
            $instance = $this->parserContext->getForm($name, $formClass, $formType);

            if (null === $instance) {
                // If not, create a new instance
                $instance = $this->formFactory->createForm($name);
            }

            // Set the current form
            $this->parserContext->pushCurrentForm($instance);

            $instance->createView();

            $template->assign("form", $instance);
            $template->assign("form_name", $instance->getName());

            $template->assign("form_error", $instance->hasError() ? true : false);
            $template->assign("form_error_message", $instance->getErrorMessage());
        } else {
            $this->parserContext->popCurrentForm();

            return $content;
        }
    }

    /**
     * @param \Smarty_Internal_Template $template
     * @param string $fieldName
     * @param string $fieldValue
     * @param string $fieldType
     * @param array $fieldVars
     * @param int $total_value_count
     */
    protected function assignFieldValues(
        $template,
        $fieldName,
        $fieldValue,
        $fieldType,
        $fieldVars,
        $total_value_count = 1
    ) {
        $template->assign("name", $fieldName);
        $template->assign("value", $fieldValue);
        $template->assign("data", $fieldVars['data']);

        $template->assign("type", $fieldType);

        $template->assign("checked", isset($fieldVars['data']) ? $fieldVars['data'] : false);
        $template->assign("choices", isset($fieldVars['choices']) ? $fieldVars['choices'] : false);
        $template->assign("multiple", isset($fieldVars['multiple']) ? $fieldVars['multiple'] : false);
        $template->assign("disabled", isset($fieldVars['disabled']) ? $fieldVars['disabled'] : false);
        $template->assign("read_only", isset($fieldVars['read_only']) ? $fieldVars['read_only'] : false);
        $template->assign("max_length", isset($fieldVars['max_length']) ? $fieldVars['max_length'] : false);
        $template->assign('required', isset($fieldVars['required']) ? $fieldVars['required'] : false);

        $template->assign("label", $fieldVars["label"]);
        $template->assign("label_attr", $fieldVars["label_attr"]);

        $template->assign('total_value_count', $total_value_count);

        /** @var FormErrorIterator $errors */
        $errors = $fieldVars["errors"];
        if ($errors) {
            $template->assign("error", $errors->count() ? true : false);
            $this->assignFieldErrorVars($template, $errors);
        }

        $attr = array();

        foreach ($fieldVars["attr"] as $key => $value) {
            $attr[] = sprintf('%s="%s"', $key, $value);
        }

        $template->assign("attr", implode(" ", $attr));
        $template->assign("attr_list", $fieldVars["attr"]);
    }

    /**
     * @param \Smarty_Internal_Template $template
     * @param FormConfigInterface $formFieldConfig
     * @param FormView $formFieldView
     */
    protected function assignFormTypeValues($template, $formFieldConfig, $formFieldView)
    {
        $formFieldType = $formFieldConfig->getType()->getInnerType();

        /* access to choices */
        if ($formFieldType instanceof ChoiceType) {
            $template->assign("choices", $formFieldView->vars['choices']);
        }

        /* access to collections */
        if ($formFieldType instanceof CollectionType) {
            if (true === $formFieldConfig->getOption('prototype')) {
            } else {
                /* access to choices */
                if (isset($formFieldView->vars['choices'])) {
                    $template->assign("choices", $formFieldView->vars['choices']);
                }
            }
        }

        /* access to date */
        if ($formFieldType instanceof DateType || $formFieldType instanceof DateTimeType || $formFieldType instanceof BirthdayType) {
            if ('choice' === $formFieldConfig->getOption('widget')) {
                /* access to years */
                if ($formFieldConfig->getOption('years')) {
                    $formFieldView->vars['years'] = $formFieldConfig->getOption('years');
                    $template->assign("years", $formFieldView->vars['years']);
                }

                /* access to month */
                if ($formFieldConfig->getOption('months')) {
                    $formFieldView->vars['months'] = $formFieldConfig->getOption('months');
                    $template->assign("months", $formFieldView->vars['months']);
                }

                /* access to days */
                if ($formFieldConfig->getOption('days')) {
                    $formFieldView->vars['days'] = $formFieldConfig->getOption('days');
                    $template->assign("days", $formFieldView->vars['days']);
                }

                /* access to empty_value */
                if ($formFieldConfig->getOption('empty_value')) {
                    $formFieldView->vars['empty_value'] = $formFieldConfig->getOption('empty_value');
                    $template->assign("empty_value", $formFieldView->vars['empty_value']);
                }
            }
        }

        /* access to thelia type */
        if ($formFieldType instanceof TheliaType) {
            $template->assign("formType", $formFieldView->vars['type']);

            switch ($formFieldView->vars['type']) {
                case "choice":
                    if (!isset($formFieldView->vars['options']['choices']) ||
                        !is_array($formFieldView->vars['options']['choices'])
                    ) {
                        //throw new
                    }
                    $choices = array();
                    foreach ($formFieldView->vars['options']['choices'] as $value => $choice) {
                        $choices[] = new ChoiceView($value, $value, $choice);
                    }
                    $template->assign("choices", $choices);
                    break;
            }
        }
    }

    /**
     * @param array $params
     * @param \Smarty_Internal_Template $template
     */
    protected function processFormField($params, $template)
    {
        $formFieldView = $this->getFormFieldView($params);
        $formFieldConfig = $this->getFormFieldConfig($params);

        $formFieldType = $formFieldConfig->getType()->getName();

        $this->assignFormTypeValues($template, $formFieldConfig, $formFieldView);

        $value = $formFieldView->vars["value"];

        $key = $this->getParam($params, 'value_key', null);

        // We (may) have a collection
        if ($key !== null) {
            // Force array
            if (!is_array($value)) {
                $value = array();
            }

            // If the field is not found, use an empty value
            $name = sprintf("%s[%s]", $formFieldView->vars["full_name"], $key);

            $val = $value[$key];

            // For collection types, the type of field is defined in the 'type' option.
            // We will use this instead of the 'collection' type
            $formFieldType = $formFieldConfig->getType()->getInnerType();

            if ($formFieldType instanceof CollectionType) {
                $formFieldType = $formFieldConfig->getOption('type');
            }

            $this->assignFieldValues(
                $template,
                $name,
                $val,
                $formFieldType,
                $formFieldView->vars,
                count($formFieldView->children)
            );
        } else {
            $this->assignFieldValues(
                $template,
                $formFieldView->vars["full_name"],
                $formFieldView->vars["value"],
                $formFieldType,
                $formFieldView->vars
            );
        }

        $formFieldView->setRendered();
    }

    public function renderFormField($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        if ($repeat) {
            $this->processFormField($params, $template);
        } else {
            return $content;
        }
    }

    /**
     * @param  array $params
     * @param  string $content
     * @param  string $templateFile
     * @param  \Smarty_Internal_Template $template
     * @return string
     */
    protected function automaticFormFieldRendering($params, $content, $template, $templateFile)
    {
        $data = '';

        $templateStyle = $this->getParam($params, 'template', 'standard');

        $snippet_path = sprintf(
            '%s' . DS . 'forms' . DS . '%s' . DS . '%s.html',
            $this->parser->getTemplateDefinition()->getAbsolutePath(),
            $templateStyle,
            $templateFile
        );

        if (false !== $snippet_content = file_get_contents($snippet_path)) {
            $this->processFormField($params, $template);

            if (null === $form = $this->getParam($params, 'form', null)) {
                $form = $this->parserContext->getCurrentForm();
            }

            $field_name = $this->getParam($params, 'field', false);
            $field_extra_class = $this->getParam($params, 'extra_class', '');
            $field_extra_class = $this->getParam($params, 'extra_classes', $field_extra_class);
            $field_no_standard_classes = $this->getParam($params, 'no_standard_classes', false);
            $field_value = $this->getParam($params, 'value', '');
            $show_label = $this->getParam($params, 'show_label', true);
            $value_key = $this->getParam($params, 'value_key', false);

            $template->assign([
                'content' => trim($content),
                'form' => $form,
                'field_name' => $field_name,
                'field_extra_class' => $field_extra_class,
                'field_no_standard_classes' => $field_no_standard_classes,
                'field_value' => $field_value,
                'field_template' => $templateStyle,
                'value_key' => $value_key,
                'show_label' => $show_label,
            ]);

            $data = $template->fetch(sprintf('string:%s', $snippet_content));
        }

        return $data;
    }

    /**
     * @param $params
     * @param $content
     * @param  \Smarty_Internal_Template $template
     * @param $repeat
     * @return mixed
     */
    public function customFormFieldRendering($params, $content, $template, &$repeat)
    {
        if (!$repeat) {
            return $this->automaticFormFieldRendering($params, $content, $template, 'form-field-renderer');
        }
    }

    public function standardFormFieldRendering($params, \Smarty_Internal_Template $template)
    {
        return $this->automaticFormFieldRendering($params, '', $template, 'form-field-renderer');
    }

    public function standardFormFieldAttributes($params, \Smarty_Internal_Template $template)
    {
        return $this->automaticFormFieldRendering($params, '', $template, 'form-field-attributes-renderer');
    }

    public function renderTaggedFormFields($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        if (null === $content) {
            self::$taggedFieldsStack = $this->getFormFieldsFromTag($params);
            self::$taggedFieldsStackPosition = 0;
        } else {
            self::$taggedFieldsStackPosition++;
        }

        if (isset(self::$taggedFieldsStack[self::$taggedFieldsStackPosition])) {
            $field = self::$taggedFieldsStack[self::$taggedFieldsStackPosition];

            $this->assignFieldValues(
                $template,
                $field['view']->vars["full_name"],
                $field['view']->vars["value"],
                $field['config']->getType()->getName(),
                $field['view']->vars
            );

            $this->assignFormTypeValues($template, $field['config'], $field['view']);

            $field['view']->setRendered();

            $repeat = true;
        }

        if (!$repeat) {
            self::$taggedFieldsStack = null;
            self::$taggedFieldsStackPosition = null;
        }

        if (null !== $content) {
            return $content;
        }
    }

    public function renderHiddenFormField($params, \Smarty_Internal_Template $template)
    {
        $attrFormat = '%s="%s"';
        $field = '<input type="hidden" name="%s" value="%s" %s>';

        $baseFormInstance = $this->getInstanceFromParams($params);

        $formView = $baseFormInstance->getView();

        $return = "";

        $exclude = explode(',', $this->getParam($params, 'exclude', ''));

        /** @var FormView $row */
        foreach ($formView->getIterator() as $row) {
            // We have to exclude the fields for which value is defined in the template.
            if ($baseFormInstance->isTemplateDefinedHiddenField($row)
                ||
                in_array($row->vars['name'], $exclude)
            ) {
                continue;
            }

            if ($this->isHidden($row) && $row->isRendered() === false) {
                $attributeList = array();
                if (isset($row->vars["attr"])) {
                    foreach ($row->vars["attr"] as $attrKey => $attrValue) {
                        $attributeList[] = sprintf($attrFormat, $attrKey, $attrValue);
                    }
                }
                $return .= sprintf($field, $row->vars["full_name"], $row->vars["value"], implode(' ', $attributeList));
            }
        }

        return $return;
    }

    public function formEnctype($params, \Smarty_Internal_Template $template)
    {
        $instance = $this->getInstanceFromParams($params);

        $formView = $instance->getView();

        if ($formView->vars["multipart"]) {
            return sprintf('%s="%s"', "enctype", "multipart/form-data");
        }
    }

    public function formError($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        $formFieldView = $this->getFormFieldView($params);

        /** @var FormErrorIterator $errors */
        $errors = $formFieldView->vars["errors"];

        if (!$errors->count()) {
            return "";
        }

        if ($repeat) {
            $this->assignFieldErrorVars($template, $errors);
        } else {
            return $content;
        }
    }

    protected function assignFieldErrorVars(\Smarty_Internal_Template $template, FormErrorIterator $errors)
    {
        if ($errors->count()) {
            $template->assign("message", $errors[0]->getMessage());
            $template->assign("parameters", $errors[0]->getMessageParameters());
            $template->assign("pluralization", $errors[0]->getMessagePluralization());
        }
    }

    protected function isHidden(FormView $formView)
    {
        return array_search("hidden", $formView->vars["block_prefixes"]);
    }

    /**
     * @param $params
     * @return FormView
     * @throws \InvalidArgumentException
     */
    protected function getFormFieldView($params)
    {
        $instance = $this->getInstanceFromParams($params);

        $fieldName = $this->getParam($params, 'field');

        if (null == $fieldName) {
            throw new \InvalidArgumentException("'field' parameter is missing");
        }

        $view = $this->retrieveField(
            $fieldName,
            $instance->getView(),
            $instance->getName()
        );

        return $view;
    }

    protected function getFormFieldsFromTag($params)
    {
        $instance = $this->getInstanceFromParams($params);

        $tag = $this->getParam($params, 'tag');

        if (null == $tag) {
            throw new \InvalidArgumentException("'tag' parameter is missing");
        }

        $viewList = array();
        foreach ($instance->getView() as $view) {
            if (isset($view->vars['attr']['tag']) && $tag == $view->vars['attr']['tag']) {
                $fieldData = $instance->getForm()->all()[$view->vars['name']];
                $viewList[] = array(
                    'view' => $view,
                    'config' => $fieldData->getConfig(),
                );
            }
        }

        return $viewList;
    }

    /**
     * @param $params
     * @return FormConfigInterface
     * @throws \InvalidArgumentException
     */
    protected function getFormFieldConfig($params)
    {
        $instance = $this->getInstanceFromParams($params);

        $fieldName = $this->getParam($params, 'field');

        if (null == $fieldName) {
            throw new \InvalidArgumentException("'field' parameter is missing");
        }

        $fieldData = $this->retrieveField(
            $fieldName,
            $instance->getForm()->all(),
            $instance->getName()
        );

        if (empty($fieldData)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Field name '%s' not found in form %s children",
                    $fieldName,
                    $instance->getName()
                )
            );
        }

        return $fieldData->getConfig();
    }

    /**
     * @param $params
     * @return BaseForm
     * @throws \InvalidArgumentException
     */
    protected function getInstanceFromParams($params)
    {
        if (null === $instance = $this->getParam($params, 'form')) {
            $instance = $this->parserContext->getCurrentForm();
        }

        if (null == $instance) {
            throw new \InvalidArgumentException(
                "Missing 'form' parameter in form arguments, and no current form was found."
            );
        }

        if (!$instance instanceof BaseForm) {
            throw new \InvalidArgumentException(
                sprintf(
                    "form parameter in form_field block must be an instance of " .
                    "\Thelia\Form\BaseForm, instance of %s found",
                    get_class($instance)
                )
            );
        }

        return $instance;
    }

    /**
     * @param $needle
     * @param $haystack
     * @param $formName
     * @return \Symfony\Component\Form\Form
     */
    protected function retrieveField($needle, $haystack, $formName)
    {
        $splitName = explode(".", $needle);

        foreach ($splitName as $level) {
            if (empty($haystack[$level])) {
                throw new \InvalidArgumentException(
                    sprintf("Field name '%s' not found in form %s", $needle, $formName)
                );
            }
            $haystack = $haystack[$level];
        }

        return $haystack;
    }

    /**
     * @param $params
     * @param $name
     * @param bool $throwException
     * @return mixed|null
     *
     * Get a symfony form object form a function/block parameter
     */
    protected function getSymfonyFormFromParams($params, $name, $throwException = false)
    {
        $sfForm = $this->getParam($params, $name);

        if (null === $sfForm && false === $throwException) {
            return null;
        }

        if (!$sfForm instanceof SymfonyForm) {
            throw new \InvalidArgumentException(
                sprintf(
                    "%s parameter must be an instance of " .
                    "\Symfony\Component\Form\Form, instance of %s found",
                    $name,
                    is_object($sfForm) ? get_class($sfForm) : gettype($sfForm)
                )
            );
        }

        return $sfForm;
    }

    /**
     * @param $params
     * @param $content
     * @param \Smarty_Internal_Template $template
     * @param $repeat
     * @return mixed
     *
     * Loops around a form collection entries and assigns values to template
     */
    public function renderFormCollection($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        /**
         * Get parameters
         */
        $form = $this->getInstanceFromParams($params);
        $row = $this->getSymfonyFormFromParams($params, "row");
        $collection = $this->resolveCollection($this->getParam($params, "collection"), $form);

        $hash = $this->initializeCollection($form, $collection, $row);

        $limit = $this->getParam($params, "limit", -1);

        /**
         * Check if it has a limit
         */
        if (!preg_match("#^\-?\d+$#", $limit)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Invalid value for 'limit' parameter in 'form_collection'. '%s' given, integer expected",
                    $limit
                )
            );
        }

        /**
         * Then load stack and create the stack count
         */
        $limit = (int)$limit;
        $hasLimit = $limit >= 0;

        /**
         * If we have reached the limit, stop
         */
        $collectionLimit = $this->formCollectionCount[$hash]["limit"];

        if (($hasLimit && $limit === $collectionLimit) ||
            null === $row = array_shift($this->formCollectionStack[$hash])
        ) {
            $repeat = false;

            /**
             * Reload stack limit
             */
            $this->formCollectionCount[$hash]["limit"] = 0;

            return $content;
        }

        /**
         * Assign variables into the template
         */
        $template->assign("row", $row);
        $template->assign("collection_current", $this->formCollectionCount[$hash]["count"]++);
        $template->assign("collection_count", $this->formCollectionCount[$hash]["total_count"]);

        /**
         * Increment the current limit state
         * Force the repeat
         */
        $this->formCollectionCount[$hash]["limit"]++;
        $repeat = true;

        /**
         * ANd return the content
         */

        return $content;
    }

    /**
     * @param BaseForm $form
     * @param SymfonyForm $field
     * @return string
     *
     * Get definition, return hash
     */
    protected function getFormStackHash(BaseForm $form, SymfonyForm $field = null)
    {
        $build = get_class($form) . ":" . $form->getType();

        if (null !== $field) {
            $build .= ":" . $this->buildFieldName($field);
        }

        return md5($build);
    }

    /**
     * @param $collection
     * @param BaseForm $form
     * @return SymfonyForm
     *
     * Extract the collection object from the form
     */
    protected function resolveCollection($collection, BaseForm $form)
    {
        if (null === $collection) {
            throw new \InvalidArgumentException(
                "Missing parameter 'collection' in 'form_collection"
            );
        }

        $sfForm = $form->getForm();

        if (!$sfForm->has($collection)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Field name '%s' not found in form %s children",
                    $collection,
                    $form->getName()
                )
            );
        }

        /**
         * Check that the field is a "collection" type
         */
        $collectionConfig = $this->retrieveField(
            $collection,
            $sfForm->all(),
            $form->getName()
        );

        $fieldType = $collectionConfig->getConfig()->getType();

        if ($fieldType->getName() !== static::COLLECTION_TYPE_NAME) {
            $baseFieldType = $fieldType;
            $resolved = false;

            while (null !== $fieldType && !$resolved) {
                if ($fieldType->getName() !== static::COLLECTION_TYPE_NAME) {
                    $fieldType = $fieldType->getParent();
                }
            }

            if (!$resolved) {
                throw new \LogicException(
                    sprintf(
                        "The field '%s' is not a collection, it's a '%s'." .
                        "You can't use it with the function 'form_collection' in form '%s'",
                        $collection,
                        $baseFieldType->getName(),
                        $form->getName()
                    )
                );
            }
        }

        return $collectionConfig;
    }

    /**
     * @param $params
     * @param $content
     * @param \Smarty_Internal_Template $template
     * @param $repeat
     * @return string
     *
     * Injects a collection field variables into the parser
     */
    public function renderFormCollectionField($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        if (!$repeat) {
            return $content;
        }

        $form = $this->getInstanceFromParams($params);
        /** @var \Symfony\Component\Form\Form $row */
        $row = $this->getSymfonyFormFromParams($params, "row", true);
        $field = $this->getParam($params, "field");

        $formField = $this->retrieveField($field, $row->all(), $form->getName());

        $formFieldConfig = $formField->getConfig();

        $this->assignFieldValues(
            $template,
            $this->buildFieldName($formField),
            $formField->getViewData(),
            $formFieldConfig->getType(),
            $this->findCollectionFieldFormView($form->getView(), $formField)
        );

        return '';
    }

    /**
     * @param FormView $formView
     * @param SymfonyForm $formField
     * @return array
     */
    protected function findCollectionFieldFormView(FormView $formView, SymfonyForm $formField)
    {
        $formFieldParentList = [];

        do {
            // don't need to set first form name child
            if (null === $formField->getParent()) {
                break;
            }

            $formFieldParentList[] = $formField->getConfig()->getName();

        } while (null !== $formField = $formField->getParent());

        $formFieldParentList = array_reverse($formFieldParentList);

        foreach ($formFieldParentList as $val) {
            $formView = $formView->children[$val];
        }

        return $formView->vars;
    }

    /**
     * @param FormInterface $form
     * @param array $tree
     * @return string
     *
     * Tail recursive method that builds the field full name
     */
    protected function buildFieldName(FormInterface $form, array &$tree = array())
    {
        $config = $form->getConfig();
        $parent = $form->getParent();
        $hasParent = null !== $parent;

        if (null !== $proprietyPath = $config->getPropertyPath()) {
            $name = (string)$proprietyPath;
        } else {
            $name = $config->getName();

            if ($name === null) {
                $name = '';
            } elseif ($name !== '' && $hasParent) {
                $name = "[$name]";
            }
        }

        array_unshift($tree, $name);

        if (!$hasParent) {
            return implode("", $tree);
        }

        return $this->buildFieldName($parent, $tree);
    }

    /**
     * @param $params
     * @param \Smarty_Internal_Template $template
     * @return mixed
     *
     * Counts collection entries
     */
    public function formCollectionCount($params, \Smarty_Internal_Template $template)
    {
        /**
         * Get parameters
         */
        $form = $this->getInstanceFromParams($params);
        $row = $this->getSymfonyFormFromParams($params, "row");
        $collection = $this->resolveCollection($this->getParam($params, "collection"), $form);

        $hash = $this->initializeCollection($form, $collection, $row);

        return $this->formCollectionCount[$hash]["total_count"];
    }

    /**
     * @param BaseForm $form
     * @param SymfonyForm $collection
     * @param SymfonyForm $row
     * @return string
     *
     * Initialize a collection into this class ( values stack, counting table )
     */
    protected function initializeCollection(BaseForm $form, SymfonyForm $collection, SymfonyForm $row = null)
    {
        $hash = $this->getFormStackHash($form, $collection);

        if (!isset($this->formCollectionStack[$hash])) {
            $this->formCollectionStack[$hash] = $collection->all();
        }

        if (!isset($this->formCollectionCount[$hash])) {
            $this->formCollectionCount[$hash] = [
                "count" => 0,
                "limit" => 0,
                "total_count" => count($this->formCollectionStack[$hash]),
            ];
        }

        return $hash;
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("block", "form", $this, "generateForm"),
            new SmartyPluginDescriptor("block", "form_field", $this, "renderFormField"),
            new SmartyPluginDescriptor("block", "form_tagged_fields", $this, "renderTaggedFormFields"),
            new SmartyPluginDescriptor("function", "form_hidden_fields", $this, "renderHiddenFormField"),
            new SmartyPluginDescriptor("function", "form_enctype", $this, "formEnctype"),
            new SmartyPluginDescriptor("block", "form_error", $this, "formError"),
            new SmartyPluginDescriptor("function", "form_field_attributes", $this, "standardFormFieldAttributes"),
            new SmartyPluginDescriptor("function", "render_form_field", $this, "standardFormFieldRendering"),
            new SmartyPluginDescriptor("block", "custom_render_form_field", $this, "customFormFieldRendering"),
            new SmartyPluginDescriptor("block", "form_collection", $this, "renderFormCollection"),
            new SmartyPluginDescriptor("block", "form_collection_field", $this, "renderFormCollectionField"),
            new SmartyPluginDescriptor("function", "form_collection_count", $this, "formCollectionCount"),
        );
    }
}
