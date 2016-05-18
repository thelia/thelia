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

namespace Thelia\Form;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Model\HookQuery;

/**
 * Class HookCreationForm
 * @package Thelia\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("code", "text", array(
                "constraints" => array(
                    new NotBlank(),
                    new Callback(array(
                        "methods" => array(array($this, "checkCodeUnicity"))
                    )),
                ),
                "label" => Translator::getInstance()->trans("Hook code"),
                "label_attr" => array(
                    "for" => "code",
                ),
            ))
            ->add("locale", "hidden", array(
                "constraints" => array(
                    new NotBlank(),
                ),
            ))
            ->add("type", "choice", array(
                "choices" => array(
                    TemplateDefinition::FRONT_OFFICE => Translator::getInstance()->trans("Front Office"),
                    TemplateDefinition::BACK_OFFICE => Translator::getInstance()->trans("Back Office"),
                    TemplateDefinition::EMAIL => Translator::getInstance()->trans("email"),
                    TemplateDefinition::PDF => Translator::getInstance()->trans("pdf"),
                ),
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Type"),
                "label_attr" => array(
                    "for" => "type",
                ),
            ))
            ->add("native", "hidden", array(
                "label" => Translator::getInstance()->trans("Native"),
                "label_attr" => array(
                    "for" => "native",
                    "help" => Translator::getInstance()->trans("Core hook of Thelia."),
                ),
            ))
            ->add("active", "checkbox", array(
                "label" => Translator::getInstance()->trans("Active"),
                "required" => false,
                "label_attr" => array(
                    "for" => "active",
                ),
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Hook title"),
                "label_attr" => array(
                    "for" => "title",
                ),
            ))
        ;
    }

    public function checkCodeUnicity($code, ExecutionContextInterface $context)
    {
        $type = $context->getRoot()->getData()['type'];

        $query = HookQuery::create()->filterByCode($code)->filterByType($type);

        if ($this->form->has('id')) {
            $query->filterById($this->form->getRoot()->getData()['id'], Criteria::NOT_EQUAL);
        }

        if ($query->count() > 0) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "A Hook with code %name already exists. Please choose another code.",
                    array('%name' => $code)
                )
            );
        }
    }

    public function getName()
    {
        return "thelia_hook_creation";
    }
}
