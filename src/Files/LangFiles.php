<?php

namespace HighSolutions\TranslationManager\Files;

/**
 * The application's lang files: which ones exist, what they hold, and writing texts into them.
 */
class LangFiles
{

    public function __construct(
        protected string $langPath,
        protected LangWriter $writer,
        protected Signatures $signatures,
        protected array $excludeLocales = [],
        protected array $excludeGroups = [],
        protected ?string $sourceLocale = null,
    ) {
    }

    /**
     * Every lang file with its current signature.
     *
     * @return array<string, string>  "locale/group" => signature
     */
    public function all(): array
    {
        $files = [];

        foreach (@scandir($this->langPath) ?: [] as $locale) {
            if (!$this->validName($locale) || in_array($locale, $this->excludeLocales) || !is_dir($this->langPath . '/' . $locale)) {
                continue;
            }

            foreach ($this->groupsIn($this->langPath . '/' . $locale) as $group) {
                if (in_array($group, $this->excludeGroups)) {
                    continue;
                }

                $signature = Signatures::of($this->langPath . '/' . $locale . '/' . $group . '.php');
                if ($signature !== null) {
                    $files[$locale . '/' . $group] = $signature;
                }
            }
        }

        return $files;
    }

    protected function groupsIn(string $directory, string $prefix = ''): array
    {
        $groups = [];

        foreach (@scandir($directory) ?: [] as $entry) {
            if (is_dir($directory . '/' . $entry)) {
                if ($this->validName($entry)) {
                    $groups = array_merge($groups, $this->groupsIn($directory . '/' . $entry, $prefix . $entry . '/'));
                }
            // Files with other characters in their name are left alone.
            } elseif (str_ends_with($entry, '.php') && $this->validName(substr($entry, 0, -4))) {
                $groups[] = $prefix . substr($entry, 0, -4);
            }
        }

        return $groups;
    }

    /**
     * The path of a lang file. Names that could point outside the lang folder are refused.
     *
     * @throws LangFileException
     */
    public function path(string $locale, string $group): string
    {
        $segments = array_merge([$locale], explode('/', $group));

        foreach ($segments as $segment) {
            if (!$this->validName($segment)) {
                throw new LangFileException('Not a valid language or file name.');
            }
        }

        return $this->langPath . '/' . implode('/', $segments) . '.php';
    }

    /**
     * Letters, digits, "_" and "-" only: no dots or slashes, so no way out of the folder.
     */
    protected function validName(string $name): bool
    {
        return preg_match('/^[\p{L}\p{N}_-]+\z/u', $name) === 1;
    }

    public function exists(string $locale, string $group): bool
    {
        return is_file($this->path($locale, $group));
    }

    /**
     * True when the file exists in at least one language.
     */
    public function groupExists(string $group): bool
    {
        foreach (array_keys($this->all()) as $name) {
            if (explode('/', $name, 2)[1] === $group) {
                return true;
            }
        }

        return false;
    }

    public function localeExists(string $locale): bool
    {
        return !in_array($locale, $this->excludeLocales)
            && $this->validName($locale)
            && is_dir($this->langPath . '/' . $locale);
    }

    /**
     * The texts of a lang file as dotted key => text. A missing file has none.
     *
     * @return array<string, string>
     */
    public function read(string $locale, string $group): array
    {
        return $this->load($this->path($locale, $group));
    }

    protected function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        // Without this a cached, older version of the file could be returned.
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }

        $texts = (static fn () => include $path)();

        return is_array($texts) ? $this->flatten($texts) : [];
    }

    protected function flatten(array $texts, string $prefix = ''): array
    {
        $flat = [];

        foreach ($texts as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, $prefix . $key . '.');
            } elseif (is_scalar($value)) {
                $flat[$prefix . $key] = (string) $value;
            }
        }

        return $flat;
    }

    /**
     * Write texts into a lang file, changing only those keys.
     *
     * The new source is checked before it replaces the file: it must load and
     * hold exactly the old texts plus the changes.
     *
     * @param  array<string, string>  $changes  Dotted key => text.
     *
     * @throws LangFileException  Nothing is written.
     */
    public function write(string $locale, string $group, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $path = $this->path($locale, $group);
        $exists = is_file($path);
        $source = $this->sourceLocale && $this->sourceLocale !== $locale ? $this->path($this->sourceLocale, $group) : null;

        $code = $this->writer->apply(
            $exists ? file_get_contents($path) : '',
            $changes,
            $source && is_file($source) ? file_get_contents($source) : null,
        );

        if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0775, true)) {
            throw new LangFileException('The language folder could not be created.');
        }

        $temp = dirname($path) . '/.' . basename($path) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        try {
            if (@file_put_contents($temp, $code) === false) {
                throw new LangFileException('The lang file is not writable.');
            }

            $expected = array_replace($this->load($path), array_map('strval', $changes));
            $written = $this->load($temp);
            ksort($expected);
            ksort($written);

            if ($expected !== $written) {
                throw new LangFileException('The change would have altered other texts in the file.');
            }

            @chmod($temp, $exists ? fileperms($path) & 0777 : 0664);

            // Rename over the old file, so a reader never sees half a file.
            if (!@rename($temp, $path)) {
                throw new LangFileException('The lang file is not writable.');
            }
        } catch (\Throwable $e) {
            @unlink($temp);

            throw $e instanceof LangFileException ? $e : new LangFileException('The new file does not load: ' . $e->getMessage());
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }

        // Remember our own write, so it isn't taken for a deploy.
        $this->signatures->set($locale . '/' . $group, Signatures::of($path));
        $this->signatures->save();
    }

}
