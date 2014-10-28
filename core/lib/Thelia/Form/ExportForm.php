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

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Model\LangQuery;

/**
 * Class ExportForm
 * @package Thelia\Form
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportForm extends BaseForm
{
    protected $translator;

    public function __construct(Request $request, $type = "form", $data = array(), $options = array())
    {
        $this->translator = Translator::getInstance();

        parent::__construct($request, $type, $data, $options);
    }

    protected function buildForm()
    {
        $this->formBuilder
            ->add("formatter", "text", array(
                "label" => $this->translator->trans("File format"),
                "label_attr" => ["for" => "formatter"],
                "required" => true,
            ))
            ->add("do_compress", "checkbox", array(
                "label" => $this->translator->trans("Do compress"),
                "label_attr" => ["for" => "do_compress"],
                "required" => false,
            ))
            ->add("archive_builder", "text", array(
                "label" => $this->translator->trans("Archive Format"),
                "label_attr" => ["for" => "archive_builder"],
                "required" => false,
            ))
            ->add("images", "checkbox", array(
                "label" => $this->translator->trans("Include images"),
                "label_attr" => ["for" => "with_images"],
                "required" => false,
            ))
            ->add("documents", "checkbox", array(
                "label" => $this->translator->trans("Include documents"),
                "label_attr" => ["for" => "with_documents"],
                "required" => false,
            ))
            ->add("language", "integer", array(
                "label" => $this->translator->trans("Language"),
                "label_attr" => ["for" => "language"],
                "required" => true,
                "constraints" => [
                    new Callback([
                        "methods" => [
                            [$this, "checkLanguage"],
                        ]
                    ])
                ]
            ))
        ;
    }

    public function getName()
    {
        return "thelia_export";
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
