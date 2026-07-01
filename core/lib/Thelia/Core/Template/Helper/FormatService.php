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

namespace Thelia\Core\Template\Helper;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderAddressQuery;
use Thelia\Tools\AddressFormat;

/**
 * Formats money, numbers, dates and addresses independently of any template engine.
 *
 * This is the engine-agnostic core of the historical Smarty format plugins
 * (TheliaSmarty\Template\Plugins\Format): the same money/number/date/address rendering,
 * usable behind any parser through a thin adapter. Unlike the legacy Thelia\Tools\*
 * helpers, it never requires an HTTP Request: the locale (and currency) can be passed
 * explicitly, so it works from the CLI, a worker or a cron — where emails and PDFs are
 * rendered. When no locale is given it falls back to the current request, then to the
 * default language, so HTTP behaviour is preserved.
 */
#[Autoconfigure(public: true)]
final readonly class FormatService
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Format an amount with its currency, mirroring MoneyFormat::formatByCurrency().
     *
     * @param int|float|string $number     the amount to format
     * @param int|null         $currencyId currency to use; the default currency when null
     */
    public function money(
        int|float|string $number,
        ?int $currencyId = null,
        ?string $locale = null,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
        bool $removeZeroDecimal = false,
    ): string {
        if ($number === '') {
            return '';
        }

        $lang = $this->resolveLang($locale);
        $formatted = $this->preFormat((float) $number, $decimals, $decPoint, $thousandsSep, $removeZeroDecimal, $lang);

        $currency = $this->resolveCurrency($currencyId);

        if (null !== $currency && str_contains((string) $currency->getFormat(), '%n')) {
            return str_replace(
                ['%n', '%s', '%c'],
                [$formatted, (string) $currency->getSymbol(), (string) $currency->getCode()],
                (string) $currency->getFormat(),
            );
        }

        return $formatted;
    }

    /**
     * Format a plain number, mirroring NumberFormat::format().
     *
     * @param int|float|string $number the number to format
     */
    public function number(
        int|float|string $number,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
        ?string $locale = null,
    ): string {
        if ($number === '') {
            return '';
        }

        return $this->numberFormat((float) $number, $decimals, $decPoint, $thousandsSep, $this->resolveLang($locale));
    }

    /**
     * Format a date, mirroring the Smarty {format_date} plugin.
     *
     * When $format is null it falls back to the language format for $output
     * ("date", "time" or "datetime"). When $locale is given the date is localized
     * through IntlDateFormatter, otherwise DateTime::format() is used as-is.
     *
     * @param \DateTimeInterface|string|int|array<string,int|string>|null $date
     * @param string|null                                                 $output "date", "time" or "datetime"
     */
    public function date(
        \DateTimeInterface|string|int|array|null $date,
        ?string $format = null,
        ?string $output = null,
        ?string $locale = null,
    ): string {
        $dateTime = $this->toDateTime($date);

        if (null === $dateTime) {
            return '';
        }

        $format ??= $this->languageDateFormat($this->resolveLang($locale), $output);

        if (null === $locale) {
            return $dateTime->format((string) $format);
        }

        return $this->formatDateWithLocale($dateTime, $locale, (string) $format);
    }

    /**
     * Format an order address, mirroring the Smarty {format_address} plugin.
     *
     * Email and PDF templates only ever format order addresses.
     */
    public function address(
        int $orderAddressId,
        ?string $locale = null,
        bool $html = true,
        string $htmlTag = 'p',
        bool $postal = false,
        ?string $originCountry = null,
    ): string {
        $orderAddress = OrderAddressQuery::create()->findPk($orderAddressId);

        if (null === $orderAddress) {
            return '';
        }

        $locale ??= $this->resolveLang(null)->getLocale();
        $addressFormat = AddressFormat::getInstance();

        return $postal
            ? $addressFormat->postalLabelFormatTheliaAddress($orderAddress, $locale, $originCountry)
            : $addressFormat->formatTheliaAddress($orderAddress, $locale, $html, $htmlTag);
    }

    private function preFormat(
        float $number,
        ?int $decimals,
        ?string $decPoint,
        ?string $thousandsSep,
        bool $removeZeroDecimal,
        Lang $lang,
    ): string {
        $number = (float) preg_replace('/\s+/', '', (string) $number);

        if ($removeZeroDecimal) {
            $decimals ??= (int) $lang->getDecimals();
            $number = round($number, $decimals);

            if (($number - (int) $number) === 0.0) {
                $decimals = 0;
            }
        }

        return $this->numberFormat($number, $decimals, $decPoint, $thousandsSep, $lang);
    }

    private function numberFormat(float $number, ?int $decimals, ?string $decPoint, ?string $thousandsSep, Lang $lang): string
    {
        $decimals ??= (int) $lang->getDecimals();
        $decPoint ??= (string) $lang->getDecimalSeparator();
        $thousandsSep ??= (string) $lang->getThousandsSeparator();

        return number_format($number, $decimals, $decPoint, $thousandsSep);
    }

    /**
     * @param \DateTimeInterface|string|int|array<string,int|string>|null $date
     */
    private function toDateTime(\DateTimeInterface|string|int|array|null $date): ?\DateTimeInterface
    {
        if ($date instanceof \DateTimeInterface) {
            return $date;
        }

        if (null === $date || '' === $date) {
            return null;
        }

        if (\is_int($date)) {
            return (new \DateTime())->setTimestamp($date);
        }

        if (\is_array($date)) {
            $hasDate = isset($date['year'], $date['month'], $date['day']);
            $hasTime = isset($date['hour'], $date['minute'], $date['second']);

            $datePart = $hasDate ? \sprintf('%d-%d-%d', $date['year'], $date['month'], $date['day']) : (new \DateTime())->format('Y-m-d');
            $timePart = $hasTime ? \sprintf('%d:%d:%d', $date['hour'], $date['minute'], $date['second']) : '0:0:0';

            return new \DateTime(\sprintf('%s %s', $datePart, $timePart));
        }

        try {
            return new \DateTime($date);
        } catch (\Exception) {
            return null;
        }
    }

    private function languageDateFormat(Lang $lang, ?string $output): string
    {
        return match ($output) {
            'date' => (string) $lang->getDateFormat(),
            'time' => (string) $lang->getTimeFormat(),
            default => (string) $lang->getDatetimeFormat(),
        };
    }

    private function formatDateWithLocale(\DateTimeInterface $date, string $locale, string $format): string
    {
        $icuFormat = str_contains($format, '%')
            ? $this->convertStrftimeToIcu($format)
            : $this->convertDatePhpToIcu($format);

        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
        $formatter->setPattern($icuFormat);

        return (string) $formatter->format($date);
    }

    private function resolveLang(?string $locale): Lang
    {
        if (null !== $locale) {
            $lang = LangQuery::create()->findOneByLocale($locale);

            if (null !== $lang) {
                return $lang;
            }
        }

        $session = $this->currentSession();

        if (null !== $session) {
            $lang = $session->getLang(false);

            if ($lang instanceof Lang) {
                return $lang;
            }
        }

        return Lang::getDefaultLanguage();
    }

    private function resolveCurrency(?int $currencyId): ?Currency
    {
        if (null !== $currencyId) {
            return CurrencyQuery::create()->findPk($currencyId);
        }

        $session = $this->currentSession();

        if (null !== $session) {
            return $session->getCurrency(false);
        }

        return Currency::getDefaultCurrency();
    }

    private function currentSession(): ?Session
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        return $session instanceof Session ? $session : null;
    }

    /**
     * Convert a php date() pattern to an ICU pattern (from the Yii framework, as used by
     * the legacy Smarty plugin). Escaped characters are not supported.
     */
    private function convertDatePhpToIcu(string $pattern): string
    {
        return strtr($pattern, [
            'd' => 'dd', 'D' => 'eee', 'j' => 'd', 'l' => 'eeee', 'N' => 'e', 'S' => '', 'w' => '', 'z' => 'D',
            'W' => 'w',
            'F' => 'MMMM', 'm' => 'MM', 'M' => 'MMM', 'n' => 'M', 't' => '',
            'L' => '', 'o' => 'Y', 'Y' => 'yyyy', 'y' => 'yy',
            'a' => 'a', 'A' => 'a', 'B' => '', 'g' => 'h', 'G' => 'H', 'h' => 'hh', 'H' => 'HH', 'i' => 'mm', 's' => 'ss', 'u' => '',
            'e' => 'VV', 'I' => '', 'O' => 'xx', 'P' => 'xxx', 'T' => 'zzz', 'Z' => '',
            'c' => 'yyyy-MM-dd\'T\'HH:mm:ssxxx', 'r' => 'eee, dd MMM yyyy HH:mm:ss xx', 'U' => '',
        ]);
    }

    /**
     * Convert a strftime() format string (%-tokens) to an ICU pattern.
     */
    private function convertStrftimeToIcu(string $format): string
    {
        return strtr($format, [
            '%Y' => 'yyyy', '%y' => 'yy', '%m' => 'MM', '%d' => 'dd', '%e' => 'd',
            '%H' => 'HH', '%I' => 'hh', '%M' => 'mm', '%S' => 'ss', '%p' => 'a', '%P' => 'a',
            '%A' => 'EEEE', '%a' => 'EEE', '%B' => 'MMMM', '%b' => 'MMM', '%h' => 'MMM',
            '%Z' => 'zzz', '%z' => 'xx', '%j' => 'DDD', '%G' => 'YYYY', '%g' => 'YY',
            '%u' => 'e', '%w' => '', '%V' => 'ww', '%n' => "\n", '%t' => "\t", '%%' => "'%'",
        ]);
    }
}
