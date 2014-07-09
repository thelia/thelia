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
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class ExportForm
 * @package Thelia\Form
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportForm extends BaseForm
{
    protected $archiveBuilderManager = array();

    protected $formattersNames = array();

    protected $exportTypes = array();

    protected $translator;

    public function __construct(Request $request, $type= "form", $data = array(), $options = array())
    {
        $this->translator = Translator::getInstance();

        parent::__construct($request, $type, $data, $options);
    }

    protected function buildForm()
    {
        $this->formBuilder
            ->add("formatter", "choice", array(
                "label" => $this->translator->trans("File format"),
                "label_attr" => ["for" => "formatter"],
                "required" => true,
                "multiple" => false,
                "choices" => $this->formattersNames,
            ))
            ->add("archive_builder", "choice", array(
                "label" => $this->translator->trans("Archive Format"),
                "label_attr" => ["for" => "archive_builder"],
                "required" => true,
                "multiple" => false,
                "choices" => $this->archiveBuilderManager,
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
            ->add("export_type", "choice", array(
                "required" => true,
                "choices" => $this->exportTypes,
            ))
        ;
    }


    public function setFormatters(array $formattersNames) {
        $this->formattersNames = $formattersNames;

        return $this;
    }

    public function setArchiveBuilderNames(array $archiveBuildersNames)
    {
        $this->archiveBuilderManager += $archiveBuildersNames;

        return $this;
    }

    public function setExportTypes(array $exportType)
    {
        $this->exportTypes = $exportType;

        return $this;
    }

    public function getName()
    {
        return "thelia_export";
    }
} 