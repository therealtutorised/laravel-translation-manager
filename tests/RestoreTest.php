<?php

namespace HighSolutions\TranslationManager\Tests;

use HighSolutions\TranslationManager\Importer;
use HighSolutions\TranslationManager\Models\Change;
use HighSolutions\TranslationManager\Models\Translation;
use HighSolutions\TranslationManager\Saver;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;

class RestoreTest extends TestCase
{

    const GIT = "<?php\n\nreturn [\n    'hello' => 'Hei',\n    'bye' => 'Heippa',\n];\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->putLang('fi', 'app', self::GIT);
        app(Importer::class)->importAll();
        app(Saver::class)->save('fi', 'app', 'hello', 'Moi');
    }

    /**
     * What: after a deploy resets a lang file, the next request writes the
     * tool's text back and leaves the other lines alone.
     * Where: the RestoreTranslations middleware, on any request.
     * Why: texts that are not committed yet would otherwise vanish from the
     * site with every deploy.
     */
    public function test_a_reset_file_gets_the_tools_text_back_on_the_next_request()
    {
        $this->putLang('fi', 'app', self::GIT);

        $this->actingAs(new User)->get('/translations');

        $this->assertSame(str_replace("'Hei'", "'Moi'", self::GIT), $this->lang('fi', 'app'));
        $this->assertSame(Translation::STATUS_PUBLISHED, Translation::where('key', 'hello')->first()->status);
        $this->assertSame(1, Change::where('action', Change::ACTION_RESTORE)->count());
    }

    /**
     * What: when a deploy brings the tool's own text, the row is marked
     * saved; a later reset to the old text is then a developer's change.
     * Where: the RestoreTranslations middleware.
     * Why: this is how the tool learns that a text was committed, with no
     * button for a person to press too early.
     */
    public function test_a_deploy_that_brings_the_tools_text_marks_it_saved()
    {
        $this->putLang('fi', 'app', str_replace("'Hei'", "'Moi'", self::GIT));

        $this->actingAs(new User)->get('/translations');

        $row = Translation::where('key', 'hello')->first();
        $this->assertSame(Translation::STATUS_SAVED, $row->status);
        $this->assertNull($row->base_value);
    }

    /**
     * What: a request with no changed lang file runs no query and writes no file.
     * Where: Importer::importChanged(), the check that runs on every request.
     * Why: it runs before every page of the application, so the idle case
     * must cost next to nothing.
     */
    public function test_an_unchanged_folder_costs_no_queries()
    {
        $before = $this->lang('fi', 'app');
        $modified = filemtime($this->scratch . '/lang/fi/app.php');

        DB::enableQueryLog();
        $count = app(Importer::class)->importChanged();

        $this->assertSame(0, $count);
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame($before, $this->lang('fi', 'app'));
        $this->assertSame($modified, filemtime($this->scratch . '/lang/fi/app.php'));
    }

}
