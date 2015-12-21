# Free Order

This module is used to terminate the order process when the order amount is 0,00. In this case, none of the traditional
payment modules applies.

## Installation

This module is bundled with Thelia standard distribution.

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is FreeOrder.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/free-order-module:~1.0
```

## Usage

The module is displayed as needed in the payment modules list of the order-invoice page. 