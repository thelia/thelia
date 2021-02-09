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

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProfileQuery;

/**
 * Class ProfileCreationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProfileCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add("locale", TextType::class, [
                "constraints" => [new NotBlank()],
            ])
            ->add("code", TextType::class, [
                "constraints" => [
                    new NotBlank(),
                    new Constraints\Callback(
                        [$this, "verifyCode"]
                    ),
                ],
                "label" => Translator::getInstance()->trans("Profile Code"),
                "label_attr" => ["for" => "profile_code_fiels"],
            ])
        ;

        $this->addStandardDescFields(['locale']);
    }

    public function getName()
    {
        return "thelia_profile_creation";
    }

    public function verifyCode($value, ExecutionContextInterface $context)
    {
        /* check unicity */
        $profile = ProfileQuery::create()
            ->findOneByCode($value);

        if (null !== $profile) {
            $context->addViolation(Translator::getInstance()->trans("Profile `code` already exists"));
        }
    }
}
