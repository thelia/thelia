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

namespace Thelia\Tests\Integration\Model;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\EventListener\ZipCodeRuleCacheListener;
use Thelia\Domain\Shipping\ZipCode\ZipCodeRuleSet;
use Thelia\Log\AbstractTlogDestination;
use Thelia\Log\Tlog;
use Thelia\Model\Area;
use Thelia\Model\AreaZipCodeRuleQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Test\IntegrationTestCase;

/**
 * The postal code rules of a zone as the carriers read them: kept for the request, and a
 * row nobody could have written through the back office left aside.
 */
final class AreaZipCodeRuleQueryTest extends IntegrationTestCase
{
    private Area $area;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = $this->createFixtureFactory()->country(['isoalpha2' => 'XK', 'isoalpha3' => 'XKX', 'isocode' => '984']);
        $this->area = (new Area())->setName('Postal code rules');
        $this->area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($this->area->getId())->setCountryId($this->country->getId())->save($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        // The next Tlog::getInstance() reads its configuration again.
        (new \ReflectionProperty(Tlog::class, 'instance'))->setValue(null, null);

        parent::tearDown();
    }

    public function testAnUnreadableExclusionExcludesNothingAndIsLoggedOnceWithItsReason(): void
    {
        $lines = $this->captureTheLog();
        $ruleId = $this->insertStraightIntoTheTable('***', excluded: true);

        self::assertTrue($this->ruleSet()->accepts('20090'), 'An unreadable exclusion excludes nothing.');
        self::assertTrue($this->ruleSet()->accepts('75001'));

        $warnings = array_values(array_filter(
            $lines->lines,
            static fn (string $line): bool => str_contains($line, 'Postal code rule '.$ruleId.' of zone '),
        ));
        self::assertCount(1, $warnings, 'The rule is logged once for the zone and the country read in the request.');
        self::assertStringContainsString(
            'Postal code rule '.$ruleId.' of zone '.$this->area->getId().' cannot be read ("***": A postal code pattern needs at least one character other than *.): it excludes no postal code.',
            $warnings[0],
        );
    }

    public function testAPatternStoredInFullWidthCharactersIsReadInItsNormalForm(): void
    {
        $this->insertStraightIntoTheTable("\u{FF12}\u{FF10}\u{FF0A}", excluded: true);

        self::assertFalse($this->ruleSet()->accepts('20090'), 'The full-width 20* excludes Corsica.');
        self::assertTrue($this->ruleSet()->accepts('69001'));
    }

    /**
     * A pattern written by hand may hold a line break: logged as it is, it would forge a line
     * of the log.
     */
    public function testAnUnreadableRuleCannotForgeALineOfTheLog(): void
    {
        $lines = $this->captureTheLog();
        $ruleId = $this->insertStraightIntoTheTable("1...2\r\n[ERROR] forged", excluded: true);

        $this->ruleSet()->accepts('20090');

        $warning = $this->warningAbout($lines, $ruleId);
        self::assertStringNotContainsString("\n", $warning, 'A line break of the pattern reached the log.');
        self::assertStringContainsString('1...2\\r\\n[ERROR] forged', $warning);
    }

    /**
     * An unreadable inclusion must not open the zone to every postal code: it takes none, and
     * the zone keeps its other inclusions.
     */
    public function testAnUnreadableInclusionKeepsTheZoneToItsOtherInclusions(): void
    {
        $lines = $this->captureTheLog();
        $ruleId = $this->insertStraightIntoTheTable('75020...75001', excluded: false);

        self::assertFalse($this->ruleSet()->accepts('69001'), 'The zone was limited by an inclusion: it does not take every code.');
        self::assertTrue($this->ruleSet()->accepts(null), 'An unknown postal code is still accepted.');
        self::assertStringContainsString('with no other inclusion the zone takes no known postal code of the country.', $this->warningAbout($lines, $ruleId));

        $this->insertStraightIntoTheTable('75*', excluded: false);
        AreaZipCodeRuleQuery::forgetRuleSets();
        $lines->lines = [];

        self::assertTrue($this->ruleSet()->accepts('75001'));
        self::assertFalse($this->ruleSet()->accepts('69001'));
        self::assertStringContainsString('the zone keeps its other inclusions only.', $this->warningAbout($lines, $ruleId));
    }

