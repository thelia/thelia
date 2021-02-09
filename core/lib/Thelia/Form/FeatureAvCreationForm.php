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

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class FeatureAvCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("title", TextType::class, [
                "constraints" => [
                    new NotBlank(),
                ],
                "label" => Translator::getInstance()->trans("Title *"),
                "label_attr" => [
                    "for" => "title",
                ],
            ])
            ->add("locale", TextType::class, [
                "constraints" => [
                    new NotBlank(),
                ],
            ])
            ->add("feature_id", HiddenType::class, [
                "constraints" => [
                        new NotBlank(),
                ],
            ])
        ;
    }

    public function getName()
    {
        return "thelia_featureav_creation";
    }
}
