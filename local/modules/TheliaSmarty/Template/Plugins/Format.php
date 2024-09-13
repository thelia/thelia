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

namespace TheliaSmarty\Template\Plugins;

use CommerceGuys\Addressing\Address;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\AddressQuery;
use Thelia\Model\OrderAddressQuery;
use Thelia\Tools\AddressFormat;
use Thelia\Tools\DateTimeFormat;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\NumberFormat;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\Exception\SmartyPluginException;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * format_date and format_date smarty function.
 *
 * Class Format
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Benjamin Perche <benjamin@thelia.net>
 */
class Format extends AbstractSmartyPlugin
{
    private static $dateKeys = ['day', 'month', 'year'];
    private static $timeKeys = ['hour', 'minute', 'second'];

    /** @var RequestStack */
    protected $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * return date in expected format.
     *
     * available parameters :
     *  date => DateTime object (mandatory)
     *  format => expected format
     *  output => list of default system format. Values available :
     *      date => date format
     *      time => time format
     *      datetime => datetime format (default)
     *
     * ex :
     *  {format_date date=$dateTimeObject format="Y-m-d H:i:s"} will output the format with specific format
     *  {format_date date=$dateTimeObject format="l F j" locale="fr_FR"} will output the format with specific format (see date() function)
     *  {format_date date=$dateTimeObject output="date"} will output the date using the default date system format
     *  {format_date date=$dateTimeObject} will output with the default datetime system format
     *
     * @param array $params
     * @param null  $template
     *
     * @throws \TheliaSmarty\Template\Exception\SmartyPluginException
     *
     * @return string
     */
    public function formatDate($params, $template = null)
    {
        $date = $this->getParam($params, 'date', false);

        if ($date === false) {
            // Check if we have a timestamp
            $timestamp = $this->getParam($params, 'timestamp', false);

            if ($timestamp === false) {
                // No timestamp => error
                throw new SmartyPluginException('Either date or timestamp is a mandatory parameter in format_date function');
            }
            $date = new \DateTime();
            $date->setTimestamp($timestamp);
        } elseif (\is_array($date)) {
            $keys = array_keys($date);

            $isDate = $this->arrayContains(static::$dateKeys, $keys);
            $isTime = $this->arrayContains(static::$timeKeys, $keys);

            // If this is not a date, fallback on today
            // If this is not a time, fallback on midnight
            $dateFormat = $isDate ? sprintf('%d-%d-%d', $date['year'], $date['month'], $date['day']) : (new \DateTime())->format('Y-m-d');
            $timeFormat = $isTime ? sprintf('%d:%d:%d', $date['hour'], $date['minute'], $date['second']) : '0:0:0';

            $date = new \DateTime(sprintf('%s %s', $dateFormat, $timeFormat));
        }

        if (!($date instanceof \DateTime)) {
            try {
                $date = new \DateTime($date);
            } catch (\Exception $e) {
                return '';
            }
        }

        $format = $this->getParam($params, 'format', false);

        if ($format === false) {
            $format = DateTimeFormat::getInstance($this->requestStack->getCurrentRequest())->getFormat($this->getParam($params, 'output', null));
        }

        $locale = $this->getParam($params, 'locale', false);

        if (false === $locale) {
            $value = $date->format($format);
        } else {
            $value = $this->formatDateWithLocale($date, $locale, $format);
        }

        return $value;
    }

    private function formatDateWithLocale(\DateTime $date, $locale, $format)
    {
        if (!str_contains($format, '%')) {
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);

            $icuFormat = $this->convertDatePhpToIcu($format);
            $formatter->setPattern($icuFormat);

            $localizedDate = $formatter->format($date);
        } else {
            // for backward compatibility
            if (\function_exists('setlocale')) {
                // Save the current locale
                $systemLocale = setlocale(\LC_TIME, 0);
                setlocale(\LC_TIME, $locale);
                $localizedDate = strftime($format, $date->getTimestamp());
                // Restore the locale
                setlocale(\LC_TIME, $systemLocale);
            } else {
                // setlocale() function not available => error
                throw new SmartyPluginException('The setlocale() function is not available on your system.');
            }
        }

