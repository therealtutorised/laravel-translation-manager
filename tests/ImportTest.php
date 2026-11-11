<?php

namespace HighSolutions\TranslationManager\Tests;

use HighSolutions\TranslationManager\Importer;
use HighSolutions\TranslationManager\Models\Translation;
use HighSolutions\TranslationManager\Saver;
use Illuminate\Support\Facades\DB;

class ImportTest extends TestCase
{

    protected function row(string $key): ?Translation
    {
        return Translation::where('locale', 'fi')->where('group', 'app')->where('key', $key)->first();
    }

    /**
     * What: the first import stores every text as saved, and importing the
     * same files again changes nothing.
     * Where: Importer::importAll().
     * Why: the import runs after every deploy, so an unchanged folder must
     * cost no writes, and nested keys must be stored under their dotted name.
     */
    public function test_first_import_stores_the_texts_and_a_second_one_writes_nothing()
    {
        $this->putLang('fi', 'app', "<?php return ['hello' => 'Hei', 'menu' => ['home' => 'Koti']];");

        $this->assertSame(2, app(Importer::class)->importAll());
        $this->assertSame('Koti', $this->row('menu.home')->value);
        $this->assertSame(Translation::STATUS_SAVED, $this->row('hello')->status);

        DB::enableQueryLog();
        app(Importer::class)->importAll();
        $writes = array_filter(DB::getQueryLog(), fn ($query) => !str_starts_with(strtolower($query['query']), 'select'));

        $this->assertSame([], $writes);
    }

    /**
     * What: for texts nobody edited in the tool, the file wins: a changed
     * text is taken over and a removed key is deleted.
     * Where: Importer::importFile(), rows with status saved.
     * Why: a developer's fix in version control must never be reverted by an
     * old copy in the database.
     */
    public function test_a_developers_change_wins_over_a_saved_row()
    {
        $this->putLang('fi', 'app', "<?php return ['hello' => 'Hei', 'old' => 'Vanha'];");
        app(Importer::class)->importAll();

        $this->putLang('fi', 'app', "<?php return ['hello' => 'Terve'];");
        app(Importer::class)->importAll();

        $this->assertSame('Terve', $this->row('hello')->value);
        $this->assertNull($this->row('old'));
    }

    /**
     * What: when a deploy brings a third text for a key that was edited in
     * the tool, the row becomes a conflict and the file is left alone.
     * Where: Importer::importFile(), rows with status published.
     * Why: neither the developer's text nor the translator's may be
     * overwritten silently; a person has to choose.
     */
    public function test_a_third_text_in_the_file_becomes_a_conflict()
    {
        $this->putLang('fi', 'app', "<?php return ['hello' => 'Hei'];");
        app(Importer::class)->importAll();
        app(Saver::class)->save('fi', 'app', 'hello', 'Moi');

        $this->putLang('fi', 'app', "<?php return ['hello' => 'Terve'];");
        app(Importer::class)->importAll();

        $this->assertSame(Translation::STATUS_CONFLICT, $this->row('hello')->status);
        $this->assertSame('Moi', $this->row('hello')->value);
        $this->assertStringContainsString("'Terve'", $this->lang('fi', 'app'));

        // The developer's text goes away again: the conflict heals and the tool's text returns.
        $this->putLang('fi', 'app', "<?php return ['hello' => 'Hei'];");
        app(Importer::class)->importAll();

        $this->assertSame(Translation::STATUS_PUBLISHED, $this->row('hello')->status);
        $this->assertStringContainsString("'Moi'", $this->lang('fi', 'app'));
    }

    /**
     * What: when a deploy brings an older text that the tool itself wrote,
     * the newest text is written back instead of raising a conflict.
     * Where: Importer::importFile(), the change-log lookup.
     * Why: texts are committed from a copy taken earlier; edits made after
     * that copy must survive the deploy of that commit.
     */
    public function test_an_older_committed_version_does_not_raise_a_conflict()
    {
        $this->putLang('fi', 'app', "<?php return ['hello' => 'Hei'];");
        app(Importer::class)->importAll();
        app(Saver::class)->save('fi', 'app', 'hello', 'Moi');
        app(Saver::class)->save('fi', 'app', 'hello', 'Moikka');

        $this->putLang('fi', 'app', "<?php return ['hello' => 'Moi'];");
        app(Importer::class)->importAll();

        $row = $this->row('hello');
        $this->assertSame(Translation::STATUS_PUBLISHED, $row->status);
        $this->assertSame('Moi', $row->base_value);
        $this->assertStringContainsString("'Moikka'", $this->lang('fi', 'app'));
    }

}
