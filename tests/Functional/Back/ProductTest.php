<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Functional\Back;

use Thelia\Tests\Functional\WebTestCase;

class ProductTest extends WebTestCase
{
    public function testOpen(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/products/update?product_id=1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/products/update?product_id=2');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/products/update?product_id=3');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/products/update?product_id=4');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/products/update?product_id=5');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/products/update?product_id=6');

        self::assertResponseIsSuccessful();
    }

    public function testSaveGeneral(): void
    {
        $this->loginAdmin();

        $crawler = static::$client->request('GET', '/admin/products/update?product_id=1&page=1');

        self::assertResponseIsSuccessful();

        $formCrawlerNode = $crawler->filter('#general form');

        $form = $formCrawlerNode->form([]);

        //        $this->assertFormSameValues([
        //            'page' => '1',
        //            'product_id' => '1',
        //            'current_tab' => 'general',
        //            'thelia_product_modification[id]' => '1',
        //            'thelia_product_modification[success_url]' => 'http://localhost/admin/categories?category_id=1&page=1',
        //            'thelia_product_modification[locale]' => 'en_US',
        //            'thelia_product_modification[ref]' => 'PROD001',
        //            'thelia_product_modification[title]' => 'Horatio',
        //            'thelia_product_modification[chapo]' => 'Contemporary atypical chair',
        //            'thelia_product_modification[description]' => "Its design is based on a very simple idea\u{a0}:  atypical aesthetics for an everyday use. You may even choose to combine the various colours ! A specific look that will happily and impertinently fit with your furniture. ",
        //            'thelia_product_modification[postscriptum]' => "Dimensions : Width\u{a0}: 20'' – Depth: 19'' – Height: 42''",
        //            'thelia_product_modification[default_category]' => '1',
        //            'thelia_product_modification[virtual_document_id]' => '-1',
        //            'thelia_product_modification[brand_id]' => '1',
        //            'thelia_product_modification[visible]' => '1',
        //        ], $form);

        $form->setValues([
            'thelia_product_modification[ref]' => 'test ref',
            'thelia_product_modification[title]' => 'test title',
            'thelia_product_modification[chapo]' => 'test chapo',
            'thelia_product_modification[description]' => 'test description',
            'thelia_product_modification[postscriptum]' => 'test postscriptum',
            'thelia_product_modification[default_category]' => '3',
            'thelia_product_modification[brand_id]' => '3',
            'thelia_product_modification[visible]' => '1',
        ]);

        self::$client->request($form->getMethod(), $form->getUri().'?save_mode=stay', $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects(null, 302);

        $crawler = self::$client->followRedirect();

        $form = $crawler->filter('#general form')->form([]);

        $this->assertFormSameValues([
            'page' => '1',
            'product_id' => '1',
            'current_tab' => 'general',
            'thelia_product_modification[id]' => '1',
            'thelia_product_modification[success_url]' => 'http://localhost/admin/categories?category_id=3&page=1',
            'thelia_product_modification[locale]' => 'en_US',
            'thelia_product_modification[ref]' => 'test ref',
            'thelia_product_modification[title]' => 'test title',
            'thelia_product_modification[chapo]' => 'test chapo',
            'thelia_product_modification[description]' => 'test description',
            'thelia_product_modification[postscriptum]' => 'test postscriptum',
            'thelia_product_modification[default_category]' => '3',
            'thelia_product_modification[virtual_document_id]' => '-1',
            'thelia_product_modification[brand_id]' => '3',
            'thelia_product_modification[visible]' => '1',
        ], $form);
    }

    protected function tearDown(): void
    {
        $crawler = static::$client->request('GET', '/admin/products/update?product_id=1&page=1');

        self::assertResponseIsSuccessful();

        $formCrawlerNode = $crawler->filter('#general form');

        $form = $formCrawlerNode->form([]);

        $form->setValues([
            'thelia_product_modification[ref]' => 'PROD001',
            'thelia_product_modification[title]' => 'Horatio',
            'thelia_product_modification[chapo]' => 'Contemporary atypical chair',
            'thelia_product_modification[description]' => "Its design is based on a very simple idea\u{a0}:  atypical aesthetics for an everyday use. You may even choose to combine the various colours ! A specific look that will happily and impertinently fit with your furniture. ",
            'thelia_product_modification[postscriptum]' => "Dimensions : Width\u{a0}: 20'' – Depth: 19'' – Height: 42''",
            'thelia_product_modification[default_category]' => '1',
            'thelia_product_modification[brand_id]' => '1',
            'thelia_product_modification[visible]' => '1',
        ]);

        self::$client->request($form->getMethod(), $form->getUri().'?save_mode=stay', $form->getPhpValues(), $form->getPhpFiles());

        parent::tearDown();
    }
}
