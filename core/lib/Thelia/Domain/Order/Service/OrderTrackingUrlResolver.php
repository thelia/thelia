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
 * link shown to the customer into a script or a local file.
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
            $url = $source->getTrackingUrl($order);

            return null !== $url && self::isWebAddress($url) ? $url : null;
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
        return self::isWebAddress($template) && str_contains($template, self::NUMBER_MARKER);
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

    private static function isWebAddress(string $url): bool
    {
        return 1 === preg_match('#^https?://[^\s/?\#@]+(?:[/?\#]\S*)?$#i', trim($url));
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
        }

        if ($instance instanceof DeliveryTrackingUrlProviderInterface) {
            return $this->sourceByModuleId[$moduleId] = $instance;
        }

        $template = trim((string) ModuleConfigQuery::create()->getConfigValue($moduleId, self::TRACKING_URL_CONFIG_KEY));

        return $this->sourceByModuleId[$moduleId] = '' === $template ? null : $template;
    }
}
