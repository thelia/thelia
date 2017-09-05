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

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Module;

interface BaseModuleInterface
{
    /**
     * This method is called when the plugin is installed for the first time
     *
     * @param ConnectionInterface $con
     */
    public function install(ConnectionInterface $con = null);

    /**
     * This method is called when a newer version of the plugin is installed
     *
     * @param string $currentVersion the current (installed) module version, as defined in the module.xml file
     * @param string $newVersion the new module version, as defined in the module.xml file
     * @param ConnectionInterface $con
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null);


    /**
     * This method is called just before the deletion of the module, giving the module an opportunity
     * to delete its data.
     *
     * @param ConnectionInterface $con
     * @param bool $deleteModuleData if true, the module should remove all its data from the system.
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false);

    /**
     * This method is called when the module is activated
     *
     * @param Module $moduleModel the module
     */
    public function activate($moduleModel = null);

    /**
     * This method is called when the module is deactivated
     *
     * @param Module $moduleModel the module
     */
    public function deActivate($moduleModel = null);


    /**
     * This method is called before the module activation, and may prevent it by returning false.
     *
     * @param ConnectionInterface $con
     *
     * @return bool true to continue module activation, false to prevent it.
     */
    public function preActivation(ConnectionInterface $con = null);

    /**
     * This method is called just after the module was successfully activated.
     *
     * @param ConnectionInterface $con
     */
    public function postActivation(ConnectionInterface $con = null);

    /**
     * This method is called before the module de-activation, and may prevent it by returning false.
     *
     * @param  ConnectionInterface $con
     * @return bool                true to continue module de-activation, false to prevent it.
     */
    public function preDeactivation(ConnectionInterface $con = null);

    /**
     * This method is called just after the module was successfully deactivated.
     *
     * @param ConnectionInterface $con
     */
    public function postDeactivation(ConnectionInterface $con = null);


    /**
     * Sets a module titles for various languages
     *
     * @param Module $module the module.
     * @param array $titles an associative array of locale => title_string
     */
    public function setTitle(Module $module, $titles);

    /**
     * Get a module's configuration variable
     *
     * @param  string $variableName the variable name
     * @param  string $defaultValue the default value, if variable is not defined
     * @param  null $valueLocale the required locale, or null to get default one
     * @return string the variable value
     */
    public static function getConfigValue($variableName, $defaultValue = null, $valueLocale = null);

    /**
     * Set module configuration variable, creating it if required
     *
     * @param  string $variableName the variable name
     * @param  string $variableValue the variable value
     * @param  null $valueLocale the locale, or null if not required
     * @param  bool $createIfNotExists if true, the variable will be created if not already defined
     * @throws \LogicException if variable does not exists and $createIfNotExists is false
     * @return $this;
     */
    public static function setConfigValue(
        $variableName,
        $variableValue,
        $valueLocale = null,
        $createIfNotExists = true
    );

    /**
     * Ensure the proper deployment of the module's images.
     *
     * TODO : this method does not take care of internationalization. This is a bug.
     *
     * @param Module $module the module
     * @param string $folderPath the image folder path
     * @param ConnectionInterface $con
     *
     * @throws \Thelia\Exception\ModuleException
     * @throws \Exception
     * @throws \UnexpectedValueException
     */
    public function deployImageFolder(Module $module, $folderPath, ConnectionInterface $con = null);

    /**
     * @return Module
     * @throws \Thelia\Exception\ModuleException
     */
    public function getModuleModel();

    /**
     * @return string The module id
     */
    public static function getModuleId();

    /**
     * @return string The module code, in a static way
     */
    public static function getModuleCode();

    /**
     * @return string The module code
     */
    public function getCode();

    /**
     *
     * This method adds new compilers to Thelia container
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
     *
     */
    public static function getCompilers();


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
    public function getHooks();

    /**
     * Create or update module hooks returned by the `getHooks` function
     */
    public function registerHooks();
}
