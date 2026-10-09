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

namespace Thelia\Tests\Unit\Core\Propel;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Propel\Schema\SchemaCombiner;

/**
 * The combined schema marks where each table comes from, by the path of its schema: a
 * folder name a module ships, that must not end the comment nor add markup.
 */
final class SchemaCombinerTest extends TestCase
{
    public function testThePathOfASchemaCannotCloseItsMarkerNorAddMarkup(): void
    {
        $folder = '/srv/modules/x--><table name="evil"><column name="id" type="INTEGER"/></table><!--'."\u{202E}";
        $source = self::schema('sample_one');
        $source->documentURI = $folder.'/schema.xml';
        $external = self::schema('sample_two');
        $external->documentURI = $folder.'/external.xml';

        $combined = (new SchemaCombiner([$source], [$external]))->getCombinedDocument('TheliaMain');

        self::assertNotNull($combined);
        $written = $combined->saveXML();
        $reread = new \DOMDocument();
        self::assertTrue($reread->loadXML($written), $written);
        self::assertSame(['sample_one'], array_map(static fn (\DOMElement $table): string => $table->getAttribute('name'), iterator_to_array($reread->getElementsByTagName('table'))));
        self::assertStringContainsString("Start of schema from '/srv/modules/x- -><table name=\"evil\">", $written);
        self::assertSame('/srv/modules/x- -><table name="evil"><column name="id" type="INTEGER"/></table><!- -?/external.xml', $reread->getElementsByTagName('external-schema')->item(0)?->textContent);
    }

    /**
     * The external-schema references of a source schema are removed and said so, by the
     * filename they carried: a text the module wrote, held to the same rule.
     */
    public function testTheFilenameOfARemovedReferenceCannotCloseItsNotice(): void
    {
        $source = self::schema('sample_one', '<external-schema filename="x--&gt;&lt;table name=&quot;evil&quot;/&gt;&lt;!--"/>');
        $source->documentURI = '/srv/modules/Sample/Config/schema.xml';

        $combined = (new SchemaCombiner([$source]))->getCombinedDocument('TheliaMain');

        self::assertNotNull($combined);
        $written = $combined->saveXML();
        $reread = new \DOMDocument();
        self::assertTrue($reread->loadXML($written), $written);
        self::assertSame(['sample_one'], array_map(static fn (\DOMElement $table): string => $table->getAttribute('name'), iterator_to_array($reread->getElementsByTagName('table'))));
        self::assertStringContainsString("external-schema reference to 'x- -><table name=\"evil\"/><!- -' removed", $written);
    }

    private static function schema(string $table, string $trailingElements = ''): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML(\sprintf('<?xml version="1.0"?><database name="TheliaMain" defaultIdMethod="native"><table name="%s"><column name="id" type="INTEGER" primaryKey="true"/></table>%s</database>', $table, $trailingElements));

        return $document;
    }
}