        return $localizedDate;
    }

    /**
     * display numbers in expected format.
     *
     * available parameters :
     *  number => int or float number
     *  decimals => how many decimals format expected
     *  dec_point => separator for the decimal point
     *  thousands_sep => thousands separator
     *
     *  ex : {format_number number="1246.12" decimals="1" dec_point="," thousands_sep=" "} will output "1 246,1"
     *
     * @param null $template
     *
     * @throws \TheliaSmarty\Template\Exception\SmartyPluginException
     *
     * @return string the expected number formatted
     */
    public function formatNumber($params, $template = null)
    {
        $number = $this->getParam($params, 'number', false);

        if ($number === false || $number === '') {
            return '';
        }

        return NumberFormat::getInstance($this->requestStack->getCurrentRequest())->format(
            $number,
            $this->getParam($params, 'decimals', null),
            $this->getParam($params, 'dec_point', null),
            $this->getParam($params, 'thousands_sep', null)
        );
    }

    /**
     * display a amount in expected format.
     *
     * available parameters :
     *  number => int or float number
     *  decimals => how many decimals format expected
     *  dec_point => separator for the decimal point
     *  thousands_sep => thousands separator
     *  symbol => Currency symbol
     *  remove_zero_decimal => remove zero after the dec_point
     *
     *  ex : {format_money number="1246.12" decimals="1" dec_point="," thousands_sep=" " symbol="€"} will output "1 246,1 €"
     *  ex : {format_money number="1246.00" decimals="2" dec_point="," thousands_sep=" " symbol="€" remove_zero_decimal=true} will output "1 246 €"
     *
     * @param null $template
     *
     * @throws \TheliaSmarty\Template\Exception\SmartyPluginException
     *
     * @return string the expected number formatted
     */
    public function formatMoney($params, $template = null)
    {
        $number = $this->getParam($params, 'number', false);

        if ($number === false || $number === '') {
            return '';
        }

        if ($this->getParam($params, 'symbol', null) === null) {
            return MoneyFormat::getInstance($this->requestStack->getCurrentRequest())->formatByCurrency(
                $number,
                $this->getParam($params, 'decimals', null),
                $this->getParam($params, 'dec_point', null),
                $this->getParam($params, 'thousands_sep', null),
                $this->getParam($params, 'currency_id', null),
                $this->getParam($params, 'remove_zero_decimal', false)
            );
        }

        return MoneyFormat::getInstance($this->requestStack->getCurrentRequest())->format(
            $number,
            $this->getParam($params, 'decimals', null),
            $this->getParam($params, 'dec_point', null),
            $this->getParam($params, 'thousands_sep', null),
            $this->getParam($params, 'symbol', null),
            $this->getParam($params, 'remove_zero_decimal', false)
        );
    }

    /**
     * return two-dimensional arrays in string.
     *
     * available parameters :
     *  values => array 2D ['key A' => ['value 1', 'value 2'], 'key B' => ['value 3', 'value 4']]
     *  separators => ['key value separator', 'value value separator', 'key key separator']
     *
     * ex :
     *  {format_array_2d values=['Colors' => ['Green', 'Yellow', 'Red'], 'Material' => ['Wood']] separators=[' : ', ' / ', ' | ']}
     *  will output the format with specific format : "Colors : Green / Yellow / Red | Material : Wood"
     *
     * @return string
     */
    public function formatTwoDimensionalArray($params)
    {
        $output = '';
        $values = $this->getParam($params, 'values', null);
        $separators = $this->getParam($params, 'separators', [' : ', ' / ', ' | ']);

        if (!\is_array($values)) {
            return $output;
        }

        foreach ($values as $key => $value) {
            if ($output !== '') {
                $output .= $separators[2];
            }

            $output .= $key.$separators[0];

            if (!\is_array($value)) {
                $output .= $value;
                continue;
            }

            $output .= implode($separators[1], $value);
        }

        return $output;
    }

    protected function arrayContains(array $expected, array $hayStack)
    {
        foreach ($expected as $value) {
            if (!\in_array($value, $hayStack)) {
                return false;
            }
        }

        return true;
    }

    /**
     * This function comes from [Yii framework](http://www.yiiframework.com/).
     *
     * Converts a date format pattern from [php date() function format][] to [ICU format][].
     *
     * The conversion is limited to date patterns that do not use escaped characters.
     * Patterns like `jS \o\f F Y` which will result in a date like `1st of December 2014` may not be converted correctly
     * because of the use of escaped characters.
     *
     * Pattern constructs that are not supported by the ICU format will be removed.
     *
     * [php date() function format]: http://php.net/manual/en/function.date.php
     * [ICU format]: http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Time-Format-Syntax
     *
     * @param string $pattern date format pattern in php date()-function format
     *
     * @return string the converted date format pattern
     */
    protected function convertDatePhpToIcu($pattern)
    {
        // http://php.net/manual/en/function.date.php
        return strtr(
            $pattern,
            [
                // Day
                'd' => 'dd',    // Day of the month, 2 digits with leading zeros 	01 to 31
                'D' => 'eee',   // A textual representation of a day, three letters 	Mon through Sun
                'j' => 'd',     // Day of the month without leading zeros 	1 to 31
                'l' => 'eeee',  // A full textual representation of the day of the week 	Sunday through Saturday
                'N' => 'e',     // ISO-8601 numeric representation of the day of the week, 1 (for Monday) through 7 (for Sunday)
                'S' => '',      // English ordinal suffix for the day of the month, 2 characters 	st, nd, rd or th. Works well with j
                'w' => '',      // Numeric representation of the day of the week 	0 (for Sunday) through 6 (for Saturday)
                'z' => 'D',     // The day of the year (starting from 0) 	0 through 365
                // Week
                'W' => 'w',     // ISO-8601 week number of year, weeks starting on Monday (added in PHP 4.1.0) 	Example: 42 (the 42nd week in the year)
                // Month
                'F' => 'MMMM',  // A full textual representation of a month, January through December
                'm' => 'MM',    // Numeric representation of a month, with leading zeros 	01 through 12
                'M' => 'MMM',   // A short textual representation of a month, three letters 	Jan through Dec
                'n' => 'M',     // Numeric representation of a month, without leading zeros 	1 through 12, not supported by ICU but we fallback to "with leading zero"
                't' => '',      // Number of days in the given month 	28 through 31
                // Year
                'L' => '',      // Whether it's a leap year, 1 if it is a leap year, 0 otherwise.
                'o' => 'Y',     // ISO-8601 year number. This has the same value as Y, except that if the ISO week number (W) belongs to the previous or next year, that year is used instead.
                'Y' => 'yyyy',  // A full numeric representation of a year, 4 digits 	Examples: 1999 or 2003
                'y' => 'yy',    // A two digit representation of a year 	Examples: 99 or 03
                // Time
                'a' => 'a',     // Lowercase Ante meridiem and Post meridiem, am or pm
                'A' => 'a',     // Uppercase Ante meridiem and Post meridiem, AM or PM, not supported by ICU but we fallback to lowercase
                'B' => '',      // Swatch Internet time 	000 through 999
                'g' => 'h',     // 12-hour format of an hour without leading zeros 	1 through 12
                'G' => 'H',     // 24-hour format of an hour without leading zeros 0 to 23h
                'h' => 'hh',    // 12-hour format of an hour with leading zeros, 01 to 12 h
                'H' => 'HH',    // 24-hour format of an hour with leading zeros, 00 to 23 h
                'i' => 'mm',    // Minutes with leading zeros 	00 to 59
                's' => 'ss',    // Seconds, with leading zeros 	00 through 59
                'u' => '',      // Microseconds. Example: 654321
                // Timezone
                'e' => 'VV',    // Timezone identifier. Examples: UTC, GMT, Atlantic/Azores
                'I' => '',      // Whether or not the date is in daylight saving time, 1 if Daylight Saving Time, 0 otherwise.
                'O' => 'xx',    // Difference to Greenwich time (GMT) in hours, Example: +0200
                'P' => 'xxx',   // Difference to Greenwich time (GMT) with colon between hours and minutes, Example: +02:00
                'T' => 'zzz',   // Timezone abbreviation, Examples: EST, MDT ...
                'Z' => '',    // Timezone offset in seconds. The offset for timezones west of UTC is always negative, and for those east of UTC is always positive. -43200 through 50400
                // Full Date/Time
                'c' => 'yyyy-MM-dd\'T\'HH:mm:ssxxx', // ISO 8601 date, e.g. 2004-02-12T15:19:21+00:00
                'r' => 'eee, dd MMM yyyy HH:mm:ss xx', // RFC 2822 formatted date, Example: Thu, 21 Dec 2000 16:01:07 +0200
                'U' => '',      // Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT)
            ]
        );
    }

    /**
     * display an address in expected format.
     *
     * available parameters :
     *  address => the id of the address to display
     *  order_address => the id of the order address to display
     *  from_country_id => the country id
     *  dec_point => separator for the decimal point
     *  thousands_sep => thousands separator
     *  symbol => Currency symbol
     *
     *  ex : {format_money number="1246.12" decimals="1" dec_point="," thousands_sep=" " symbol="€"} will output "1 246,1 €"
     *
     * @param null $template
     *
     * @throws \TheliaSmarty\Template\Exception\SmartyPluginException
     *
     * @return string the expected number formatted
     */
    public function formatAddress($params, $template = null)
    {
        $postal = filter_var(
            $this->getParam($params, 'postal', null),
            \FILTER_VALIDATE_BOOLEAN
        );

        $html = filter_var(
            $this->getParam($params, 'html', true),
            \FILTER_VALIDATE_BOOLEAN
        );

        $htmlTag = $this->getParam($params, 'html_tag', 'p');
        $originCountry = $this->getParam($params, 'origin_country', null);
        $locale = $this->getParam($params, 'locale', $this->getSession()->getLang()->getLocale());

        // extract html attributes
        $htmlAttributes = [];
        foreach ($params as $k => $v) {
            if (str_contains($k, 'html_') && $k !== 'html_tag') {
                $htmlAttributes[substr($k, 5)] = $v;
            }
        }

        // get address or order address
        $address = null;
        if (null !== $id = $this->getParam($params, 'address', null)) {
            if (null === $address = AddressQuery::create()->findPk($id)) {
                return '';
            }
        } elseif (null !== $id = $this->getParam($params, 'order_address', null)) {
            if (null === $address = OrderAddressQuery::create()->findPk($id)) {
                return '';
            }
        } else {
            // try to parse arguments to build address
            $address = $this->getAddressFormParams($params);
        }

        if (null === $address) {
            throw new SmartyPluginException(
                'Either address, order_address or full list of address fields should be provided'
            );
        }

        $addressFormat = AddressFormat::getInstance();
        if ($postal) {
            if ($address instanceof Address) {
                $formattedAddress = $addressFormat->postalLabelFormat($address, $locale, $originCountry);
            } else {
                $formattedAddress = $addressFormat->postalLabelFormatTheliaAddress($address, $locale, $originCountry);
            }
        } else {
            if ($address instanceof Address) {
                $formattedAddress = $addressFormat->format($address, $locale, $html, $htmlTag, $htmlAttributes);
            } else {
                $formattedAddress = $addressFormat->formatTheliaAddress($address, $locale, $html, $htmlTag, $htmlAttributes);
            }
        }

        return $formattedAddress;
    }

    protected function getAddressFormParams($params)
    {
        // Check if there is arguments
        $addressArgs = [
            'country_code',
            'administrative_area',
            'locality',
            'dependent_locality',
            'postal_code',
            'sorting_code',
            'address_line1',
            'address_line2',
            'organization',
            'given_name',
            'additional_name',
            'family_name',
            'locale',
        ];
        $valid = false;

        $address = new Address();

        foreach ($addressArgs as $arg) {
            if (null !== $argVal = $this->getParam($params, $arg, null)) {
                $valid = true;
                $functionName = 'with'.Container::camelize($arg);
                $address = $address->$functionName($argVal);
            }
        }

        if (false === $valid) {
            return null;
        }

        return $address;
    }

    public function theliaImplode($firstParameter, $secondParameter = '')
    {
        if (\is_array($secondParameter)) {
            return implode($firstParameter, $secondParameter);
        }

        return implode($secondParameter, $firstParameter);
    }

    /**
     * @return SmartyPluginDescriptor[]
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'format_date', $this, 'formatDate'),
            new SmartyPluginDescriptor('function', 'format_number', $this, 'formatNumber'),
            new SmartyPluginDescriptor('function', 'format_money', $this, 'formatMoney'),
            new SmartyPluginDescriptor('function', 'format_array_2d', $this, 'formatTwoDimensionalArray'),
            new SmartyPluginDescriptor('function', 'format_address', $this, 'formatAddress'),
            new SmartyPluginDescriptor('modifier', 'implode', $this, 'theliaImplode'),
        ];
    }

    /**
     * @return Session
     */
    protected function getSession()
    {
        return $this->requestStack->getCurrentRequest()->getSession();
    }
}
