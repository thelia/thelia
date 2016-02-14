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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
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
                    'required' => true,
                    'constraints' => [
                        new Constraints\File(
                            [
                                'mimeTypes' => [
                                    'application/zip',
                                ],
                                'mimeTypesMessage' => Translator::getInstance()->trans('Please upload a valid Zip file'),
                            ]
                        ),
                        new Constraints\Callback([
                            "methods" => [
                                [$this, "checkModuleValidity"],
                            ],
                        ]),
                    ],
                    'label' => Translator::getInstance()->trans('The module zip file'),
                    'label_attr' => [
                        'for' => 'module',
                    ]
                ]
            );
    }

    /**
     * Check module validity
     *
     * @param UploadedFile              $file
     * @param ExecutionContextInterface $context
     */
    public function checkModuleValidity(UploadedFile $file, ExecutionContextInterface $context)
    {
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

                $moduleDirectory = $moduleFiles['directories'][0];

                $this->modulePath = sprintf('%s/%s', $modulePath, $moduleDirectory);

                $moduleValidator = new ModuleValidator($this->modulePath);

                $moduleValidator->validate();

                $this->moduleDefinition = $moduleValidator->getModuleDefinition();
            } catch (Exception $ex) {
                $context->addViolation(
                    Translator::getInstance()->trans(
                        "The module is not valid : %message",
                        ['%message' => $ex->getMessage()]
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
     * @return string|null
     */
    public function getModulePath()
    {
        return $this->modulePath;
    }

    /**
     * Unzip a module file.
     *
     * @param UploadedFile $file
     *
     * @return string|bool the path where the module has been extracted or false if an error has occured
     */
    protected function unzipModule(UploadedFile $file)
    {
        $extractPath = false;
        $zip = new ZipArchive();
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

    /**
     * create a unique directory.
     *
     * @return bool|string the directory path or false if it fails
     */
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
        $paths = array_diff(scandir($dir), ['..', '.']);

        $out = [
            'directories' => [],
            'files' => [],
        ];

        foreach ($paths as $path) {
            if (is_dir($dir.DS.$path)) {
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
