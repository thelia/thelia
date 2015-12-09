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

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Cache\Driver\BaseCacheDriver;
use Thelia\Cache\Driver\FileDriver;
use Thelia\Cache\Driver\MemcachedDriver;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

/**
 * Class CacheForm
 * @package Thelia\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CacheConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("enabled", "checkbox", [
                "data" => boolval(ConfigQuery::read(
                    BaseCacheDriver::CONFIG_ENABLED,
                    false
                )),
                "label" => Translator::getInstance()->trans('Enabled.'),
                "label_attr" => [
                    "for" => "enabled"
                ],
                "required" => false
            ])
            ->add(
                'driver',
                "choice",
                [
                    "choices" => $this->getDriverList(),
                    "data" => ConfigQuery::read(
                        BaseCacheDriver::CONFIG_DRIVER,
                        BaseCacheDriver::DEFAULT_DRIVER
                    ),
                    "constraints" => [
                        new Constraints\NotBlank(),
                    ],
                    "label" => Translator::getInstance()->trans('Driver'),
                    "label_attr" => [
                        "for" => "driver"
                    ],
                    "required" => true
                ]
            )
            ->add(
                'lifetime',
                "text",
                [
                    "data" => intval(ConfigQuery::read(
                        BaseCacheDriver::CONFIG_LIFE_TIME,
                        BaseCacheDriver::DEFAULT_LIFE_TIME
                    )),
                    "label" => Translator::getInstance()->trans('Life Time (in seconds)'),
                    "label_attr" => [
                        "for" => "lifetime"
                    ],
                ]
            )
            ->add(
                'namespace',
                "text",
                [
                    "data" => ConfigQuery::read(
                        BaseCacheDriver::CONFIG_NAMESPACE,
                        BaseCacheDriver::DEFAULT_NAMESPACE
                    ),
                    "label" => Translator::getInstance()->trans('Namespace'),
                    "label_attr" => [
                        "for" => "namespace"
                    ],
                    "required" => false
                ]
            )
            // File
            ->add(
                'file_directory',
                "text",
                [
                    "data" => ConfigQuery::read(
                        FileDriver::CONFIG_DIRECTORY,
                        ""
                    ),
                    "label" => Translator::getInstance()->trans('Directory'),
                    "label_attr" => [
                        "for" => "file_directory"
                    ],
                    "required" => false
                ]
            )
            // Memcached
            ->add(
                'memcached_server',
                "text",
                [
                    "data" => ConfigQuery::read(
                        MemcachedDriver::CONFIG_SERVER,
                        MemcachedDriver::DEFAULT_SERVER
                    ),
                    "label" => Translator::getInstance()->trans('Server IP'),
                    "label_attr" => [
                        "for" => "memcached_server"
                    ],
                    "required" => false
                ]
            )
            ->add(
                'memcached_port',
                "text",
                [
                    "data" => ConfigQuery::read(
                        MemcachedDriver::CONFIG_PORT,
                        MemcachedDriver::DEFAULT_PORT
                    ),
                    "label" => Translator::getInstance()->trans('Server port'),
                    "label_attr" => [
                        "for" => "memcached_port"
                    ],
                    "required" => false
                ]
            );
    }

    public function getEvent()
    {
    }

    protected function getDriverList()
    {
        $drivers = [];

        $drivers["null"] = Translator::getInstance()->trans('No cache');
        $drivers["array"] = Translator::getInstance()->trans('PHP Array cache (not persistent)');
        $drivers["file"] = Translator::getInstance()->trans('File system');
        $drivers["memcached"] = Translator::getInstance()->trans('Memcached');

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
