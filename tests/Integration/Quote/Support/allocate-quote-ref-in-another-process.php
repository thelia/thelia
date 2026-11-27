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

/*
 * Takes the next reference of the quotes from a process of its own, the way a second request
 * does: its own kernel, its own connection, its own transaction. Run by QuoteRefConcurrencyTest
 * with the environment of the test process, so that it works on the same database. Prints, as
 * JSON, the reference it was given once its transaction is committed.
 *
 * Usage: php allocate-quote-ref-in-another-process.php [rollback]
 *
 * With "rollback", the transaction is rolled back instead of committed, and the reference is
 * printed all the same: it is the number that goes back to the series.
 */

use App\Kernel;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Quote\Service\QuoteRefAllocator;
use Thelia\Model\Map\QuoteTableMap;

require dirname(__DIR__, 4).'/tests/bootstrap.php';

$rollBack = 'rollback' === ($argv[1] ?? null);

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');

$container->get('thelia.translator');
$container->get('thelia.url.manager');
$request = Request::create('http://localhost');
$request->setSession(new Session(new MockArraySessionStorage()));
$container->get('request_stack')->push($request);

$connection = Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME);
$connection->beginTransaction();
$ref = $container->get(QuoteRefAllocator::class)->allocate($connection);
$rollBack ? $connection->rollBack() : $connection->commit();

echo json_encode(['ref' => $ref], \JSON_THROW_ON_ERROR);

$kernel->shutdown();
