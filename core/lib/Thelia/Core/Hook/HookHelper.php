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

namespace Thelia\Core\Hook;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;


/**
 * Class HookHelper
 * @package Thelia\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookHelper {

    private static $instance = null;

    private function __construct() {}

    public static function getInstance()
    {
        if (self::$instance == null) self::$instance = new HookHelper();
        return self::$instance;
    }

    public function parseActiveTemplate($templateType=TemplateDefinition::FRONT_OFFICE)
    {
        switch ($templateType) {
            case TemplateDefinition::FRONT_OFFICE:
                $tplVar = 'active-front-template';
                break;
            case TemplateDefinition::BACK_OFFICE:
                $tplVar = 'active-admin-template';
                break;
            case TemplateDefinition::PDF:
                $tplVar = 'active-pdf-template';
                break;
            case TemplateDefinition::EMAIL:
                $tplVar = 'active-mail-template';
                break;
        }

        return $this->parseTemplate($templateType, ConfigQuery::read($tplVar, 'default'));
    }


    public function parseTemplate($templateType, $template)
    {
        $templateDefinition = new TemplateDefinition($template, $templateType);



        return null;
    }


    /**
     * Recursively examine files in a directory tree, and extract translatable strings.
     *
     * Returns an array of translatable strings, each item having with the following structure:
     * 'files' an array of file names in which the string appears,
     * 'text' the translatable text
     * 'translation' => the text translation, or an empty string if none available.
     * 'dollar'  => true if the translatable text contains a $
     *
     * @param  string                              $directory     the path to the directory to examine
     * @param  string                              $walkMode      type of file scanning: WALK_MODE_PHP or WALK_MODE_TEMPLATE
     * @param  \Thelia\Core\Translation\Translator $translator    the current translator
     * @param  string                              $currentLocale the current locale
     * @param  string                              $domain        the translation domain (fontoffice, backoffice, module, etc...)
     * @param  array                               $strings       the list of strings
     * @throws \InvalidArgumentException           if $walkMode contains an invalid value
     * @return number                              the total number of translatable texts
     */
    public function walkDir($directory, &$hooks)
    {

        $allowed_exts = array('html', 'tpl', 'xml');

        try {

            Tlog::getInstance()->debug("Walking in $directory");

            /** @var \DirectoryIterator $fileInfo */
            foreach (new \DirectoryIterator($directory) as $fileInfo) {

                if ($fileInfo->isDot()) continue;

                if ($fileInfo->isDir()) {
                    $this->walkDir($fileInfo->getPathName(), $hooks);
                }

                if ($fileInfo->isFile()) {

                    $ext = $fileInfo->getExtension();

                    if (in_array($ext, $allowed_exts)) {

                        if ($content = file_get_contents($fileInfo->getPathName())) {

                            $short_path = $this->normalizePath($fileInfo->getPathName());

                            Tlog::getInstance()->debug("Examining file $short_path\n");

                            $hooksTemplate = $this->parseSmartyTemplate($content);

                            foreach ($hooksTemplate as $function){
                                try{
                                    $hooks[] = $this->sanitizeHook($function);
                                } catch (\UnexpectedValueException $ex){
                                    Tlog::getInstance()->warn($ex->getMessage());
                                }
                            }
                        }
                    }
                }
            }

        } catch (\UnexpectedValueException $ex) {
            // Directory does not exists => ignore/
        }
    }

    protected function sanitizeHook($hook){

        $ret = array();
        $ret['block'] = ($hook['name'] !== 'hook');
        $attributes = $hook['attributes'];
        if ( array_key_exists("name", $attributes) ){
            $ret['code'] = $attributes['name'];
            $params = explode(".", $attributes['name']);
            if (count($params != 1)){
                throw new \UnexpectedValueException("hook name should contain a . : " . $attributes['name']);
            }
            $ret['context'] = $params[0];
            $ret['type'] = $params[1];
            $ret['module'] = array_key_exists("module", $attributes);
        } else {
            throw new \UnexpectedValueException("The hook should have a name attribute");
        }
        return $ret;
    }


    protected function parseSmartyTemplate($content)
    {
        $strlen = strlen( $content );

        // init
        $buffer          = '';
        $name            = '';
        $attributeName   = '';
        $attributeValue  = '';
        $waitfor         = '';

        $inFunction      = false;
        $hasName         = false;
        $inAttribute     = false;
        $inInnerFunction = false;

        $ldelim          = '{';
        $rdelim          = '}';
        $functions       = array('hook', 'hookBlock');
        $characters      = array("\t", "\r", "\n");

        $store           = array();
        $attributes      = array();

        for( $pos = 0; $pos < $strlen; $pos++ ) {
            $char = $content[$pos];

            if (in_array($char, $characters))
                continue;

            // function
            if ( ! $inFunction){
                if ($char === $ldelim){
                    $inFunction = true;
                    $inInnerFunction = false;
                }
                continue;
            }

            // get function name
            if ( ! $hasName){
                if ($char === " " || $char === $rdelim){
                    $name = $buffer;
                    // we catch this name ?
                    $hasName = $inFunction = (in_array($name, $functions));
                    $buffer = "";
                    continue;
                } else {
                    // skip {
                    if (in_array($char, array("/", "$", "'", "\""))){
                        $inFunction = false;
                    } else {
                        $buffer .= $char;
                    }
                    continue;
                }
            }

            // inner Function ?
            if ($char === $ldelim){
                $inInnerFunction = true;
                $buffer .= $char;
                continue;
            }

            // end ?
            if ($char === $rdelim){
                if ($inInnerFunction){
                    $inInnerFunction = false;
                    $buffer .= $char;
                } else {
                    if ($inAttribute) {
                        if ("" === $attributeName){
                            $attributes[trim($buffer)] = "";
                        }else {
                            $attributes[$attributeName] = $buffer;
                        }
                        $inAttribute = false;
                    }
                    $store[] = array(
                        "name" => $name,
                        "attributes" => $attributes
                    );
                    $inFunction  = false;
                    $inAttribute = false;
                    $inInnerFunction = false;
                    $hasName = false;
                    $name = "";
                    $buffer = "";
                    $waitfor = "";
                    $attributes = array();
                    #debug("function : " . $name);
                }
                continue;
            }

            // attributes
            if ( ! $inAttribute) {
                if ($char !== " "){
                    $inAttribute = true;
                    $buffer = $char;
                    $attributeName = "";
                }
            } else {
                if ("" === $attributeName){
                    if (in_array($char, array(" ", "="))){
                        $attributeName = trim($buffer);
                        if (" " === $char){
                            $attributes[$attributeName] = "";
                            $inAttribute = false;
                        }
                        $buffer = "";
                    } else {
                        $buffer .= $char;
                    }
                } else {
                    if ("" === $waitfor){
                        if (in_array($char, array("'", "\""))){
                            $waitfor = $char;
                        } else {
                            $waitfor = " ";
                            $buffer .= $char;
                        }
                        continue;
                    }
                    if ($inInnerFunction){
                        $buffer .= $char;
                    } else {
                        // end of attribute ?
                        if ($char === $waitfor) {
                            $attributes[$attributeName] = $buffer;
                            $inAttribute = false;
                            $waitfor = "";
                        } else {
                            $buffer .= $char;
                        }
                    }
                }
            }
        }

        return $store;
    }

} 