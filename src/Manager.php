<?php 

namespace HighSolutions\TranslationManager;

use HighSolutions\TranslationManager\Models\Translation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;

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

    public function missingKey($namespace, $group, $key)
    {
        $locales = in_array($namespace,  ['*', '']) ? $this->loadLocales() : $namespace;

        if(!in_array($group, $this->config['exclude_groups'])) {
            foreach($locales as $locale) {
                Translation::firstOrCreate([
                    'locale' => $locale,
                    'group' => $group,
                    'key' => $key,
                ]);
            }
        }
    }

    public function importTranslations($replace = false)
    {
        $counter = 0;
        foreach($this->files->directories($this->app['path.lang']) as $langPath) {
            $locale = basename($langPath);
            if(in_array($locale, $this->config['exclude_langs']))
                continue;

            foreach($this->files->allfiles($langPath) as $file) {
                $info = pathinfo($file);
                $group = $info['filename'];

                if(in_array($group, $this->config['exclude_groups']))
                    continue;

                $subLangPath = str_replace($langPath . DIRECTORY_SEPARATOR, "", $info['dirname']);
                if ($subLangPath != $langPath) {
                    $group = $subLangPath . "/" . $group;
                }
                $group = str_replace('\\', '/', $group);

                $translations = \Lang::getLoader()->load($locale, $group);
                if (!$translations || !is_array($translations))
                    continue;

                foreach(Arr::dot($translations) as $key => $value) {
                   if(is_array($value)) // process only string values
                        continue;
                    
                    $value = (string) $value;
                    $translation = Translation::firstOrNew([
                        'locale' => $locale,
                        'group' => $group,
                        'key' => $key,
                    ]);

                    // Check if the database is different then the files
                    $newStatus = $translation->value === $value || !$translation->value ? Translation::STATUS_SAVED : Translation::STATUS_CHANGED;
                    if($newStatus !== (int) $translation->status)
                        $translation->status = $newStatus;

                    // Only replace when empty, or explicitly told so
                    if($replace || !$translation->value)
                        $translation->value = $value;

                    $translation->save();

                    $counter++;
                }
            }
        }
        return $counter;
    }

    public function exportTranslations($group)
    {
        if(in_array($group, $this->config['exclude_groups']))
            return;

        if ($group == '*') {
            return $this->exportAllTranslations();
        }

        $tree = $this->makeTree(Translation::where('group', $group)->whereNotNull('value')->get());

        foreach($tree as $locale => $groups) {
            if(!isset($groups[$group]))
                continue;

            $translations = $groups[$group];
            ksort($translations);

            $path = $this->app->langPath() . '/' . $locale . '/' . $group . '.php';
            $output = "<?php\n\nreturn ".var_export($translations, true).";\n";

            $this->files->put($path, preg_replace("/ \R/", "\n", $output)); // get rid of trailing spaces before line breaks and store in file
        }

        Translation::where('group', $group)
            ->whereNotNull('value')
            ->where('status', '=', Translation::STATUS_CHANGED)
            ->update(array('status' => Translation::STATUS_SAVED));
    }

    public function exportAllTranslations()
    {
        $groups = Translation::whereNotNull('value')->selectDistinctGroup()->get('group');

        foreach($groups as $group){
            $this->exportTranslations($group->group);
        }
    }

    public function cleanTranslations()
    {
        Translation::whereNull('value')->delete();
    }

    public function truncateTranslations()
    {
        Translation::truncate();
    }

    protected function makeTree($translations)
    {
        $array = array();
        foreach($translations as $translation) {
            Arr::set(
                $array[$translation->locale][$translation->group], 
                $translation->key, 
                $translation->value
            );
        }
        return $array;
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
