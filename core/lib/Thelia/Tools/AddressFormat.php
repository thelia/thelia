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


namespace Thelia\Tools;

use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Formatter\PostalLabelFormatter;
use CommerceGuys\Addressing\Model\Address;
use CommerceGuys\Addressing\Model\AddressInterface;
use CommerceGuys\Addressing\Repository\AddressFormatRepository;
use CommerceGuys\Addressing\Repository\CountryRepository;
use CommerceGuys\Addressing\Repository\SubdivisionRepository;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Lang;
use Thelia\Model\OrderAddress;

/**
 * Class AddressFormat
 * @package Thelia\Tools
 * @author Julien Chanséaume <julien@thelia.net>
 */
class AddressFormat
{
    private static $instance;

    private function __construct()
    {
    }

    /**
     * @return $this
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new AddressFormat();
        }

        return self::$instance;
    }


    /**
     * Format an address
     *
     * @param AddressInterface $address
     * @param null $locale
     * @param bool $html
     * @param string $htmlTag
     * @param array $htmlAttributes
     * @return string
     */
    public function format(
        AddressInterface $address,
        $locale = null,
        $html = true,
        $htmlTag = "p",
        $htmlAttributes = []
    ) {
        $locale = $this->normalizeLocale($locale);

        $addressFormatRepository = new AddressFormatRepository();
        $countryRepository = new CountryRepository();
        $subdivisionRepository = new SubdivisionRepository();

        $formatter = new DefaultFormatter(
            $addressFormatRepository,
            $countryRepository,
            $subdivisionRepository,
            $locale
        );

        $formatter->setOption('html', $html);
        $formatter->setOption('html_tag', $htmlTag);
        $formatter->setOption('html_attributes', $htmlAttributes);


        $addressFormatted = $formatter->format($address);

        return $addressFormatted;
    }

    /**
     * Format a Thelia address (Address or OrderAddress)
     *
     * @param \Thelia\Model\OrderAddress|OrderAddress $address
     * @param null $locale
     * @param bool $html
     * @param string $htmlTag
     * @param array $htmlAttributes
     * @return string
     */
    public function formatTheliaAddress($address, $locale = null, $html = true, $htmlTag = "p", $htmlAttributes = [])
    {
        $address = $this->mapTheliaAddress($address, $locale);
        return $this->format($address, $locale, $html, $htmlTag, $htmlAttributes);
    }

    /**
     * Format an address to a postal label
     *
     * @param AddressInterface $address
     * @param null $locale
     * @param null $originCountry
     * @param array $options
     * @return string
     */
    public function postalLabelFormat(AddressInterface $address, $locale = null, $originCountry = null, $options = [])
    {
        $locale = $this->normalizeLocale($locale);

        $addressFormatRepository = new AddressFormatRepository();
        $countryRepository = new CountryRepository();
        $subdivisionRepository = new SubdivisionRepository();

        if (null === $originCountry) {
            $countryId = Country::getShopLocation();
            if (null === $country = CountryQuery::create()->findPk($countryId)) {
                $country = Country::getDefaultCountry();
            }

            $originCountry = $country->getIsoalpha2();
        }

        $formatter = new PostalLabelFormatter(
            $addressFormatRepository,
            $countryRepository,
            $subdivisionRepository,
            $originCountry,
            $locale,
            $options
        );

        $addressFormatted = $formatter->format($address);

        return $addressFormatted;
    }

    /**
     * Format a Thelia address (Address or OrderAddress) to a postal label
     *
     * @param \Thelia\Model\OrderAddress|OrderAddress $address
     * @param null $locale
     * @param null $originCountry
     * @param array $options
     * @return string
     */
    public function postalLabelFormatTheliaAddress($address, $locale = null, $originCountry = null, $options = [])
    {
        $address = $this->mapTheliaAddress($address, $locale);
        return $this->postalLabelFormat($address, $locale, $originCountry, $options);
    }

    /**
     * Convert a Thelia address (Address or OrderAddress) to ImmutableAddressInterface
     *
     * @param \Thelia\Model\OrderAddress|OrderAddress $address
     * @return Address|\CommerceGuys\Addressing\Model\ImmutableAddressInterface
     */
    protected function mapTheliaAddress($address, $locale = null)
    {
        $country = $address->getCountry();
        if (null === $locale) {
            $locale = Lang::getDefaultLanguage()->getLocale();
        }
        $customerTitle = $address->getCustomerTitle()
            ->setLocale($this->denormalizeLocale($locale))
            ->getShort()
        ;

        $addressModel = new Address();
        $addressModel = $addressModel
            ->withCountryCode($country->getIsoalpha2())
            ->withAddressLine1($address->getAddress1())
            ->withAddressLine2($address->getAddress2())
            ->withPostalCode($address->getZipcode())
            ->withLocality($address->getCity())
            ->withOrganization($address->getCompany())
            ->withRecipient(
                sprintf(
                    '%s %s %s',
                    $customerTitle,
                    $address->getLastname(),
                    $address->getFirstname()
                )
            )
        ;


        if ($country->getHasStates() && intval($address->getStateId()) !== 0) {
            $addressModel = $addressModel->withAdministrativeArea(
                sprintf(
                    '%s-%s',
                    $country->getIsoalpha2(),
                    $address->getState()->getIsocode()
                )
            );
        }

        return $addressModel;
    }

    private function normalizeLocale($locale)
    {
        if (null !== $locale) {
            $locale = str_replace('_', '-', $locale);
        }

        return $locale;
    }

    private function denormalizeLocale($locale)
    {
        if (null !== $locale) {
            $locale = str_replace('-', '_', $locale);
        }

        return $locale;
    }
}
