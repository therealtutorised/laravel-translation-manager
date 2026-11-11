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

Switch the tool on in the `.env` of the server where texts are edited, and run the migrations there:

```bash
    TRANSLATION_MANAGER_ENABLED=true
```

```bash
    php artisan migrate
```

Where the tool is off (the default), its pages don't exist, its tables are not created and lang files are not checked or written. The package brings its own migrations; they are not published.

To change the configuration, views or the page's own texts, publish them:

```bash
    php artisan vendor:publish --provider="HighSolutions\TranslationManager\ManagerServiceProvider"
```

Workflow
------------

The lang files stay the truth. The tool keeps a copy of every text in the database and remembers which texts were changed in the browser and are not in version control yet.

    - Import: the first import reads all lang files into the database
    - Edit a text in the browser: it is written into its lang file at once, and only that line changes. Key order, comments, indents and quote styles of the file stay as they are
    - Commit the changed lang files from that server to version control
    - Deploy: a deploy resets the lang files. On the next request the tool writes the texts that are not committed yet back into them

There is no Publish or Export step.

### Statuses

| Status      | Meaning                                                                                   |
|-------------|-------------------------------------------------------------------------------------------|
| `saved`     | The same as the lang file and version control                                             |
| `published` | Written to the lang file on this server, waiting for a commit                             |
| `conflict`  | The lang file holds a third text, usually a developer's change. Nothing is overwritten until a person decides |

### What happens after a deploy

Before every request the tool compares each lang file's modification time, size and inode with what it saw last. For a changed file it compares every key with its row:

| Row         | Text in the file                           | Result                                                    |
|-------------|--------------------------------------------|-----------------------------------------------------------|
| none        | any                                        | Stored as `saved`                                         |
| `saved`     | another text, or the key is gone           | The file wins: the row takes the text or is deleted       |
| `published` | the row's text                             | `saved`: version control now holds it                     |
| `published` | the text from before the edit              | The row's text is written back into the file              |
| `published` | an older text that the tool wrote          | That version was committed; the row's text is written back |
| `published` | anything else                              | `conflict`                                                |
| `conflict`  | the row's text, or the text from before the edit | Handled like `published` again                      |

Every change is recorded in the `tm_changes` table: who, when, the old and the new text.

Usage
------

You can access package in `http://yourdomain.com/translations` in default configuration. You can change as you pleased.

Configuration
-------------

| Setting name        | Description                                                                 | Default value                                  |
|---------------------|-----------------------------------------------------------------------------|------------------------------------------------|
| enabled             | Switch the tool on                                                          | env('TRANSLATION_MANAGER_ENABLED', false)      |
| connection          | Database connection for the tool's tables                                   | env('TRANSLATION_MANAGER_CONNECTION'), the default connection |
| app                 | Name of the application, stored on every row. Applications that share the tables see only their own rows | env('TRANSLATION_MANAGER_APP', 'app') |
| source_locale       | The language texts are written in first. New keys in other languages are placed as in this language's file | env('TRANSLATION_MANAGER_SOURCE_LOCALE'), the application's locale |
| storage_path        | Folder for the tool's own files. It must survive deploys                    | storage_path('app/translation-manager')        |
| user_name_attribute | Attribute of the signed-in user that the change log stores as the name      | 'name'                                         |
| route               | Route declaration (prefix, namespace, middlewares etc.)                     | ['prefix' => 'translations', 'namespace' => 'HighSolutions\TranslationManager', 'middleware' => ['web', 'auth']] |
| delete_enabled      | Enable deletion of keys in the grid. It removes the tool's rows only; the lang files keep the key | false                    |
| exclude_groups      | Exclude specific file groups (like validation, pagination, routes etc.)     | []                                             |
| exclude_langs       | Exclude specific langs and directories (like vendor and en, etc.)           | ['vendor']                                     |
| permissions         | Define who and when can edit translations.                                  | env('APP_ENV') == 'local'                      |

The web server's user needs write access to the lang files and their folders.

Commands
---------

### Import command

The import command checks every lang file against the database with the rules above. Run it once after installing; after that the check before each request keeps the database current.

```bash
    php artisan translations:import
```

### Clean command

The clean command deletes keys that were added in the browser and never got a text.

```bash
    php artisan translations:clean
```

### Reset command

The reset command deletes this application's rows, so you can start fresh with a new import. Commit the lang files first: after a reset the tool no longer knows which texts were waiting for a commit, and the next deploy would not restore them.

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
* New tables `tm_translations`, `tm_changes` and `tm_grants`; `ltm_translations` is no longer used
* A save writes the text into its lang file at once and changes only that line (`nikic/php-parser`)
* Import follows fixed rules instead of the `--replace` option, and runs in batches
* Texts that are not committed yet are written back after a deploy, before the next request
* Change log of every edit
* New settings: `enabled`, `connection`, `app`, `source_locale`, `storage_path`, `user_name_attribute`; `delete_enabled` is now off by default
* Removed: Publish and Export (the buttons, the `/publish` route, the `translations:export` command) and the `sort_keys` setting

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
