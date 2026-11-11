<?php

namespace HighSolutions\TranslationManager\Tests;

use HighSolutions\TranslationManager\ManagerServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{

    // Migrates once per run (the provider loads the migrations) and rolls each test back.
    use RefreshDatabase;

    /** @var string A scratch folder per test for lang files and the tool's own files. */
    protected $scratch;

    protected function getPackageProviders($app)
    {
        return [ManagerServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        // The web middleware encrypts cookies, which needs an app key.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        $this->scratch = sys_get_temp_dir() . '/translation-manager-' . bin2hex(random_bytes(6));
        mkdir($this->scratch . '/lang', 0777, true);

        $app->useLangPath($this->scratch . '/lang');
        $app['config']->set('app.locale', 'fi');
        $app['config']->set('translation-manager.enabled', true);
        $app['config']->set('translation-manager.storage_path', $this->scratch . '/storage');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->scratch);

        parent::tearDown();
    }

    /**
     * Write a lang file the way a deploy would: behind the tool's back.
     */
    protected function putLang(string $locale, string $group, string $code): string
    {
        $path = $this->scratch . "/lang/{$locale}/{$group}.php";
        $exists = is_file($path);
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $code);

        // Signatures see whole seconds, so a rewrite in the same second gets a later time.
        if ($exists) {
            touch($path, filemtime($path) + 5);
        }

        return $path;
    }

    protected function lang(string $locale, string $group): string
    {
        return file_get_contents($this->scratch . "/lang/{$locale}/{$group}.php");
    }

}
