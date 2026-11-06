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
 * Adds a line to a cart through the front API, from a process of its own, the way a second
 * buyer does: its own kernel, its own connection, a real HTTP request handled by the kernel. Run
 * by CartItemConcurrentHoldApiTest with the environment of the test process, so that it works on
 * the same database. Prints, as JSON, the status of the answer and its body.
 *
 * Usage: php add-to-cart-through-the-api-in-another-process.php <token> <cart id> <sale element id> <quantity>
 */

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 3).'/tests/bootstrap.php';

[, $token, $cartId, $productSaleElementsId, $quantity] = $argv + [null, '', '0', '0', '1'];

$kernel = new Kernel('test', false);
$kernel->boot();

$request = Request::create(
    'http://localhost/api/front/cart_items',
    'POST',
    server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ],
    content: json_encode([
        'cart' => '/api/front/carts/'.(int) $cartId,
        'productSaleElements' => '/api/front/product_sale_elements/'.(int) $productSaleElementsId,
        'quantity' => (int) $quantity,
    ], \JSON_THROW_ON_ERROR),
);

$response = $kernel->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => substr((string) $response->getContent(), 0, 2000)], \JSON_THROW_ON_ERROR);

$kernel->terminate($request, $response);
$kernel->shutdown();
