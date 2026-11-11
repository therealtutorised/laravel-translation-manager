<?php

namespace HighSolutions\TranslationManager\Tests;

use HighSolutions\TranslationManager\Files\LangFileException;
use HighSolutions\TranslationManager\Importer;
use HighSolutions\TranslationManager\Models\Change;
use HighSolutions\TranslationManager\Models\Translation;
use HighSolutions\TranslationManager\Saver;
use Illuminate\Foundation\Auth\User;

class SaveTest extends TestCase
{

    const GIT = "<?php\n\nreturn [\n    // Greetings\n    'hello' => 'Hei',\n    'bye' => \"Heippa\",\n];\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->putLang('fi', 'app', self::GIT);
        app(Importer::class)->importAll();
    }

    /**
     * What: saving a text in the grid writes that key into the lang file at
     * once, remembers the old text, logs the change, and an edit back to the
     * old text makes the row saved again.
     * Where: POST /translations/edit/{group} through Saver::saveMany().
     * Why: translators must see their change on the site right away, and
     * the tool must know which texts still wait for a commit.
     */
    public function test_a_save_writes_only_that_key_into_the_file()
    {
        $response = $this->actingAs(new User)->post('/translations/edit/app', ['name' => 'fi|hello', 'value' => 'Moi']);

        $response->assertJson(['success' => true, 'status' => Translation::STATUS_PUBLISHED]);
        $this->assertSame(str_replace("'Hei'", "'Moi'", self::GIT), $this->lang('fi', 'app'));

        $row = Translation::where('key', 'hello')->first();
        $this->assertSame('Hei', $row->base_value);
        $this->assertSame(['Hei', 'Moi'], Change::where('action', Change::ACTION_EDIT)->get()->map(fn ($c) => [$c->old_value, $c->new_value])->first());

        app(Saver::class)->save('fi', 'app', 'hello', 'Hei');

        $this->assertSame(self::GIT, $this->lang('fi', 'app'));
        $this->assertSame(Translation::STATUS_SAVED, $row->fresh()->status);
    }

    /**
     * What: a save doesn't overwrite a text that changed in the file behind
     * the tool's back; the row becomes a conflict and keeps the new text.
     * Where: Saver::saveMany(), the check just before writing.
     * Why: a deploy can land between two edits, and a developer's change
     * must not be lost to a save that never saw it.
     */
    public function test_a_save_over_a_third_text_becomes_a_conflict()
    {
        app(Saver::class)->save('fi', 'app', 'hello', 'Moi');
        $deployed = str_replace("'Moi'", "'Terve'", $this->lang('fi', 'app'));
        file_put_contents($this->scratch . '/lang/fi/app.php', $deployed);

        $status = app(Saver::class)->save('fi', 'app', 'hello', 'Moikka');

        $this->assertSame(Translation::STATUS_CONFLICT, $status);
        $this->assertSame('Moikka', Translation::where('key', 'hello')->first()->value);
        $this->assertSame($deployed, $this->lang('fi', 'app'));

        // Taking over the file's text settles it.
        $this->assertSame(Translation::STATUS_SAVED, app(Saver::class)->save('fi', 'app', 'hello', 'Terve'));
        $this->assertSame($deployed, $this->lang('fi', 'app'));
    }

    /**
     * What: a file name that points outside the lang folder, or to no
     * existing lang file, is refused and nothing is written.
     * Where: Saver::saveMany() and LangFiles::path().
     * Why: the file name comes from the request, and the tool writes PHP
     * files; it must never write anywhere but existing lang files.
     */
    public function test_a_save_outside_the_lang_files_is_refused()
    {
        foreach (['../../evil', 'unknown', 'app/../app'] as $group) {
            try {
                app(Saver::class)->save('fi', $group, 'hello', 'x');
                $this->fail("The file name [{$group}] was accepted.");
            } catch (LangFileException $e) {
                $this->assertSame(0, Translation::where('value', 'x')->count());
            }
        }

        $this->assertFileDoesNotExist($this->scratch . '/evil.php');
        $this->assertSame(self::GIT, $this->lang('fi', 'app'));
    }

}
