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

namespace Thelia\Domain\Order\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Log\Tlog;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Module\DeliveryTrackingUrlProviderInterface;

/**
 * The address of the carrier page where the customer follows the parcel of an
 * order, built from the tracking number of the order (`delivery_ref`).
 *
 * A delivery module implementing DeliveryTrackingUrlProviderInterface builds the
 * address itself. Any other delivery module may carry, in its module
 * configuration, a tracking address template where %ID% stands for the tracking
 * number: the number is url-encoded into it.
 *
 * The template comes from the back office and the number from the order: an
 * address that is not http(s) is never returned, so a setting cannot turn the
 * link shown to the customer into a script or a local file, and the marker is
 * refused in the host, so a tracking number can never choose the site it leads to.
 */
final class OrderTrackingUrlResolver implements ResetInterface
{
    public const string TRACKING_URL_CONFIG_KEY = 'tracking_url';

    public const string NUMBER_MARKER = '%ID%';

    /**
     * What answers for each delivery module already met in this request: the module
     * itself, its template, or null when it has neither.
     *
     * @var array<int, DeliveryTrackingUrlProviderInterface|string|null>
     */
    private array $sourceByModuleId = [];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function resolve(Order $order): ?string
    {
        $number = trim((string) $order->getDeliveryRef());
        $moduleId = $order->getDeliveryModuleId();

        // A module that was uninstalled leaves a null id behind: the order keeps the
        // carrier name, but nothing can build an address for it any more.
        if ('' === $number || null === $moduleId) {
            return null;
        }

        $source = $this->sourceOf($moduleId);

        if ($source instanceof DeliveryTrackingUrlProviderInterface) {
            return $this->askTheModule($source, $order);
        }

        return null === $source ? null : self::fill($source, $number);
    }

    /**
     * Whether a module implements its own tracking address, in which case the
     * template typed for it would not be read.
     */
    public function isProvidedByModule(int $moduleId): bool
    {
        return $this->sourceOf($moduleId) instanceof DeliveryTrackingUrlProviderInterface;
    }

    /**
     * A template is usable when it is an http(s) address carrying the %ID% marker.
     */
    public static function isValidTemplate(string $template): bool
    {
        $template = trim($template);

        return self::isWebAddress($template)
            && str_contains($template, self::NUMBER_MARKER)
            && !str_contains(self::authorityOf($template), self::NUMBER_MARKER);
    }

    /**
     * The template with the tracking number in place of %ID%, or null when the
     * template is not usable or the number is empty.
     */
    public static function fill(string $template, string $number): ?string
    {
        $template = trim($template);
        $number = trim($number);

        if ('' === $number || !self::isValidTemplate($template)) {
            return null;
        }

        return str_replace(self::NUMBER_MARKER, rawurlencode($number), $template);
    }

    public function reset(): void
    {
        $this->sourceByModuleId = [];
    }

    /**
     * An http(s) address with a host, and nothing a browser would read differently
     * from what is checked here: no credentials before the host, no backslash (read
     * as a slash), no space, control or invisible character anywhere.
     */
    private static function isWebAddress(string $url): bool
    {
        return 1 === preg_match('#^https?://[^\p{Z}\p{C}\\\\/?\#@]+(?:[/?\#][^\p{Z}\p{C}\\\\]*)?\z#iu', $url);
    }

    private static function authorityOf(string $url): string
    {
        return (string) preg_replace('#^https?://([^/?\#]*).*$#is', '$1', $url);
    }

    /**
     * The module is third-party code: whatever goes wrong in it costs the order its
     * link, never the page, the API read or the status change that asked for it.
     */
    private function askTheModule(DeliveryTrackingUrlProviderInterface $module, Order $order): ?string
    {
        try {
            $url = trim((string) $module->getTrackingUrl($order));
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->error('The tracking link of order {ref} could not be built by {module}: {error}', ['ref' => $order->getRef(), 'module' => $module::class, 'error' => $throwable->getMessage()]);

            return null;
        }

        return self::isWebAddress($url) ? $url : null;
    }

    private function sourceOf(int $moduleId): DeliveryTrackingUrlProviderInterface|string|null
    {
        if (\array_key_exists($moduleId, $this->sourceByModuleId)) {
            return $this->sourceByModuleId[$moduleId];
        }

        $module = ModuleQuery::create()->findPk($moduleId);

        if (null === $module) {
            return $this->sourceByModuleId[$moduleId] = null;
        }

        try {
            $instance = $module->getDeliveryModuleInstance($this->container);
        } catch (\InvalidArgumentException) {
            // A deactivated module is not in the container: its template still applies
            // to the orders it shipped.
            $instance = null;
        } catch (\Throwable $throwable) {
            // Building the module runs third-party code too.
            Tlog::getInstance()->error('The delivery module {module} could not be loaded for a tracking link: {error}', ['module' => $module->getCode(), 'error' => $throwable->getMessage()]);
            $instance = null;
        }

        if ($instance instanceof DeliveryTrackingUrlProviderInterface) {
            return $this->sourceByModuleId[$moduleId] = $instance;
        }

        $template = trim((string) ModuleConfigQuery::create()->getConfigValue($moduleId, self::TRACKING_URL_CONFIG_KEY));

        return $this->sourceByModuleId[$moduleId] = '' === $template ? null : $template;
    }
}
