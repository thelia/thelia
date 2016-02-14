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

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Model\LangQuery;

/**
 * Class ImportForm
 * @package Thelia\Form
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("file_upload", "file", array(
            "label" => $this->translator->trans("File to upload"),
            "label_attr" => ["for" => "file_to_upload"],
            "required" => true,
                "constraints" => [
                    new Assert\NotNull
                ]
            ))
            ->add("language", "integer", array(
                "label" => $this->translator->trans("Language"),
                "label_attr" => ["for" => "language"],
                "required" => true,
                "constraints" => [
                    new Assert\Callback([
                        "methods" => [
                            [$this, "checkLanguage"],
                        ],
                    ]),
                ],
            ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_import";
    }

    public function checkLanguage($value, ExecutionContextInterface $context)
    {
        if (null === LangQuery::create()->findPk($value)) {
            $context->addViolation(
                $this->translator->trans(
                    "The language \"%id\" doesn't exist",
                    [
                        "%id" => $value
                    ]
                )
            );
        }
    }
}
