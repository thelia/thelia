# Parcel tracking link and shipping e-mail

An order keeps its tracking number in `order.delivery_ref`: the merchant types it on the order page of the back office, or a carrier module sets it when it prints the label. The core turns that number into a link to the carrier page, and tells the customer when the order leaves.

## The tracking address of a carrier

Each delivery module can carry a tracking address template in its module configuration, under the key `tracking_url` (`OrderTrackingUrlResolver::TRACKING_URL_CONFIG_KEY`). `%ID%` stands for the tracking number:

```
https://www.laposte.fr/outils/suivre-vos-envois?code=%ID%
```

The merchant types it in the back office, on the shipping zones page of the carrier. A template is refused unless it is an `http` or `https` address carrying `%ID%` outside the host, with no credentials, backslash, space or control character: the template comes from the back office and ends up as a link in front of the customer, and a `javascript:` or `data:` address must never get there. The tracking number is url-encoded into the address, so a number with a space or a slash keeps the link valid.

A module whose carrier wants a signature, a customer code or a format a template cannot express builds the link itself by implementing `Thelia\Module\DeliveryTrackingUrlProviderInterface` on its module class:

```php
final class MyCarrier extends AbstractDeliveryModuleWithState implements DeliveryTrackingUrlProviderInterface
{
    public function getTrackingUrl(Order $order): ?string
    {
        // null when the module cannot tell
    }
}
```

When the module implements it, the template is not read. An address it returns that is not `http(s)` is dropped all the same, and an exception thrown by the module costs the order its link (it is logged), never the page or the status change that asked for it.

## Reading the link of an order

`Thelia\Domain\Order\Service\OrderTrackingUrlResolver::resolve(Order $order): ?string` gives the link, or null when the order has no tracking number, when its carrier has no tracking address, or when its delivery module was uninstalled (the order keeps the carrier name in `delivery_module_title`, nothing can build an address any more). The source of each delivery module is read once per request.

Where it shows:

- API: `deliveryTrackingUrl` on a single order read, `/api/front/account/orders/{id}`, `/api/front/guest-orders/{token}` and `/api/admin/orders/{id}`. Read only, omitted when null. The operations already restrict an order to its owner.
- Back office: next to the tracking number on the order page.
- Flexy: on the order page of the customer account and on the order cards of the order list.

## The shipping e-mail

`Thelia\Domain\Order\EventListener\SendShippingEmailListener` sends the message `order_shipped` to the customer when an order **enters** the `sent` status, a custom status declared equivalent to it included. It listens to `ORDER_UPDATE_STATUS` at priority 32, after the status is written (128) and after the order history (64), and compares the previous and the new status on their effective code:

- saving an order that is already sent, or moving it between two statuses that both mean sent, sends nothing;
- correcting the tracking number of a shipped order sends nothing, and the link follows the new number;
- a status changed without a request (payment notification, API, script) sends the e-mail the same way.
- an order taken back out of `sent` and shipped again enters the status again, and the customer is mailed again;
- an import or a synchronisation that moves orders to `sent` through the status event mails their customers too: switch the e-mail off for the time of a bulk catch-up on historical orders.

The message receives `order_id`, `order_ref`, `delivery_ref`, `tracking_url` and `carrier` (the title of the delivery module in the language of the customer), each null when unknown. Without a tracking number the e-mail still goes out and simply announces the shipment. The templates `order_shipped.html.twig` and `order_shipped.txt.twig` belong to the e-mail theme.

The setting `order_shipped_email_enabled` (`1` by default, on a fresh install and after the update) switches the e-mail off with `0`, for a shop that notifies through another channel or whose carrier already writes to the customer. It is a checkbox in Configuration › Store.

## Coexistence with a carrier module that sends its own e-mail

CustomDelivery used to send its own shipping message, `mail_custom_delivery`. From its version 4.1 it stores its tracking address under the core key `tracking_url` (the update moves there the address it held in the global setting `custom_delivery_tracking_url`), so the core builds the link of its orders like any other carrier's, and the address is edited either on the module page or on its shipping page. On a core that sends the shipping e-mail, the module leaves that e-mail to the core and its store switch; a transition setting in its configuration keeps the old message for a shop that has not reviewed it yet. It does not implement `DeliveryTrackingUrlProviderInterface`, so it keeps loading on an older core, where it sends its message as before. A shop that keeps CustomDelivery 4.0 receives both e-mails until the module is updated, or until one of the two is switched off.
