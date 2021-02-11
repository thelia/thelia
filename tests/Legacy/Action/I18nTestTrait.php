<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Action;

use Thelia\Model\LangQuery;

/**
 * Class I18NTestTrait
 * @package Thelia\Tests\Action
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
trait I18nTestTrait
{
    /** @var array list of available locale */
    protected static $localeList = null;

    /**
     * populate a list of field for each locale for an object
     *
     * @param mixed $object     the object to populate
     * @param array $fields     list of field to populate
     * @param array $localeList list of locale to use populate the object
     */
    protected function setI18n(&$object, $fields = ["Title"], $localeList = null)
    {
        if (null === $localeList) {
            if (null === self::$localeList) {
                self::$localeList = LangQuery::create()
                    ->select("Locale")
                    ->find()
                    ->toArray();
            }

            $localeList = self::$localeList;
        }

        foreach ($localeList as $locale) {
            foreach ($fields as $name) {
                $object->getTranslation($locale)->setByName($name, $locale . ' : ' . $name);
            }
        }
    }
}
