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

use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Symfony\Component\Validator\Constraints;

/**
 * Class CacheForm
 * @package Thelia\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CacheConfigurationForm extends BaseForm {

    protected function buildForm()
    {
        $this->formBuilder
            ->add("enabled", "checkbox", array(
                "label" => Translator::getInstance()->trans('Enabled.'),
                "label_attr" => array(
                    "for" => "enabled"
                ),
                "required" => false
            ))
            ->add(
                'driver',
                "choice",
                array(
                    "choices" => $this->getDriverList(),
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => Translator::getInstance()->trans('Driver'),
                    "label_attr" => array(
                        "for" => "driver"
                    ),
                )
            )
            ->add(
                'lifetime',
                "text",
                array(
                    "label" => Translator::getInstance()->trans('Life Time'),
                    "label_attr" => array(
                        "for" => "lifetime"
                    ),
                )
            )
            ->add(
                'namespace',
                "text",
                array(
                    "label" => Translator::getInstance()->trans('Namespace'),
                    "label_attr" => array(
                        "for" => "namespace"
                    ),
                )
            )
            // File
            ->add(
                'file_directory',
                "text",
                array(
                    "label" => Translator::getInstance()->trans('Directory'),
                    "label_attr" => array(
                        "for" => "file_directory"
                    ),
                )
            )
            // Memcached
            ->add(
                'memcached_server',
                "text",
                array(
                    "label" => Translator::getInstance()->trans('Server IP'),
                    "label_attr" => array(
                        "for" => "memcached_server"
                    ),
                )
            )
            ->add(
                'memcached_port',
                "text",
                array(
                    "label" => Translator::getInstance()->trans('Server port'),
                    "label_attr" => array(
                        "for" => "memcached_port"
                    ),
                )
            )
        ;
    }

    public function getEvent()
    {

    }

    protected function getDriverList()
    {
        $drivers = array();

        $drivers["\Thelia\Cache\Driver\NullDriver"] = Translator::getInstance()->trans('No cache');
        $drivers["\Thelia\Cache\Driver\ArrayDriver"] = Translator::getInstance()->trans('PHP Array cache (not persistent)');
        $drivers["\Thelia\Cache\Driver\FileDriver"] = Translator::getInstance()->trans('File system');
        $drivers["\Thelia\Cache\Driver\MemcachedDriver"] = Translator::getInstance()->trans('Memcached');

        return $drivers;
    }

    public function verifyDriver($value, ExecutionContextInterface $context)
    {

    }

    public function getName()
    {
        return "thelia_admin_cache";
    }

} 