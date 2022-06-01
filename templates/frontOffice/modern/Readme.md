> ⚠ This is the repository of Thelia default frontoffice template. All the pull requests on this repo will be ignored.
> If you want to create a project, please take a look at [thelia/thelia-project](https://github.com/thelia/thelia-project)
> If you want to contribute to Thelia, please take a look at [thelia/thelia](https://github.com/thelia/thelia)



# About
This Thelia template is based on [Symfony Encore](https://symfony.com/doc/current/frontend.html), it uses Smarty (Thelia default templating engine) for static content and [React](https://reactjs.org) for managing dynamic components (eg:cart).
Styling is done with [Tailwind](https://tailwindcss.com) and PostCSS, but can be overrided or switched, to use any other css preprocessor/ css framework.


## Prerequisites
* [NodeJS](https://nodejs.org/) (^10)
* [Yarn](https://yarnpkg.com/)


## Available commands
```console
$ yarn build
```
(compile & optimize assets for production)

```console
$ yarn start
```
(development mode with live-reload)


## Manual Installation
```console
$ yarn install && yarn build
```

## Components

This template is based around pages templates (located at the `root` of this directory) and a components system (in the `components` directory).

### Components system
- Component should be reusable and the more agnostic possible to allow them to be composed freely.
- Files relative to the component (html, css, js, images, ...) should all be inside the component directory to keep them organized and easy to move around.

## Retrieving compiled assets in pages
Symfony Encore use entries to identify the different JS bundles composing the template, those entries can be modified in the `webpack.config.js` file.
This pattern allow your js code to be split and used only on the relevant pages.

You declare them like:

``` javascript
Encore.addEntry('app', './assets/js/app.js')
	.addEntry('home', './assets/js/routes/home')
	.addEntry('category', './assets/js/routes/category')
	.addEntry('product', './assets/js/routes/product')
	.addEntry('register', './assets/js/routes/register')
	.addEntry('address', './assets/js/routes/address')
	.addEntry('delivery', './assets/js/routes/delivery');
```

and you use them like:

⚠️ **layout.tpl** ⚠️ (this is the main JS bundle, which is used for every pages of the website)
``` smarty
    {block name="css"}
      {encore_entry_link_tags entry="app"}
    {/block}
    {block name="javascript"}
      {encore_entry_script_tags entry="app"}
    {/block}
```

**product.html** (note the use of the *append* keyword in the smarty block, allowing us to keep the main bundle instead of replacing it)
```smarty
{block name="css" append}
  {encore_entry_link_tags entry="product"}
{/block}

{block name="javascript" append}
  {encore_entry_script_tags entry="product"}
{/block}
```

### Image & other assets
You can use the smarty function `{encore_manifest_file file="key/of/your/asset/in/the/manifest"}` to retrieve any assets compiled by Symfony Encore, the `manifest.json` file can be found inside the `dist` directory

```smarty
  <link rel="icon" type="image/png" sizes="32x32" href="{encore_manifest_file file="dist/favicons/favicon-32x32.png"}">
```


## Documentations
  * [Thelia](http://doc.thelia.net)
  * [Smarty](https://www.smarty.net/)
  * [Symfony Encore](https://symfony.com/doc/current/frontend.html)
  * [Tailwind](https://tailwindcss.com)
  * [React](https://reactjs.org)


## Useful extensions for developping with VS Code
  * [Prettier](https://marketplace.visualstudio.com/items?itemName=esbenp.prettier-vscode)
  * [ESlint](https://marketplace.visualstudio.com/items?itemName=dbaeumer.vscode-eslint)
  * [Tailwind CSS IntelliSense](https://marketplace.visualstudio.com/items?itemName=bradlc.vscode-tailwindcss)
  * [Headwind](https://marketplace.visualstudio.com/items?itemName=heybourn.headwind)

