<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Module\Validator;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\File\Exception\FileNotFoundException;
use Thelia\Core\TheliaKernel;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Module\Exception\ModuleException;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\ModuleDescriptorValidator;
use Thelia\Tools\Version\Version;

/**
 * Class ModuleValidartor.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleValidator
{
    protected ?\SimpleXMLElement $moduleDescriptor = null;
    protected ?ModuleDefinition $moduleDefinition = null;
    protected ?string $moduleVersion = null;
    protected array $errors = [];
    protected ?string $moduleDirName = null;

    /**
     * @param string|null     $modulePath the path of the module directory
     * @param Translator|null $translator FOR UNIT TEST PURPOSE ONLY
     *
     * @throws FileNotFoundException
     */
    public function __construct(
        protected ?string $modulePath = null,
        protected ?Translator $translator = null,
    ) {
        $this->moduleDirName = basename((string) $this->modulePath);
        $this->checkDirectoryStructure();
        $this->loadModuleDescriptor();
        $this->loadModuleDefinition();
    }

    protected function trans(?string $id, array $parameters = []): string
    {
        if (!$this->translator instanceof Translator) {
            try {
                $this->translator = Translator::getInstance();
            } catch (\RuntimeException) {
                return strtr($id, $parameters);
            }
        }

        return $this->translator->trans($id, $parameters);
    }

    /**
     * Validate a module, checks :.
     *
     * - version of Thelia
     * - modules dependencies
     *
     * @param bool $checkCurrentVersion if true it will also check if the module is
     *                                  already installed (not activated - present in module list)
     *
     * @throws \Exception
     */
    public function validate(bool $checkCurrentVersion = true): void
    {
        if (!$this->moduleDescriptor instanceof \SimpleXMLElement) {
            throw new \Exception($this->trans('The %name module definition has not been initialized.', ['%name' => $this->moduleDirName]));
        }

        $this->checkVersion();

        if ($checkCurrentVersion) {
            $this->checkModuleVersion();
        }

        $this->checkModuleDependencies();
        $this->checkModulePropelSchema();
    }

    /**
     * @throws FileNotFoundException
     */
    protected function checkDirectoryStructure(): void
    {
        if (false === file_exists($this->modulePath)) {
            throw new FileNotFoundException($this->trans("Module %name directory doesn't exists.", ['%name' => $this->moduleDirName]));
        }

        $path = \sprintf('%s/Config/module.xml', $this->modulePath);

        if (false === file_exists($path)) {
            throw new FileNotFoundException($this->trans('Module %name should have a module.xml in the Config directory.', ['%name' => $this->moduleDirName]));
        }

        $path = \sprintf('%s/Config/config.xml', $this->modulePath);

        if (false === file_exists($path)) {
            throw new FileNotFoundException($this->trans('Module %name should have a config.xml in the Config directory.', ['%name' => $this->moduleDirName]));
        }
    }

    protected function loadModuleDescriptor(): void
    {
        $path = \sprintf('%s/Config/module.xml', $this->modulePath);

        $descriptorValidator = new ModuleDescriptorValidator();

        // validation with xsd
        $this->moduleDescriptor = $descriptorValidator->getDescriptor($path);
        $this->moduleVersion = (string) $descriptorValidator->getModuleVersion();
    }

    /**
     * @throws \Exception
     */
    public function loadModuleDefinition(): void
    {
        if (!$this->moduleDescriptor instanceof \SimpleXMLElement) {
            throw new \Exception($this->trans('The %name module descriptor has not been initialized.', ['%name' => $this->moduleDirName]));
        }

        $moduleDefinition = new ModuleDefinition();

        // Try to guess the proper module name, using the descriptor information.
        $fullnamespace = trim((string) $this->moduleDescriptor->fullnamespace);

        $namespaceComponents = explode('\\', $fullnamespace);

        if (empty($namespaceComponents[0])) {
            throw new ModuleException($this->trans("Unable to get module code from the fullnamespace element of the module descriptor: '%val'", ['%name' => $this->moduleDirName, '%val' => $fullnamespace]));
        }

        // Assume the module code is the first component of the declared namespace
        $moduleDefinition->setCode($namespaceComponents[0]);
        $moduleDefinition->setNamespace($fullnamespace);
        $moduleDefinition->setVersion((string) $this->moduleDescriptor->version);

        $this->getModuleLanguages($moduleDefinition);

        $this->getModuleDescriptives($moduleDefinition);

        $this->getModuleDependencies($moduleDefinition);

        $this->getModuleAuthors($moduleDefinition);

        $moduleDefinition->setLogo((string) $this->moduleDescriptor->logo);
        $moduleDefinition->setTheliaVersion((string) $this->moduleDescriptor->thelia);
        $moduleDefinition->setType((string) $this->moduleDescriptor->type);
        $moduleDefinition->setStability((string) $this->moduleDescriptor->stability);

        // documentation
        $moduleDefinition->setDocumentation((string) $this->moduleDescriptor->documentation);

        $this->moduleDefinition = $moduleDefinition;
    }

    public function checkModulePropelSchema(): void
    {
        $schemaFile = $this->getModulePath().DS.'Config'.DS.'schema.xml';
        $fs = new Filesystem();

        if (false === $fs->exists($schemaFile)) {
            return;
        }

        if (preg_match('/<behavior.*name="versionable".*\/>/s', (string) preg_replace('/<!--(.|\s)*?-->/', '', file_get_contents($schemaFile)))) {
            throw new ModuleException('On Thelia version >= 2.4.0 the behavior "versionnable" is not available for modules, please remove this behavior from your module schema.');
        }
    }

    protected function checkVersion(): void
    {
        if ($this->moduleDefinition->getTheliaVersion()
            && !Version::test(TheliaKernel::THELIA_VERSION, $this->moduleDefinition->getTheliaVersion(), false, '>=')) {
            throw new ModuleException($this->trans('The module %name requires Thelia %version or newer', ['%name' => $this->moduleDirName, '%version' => $this->moduleDefinition->getTheliaVersion()]));
        }
    }

    protected function checkModuleVersion(): void
    {
        $module = ModuleQuery::create()
            ->findOneByFullNamespace($this->moduleDefinition->getNamespace());

        if ((null !== $module) && version_compare($module->getVersion(), $this->moduleDefinition->getVersion(), '>=')) {
            throw new ModuleException($this->trans('The module %name is already installed in the same or greater version.', ['%name' => $this->moduleDirName]));
        }
    }

    protected function checkModuleDependencies(): void
    {
        $errors = [];

        foreach ($this->moduleDefinition->getDependencies() as $dependency) {
            $module = ModuleQuery::create()
                ->findOneByCode($dependency[0]);

            $pass = false;

            if (null !== $module && BaseModule::IS_ACTIVATED === $module->getActivate() && ('' === $dependency[1] || Version::test($module->getVersion(), $dependency[1], false, '>='))) {
                $pass = true;
            }

            if (false === $pass) {
                if ('' !== $dependency[1]) {
                    $errors[] = $this->trans(
                        '%module (version: %version)',
                        [
                            '%module' => $dependency[0],
                            '%version' => $dependency[1],
                        ],
                    );
                } else {
                    $errors[] = \sprintf('%s', $dependency[0]);
                }
            }
        }

        if ([] !== $errors) {
            $errorsMessage = $this->trans(
                'To activate module %name, the following modules should be activated first: %modules',
                ['%name' => $this->moduleDirName, '%modules' => implode(', ', $errors)],
            );

            throw new ModuleException($errorsMessage);
        }
    }

    /**
     * Get an array of modules that depend of the current module.
     *
     * @param bool|null $active if true only search in activated module, false only deactivated and null on all modules
     *
     * @return array array of array with `code` which is the module code that depends of this current module and
     *               `version` which is the required version of current module
     */
    public function getModulesDependOf(?bool $active = true): array
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
                $validator = new self($module->getAbsoluteBaseDir());

                $definition = $validator->getModuleDefinition();
                $dependencies = $definition->getDependencies();

                foreach ($dependencies as $dependency) {
                    if ($dependency[0] === $code) {
                        $dependantModules[] = [
                            'code' => $definition->getCode(),
                            'version' => $dependency[1],
                        ];

                        break;
                    }
                }
            } catch (\Exception) {
            }
        }

        return $dependantModules;
    }

    /**
     * Get the dependencies of this module.
     *
     * @param bool $recursive Whether to also get the dependencies of dependencies, their dependencies, and so on...
     *
     * @return array Array of dependencies as ["code" => ..., "version" => ...]. No check for duplicates is made.
     *
     * @throws FileNotFoundException
     */
    public function getCurrentModuleDependencies(bool $recursive = false): array
    {
        if (empty($this->moduleDescriptor->required)) {
            return [];
        }

        $dependencies = [];

        foreach ($this->moduleDescriptor->required->module as $dependency) {
            $dependencyArray = [
                'code' => (string) $dependency,
                'version' => (string) $dependency['version'],
            ];

            if (!\in_array($dependencyArray, $dependencies, true)) {
                $dependencies[] = $dependencyArray;
            }

            if ($recursive) {
                $recursiveModuleValidator = new self(THELIA_MODULE_DIR.'/'.$dependency);
                array_merge(
                    $dependencies,
                    $recursiveModuleValidator->getCurrentModuleDependencies(true),
                );
            }
        }

        return $dependencies;
    }

    protected function getModuleLanguages(ModuleDefinition $moduleDefinition): void
    {
        $languages = [];

        if ('1' !== $this->getModuleVersion()) {
            foreach ($this->moduleDescriptor->languages->language as $language) {
                $languages[] = (string) $language;
            }
        }

        $moduleDefinition->setLanguages($languages);
    }

    protected function getModuleDescriptives(ModuleDefinition $moduleDefinition): void
    {
        $descriptives = [];

        foreach ($this->moduleDescriptor->descriptive as $descriptive) {
            $descriptives[(string) $descriptive['locale']] = [
                'title' => (string) $descriptive->title,
                'subtitle' => (string) $descriptive->subtitle,
                'description' => (string) $descriptive->description,
                'postscriptum' => (string) $descriptive->postscriptum,
            ];
        }

        $moduleDefinition->setDescriptives($descriptives);
    }

    protected function getModuleDependencies(ModuleDefinition $moduleDefinition): void
    {
        $dependencies = [];

        if (is_countable($this->moduleDescriptor->required) && 0 !== \count($this->moduleDescriptor->required)) {
            foreach ($this->moduleDescriptor->required->module as $dependency) {
                $dependencies[] = [
                    (string) $dependency,
                    (string) $dependency['version'],
                ];
            }
        }

        $moduleDefinition->setDependencies($dependencies);
    }

    protected function getModuleAuthors(ModuleDefinition $moduleDefinition): void
    {
        $authors = [];

        if (is_countable($this->moduleDescriptor->author) && 0 !== \count($this->moduleDescriptor->author)) {
            foreach ($this->moduleDescriptor->author as $author) {
                $authors[] = [
                    (string) $author->name,
                    (string) $author->company,
                    (string) $author->email,
                    (string) $author->website,
                ];
            }
        } else {
            $authors = $this->getModuleAuthors22($moduleDefinition);
        }

        $moduleDefinition->setAuthors($authors);
    }

    protected function getModuleAuthors22(ModuleDefinition $moduleDefinition): array
    {
        $authors = [];

        if (!is_countable($this->moduleDescriptor->authors->author)
        || 0 === \count($this->moduleDescriptor->authors->author)
        ) {
            return $authors;
        }

        foreach ($this->moduleDescriptor->authors->author as $author) {
            $authors[] = [
                (string) $author->name,
                (string) $author->company,
                (string) $author->email,
                (string) $author->website,
            ];
        }

        return $authors;
    }

    public function setModulePath(?string $modulePath): void
    {
        $this->modulePath = $modulePath;
    }

    public function getModulePath(): ?string
    {
        return $this->modulePath;
    }

    public function getModuleDescriptor(): \SimpleXMLElement|ModuleDescriptorValidator|null
    {
        return $this->moduleDescriptor;
    }

    public function getModuleDefinition(): ?ModuleDefinition
    {
        return $this->moduleDefinition;
    }

    public function getModuleVersion(): ?string
    {
        return $this->moduleVersion;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
