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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\ImportExport;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\FileFormat\Formatting\Formatter\CSVFormatter;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;

/**
 * Class Export
 * @package Thelia\Action
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class Export extends BaseAction implements EventSubscriberInterface
{
    protected $environment;

    public function __construct($environment)
    {
        $this->environment = $environment;
    }

    public function changeCategoryPosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(new ExportCategoryQuery(), $event);

        $this->cacheClear($event->getDispatcher());
    }

    public function changeExportPosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(new ExportQuery(), $event);

        $this->cacheClear($event->getDispatcher());
    }

    protected function cacheClear(EventDispatcherInterface $dispatcher)
    {
        $cacheEvent = new CacheEvent(
            $this->environment
        );

        $dispatcher->dispatch(TheliaEvents::CACHE_CLEAR, $cacheEvent);
    }

    /**
     * Before a CSV Export
     *
     * Replace labels into heading row
     * Add a heading row with translated column names manually
     *
     * Of course the export has to use a CSVFormatter.
     *
     * @param ImportExport $event
     */
    public function addHeadingRow(ImportExport $event)
    {
        $handler = $event->getHandler();
        $formatter = $event->getFormatter();

        if ($formatter instanceof CSVFormatter) {
            // Get existing data
            $formatterData = $event->getData();
            $data = $formatterData->getData();

            // Get heading labels
            $heading = $handler->getTranslatedHeading();

            // Complete heading row (with all keys)
            // - Use label if possible
            // - Use alias else
            $orderCols = array_keys($formatterData->getRow(0));
            $orderCols = array_combine($orderCols, $orderCols);
            $headingRow = array_merge($orderCols, $heading);

            // Add heading row to the top of data
            array_unshift($data, $headingRow);

            // Update event's data
            $formatterData->setData($data);
            $event->setData($formatterData);
        }

    }

    /**
     * After a CSV Export
     *
     * Removes the first row, which is automatically added by encode method of CSVFormatter
     * (The heading row with column names was just add in "before" event)
     *
     * @param ImportExport $event
     * @return mixed|string
     */
    public function deleteDefaultHeadingRow(ImportExport $event)
    {
        $formatter = $event->getFormatter();

        if ($formatter instanceof CSVFormatter) {
            $content = $event->getContent();

            // Heading row
            // Replace it

            if (false === $firstRow = strpos($content, $formatter->lineReturn)) {
                return $content;
            }

            // Remove old first row and concatenate the rest
            $content = substr($content, $firstRow + strlen($formatter->lineReturn));

            $event->setContent($content);
        }

    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::EXPORT_CATEGORY_CHANGE_POSITION => array("changeCategoryPosition", 128),
            TheliaEvents::EXPORT_CHANGE_POSITION => array("changeExportPosition", 128),
            TheliaEvents::EXPORT_BEFORE_ENCODE => array('addHeadingRow', 128),
            TheliaEvents::EXPORT_AFTER_ENCODE => array('deleteDefaultHeadingRow', 128),
        );
    }
}
