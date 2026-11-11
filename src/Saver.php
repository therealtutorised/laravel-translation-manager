<?php

namespace HighSolutions\TranslationManager;

use HighSolutions\TranslationManager\Files\LangFileException;
use HighSolutions\TranslationManager\Files\LangFiles;
use HighSolutions\TranslationManager\Files\Signatures;
use HighSolutions\TranslationManager\Files\WriteLock;
use HighSolutions\TranslationManager\Models\Change;
use HighSolutions\TranslationManager\Models\Translation;

/**
 * Saves texts: every save is written into the lang file at once.
 */
class Saver
{

    public function __construct(
        protected LangFiles $files,
        protected Signatures $signatures,
        protected WriteLock $lock,
    ) {
    }

    /**
     * Save one text. Returns the row's status afterwards.
     *
     * @throws LangFileException  Nothing was saved.
     */
    public function save(string $locale, string $group, string $key, string $text, ?Actor $actor = null): string
    {
        return $this->saveMany($locale, $group, [$key => $text], $actor)[$key];
    }

    /**
     * Save several texts of one lang file; the file is written once.
     *
     * A text is written only when the file still holds what the tool expects:
     * the row's text, or the text from before the change (a deploy reset the
     * file). When the file holds a third text, the row keeps the new text as
     * a conflict and the file is left alone.
     *
     * @param  array<string, string>  $texts  Dotted key => text.
     * @return array<string, string>  Dotted key => status afterwards.
     *
     * @throws LangFileException  Nothing was saved.
     */
    public function saveMany(string $locale, string $group, array $texts, ?Actor $actor = null): array
    {
        $actor ??= Actor::current();

        return $this->lock->run(function () use ($locale, $group, $texts, $actor) {
            if (!$this->files->localeExists($locale) || !$this->files->groupExists($group)) {
                throw new LangFileException('Unknown language or file.');
            }

            $this->signatures->refresh();

            // Read just before writing, so a deploy in between isn't overwritten.
            $fileTexts = $this->files->read($locale, $group);
            $rows = Translation::where('locale', $locale)->where('group', $group)
                ->whereIn('key', array_map('strval', array_keys($texts)))->get()->keyBy('key');

            $write = [];
            $changed = [];
            $log = [];
            $statuses = [];

            foreach ($texts as $key => $text) {
                $key = (string) $key;
                $text = (string) $text;

                if ($text === '') {
                    throw new LangFileException('A text can not be empty.');
                }

                $inFile = $fileTexts[$key] ?? null;
                $row = $rows[$key] ?? new Translation(['locale' => $locale, 'group' => $group, 'key' => $key]);
                $pending = $row->exists && $row->value !== null && $row->status !== Translation::STATUS_SAVED;

                $stamp = ['value' => $text, 'ai' => false, 'changed_by' => $actor->id, 'changed_by_name' => $actor->name, 'changed_at' => now()];

                if ($inFile === null && in_array('', explode('.', $key), true)) {
                    throw new LangFileException('Not a valid key.');
                }

                // Taking over the file's text settles a conflict: the row follows the file again.
                if ($pending && $text === $inFile && $inFile !== $row->value) {
                    $log[] = [$row, Change::ACTION_EDIT, $row->value, $text];
                    $changed[] = $row->fill($stamp + ['status' => Translation::STATUS_SAVED, 'base_value' => null]);
                    $statuses[$key] = Translation::STATUS_SAVED;
                    continue;
                }

                if ($pending && $inFile !== $row->value && $inFile !== $row->base_value) {
                    $changed[] = $row->fill($stamp + ['status' => Translation::STATUS_CONFLICT]);
                    $log[] = [$row, Change::ACTION_CONFLICT, $inFile, $text];
                    $statuses[$key] = Translation::STATUS_CONFLICT;
                    continue;
                }

                // A saved or new row follows the file: its text is what version control holds.
                $base = $pending ? $row->base_value : $inFile;
                $old = $pending ? $row->value : $inFile;

                if ($text === $old && $text === $inFile) {
                    // Nothing to change; a text the tool hasn't stored yet gets its row.
                    if (!$row->exists || $row->value === null) {
                        $changed[] = $row->fill(['value' => $text, 'status' => Translation::STATUS_SAVED]);
                    }
                    $statuses[$key] = $row->status;
                    continue;
                }

                if ($text !== $inFile) {
                    $write[$key] = $text;
                }

                $back = $text === $base;
                $changed[] = $row->fill($stamp + [
                    'status' => $back ? Translation::STATUS_SAVED : Translation::STATUS_PUBLISHED,
                    'base_value' => $back ? null : $base,
                ]);
                $log[] = [$row, Change::ACTION_EDIT, $old, $text];
                $statuses[$key] = $row->status;
            }

            Translation::resolveConnection(config('translation-manager.connection'))->transaction(function () use ($changed, $log, $actor, $locale, $group, $write) {
                foreach ($changed as $row) {
                    $row->save();
                }

                Change::insert(array_map(fn ($entry) => [
                    'app' => config('translation-manager.app'),
                    'locale' => $entry[0]->locale,
                    'group' => $entry[0]->group,
                    'key' => $entry[0]->key,
                    'action' => $entry[1],
                    'old_value' => $entry[2],
                    'new_value' => $entry[3],
                    'user_id' => $actor->id,
                    'user_name' => $actor->name,
                    'created_at' => now(),
                ], $log));

                // Last, so a failed write rolls the rows back and a failed row leaves the file alone.
                $this->files->write($locale, $group, $write);
            });

            return $statuses;
        });
    }

    /**
     * Add keys that have no text yet. They reach the lang files with their first text.
     */
    public function addKeys(string $group, array $keys, array $locales, ?Actor $actor = null): void
    {
        $actor ??= Actor::current();

        if (!$this->files->groupExists($group)) {
            throw new LangFileException('Unknown file.');
        }

        $this->lock->run(function () use ($group, $keys, $locales, $actor) {
            foreach ($keys as $key) {
                if (in_array('', explode('.', (string) $key), true)) {
                    continue;
                }

                foreach ($locales as $locale) {
                    $row = Translation::firstOrCreate(
                        ['locale' => $locale, 'group' => $group, 'key' => $key],
                        ['status' => Translation::STATUS_PUBLISHED],
                    );

                    if ($row->wasRecentlyCreated) {
                        Change::create([
                            'app' => config('translation-manager.app'),
                            'locale' => $locale,
                            'group' => $group,
                            'key' => $key,
                            'action' => Change::ACTION_ADD,
                            'user_id' => $actor->id,
                            'user_name' => $actor->name,
                        ]);
                    }
                }
            }
        });
    }

}
