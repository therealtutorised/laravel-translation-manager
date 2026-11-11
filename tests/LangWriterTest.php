<?php

namespace HighSolutions\TranslationManager\Tests;

use HighSolutions\TranslationManager\Files\LangWriter;
use PHPUnit\Framework\TestCase;

class LangWriterTest extends TestCase
{

    const SOURCE = <<<'PHP'
<?php

// A header comment

return [
    // Greetings
    'hello' => 'Hei',
    'bye' => "Heippa",   // said when leaving
    'menu' => [
        'home' => 'Koti',
        'settings' => 'Asetukset',
    ],
    'zebra' => 'Seepra',
    'apple' => 'Omena',
];

PHP;

    /**
     * What: changing one text changes only that line.
     * Where: LangWriter::apply() with an existing key.
     * Why: files are committed from the server, so a save must not reorder
     * keys, drop comments or change quote styles in the rest of the file.
     */
    public function test_an_edit_changes_only_its_own_line()
    {
        $result = (new LangWriter)->apply(self::SOURCE, ['bye' => 'It\'s "bye"', 'menu.home' => "Koti's"]);

        $expected = str_replace(
            ['"Heippa"', "'Koti'"],
            ['"It\'s \\"bye\\""', "'Koti\\'s'"],
            self::SOURCE
        );

        $this->assertSame($expected, $result);
    }

    /**
     * What: a key that is missing in a language lands after the same
     * neighbour as in the source language, nested arrays included.
     * Where: LangWriter::apply() with the source language's file.
     * Why: files in every language keep the same order, so diffs stay small
     * and people find keys in the same place.
     */
    public function test_a_missing_key_lands_where_the_source_language_has_it()
    {
        $target = <<<'PHP'
<?php

return [
    'hello' => 'Hello',
    'zebra' => 'Zebra',
    'apple' => 'Apple',
];

PHP;

        $result = (new LangWriter)->apply($target, ['menu.settings' => 'Settings', 'bye' => 'Bye'], self::SOURCE);

        $this->assertSame(<<<'PHP'
<?php

return [
    'hello' => 'Hello',
    'bye' => 'Bye',
    'menu' => [
        'settings' => 'Settings',
    ],
    'zebra' => 'Zebra',
    'apple' => 'Apple',
];

PHP, $result);
    }

}