    private function warningAbout(AbstractTlogDestination $lines, int $ruleId): string
    {
        $warnings = array_values(array_filter($lines->lines, static fn (string $line): bool => str_contains($line, 'Postal code rule '.$ruleId.' of zone ')));
        self::assertCount(1, $warnings);

        return $warnings[0];
    }

    /**
     * Between two messages of a worker Symfony resets the services: a rule written by another
     * process, which no model hook of this one saw, is read from then on.
     */
    public function testTheResetOfTheServicesForgetsTheRulesRead(): void
    {
        $this->getService(ZipCodeRuleCacheListener::class);
        self::assertTrue($this->ruleSet()->accepts('20090'));
        $this->insertStraightIntoTheTable('20*', excluded: true);

        $this->getService('services_resetter')->reset();

        self::assertFalse($this->ruleSet()->accepts('20090'));
    }

    public function testANewMainRequestForgetsTheRulesReadAndASubRequestDoesNot(): void
    {
        self::assertTrue($this->ruleSet()->accepts('20090'));
        $this->insertStraightIntoTheTable('20*', excluded: true);
        $onRequest = $this->listenerOf(KernelEvents::REQUEST);

        $onRequest(new RequestEvent(static::$kernel, Request::create('/'), HttpKernelInterface::SUB_REQUEST), KernelEvents::REQUEST, $this->dispatcher());
        self::assertTrue($this->ruleSet()->accepts('20090'), 'A sub-request belongs to the request being served.');

        $onRequest(new RequestEvent(static::$kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST), KernelEvents::REQUEST, $this->dispatcher());
        self::assertFalse($this->ruleSet()->accepts('20090'));
    }

    public function testAConsoleCommandForgetsTheRulesRead(): void
    {
        self::assertTrue($this->ruleSet()->accepts('20090'));
        $this->insertStraightIntoTheTable('20*', excluded: true);

        $this->listenerOf(ConsoleEvents::COMMAND)(new ConsoleCommandEvent(null, new ArrayInput([]), new NullOutput()), ConsoleEvents::COMMAND, $this->dispatcher());

        self::assertFalse($this->ruleSet()->accepts('20090'));
    }

    /**
     * The listener the event dispatcher of the application holds for the event: the test
     * goes through the registration, not through the class.
     */
    private function listenerOf(string $eventName): callable
    {
        foreach ($this->dispatcher()->getListeners($eventName) as $listener) {
            if (\is_array($listener) && $listener[0] instanceof ZipCodeRuleCacheListener) {
                return $listener;
            }
        }

        self::fail('No postal code rule listener is registered on '.$eventName.'.');
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }

    private function ruleSet(): ZipCodeRuleSet
    {
        return AreaZipCodeRuleQuery::ruleSetFor((int) $this->area->getId(), (int) $this->country->getId());
    }

    private function insertStraightIntoTheTable(string $pattern, bool $excluded): int
    {
        $connection = $this->getPropelConnection();
        $statement = $connection->prepare(
            'INSERT INTO area_zip_code_rule (area_id, country_id, pattern, excluded, created_at, updated_at)'
            .' VALUES (:area_id, NULL, :pattern, :excluded, NOW(), NOW())',
        );
        $statement->execute([':area_id' => $this->area->getId(), ':pattern' => $pattern, ':excluded' => (int) $excluded]);

        return (int) $connection->lastInsertId();
    }

    /**
     * Tlog keeps its lines for the end of the request: a destination of the test reads them
     * as they come.
     */
    private function captureTheLog(): AbstractTlogDestination
    {
        $destination = new class extends AbstractTlogDestination {
            /**
             * @var list<string>
             */
            public array $lines = [];

            public function add($string): void
            {
                $this->lines[] = (string) $string;
            }

            public function write(&$res): void
            {
            }

            public function getTitle(): string
            {
                return 'Test';
            }

            public function getDescription(): string
            {
                return 'Keeps the lines for the test.';
            }
        };

        $log = Tlog::getInstance();
        $log->setLevel(Tlog::WARNING);
        (new \ReflectionProperty(Tlog::class, 'destinations'))->setValue($log, [$destination]);

        return $destination;
    }
}
