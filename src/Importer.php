<?php

namespace HighSolutions\TranslationManager;

use HighSolutions\TranslationManager\Files\LangFileException;
use HighSolutions\TranslationManager\Files\LangFiles;
use HighSolutions\TranslationManager\Files\Signatures;
use HighSolutions\TranslationManager\Files\WriteLock;
use HighSolutions\TranslationManager\Models\Change;
use HighSolutions\TranslationManager\Models\Translation;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the rows in line with the lang files, and writes texts that are not
 * committed yet back into files that a deploy has reset.
 *
 * Per key, the file's text is compared with the row:
 *
 * - no row: the text is inserted as saved.
 * - saved row: the file wins. A developer changed or removed the key.
 * - published row, file holds the row's text: kept; marked saved when a
 *   deploy rewrote the file, because version control now holds the text.
 * - published row, file holds the text from before the change: a deploy reset
 *   the file, so the row's text is written back.
 * - published row, file holds an older text the tool wrote: that version was
 *   committed. It becomes the new base, and the row's text is written back.
 * - published row, file holds anything else: conflict. Nothing is written.
 * - conflict row: kept until the file holds the row's text, or the text from
 *   before the change again; then it is handled like a published row.
 */
class Importer
{

    public function __construct(
        protected LangFiles $files,
        protected Signatures $signatures,
        protected WriteLock $lock,
    ) {
    }

    /**
     * Check every lang file. Returns the number of texts read.
     */
    public function importAll(): int
    {
        return $this->lock->run(function () {
            $this->signatures->refresh();
            $current = $this->files->all();

            // Files that are gone still have rows to check.
            $names = array_unique(array_merge(array_keys($current), $this->namesWithRows()));

            return $this->importFiles($names, $current);
        });
    }

    /**
     * Check only the files that changed since the tool last saw them. Cheap
     * when nothing changed, so it can run on every request.
     */
    public function importChanged(): int
    {
        // Before the tables exist there is nothing to do, and no reason to queue for the lock.
        if ($this->changedNames($this->files->all()) === [] || !$this->tablesExist()) {
            return 0;
        }

        return $this->lock->run(function () {
            // Look again under the lock: another request may have done the work.
            $this->signatures->refresh();
            $current = $this->files->all();
            $names = $this->changedNames($current);

            if ($names === []) {
                return 0;
            }

            return $this->importFiles($names, $current);
        });
    }

    protected function changedNames(array $current): array
    {
        $known = $this->signatures->all();

        return array_keys(array_diff_assoc($current, $known) + array_diff_key($known, $current));
    }

    protected function namesWithRows(): array
    {
        return Translation::query()
            ->select('locale', 'group')->distinct()->get()
            ->map(fn ($row) => $row->locale . '/' . $row->group)
            ->all();
    }

    protected function tablesExist(): bool
    {
        return Schema::connection(config('translation-manager.connection'))->hasTable('tm_translations');
    }

    protected function importFiles(array $names, array $current): int
    {
        $count = 0;

        foreach ($names as $name) {
            [$locale, $group] = explode('/', $name, 2);

            // Only a file the tool has seen before can have been rewritten by a deploy.
            $known = $this->signatures->get($name);
            $rewritten = $known !== null && $known !== ($current[$name] ?? null);

            try {
                $count += $this->importFile($locale, $group, $rewritten);
            } catch (\Throwable $e) {
                // One broken file (a syntax error, say) must not block the others.
                report($e);
            }

            // A failure leaves the file as it was; its signature is saved all
            // the same, so the next full import retries, not every request.
            $this->signatures->set($name, Signatures::of($this->files->path($locale, $group)));
        }

        $this->signatures->save();

        return $count;
    }

    /**
     * Apply the rules to one file. Returns the number of texts in the file.
     *
     * @param  bool  $rewritten  Something other than the tool changed the file since the last check.
     */
    public function importFile(string $locale, string $group, bool $rewritten = false): int
    {
        $texts = $this->files->read($locale, $group);
        $rows = Translation::where('locale', $locale)->where('group', $group)->get()->keyBy('key');

        $inserts = [];
        $deletes = [];
        $updates = [];
        $writeBack = [];
        $log = [];
        $now = now();

        foreach ($texts as $key => $text) {
            if (!$rows->has((string) $key)) {
                $inserts[] = [
                    'app' => config('translation-manager.app'),
                    'locale' => $locale,
                    'group' => $group,
                    'key' => (string) $key,
                    'value' => $text,
                    'status' => Translation::STATUS_SAVED,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($rows as $key => $row) {
            $text = $texts[$key] ?? null;

            // A key added in the tool that has no text yet: the file's text fills it.
            if ($row->value === null) {
                if ($text !== null) {
                    $updates[] = $row->fill(['value' => $text, 'status' => Translation::STATUS_SAVED, 'base_value' => null]);
                }
                continue;
            }

            if ($row->status === Translation::STATUS_SAVED) {
                if ($text === null) {
                    $deletes[] = $row->id;
                } elseif ($text !== $row->value) {
                    $updates[] = $row->fill(['value' => $text, 'ai' => false]);
                }
                continue;
            }

            if ($text === $row->value) {
                if ($rewritten) {
                    $updates[] = $row->fill(['status' => Translation::STATUS_SAVED, 'base_value' => null]);
                    $log[] = [$row, Change::ACTION_COMMITTED, $row->value, $row->value];
                } elseif ($row->status === Translation::STATUS_CONFLICT) {
                    $updates[] = $row->fill(['status' => Translation::STATUS_PUBLISHED]);
                }
                continue;
            }

            if ($text === $row->base_value) {
                // Also heals a conflict: the third text is gone from the file again.
                if ($row->status === Translation::STATUS_CONFLICT) {
                    $updates[] = $row->fill(['status' => Translation::STATUS_PUBLISHED]);
                }
                $writeBack[$key] = $row;
            } elseif ($row->status === Translation::STATUS_CONFLICT) {
                continue;
            } elseif ($text !== null && $this->toolWroteEarlier($row, $text)) {
                $updates[] = $row->fill(['base_value' => $text]);
                $writeBack[$key] = $row;
            } else {
                $updates[] = $row->fill(['status' => Translation::STATUS_CONFLICT]);
                $log[] = [$row, Change::ACTION_CONFLICT, $text, $row->value];
            }
        }

        if ($writeBack !== []) {
            try {
                $this->files->write($locale, $group, array_map(fn ($row) => $row->value, $writeBack));

                foreach ($writeBack as $row) {
                    $log[] = [$row, Change::ACTION_RESTORE, $row->base_value, $row->value];
                }
            } catch (LangFileException $e) {
                // The rows keep their texts; the file stays as the deploy left it.
                report($e);
            }
        }

        if ($inserts || $deletes || $updates || $log) {
            Translation::resolveConnection(config('translation-manager.connection'))->transaction(function () use ($inserts, $deletes, $updates, $log, $now) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    Translation::insert($chunk);
                }

                foreach (array_chunk($deletes, 500) as $chunk) {
                    Translation::whereIn('id', $chunk)->delete();
                }

                foreach ($updates as $row) {
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
                    'created_at' => $now,
                ], $log));
            });
        }

        return count($texts);
    }

    /**
     * True when the change log shows the tool wrote this text for the key before.
     */
    protected function toolWroteEarlier(Translation $row, string $text): bool
    {
        return Change::where('locale', $row->locale)
            ->where('group', $row->group)
            ->where('key', $row->key)
            ->whereIn('action', Change::WRITES)
            ->get(['new_value'])
            ->contains(fn ($change) => $change->new_value === $text);
    }

}
