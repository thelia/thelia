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

        $this->assertFormSameValues([
            'page' => '1',
            'product_id' => '1',
            'current_tab' => 'general',
            'thelia_form_product_modification_form[id]' => '1',
            'thelia_form_product_modification_form[success_url]' => 'http://localhost/admin/categories?category_id=1&page=1',
            'thelia_form_product_modification_form[locale]' => 'en_US',
            'thelia_form_product_modification_form[ref]' => 'PROD001',
            'thelia_form_product_modification_form[title]' => 'Horatio',
            'thelia_form_product_modification_form[chapo]' => 'Contemporary atypical chair',
            'thelia_form_product_modification_form[description]' => "Its design is based on a very simple idea\u{a0}:  atypical aesthetics for an everyday use. You may even choose to combine the various colours ! A specific look that will happily and impertinently fit with your furniture. ",
            'thelia_form_product_modification_form[postscriptum]' => "Dimensions : Width\u{a0}: 20'' – Depth: 19'' – Height: 42''",
            'thelia_form_product_modification_form[default_category]' => '1',
            'thelia_form_product_modification_form[virtual_document_id]' => '-1',
            'thelia_form_product_modification_form[brand_id]' => '1',
            'thelia_form_product_modification_form[visible]' => '1',
        ], $form);

        $form->setValues([
            'thelia_form_product_modification_form[ref]' => 'test ref',
            'thelia_form_product_modification_form[title]' => 'test title',
            'thelia_form_product_modification_form[chapo]' => 'test chapo',
            'thelia_form_product_modification_form[description]' => 'test description',
            'thelia_form_product_modification_form[postscriptum]' => 'test postscriptum',
            'thelia_form_product_modification_form[default_category]' => '3',
            'thelia_form_product_modification_form[brand_id]' => '3',
            'thelia_form_product_modification_form[visible]' => '1',
        ]);

        self::$client->submit($form);

        self::assertResponseIsSuccessful();

        $form = self::$client->getCrawler()->filter('#general form')->form([]);

        $this->assertFormSameValues([
            'page' => '1',
            'product_id' => '1',
            'current_tab' => 'general',
            'thelia_form_product_modification_form[id]' => '1',
            'thelia_form_product_modification_form[success_url]' => 'http://localhost/admin/categories?category_id=1&page=1',
            'thelia_form_product_modification_form[locale]' => 'en_US',
            'thelia_form_product_modification_form[ref]' => 'test ref',
            'thelia_form_product_modification_form[title]' => 'test title',
            'thelia_form_product_modification_form[chapo]' => 'test chapo',
            'thelia_form_product_modification_form[description]' => 'test description',
            'thelia_form_product_modification_form[postscriptum]' => 'test postscriptum',
            'thelia_form_product_modification_form[default_category]' => '3',
            'thelia_form_product_modification_form[virtual_document_id]' => '-1',
            'thelia_form_product_modification_form[brand_id]' => '3',
            'thelia_form_product_modification_form[visible]' => '1',
        ], $form);
    }
}
