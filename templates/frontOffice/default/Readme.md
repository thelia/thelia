Readme
======

## This is the repository of Thelia default frontoffice template. All the pull requests on this repo will be ignored.
### If you want to create a project, please take a look at [thelia/thelia-project](https://github.com/thelia/thelia-project)
### If you want to contribute to Thelia, please take a look at [thelia/thelia](https://github.com/thelia/thelia)

Thelia
------
[![Build Status](https://travis-ci.org/thelia/thelia.png?branch=master)](https://travis-ci.org/thelia/thelia) [![License](https://poser.pugx.org/thelia/thelia/license.png)](https://packagist.org/packages/thelia/thelia) [![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/thelia/thelia/badges/quality-score.png?s=61e3e04a69bffd71c29b08e5392080317a546716)](https://scrutinizer-ci.com/g/thelia/thelia/)

[Thelia](http://thelia.net/) is an open source tool for creating e-business websites and managing online content. This software is published under LGPL.

This is the new major version of Thelia.

You can download this version and have a try or take a look at the  source code (or anything you wish, respecting LGPL).  See http://thelia.net/ web site for more information.

A repository containing all thelia modules is available at this address : https://github.com/thelia-modules

How to update this template
---------------------------
If you want to customize the default template of Thelia, there are two possible solutions :

### Simple configuration
The simple process to update this template is to work into the `assets/src` directory.
In fact, this folder contain the non minified version of assets.

You can change change css rules and js code easily.

### Advanced configuration
This method is more oriented for frontend developers. You have to work with Less, Grunt and Bower.

So, after installing Grunt and Bower, do : ```bower init``` and ```npm install```.

The Gruntfile include the watch component, so with ```grunt watch```, Grunt is always listening assets update and recompile theme automatically.

The less files are into `assets/src/less` directory. After updating your less rules, do `grunt` to recompile your assets.
The compiled assets are put into the `assets/dist` directory.