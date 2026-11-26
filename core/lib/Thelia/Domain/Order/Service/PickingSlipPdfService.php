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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Order\Exception\PickingSlipException;
use Thelia\Domain\SaleUnit\Config\SaleUnitConfigScope;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\OrderQuery;

/**
 * Renders the `preparation` document of the active PDF theme for a list of orders in one PDF. The theme
 * template is rendered once with the whole list (`order_ids`, in the order asked) and loops over it, one page
 * per order; the service carries no layout.
 *
 * Nothing here needs the session of a request, but the parser reads the locale and the URL context of the
 * current request: outside HTTP (console), push a request first, as `pdf:render` does.
 */
final readonly class PickingSlipPdfService
{
    public const string DOCUMENT = 'preparation';

    /** Name of the configuration variable that names the picking slip view of the PDF theme, as `pdf_invoice_file` does for the invoice. */
    public const string CONFIG_TEMPLATE = 'pdf_preparation_file';

    /** Name of the configuration variable that overrides the size limit of a batch. */
    public const string CONFIG_MAX_ORDERS = 'picking_slip_max_orders';

    /**
     * Dompdf takes nearly all the time of a batch, and more than in proportion: with five lines an order, 50 orders take
     * under 10 s and 100 MB, 200 orders a minute and 256 MB, past the 30 s PHP stops a request at by default.
     */
    public const int DEFAULT_MAX_ORDERS = 50;

    public function __construct(
        private ParserResolver $parserResolver,
        private TemplateHelperInterface $templateHelper,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param list<int|string> $orderIds ids in the order the pages must come; an id that matches no order is dropped
     * @param string|null      $locale   locale of the document (template parameter `picking_slip_locale`), the default language of the shop when null
     *
     * @throws PickingSlipException when nothing is left to print, when the batch is over the limit, or when the active PDF theme has no preparation document
     */
    public function render(array $orderIds, ?string $locale = null): PickingSlipPdf
    {
        $requestedIds = array_values(array_unique(array_map('intval', $orderIds)));

        $limit = $this->maxOrders();
        if (\count($requestedIds) > $limit) {
            throw PickingSlipException::tooManyOrders(\count($requestedIds), $limit);
        }

        if ([] === $requestedIds) {
            throw PickingSlipException::noOrder();
        }

        $ordersById = [];
        foreach (OrderQuery::create()->filterById($requestedIds, Criteria::IN)->find() as $order) {
            $ordersById[(int) $order->getId()] = $order;
        }

        $orders = [];
        foreach ($requestedIds as $orderId) {
            if (isset($ordersById[$orderId])) {
                $orders[] = $ordersById[$orderId];
            }
        }

        if ([] === $orders) {
            throw PickingSlipException::noOrder();
        }

        $locale ??= (string) Lang::getDefaultLanguage()->getLocale();
        $foundIds = array_map(static fn ($order): int => (int) $order->getId(), $orders);

        $pdfTemplate = $this->templateHelper->getActivePdfTemplate();

        $templateName = $this->templateName();

        try {
            $parser = $this->parserResolver->getParser($pdfTemplate->getAbsolutePath(), $templateName);
        } catch (ResourceNotFoundException) {
            throw PickingSlipException::missingTemplate($templateName);
        }

        $parser->setTemplateDefinition($pdfTemplate, true);

        // The store identity printed is the one of the sale unit of the orders, when they share it.
        $saleUnitIds = array_unique(array_map(static fn ($order) => $order->getSaleUnitId(), $orders));
        $saleUnitId = 1 === \count($saleUnitIds) ? $orders[0]->getSaleUnitId() : null;

        $html = SaleUnitConfigScope::runFor(
            $saleUnitId,
            static fn (): string => $parser->render($templateName, ['order_ids' => $foundIds, 'picking_slip_locale' => $locale]),
        );

        $fileName = 1 === \count($orders)
            ? \sprintf('%s-%s.pdf', $templateName, $orders[0]->getRef())
            : \sprintf('%s-%s.pdf', $templateName, (new \DateTimeImmutable())->format('Y-m-d'));

        $pdfEvent = new PdfEvent($html);
        $pdfEvent->setTemplateName($templateName);
        $pdfEvent->setLang(strtolower(substr($locale, 0, 2)));
        $pdfEvent->setFileName(pathinfo($fileName, \PATHINFO_FILENAME));

        $this->eventDispatcher->dispatch($pdfEvent, TheliaEvents::GENERATE_PDF);

        if (!$pdfEvent->hasPdf()) {
            throw new PickingSlipException('PDF generation produced no output.');
        }

        return new PickingSlipPdf(
            (string) $pdfEvent->getPdf(),
            $fileName,
            \count($orders),
        );
    }

    /**
     * Whether the active PDF theme has a preparation document. Asks each parser without making it the current one,
     * so a page can call it while it renders.
     */
    public function isAvailable(): bool
    {
        $themePath = $this->templateHelper->getActivePdfTemplate()->getAbsolutePath();

        foreach ($this->parserResolver->getParsers() as $parser) {
            if ($parser->supportTemplateRender($themePath, $this->templateName())) {
                return true;
            }
        }

        return false;
    }

    /**
     * The view of the PDF theme the picking slip is rendered with, and the start of its file name: the
     * configuration variable when it holds a plain name (letters, digits, `-`, `_`), `preparation` otherwise,
     * so that no path reaches the file `pdf:render` writes.
     */
    public function templateName(): string
    {
        $configured = trim((string) ConfigQuery::read(self::CONFIG_TEMPLATE, self::DOCUMENT));

        return 1 === preg_match('/^[A-Za-z0-9_-]+$/', $configured) ? $configured : self::DOCUMENT;
    }

    /**
     * The most orders a batch may hold: the configuration variable when it is a positive number, the default otherwise.
     */
    public function maxOrders(): int
    {
        $configured = (int) ConfigQuery::read(self::CONFIG_MAX_ORDERS, (string) self::DEFAULT_MAX_ORDERS);

        return $configured > 0 ? $configured : self::DEFAULT_MAX_ORDERS;
    }
}
