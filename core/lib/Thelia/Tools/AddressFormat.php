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

namespace Thelia\Tools;

use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\AddressInterface;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Formatter\PostalLabelFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Lang;
use Thelia\Model\OrderAddress;

/**
 * Class AddressFormat.
 *
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
     * Format an address.
     *
     * @param null   $locale
     * @param bool   $html
     * @param string $htmlTag
     * @param array  $htmlAttributes
     *
     * @return string
     */
    public function format(
        AddressInterface $address,
        $locale = null,
        $html = true,
        $htmlTag = 'p',
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
            ['locale' => $locale]
        );

        return $formatter->format($address, ['html' => $html, 'html_tag' => $htmlTag, 'html_attributes' => $htmlAttributes]);
    }

    /**
     * Format a Thelia address (Address or OrderAddress).
     *
     * @param \Thelia\Model\OrderAddress|OrderAddress $address
     * @param null                                    $locale
     * @param bool                                    $html
     * @param string                                  $htmlTag
     * @param array                                   $htmlAttributes
     *
     * @return string
     */
    public function formatTheliaAddress($address, $locale = null, $html = true, $htmlTag = 'p', $htmlAttributes = [])
    {
        $address = $this->mapTheliaAddress($address, $locale);

        return $this->format($address, $locale, $html, $htmlTag, $htmlAttributes);
    }

    /**
     * Format an address to a postal label.
     *
     * @param null  $locale
     * @param null  $originCountry
     * @param array $options
     *
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

        $options = array_merge($options, ['locale' => $locale, 'origin_country' => $originCountry]);

        $formatter = new PostalLabelFormatter(
            $addressFormatRepository,
            $countryRepository,
            $subdivisionRepository,
            $options
        );

        return $formatter->format($address);
    }

    /**
     * Format a Thelia address (Address or OrderAddress) to a postal label.
     *
     * @param \Thelia\Model\OrderAddress|OrderAddress $address
     * @param null                                    $locale
     * @param null                                    $originCountry
     * @param array                                   $options
     *
     * @return string
     */
    public function postalLabelFormatTheliaAddress($address, $locale = null, $originCountry = null, $options = [])
    {
        $address = $this->mapTheliaAddress($address, $locale);

        return $this->postalLabelFormat($address, $locale, $originCountry, $options);
    }

    /**
     * Convert a Thelia address (Address or OrderAddress) to ImmutableAddressInterface.
     *
     * @param \Thelia\Model\OrderAddress|OrderAddress $address
     */
    protected function mapTheliaAddress($address, $locale = null)
    {
        $country = $address->getCountry();
        if (null === $locale) {
            $locale = Lang::getDefaultLanguage()->getLocale();
        }

        $addressModel = new Address();
        $addressModel = $addressModel
            ->withCountryCode($country->getIsoalpha2())
            ->withAddressLine1($address->getAddress1())
            ->withAddressLine2($address->getAddress2())
            ->withPostalCode($address->getZipcode())
            ->withLocality($address->getCity())
            ->withOrganization($address->getCompany())
            ->withGivenName($address->getFirstname())
            ->withFamilyName($address->getLastname())
        ;

        if ($country->getHasStates() && \intval($address->getStateId()) !== 0) {
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
