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

use Exception;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Module\Validator\ModuleDefinition;
use Thelia\Module\Validator\ModuleValidator;
use ZipArchive;

/**
 * Class ProductCreationForm
 * @package Thelia\Form
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleInstallForm extends BaseForm
{

    /** @var ModuleDefinition */
    protected $moduleDefinition = null;

    protected $modulePath = null;

    protected function buildForm($change_mode = false)
    {

        $this->formBuilder
            ->add(
                'module',
                'file',
                [
                    'required'    => true,
                    'constraints' => array(
                        new Constraints\File(
                            array(
                                'mimeTypes'        => array(
                                    'application/zip'
                                ),
                                'mimeTypesMessage' => Translator::getInstance()->trans('Please upload a valid Zip file')
                            )
                        ),
                        new Constraints\Callback(array(
                            "methods" => array(
                                array($this, "checkModuleValidity")
                            )
                        ))
                    ),
                    'label'       => Translator::getInstance()->trans('The module zip file'),
                    'label_attr'  => [
                        'for' => 'module'
                    ]
                ]
            );

    }

    /**
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @param ExecutionContextInterface                           $context
     */
    public function checkModuleValidity($file, ExecutionContextInterface $context)
    {
        Tlog::getInstance()->warning(sprintf(" GUGU %s", print_r($file, true)));

        $modulePath = $this->unzipModule($file);

        if ($modulePath !== false) {

            try {
                // get the first directory
                $moduleFiles = $this->getDirContents($modulePath);
                if (count($moduleFiles['directories']) !== 1) {
                    throw new Exception(
                        Translator::getInstance()->trans(
                            "Your zip must contain 1 root directory which is the root folder directory of your module"
                        )
                    );
                }

                $this->modulePath = sprintf('%s/%s', $modulePath, $moduleFiles['directories'][0]);

                $moduleValidator = new ModuleValidator($this->modulePath);

                $moduleValidator->validate();

                $this->moduleDefinition = $moduleValidator->getModuleDefinition();

            } catch (Exception $ex) {

                $context->addViolation(
                    Translator::getInstance()->trans(
                        "The module is not valid : %message",
                        array('%message' => $ex->getMessage())
                    )
                );

            }
        }
    }

    public function getModuleDefinition()
    {
        return $this->moduleDefinition;
    }

    /**
     * @return null
     */
    public function getModulePath()
    {
        return $this->modulePath;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     *
     * @return bool
     */
    protected function unzipModule($file)
    {
        $extractPath = false;
        $zip         = new ZipArchive();
        if ($zip->open($file->getRealPath()) === true) {

            $extractPath = $this->tempdir();

            if ($extractPath !== false) {
                if ($zip->extractTo($extractPath) === false) {
                    $extractPath = false;
                }
            }

            $zip->close();

        }

        return $extractPath;
    }

    protected function tempdir()
    {
        $tempfile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        mkdir($tempfile);
        if (is_dir($tempfile)) {
            return $tempfile;
        }

        return false;
    }

    protected function getDirContents($dir)
    {

        $paths = array_diff(scandir($dir), array('..', '.'));

        $out = [
            'directories' => [],
            'files'       => [],
        ];

        foreach ($paths as $path) {
            if (is_dir($dir . DS . $path)) {
                $out['directories'][] = $path;
            } else {
                $out['files'][] = $path;
            }
        }

        return $out;
    }

    public function getName()
    {
        return "module_install";
    }

}
