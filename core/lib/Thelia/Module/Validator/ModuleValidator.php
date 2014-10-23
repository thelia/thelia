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

namespace Thelia\Module\Validator;

use Symfony\Component\Config\Definition\Exception\Exception;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\FileNotFoundException;
use Thelia\Exception\ModuleException;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\Exception\InvalidXmlDocumentException;
use Thelia\Module\ModuleDescriptorValidator;
use Thelia\Tools\Version\Version;

/**
 * Class ModuleValidartor
 *
 * @package Thelia\Module\Validator
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleValidator
{

    protected $modulePath;

    /** @var bool */
    protected $isLoaded = false;

    /** @var ModuleDescriptorValidator */
    protected $moduleDescriptor;

    /** @var ModuleDefinition */
    protected $moduleDefinition;

    protected $moduleVersion;

    /** @var Translator */
    protected $translator;

    /** @var array array of errors */
    protected $errors = [];

    public function __construct($modulePath = null)
    {
        $this->modulePath = $modulePath;
    }

    /**
     * @param mixed $modulePath
     */
    public function setModulePath($modulePath)
    {
        $this->modulePath = $modulePath;
    }

    /**
     * @return mixed
     */
    public function getModulePath()
    {
        return $this->modulePath;
    }

    /**
     * @return ModuleDescriptorValidator|null
     */
    public function getModuleDescriptor()
    {
        return $this->moduleDescriptor;
    }

    /**
     * @return ModuleDefinition|null
     */
    public function getModuleDefinition()
    {
        return $this->moduleDefinition;
    }

    /**
     * @return mixed
     */
    public function getModuleVersion()
    {
        return $this->moduleVersion;
    }


    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param \Thelia\Core\Translation\Translator $translator
     */
    public function setTranslator($translator)
    {
        $this->translator = $translator;
    }

    /**
     * @return \Thelia\Core\Translation\Translator
     */
    public function getTranslator()
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator;
    }


    /**
     * Loads the module description and configuration.
     *
     * @throws FileNotFoundException if modules does not exist or not contains module.xml or config.xml
     */
    public function load()
    {
        $this->checkDirectoryStructure();

        $this->loadModuleDescriptor();

        $this->loadModuleDefinition();

        $this->isLoaded = true;
    }

    /**
     * Validate a module, checks :
     *
     * - version of Thelia
     * - modules dependencies
     *
     * @param bool $checkCurrentVersion if true it will also check if the module is
     *                                  already installed (not activated - present in module list)
     */
    public function validate($checkCurrentVersion = true)
    {
        if (false === $this->isLoaded) {
            $this->load();
        }

        if (null === $this->moduleDescriptor) {
            throw new \Exception(
                $this->getTranslator()->trans(
                    "The module definition has not been initialized."
                )
            );
        }

        $this->checkVersion();

        if (true === $checkCurrentVersion) {
            $this->checkModuleVersion();
        }

        $this->checkModuleDependencies();
    }

    protected function checkDirectoryStructure()
    {
        if (false === file_exists($this->modulePath)) {
            throw new FileNotFoundException(
                $this->getTranslator()->trans("Module directory doesn't exist.")
            );
        }

        $path = sprintf("%s/Config/module.xml", $this->modulePath);
        if (false === file_exists($path)) {
            throw new FileNotFoundException(
                $this->getTranslator()->trans("Module should have a module.xml in the Config directory.")
            );
        }

        $path = sprintf("%s/Config/config.xml", $this->modulePath);
        if (false === file_exists($path)) {
            throw new FileNotFoundException(
                $this->getTranslator()->trans("Module should have a config.xml in the Config directory.")
            );
        }
    }

    protected function loadModuleDescriptor()
    {
        $path = sprintf("%s/Config/module.xml", $this->modulePath);

        $descriptorValidator = new ModuleDescriptorValidator();

        try {
            // validation with xsd
            $this->moduleDescriptor = $descriptorValidator->getDescriptor($path);
            $this->moduleVersion = $descriptorValidator->getModuleVersion();
        } catch (InvalidXmlDocumentException $ex) {
            throw $ex;
        }
    }

    public function loadModuleDefinition()
    {
        if (null === $this->moduleDescriptor) {
            throw new \Exception(
                $this->getTranslator()->trans(
                    "The module descriptor has not been initialized."
                )
            );
        }

        $moduleDefinition = new ModuleDefinition();

        $moduleDefinition->setCode(basename($this->modulePath));
        $moduleDefinition->setNamespace((string)$this->moduleDescriptor->fullnamespace);
        $moduleDefinition->setVersion((string)$this->moduleDescriptor->version);

        $languages = [];
        if ($this->getModuleVersion() != "1") {
            foreach ($this->moduleDescriptor->languages->language as $language) {
                $languages[] = (string)$language;
            }
        }
        $moduleDefinition->setLanguages($languages);

        $descriptives = [];
        foreach ($this->moduleDescriptor->descriptive as $descriptive) {
            $descriptives[(string)$descriptive['locale']] = [
                'title' => (string)$descriptive->title,
                'subtitle' => (string)$descriptive->subtitle,
                'description' => (string)$descriptive->description,
                'postscriptum' => (string)$descriptive->postscriptum,
            ];
        }
        $moduleDefinition->setDescriptives($descriptives);

        $dependencies = [];
        if (isset($this->moduleDescriptor->required)) {
            foreach ($this->moduleDescriptor->required->module as $dependency) {
                $dependencies[] = [
                    (string)$dependency,
                    (string)$dependency['version'],
                ];
            }
        }
        $moduleDefinition->setDependencies($dependencies);

        $moduleDefinition->setLogo((string)$this->moduleDescriptor->logo);
        $moduleDefinition->setTheliaVersion((string)$this->moduleDescriptor->thelia);
        $moduleDefinition->setType((string)$this->moduleDescriptor->type);
        $moduleDefinition->setStability((string)$this->moduleDescriptor->stability);

        // documentation
        $moduleDefinition->setDocumentation((string)$this->moduleDescriptor->documentation);

        $this->moduleDefinition = $moduleDefinition;
    }

    protected function checkVersion()
    {
        if ($this->moduleDefinition->getTheliaVersion()) {
            if (!Version::test(Thelia::THELIA_VERSION, $this->moduleDefinition->getTheliaVersion())) {
                throw new ModuleException(
                    $this->getTranslator()->trans(
                        "The module requires a version of Thelia >= %version",
                        ['%version' => $this->moduleDefinition->getVersion()]
                    )
                );
            }
        }
    }

    protected function checkModuleVersion()
    {
        $module = ModuleQuery::create()
            ->findOneByFullNamespace($this->moduleDefinition->getNamespace());

        if (null !== $module) {
            if (version_compare($module->getVersion(), $this->moduleDefinition->getVersion(), '>=')) {
                throw new ModuleException(
                    $this->getTranslator()->trans(
                        "The module is already installed in the same or greater version."
                    )
                );
            }
        }
    }

    protected function checkModuleDependencies()
    {

        $errors = [];

        foreach ($this->moduleDefinition->getDependencies() as $dependency) {

            $module = ModuleQuery::create()
                ->findOneByCode($dependency[0]);

            $pass = false;

            if (null !== $module) {
                if ($module->getActivate() === BaseModule::IS_ACTIVATED) {
                    if ("" == $dependency[1] || Version::test($module->getVersion(), $dependency[1])) {
                        $pass = true;
                    }
                }
            }

            if (false === $pass) {
                if ('' !== $dependency[1]) {
                    $errors[] = $this->getTranslator()->trans(
                        '%module (version: %version)',
                        [
                            '%module' => $dependency[0],
                            '%version' => $dependency[1]
                        ]
                    );
                } else {
                    $errors[] = sprintf('%s', $dependency[0]);
                }
            }
        }

        if (count($errors) > 0) {
            $errorsMessage = $this->getTranslator()->trans(
                'The module requires this activated modules : %modules',
                ['%modules' => implode(', ', $errors)]
            );

            throw new ModuleException($errorsMessage);
        }
    }

    /**
     * Get an array of modules that depend of the current module
     *
     * @param  bool|null $active if true only search in activated module, false only deactivated and null on all modules
     * @return array     array of array with `code` which is the module code that depends of this current module and
     *                   `version` which is the required version of current module
     */
    public function getModulesDependOf($active = true)
    {
        $code = $this->getModuleDefinition()->getCode();
        $query = ModuleQuery::create();
        $dependantModules = [];

        if (true === $active) {
            $query->findByActivate(1);
        } elseif (false === $active) {
            $query->findByActivate(0);
        }

        $modules = $query->find();

        /** @var Module $module */
        foreach ($modules as $module) {

            $validator = new ModuleValidator($module->getAbsoluteBaseDir());
            try {
                $validator->load();
                $definition = $validator->getModuleDefinition();
                $dependencies = $definition->getDependencies();

                if (count($dependencies) > 0) {

                    foreach ($dependencies as $dependency) {

                        if ($dependency[0] == $code) {
                            $dependantModules[] = [
                                'code' => $definition->getCode(),
                                'version' => $dependency[1]
                            ];

                            break;
                        }
                    }
                }
            } catch (\Exception $ex) {
                ;
            }
        }

        return $dependantModules;
    }
}
