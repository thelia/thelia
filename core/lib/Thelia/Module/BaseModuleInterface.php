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

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Exception\ModuleException;
use Thelia\Model\Module;

interface BaseModuleInterface
{
    /**
     * This method is called when the plugin is installed for the first time.
     */
    public function install(?ConnectionInterface $con = null);

    /**
     * This method is called when a newer version of the plugin is installed.
     *
     * @param string $currentVersion the current (installed) module version, as defined in the module.xml file
     * @param string $newVersion     the new module version, as defined in the module.xml file
     */
    public function update(string $currentVersion, string $newVersion, ?ConnectionInterface $con = null);

    /**
     * This method is called just before the deletion of the module, giving the module an opportunity
     * to delete its data.
     *
     * @param bool $deleteModuleData if true, the module should remove all its data from the system
     */
    public function destroy(?ConnectionInterface $con = null, bool $deleteModuleData = false);

    /**
     * This method is called when the module is activated.
     *
     * @param Module $moduleModel the module
     */
    public function activate(?Module $moduleModel = null);

    /**
     * This method is called when the module is deactivated.
     *
     * @param Module $moduleModel the module
     */
    public function deActivate(?Module $moduleModel = null);

    /**
     * This method is called before the module activation, and may prevent it by returning false.
     *
     * @return bool true to continue module activation, false to prevent it
     */
    public function preActivation(?ConnectionInterface $con = null): bool;

    /**
     * This method is called just after the module was successfully activated.
     */
    public function postActivation(?ConnectionInterface $con = null);

    /**
     * This method is called before the module de-activation, and may prevent it by returning false.
     *
     * @return bool true to continue module de-activation, false to prevent it
     */
    public function preDeactivation(?ConnectionInterface $con = null): bool;

    /**
     * This method is called just after the module was successfully deactivated.
     */
    public function postDeactivation(?ConnectionInterface $con = null);

    /**
     * Sets a module titles for various languages.
     *
     * @param Module $module the module
     * @param array  $titles an associative array of locale => title_string
     */
    public function setTitle(Module $module, array $titles);

    /**
     * Get a module's configuration variable.
     *
     * @param string $variableName the variable name
     * @param string $defaultValue the default value, if variable is not defined
     * @param null   $valueLocale  the required locale, or null to get default one
     *
     * @return string the variable value
     */
    public static function getConfigValue(string $variableName, ?string $defaultValue = null, $valueLocale = null): string;

    /**
     * Set module configuration variable, creating it if required.
     *
     * @param string $variableName      the variable name
     * @param string $variableValue     the variable value
     * @param null   $valueLocale       the locale, or null if not required
     * @param bool   $createIfNotExists if true, the variable will be created if not already defined
     *
     * @return $this;
     *
     * @throws \LogicException if variable does not exists and $createIfNotExists is false
     */
    public static function setConfigValue(
        string $variableName,
        string $variableValue,
        $valueLocale = null,
        bool $createIfNotExists = true,
    );

    /**
     * Ensure the proper deployment of the module's images.
     *
     * TODO : this method does not take care of internationalization. This is a bug.
     *
     * @param Module $module     the module
     * @param string $folderPath the image folder path
     *
     * @throws ModuleException
     * @throws \Exception
     * @throws \UnexpectedValueException
     */
    public function deployImageFolder(Module $module, string $folderPath, ?ConnectionInterface $con = null);

    /**
     * @throws ModuleException
     */
    public function getModuleModel(): Module;

    /**
     * @return string The module id
     */
    public static function getModuleId(): int;

    /**
     * @return string The module code, in a static way
     */
    public static function getModuleCode(): string;

    /**
     * @return string The module code
     */
    public function getCode(): string;

    /**
     * This method adds new compilers to Thelia container.
     *
     * You must return an array. This array can contain :
     *  - arrays
     *  - one or many instance(s) of \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface
     *
     * in the first case, your array must contains 2 indexes.
     * The first is the compiler instance and the second the compilerPass type.
     * Example :
     * return array(
     *  array(
     *    new \MyModule\DependencyInjection\Compiler\MySuperCompilerPass(),
     *    \Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_OPTIMIZATION
     *  )
     * );
     *
     * In the seconde case, just an instance of CompilerPassInterface.
     * Example :
     * return array (
     *  new \MyModule\DependencyInjection\Compiler\MySuperCompilerPass()
     * );
     *
     * But you can combine both behaviors
     * Example :
     *
     * return array(
     *  new \MyModule\DependencyInjection\Compiler\MySuperCompilerPass(),
     *  array(
     *      new \MyModule\DependencyInjection\Compiler\MyOtherSuperCompilerPass(),
     *      Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_OPTIMIZATION
     *  )
     * );
     */
    public static function getCompilers();

    /**
     * Called on Thelia container configurator to allow module to add their configuration.
     */
    public static function configureContainer(ContainerConfigurator $containerConfigurator);

    /**
     * Called on Thelia services configurator to allow module to add their configuration.
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator);

    /**
     * Allow modules to add their configuration to the container.
     */
    public static function loadConfiguration(ContainerBuilder $containerBuilder);

    /**
     * Allow modules to add a prefix to all their annotated routes.
     */
    public static function getAnnotationRoutePrefix();

    /**
     * @return array
     *
     * This method must be used when your module defines hooks.
     * Override this and return an array of your hooks names to register them
     *
     * This returned value must be like the example, only type and code are mandatory
     *
     * Example:
     *
     * return array(
     *
     *      // Only register the title in the default language
     *      array(
     *          "type" => TemplateDefinition::BACK_OFFICE,
     *          "code" => "my_super_hook_name",
     *          "title" => "My hook",
     *          "description" => "My hook is really, really great",
     *      ),
     *
     *      // Manage i18n
     *      array(
     *          "type" => TemplateDefinition::FRONT_OFFICE,
     *          "code" => "my_hook_name",
     *          "title" => array(
     *              "fr_FR" => "Mon Hook",
     *              "en_US" => "My hook",
     *          ),
     *          "description" => array(
     *              "fr_FR" => "Mon hook est vraiment super",
     *              "en_US" => "My hook is really, really great",
     *          ),
     *          "chapo" => array(
     *              "fr_FR" => "Mon hook est vraiment super",
     *              "en_US" => "My hook is really, really great",
     *          ),
     *          "block" => true,
     *          "active" => true
     *      )
     * );
     */
    public function getHooks(): array;

    /**
     * Create or update module hooks returned by the `getHooks` function.
     */
    public function registerHooks();
}
