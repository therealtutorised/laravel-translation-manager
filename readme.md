Laravel Translation Manager
=============================

Easy management of translations in Laravel.

About this fork
---------------

This repository is a fork of [highsolutions/laravel-translation-manager](https://github.com/highsolutions/laravel-translation-manager), started from its v1.5.0. We change it for our own use. The original package is never changed from here, and we don't open pull requests against it.

The sections below "Working on this fork" are the original package's documentation.

Working on this fork
--------------------

### Which code is whose

| Name | What it is |
|------|------------|
| `master` (`origin/master`) | Our version. The default branch. |
| `upstream/master` | The original package. Read-only. |

### Set up your clone

```bash
git clone git@github.com:therealtutorised/laravel-translation-manager.git
cd laravel-translation-manager
git remote add upstream https://github.com/highsolutions/laravel-translation-manager.git
git remote set-url --push upstream DISABLED
gh repo set-default therealtutorised/laravel-translation-manager
```

- `set-url --push upstream DISABLED` makes a push to the original impossible.
- `gh repo set-default` makes `gh pr create` open pull requests in this repository.

### Make a change

```bash
git switch master
git pull origin master
git switch -c feature/my-change
# work, commit
git switch master
git merge feature/my-change
git push origin master
```

Small changes can be committed on `master` directly. A pull request is optional: open one when you want a review.

### If you open a pull request, check its target

Because this is a fork, GitHub's website starts every new pull request with the original repository as the base. Before you click "Create pull request", set:

- **base repository:** `therealtutorised/laravel-translation-manager`
- **base:** `master`

`gh pr create` picks the right repository once `gh repo set-default` has been run.

### See what we changed

```bash
git fetch upstream
git diff upstream/master...master
```

### Take changes from the original

```bash
git fetch upstream
git switch -c chore/merge-upstream master
git merge upstream/master
# fix conflicts, run the tests
git switch master
git merge chore/merge-upstream
git push origin master
```

Don't use GitHub's "Sync fork" button: it merges on GitHub, where conflicts can't be fixed and tests can't be run first.

### Run the tests

The tests run in Orchestra Testbench against a MySQL database.

```bash
composer install
mysql -e "CREATE DATABASE translation_manager_test"
vendor/bin/phpunit
```

`phpunit.xml.dist` holds the default database settings (`root` without a password on `127.0.0.1`). For another login, copy it to `phpunit.xml` and change the `DB_*` values there; `phpunit.xml` is ignored by git.

### This repository is public

Anyone can read the code, commits and pull requests, and they stay readable after deletion. Never push secrets, API keys, real user data, texts copied from our applications, or internal host names. Settings belong in the `.env` and config of the application that installs the package.

### Licence

MIT, as in the original. Keep `LICENSE` and its copyright lines.

![Laravel-Translation-Manager by HighSolutions](https://raw.githubusercontent.com/highsolutions/laravel-translation-manager/master/intro.jpg)

Installation
------------

The package is not on Packagist. Add this repository and the package to your Laravel webapp's `composer.json` file:

```javascript
    "repositories": [
        { "type": "vcs", "url": "https://github.com/therealtutorised/laravel-translation-manager" }
    ],
    "require": {
        "tutorised/laravel-translation-manager": "^2.0"
    }
```

Run `composer update tutorised/laravel-translation-manager` to install the package. It needs PHP 8.3 and Laravel 12 or 13. The service provider is registered automatically.

Next, publish all package resources:

```bash
    php artisan vendor:publish --provider="HighSolutions\TranslationManager\ManagerServiceProvider"
```

This will add to your project:

    - migration - database table for storing translations
    - configuration - package configurations
    - views - configurable views for translation management
    - translations - translations for webinterface

Remember to launch migration: 

```bash
    php artisan migrate
```

Workflow
------------

This package doesn't replace the Translation system, only import/export PHP files to a database and make them editable in browser.

The workflow would be:

    - Import translations: Read all translation files and save them in the database
    - Translate all keys through the webinterface
    - Export: Write all translations back to the translation files.

Usage
------

You can access package in `http://yourdomain.com/translations` in default configuration. You can change as you pleased.

Configuration
-------------

| Setting name             | Description                                                             | Default value                                                                                                        |
|--------------------------|-------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| route                    | Route declaration (prefix, namespace, middlewares etc.)                 | [,'prefix' => 'translations', 'namespace' => 'HighSolutions\TranslationManager', 'middleware' => [,'web', 'auth',],] |
| delete_enabled           | Enable deletion of translations                                         | true                                                                                                                 |
| exclude_groups           | Exclude specific file groups (like validation, pagination, routes etc.) | []                                                                                                                   |
| exclude_langs            | Exclude specific langs and directories (like vendor and en, etc.) | []                                                                                                                   |
| sort_keys                | Export translations with keys output alphabetically.                    | false                                                                                                                |
| permissions              | Define whow and when can edit translations.                             |  env('APP_ENV') == 'local'                                                                   |


Commands
---------

### Import command

The import command will search through app/lang and load all strings in the database, so you can easily manage them.

```bash
    php artisan translations:import
```

Note: By default, only new strings are added. Translations already in the DB are kept the same. If you want to replace all values with the ones from the files, 
add the `--replace` (or `-R`) option: `php artisan translations:import --replace`

### Export command

The export command will write the contents of the database back to resources/lang php files.
This will overwrite existing translations and remove all comments, so make sure to backup your data before using.
Supply the group name to define which groups you want to publish.
If you want to export all groups, provide `*` as name of group.

```bash
    php artisan translations:export <group>
```

For example, `php artisan translations:export reminders` when you have 2 locales (en/pl), will write to `resources/lang/en/reminders.php` and `resources/lang/pl/reminders.php`

### Clean command

The clean command will search for all translation that are NULL and delete them, so your interface is a bit cleaner. Note: empty translations are never exported.

```bash
    php artisan translations:clean
```

### Reset command

The reset command simply clears all translation in the database, so you can start fresh (by a new import). Make sure to export your work if needed before doing this.

```bash
    php artisan translations:reset
```

Changelog
---------

2.0.0 (not released yet)
* Fork of highsolutions/laravel-translation-manager 1.5.0, composer name `tutorised/laravel-translation-manager`
* Requires PHP 8.3 and Laravel 12 or 13
* Removed: live editing (`transEditable`, the custom translator and its service provider), the missing-translation listener, the Find, Clone and Suffix commands, and the Find, Clean and Reset buttons on the page
* Clean and Reset stay as Artisan commands
* Removed settings: `basic_lang`, `highlight_locale_marked`, `live_translation_enabled`, `popup_placement`
* Removed routes: `/clean`, `/find`, `/custom-update`
* `symfony/finder` is no longer required
* Test setup with Orchestra Testbench

1.5.0
* Support Laravel 13.x

1.4.0
* Support Laravel 12.x

1.3.0
* Support Laravel 11.x

1.2.0
* Support Laravel 9.x and 10.x

1.1.0
* Support Laravel 7.x and 8.x

1.0.0
* Support Laravel 6.0

0.6.0
* No STRICT_MODE needed anymore

0.5.1
* Fix searching translations

0.5.0
* Change the views path

0.4.7
* remove closures in config file

0.4.5
* Laravel 5.6 support

0.4.4
* Fix translation title popup

0.4.3
* New configurations (popup placement and basic language)
* Update documentation about necessary JS scripts

0.4.0

* New commands: clone and suffix
* Improve export command

0.3.7

* New configuration option to exclude langs

0.3.6

* Support auto-discovery and Laravel 5.5

0.3.0

* Support for subdirectories
* Support for array translations
* New design
* Permission management
* Translations for view
* Live editing

0.2.0

* Barryvdh version of package

Credits
-------

This package was originally created by [Barry vd. Heuvel](https://github.com/barryvdh) and is available here: [laravel-feed](https://github.com/barryvdh/laravel-translation-manager).

It was then developed by [HighSolutions](http://highsolutions.pl), software house from Poland in love in Laravel. This fork starts from their version 1.5.0.
