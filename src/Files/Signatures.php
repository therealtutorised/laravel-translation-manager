<?php

namespace HighSolutions\TranslationManager\Files;

/**
 * Remembers each lang file's modification time, size and inode from the last time
 * the tool read or wrote it. A file whose signature differs was changed by
 * something else, usually a deploy.
 */
class Signatures
{

    /** @var array<string, string>|null  "locale/group" => signature */
    protected $known;

    public function __construct(protected string $directory)
    {
    }

    public static function of(string $path): ?string
    {
        clearstatcache(true, $path);
        $stat = @stat($path);

        return $stat === false ? null : $stat['mtime'] . ':' . $stat['size'] . ':' . $stat['ino'];
    }

    /**
     * The signature saved for a file, or null when the tool hasn't seen it.
     */
    public function get(string $name): ?string
    {
        return $this->all()[$name] ?? null;
    }

    public function all(): array
    {
        if ($this->known === null) {
            $json = @file_get_contents($this->file());
            $known = $json === false ? null : json_decode($json, true);
            $this->known = is_array($known) ? $known : [];
        }

        return $this->known;
    }

    /**
     * Read the saved list again: another request may have changed it.
     */
    public function refresh(): void
    {
        $this->known = null;
    }

    public function set(string $name, ?string $signature): void
    {
        $this->all();

        if ($signature === null) {
            unset($this->known[$name]);
        } else {
            $this->known[$name] = $signature;
        }
    }

    public function save(): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        // Write a temp file and rename it, so a reader never sees half a list.
        $temp = $this->file() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temp, json_encode($this->all())) === false || !@rename($temp, $this->file())) {
            @unlink($temp);

            throw new LangFileException('The tool\'s storage folder is not writable.');
        }
    }

    protected function file(): string
    {
        return $this->directory . '/signatures.json';
    }

}
