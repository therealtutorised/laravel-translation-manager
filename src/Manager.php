<?php 

namespace HighSolutions\TranslationManager;

use HighSolutions\TranslationManager\Models\Translation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;

class Manager
{

    /** @var \Illuminate\Foundation\Application  */
    protected $app;
    /** @var \Illuminate\Filesystem\Filesystem  */
    protected $files;

    protected $config;

    public function __construct(Application $app, Filesystem $files)
    {
        $this->app = $app;
        $this->files = $files;
        $this->config = app()['config']['translation-manager'];
    }

    /**
     * Add a key without a text in every language the tool knows.
     */
    public function missingKey($namespace, $group, $key)
    {
        $locales = in_array($namespace,  ['*', '']) ? $this->loadLocales() : $namespace;

        if(!in_array($group, $this->config['exclude_groups'])) {
            $this->app->make(Saver::class)->addKeys($group, [$key], $locales);
        }
    }

    /**
     * Check every lang file against the rows. Returns the number of texts read.
     */
    public function importTranslations()
    {
        return $this->app->make(Importer::class)->importAll();
    }

    public function cleanTranslations()
    {
        Translation::whereNull('value')->delete();
    }

    public function truncateTranslations()
    {
        // Not truncate: other applications can have rows in the same table.
        Translation::query()->delete();
    }

    public function getConfig($key = null)
    {
        if($key == null)
            return $this->config;

        return $this->config[$key];
    }
    
    public function loadLocales()
    {
        $locales = Translation::groupBy('locale')
            ->select('locale')
            ->get()
            ->pluck('locale')
            ->all();
        
        $locales = array_merge([config('app.locale')], $locales);
        return array_unique($locales);
    }

}
