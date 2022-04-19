# Simple Mod Maker
[![SMF 2.1](https://img.shields.io/badge/SMF-2.1-ed6033.svg?style=flat)](https://github.com/SimpleMachines/SMF2.1)
![License](https://img.shields.io/github/license/dragomano/simple-mod-maker)
![Hooks only: Yes](https://img.shields.io/badge/Hooks%20only-YES-blue)
![PHP](https://img.shields.io/badge/PHP-^7.4-blue.svg?style=flat)
[![Crowdin](https://badges.crowdin.net/simple-mod-maker/localized.svg)](https://crowdin.com/project/simple-mod-maker)

* **Tested on:** PHP 7.4.29
* **Languages:** English, Russian

## Description
Would you like to spend less time creating localization files, JS/CSS and more on working with the code? This mod provides this opportunity!
This SMF mod skeleton generator will help you quickly generate the mod's settings and create simple tables in the database.
You just need to set the necessary options, click "Build" and get a modification package ready for installation on the SMF forum.

## Features
* Copyright management: name, email, license, etc.
* Creating templates for readme files added to the package.
* Simply adding of the desired hooks you want to use. The hooks you once added are saved in the settings and are already displayed in the drop-down list when creating the next project. In addition, there is also a preset available the first time you use the generator.
* Mod Settings Constructor - select option type, specify name and translations.
* DB Table Constructor - if your application needs them.
* Creating blanks for JS/CSS files, template file, language files, etc.

## What's under the hood?
* [Nette PHP Generator](https://github.com/nette/php-generator), for generating the working class of modifications.
* [Alpine.js](https://github.com/alpinejs/alpine), for the wizards in the frontend.
* [PhpZip](https://github.com/Ne-Lexa/php-zip), for packaging received files into a zip archive.
* [TomSelect](https://github.com/orchidjs/tom-select), for the hook list search field.

If you liked this mod, then give a star ⭐️ to this project.
