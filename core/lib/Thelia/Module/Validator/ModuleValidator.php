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

    /** @var ModuleDescriptorValidator */
    protected $moduleDescriptor;

    /** @var ModuleDefinition */
    protected $moduleDefinition;

    protected $moduleVersion;

    /** @var Translator */
    protected $translator;

    /** @var array array of errors */
    protected $errors = [];

    protected $moduleDirName;

    /**
     * @param string $modulePath the path of the module directory
     * @param \Thelia\Core\Translation\Translator $translator FOR UNIT TEST PURPOSE ONLY
     */
    public function __construct($modulePath = null, $translator = null)
    {
        $this->translator = $translator;

        $this->modulePath = $modulePath;

        $this->moduleDirName = basename($this->modulePath);

        $this->checkDirectoryStructure();

        $this->loadModuleDescriptor();

        $this->loadModuleDefinition();
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
        if (null === $this->moduleDescriptor) {
            throw new \Exception(
                $this->getTranslator()->trans(
                    "The %name module definition has not been initialized.",
                    [ '%name' => $this->moduleDirName ]
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
                $this->getTranslator()->trans(
                    "Module %name directory doesn't exists.",
                    [ '%name' => $this->moduleDirName]
                )
            );
        }

        $path = sprintf("%s/Config/module.xml", $this->modulePath);
        if (false === file_exists($path)) {
            throw new FileNotFoundException(
                $this->getTranslator()->trans(
                    "Module %name should have a module.xml in the Config directory.",
                    [ '%name' => $this->moduleDirName]
                )
            );
        }

        $path = sprintf("%s/Config/config.xml", $this->modulePath);
        if (false === file_exists($path)) {
            throw new FileNotFoundException(
                $this->getTranslator()->trans(
                    "Module %name should have a config.xml in the Config directory.",
                    [ '%name' => $this->moduleDirName]
                )
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
                    "The %name module descriptor has not been initialized.",
                    [ '%name' => $this->moduleDirName ]
                )
            );
        }

        $moduleDefinition = new ModuleDefinition();

        // Try to guess the proper module name, using the descriptor information.
        $fullnamespace = trim((string)$this->moduleDescriptor->fullnamespace);

        $namespaceComponents = explode("\\", $fullnamespace);

        if (! isset($namespaceComponents[0]) || empty($namespaceComponents[0])) {
            throw new ModuleException(
                $this->getTranslator()->trans(
                    "Unable to get module code from the fullnamespace element of the module descriptor: '%val'",
                    [
                        '%name' => $this->moduleDirName,
                        '%val' => $fullnamespace
                    ]
                )
            );
        }

        // Assume the module code is the first component of the declared namespace
        $moduleDefinition->setCode($namespaceComponents[0]);
        $moduleDefinition->setNamespace($fullnamespace);
        $moduleDefinition->setVersion((string)$this->moduleDescriptor->version);

        $this->getModuleLanguages($moduleDefinition);

        $this->getModuleDescriptives($moduleDefinition);

        $this->getModuleDependencies($moduleDefinition);

        $this->getModuleAuthors($moduleDefinition);

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
            if (!Version::test(Thelia::THELIA_VERSION, $this->moduleDefinition->getTheliaVersion(), false, ">=")) {
                throw new ModuleException(
                    $this->getTranslator()->trans(
                        "The module %name requires Thelia %version or newer",
                        [
                            '%name' => $this->moduleDirName,
                            '%version' => $this->moduleDefinition->getTheliaVersion()
                        ]
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
                        "The module %name is already installed in the same or greater version.",
                        [ '%name' => $this->moduleDirName]
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
                    if ("" == $dependency[1] || Version::test($module->getVersion(), $dependency[1], false, ">=")) {
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
                'To activate module %name, the following modules should be activated first: %modules',
                ['%name' => $this->moduleDirName, '%modules' => implode(', ', $errors)]
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
            try {
                $validator = new ModuleValidator($module->getAbsoluteBaseDir());

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

    public function getCurrentModuleDependencies()
    {
        $dependencies = [];
        if (0 !== count($this->moduleDescriptor->required)) {
            foreach ($this->moduleDescriptor->required->module as $dependency) {
                $dependencies[] = [
                   "code" => (string)$dependency,
                   "version" => (string)$dependency['version'],
                ];
            }
        }

        return $dependencies;
    }

    /**
     * @param ModuleDefinition $moduleDefinition
     */
    protected function getModuleLanguages(ModuleDefinition $moduleDefinition)
    {
        $languages = [];
        if ($this->getModuleVersion() != "1") {
            foreach ($this->moduleDescriptor->languages->language as $language) {
                $languages[] = (string)$language;
            }
        }
        $moduleDefinition->setLanguages($languages);
    }

    /**
     * @param ModuleDefinition $moduleDefinition
     */
    protected function getModuleDescriptives(ModuleDefinition $moduleDefinition)
    {
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
    }

    /**
     * @param ModuleDefinition $moduleDefinition
     */
    protected function getModuleDependencies(ModuleDefinition $moduleDefinition)
    {
        $dependencies = [];
        if (0 !== count($this->moduleDescriptor->required)) {
            foreach ($this->moduleDescriptor->required->module as $dependency) {
                $dependencies[] = [
                    (string)$dependency,
                    (string)$dependency['version'],
                ];
            }
        }
        $moduleDefinition->setDependencies($dependencies);
    }

    /**
     * @param ModuleDefinition $moduleDefinition
     */
    protected function getModuleAuthors(ModuleDefinition $moduleDefinition)
    {
        $authors = [];
        if (0 !== count($this->moduleDescriptor->author)) {
            foreach ($this->moduleDescriptor->author as $author) {
                $authors[] = [
                    (string)$author->name,
                    (string)$author->company,
                    (string)$author->email,
                    (string)$author->website
                ];
            }
        } else {
            $authors = $this->getModuleAuthors22($moduleDefinition);
        }
        $moduleDefinition->setAuthors($authors);
    }

    protected function getModuleAuthors22(ModuleDefinition $moduleDefinition)
    {
        $authors = [];

        if (0 !== count($this->moduleDescriptor->authors->author)) {
            foreach ($this->moduleDescriptor->authors->author as $author) {
                $authors[] = [
                    (string)$author->name,
                    (string)$author->company,
                    (string)$author->email,
                    (string)$author->website
                ];
            }
        }

        return $authors;
    }
}
