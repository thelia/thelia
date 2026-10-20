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

    public function testARowThatMatchesNothingIsIgnoredAndLoggedOnce(): void
    {
        $lines = $this->captureTheLog();
        $ruleId = $this->insertStraightIntoTheTable('***', excluded: true);

        self::assertTrue($this->ruleSet()->accepts('20090'), 'A pattern that matches nothing excludes nothing.');
        self::assertTrue($this->ruleSet()->accepts('75001'));

        $warnings = array_values(array_filter(
            $lines->lines,
            static fn (string $line): bool => str_contains($line, 'Postal code rule '.$ruleId.' of zone '),
        ));
        self::assertCount(1, $warnings, 'The rule is logged once for the request.');
        self::assertStringContainsString('Postal code rule '.$ruleId.' of zone '.$this->area->getId().' is ignored: "***" matches nothing.', $warnings[0]);
    }

    /**
     * Between two messages of a worker the services are reset: a rule written by another
     * process, which no model hook of this one saw, is read from then on.
     */
    public function testAResetOfTheServicesForgetsTheRulesRead(): void
    {
        self::assertTrue($this->ruleSet()->accepts('20090'));

        $this->insertStraightIntoTheTable('20*', excluded: true);

        self::assertTrue($this->ruleSet()->accepts('20090'), 'Kept for the request.');

        $this->getService(ZipCodeRuleCacheListener::class)->reset();

        self::assertFalse($this->ruleSet()->accepts('20090'));
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
