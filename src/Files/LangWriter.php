<?php

namespace HighSolutions\TranslationManager\Files;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;

/**
 * Changes single texts in the source of a lang file and leaves the rest as it is.
 *
 * The file is parsed and printed back with the format-preserving printer, so
 * key order, comments, indents and quote styles of untouched lines stay byte
 * for byte. A file is never rebuilt from an array.
 */
class LangWriter
{

    /**
     * Apply text changes to the source of a lang file.
     *
     * @param  string       $code     The file's source; '' for a file that doesn't exist yet.
     * @param  array        $changes  Dotted key => new text.
     * @param  string|null  $sourceCode  Source of the same file in the source language. A key
     *                                   that is new here lands after the same neighbour as there.
     * @return string  The new source.
     *
     * @throws LangFileException  When the file isn't a plain "return [...]" file.
     */
    public function apply(string $code, array $changes, ?string $sourceCode = null): string
    {
        if (trim($code) === '') {
            $code = "<?php\n\nreturn [\n];\n";
        }

        $parser = (new ParserFactory)->createForHostVersion();

        try {
            $oldStmts = $parser->parse($code);
            $oldTokens = $parser->getTokens();
            // A second parser: parsing replaces the tokens the printer needs.
            $sourceArray = $sourceCode === null ? null
                : $this->returnedArray((new ParserFactory)->createForHostVersion()->parse($sourceCode) ?? []);
        } catch (\PhpParser\Error $e) {
            throw new LangFileException('The file could not be parsed: ' . $e->getMessage());
        }

        $traverser = new NodeTraverser(new CloningVisitor);
        $newStmts = $traverser->traverse($oldStmts);

        $array = $this->returnedArray($newStmts);
        if ($array === null) {
            throw new LangFileException('The file does not return a plain array.');
        }

        foreach ($changes as $key => $text) {
            $key = (string) $key;
            $item = $this->findItem($array, $key);

            if ($item !== null) {
                $item->value = $this->stringNode((string) $text, $item->value);
            } else {
                $this->insert($array, $key, (string) $text, $sourceArray);
            }
        }

        $printer = new MultilineArrayPrinter([
            'newline' => str_contains($code, "\r\n") ? "\r\n" : "\n",
            'indent' => $this->indentOf($code),
            'shortArraySyntax' => $array->getAttribute('kind') !== Array_::KIND_LONG,
        ]);

        return $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
    }

    /**
     * The indent the file uses: a tab, or the spaces of its first indented line.
     */
    protected function indentOf(string $code): string
    {
        if (preg_match('/^(\t| +)\S/m', $code, $match)) {
            return $match[1][0] === "\t" ? "\t" : $match[1];
        }

        return '    ';
    }

    /**
     * The array a lang file returns, or null when the file has another shape.
     */
    protected function returnedArray(array $stmts): ?Array_
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_) {
                return $stmt->expr instanceof Array_ ? $stmt->expr : null;
            }
        }

        return null;
    }

    /**
     * Find the item a dotted key points to. A literal key that itself holds
     * dots ('a.b' => '...') wins over nesting, the same way Laravel reads it.
     */
    protected function findItem(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($this->keyedItems($array) as $name => $item) {
            $name = (string) $name;

            if ($name === $key) {
                return $item->value instanceof Array_ ? null : $item;
            }

            if ($item->value instanceof Array_ && str_starts_with($key, $name . '.')) {
                $found = $this->findItem($item->value, substr($key, strlen($name) + 1));
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * The items of an array by the key PHP gives them. Spreads and computed
     * keys are skipped: the writer can't name them.
     *
     * @return array<string|int, ArrayItem>
     */
    protected function keyedItems(Array_ $array): array
    {
        $items = [];
        $next = 0;

        foreach ($array->items as $item) {
            if ($item === null || $item->unpack) {
                continue;
            }

            if ($item->key === null) {
                $items[$next++] = $item;
            } elseif ($item->key instanceof String_ || $item->key instanceof Int_) {
                $items[$item->key->value] = $item;
                if (is_int($item->key->value) && $item->key->value >= $next) {
                    $next = $item->key->value + 1;
                }
            }
        }

        return $items;
    }

    /**
     * Insert a key that the file doesn't have yet.
     */
    protected function insert(Array_ $array, string $key, string $text, ?Array_ $sourceArray): void
    {
        $path = ($sourceArray ? $this->pathInSource($sourceArray, $key) : null) ?? $this->guessPath($array, $key);

        foreach ($path as $depth => $name) {
            $last = $depth === count($path) - 1;
            $existing = $this->keyedItems($array)[$name] ?? null;

            if (!$last && $existing !== null && $existing->value instanceof Array_) {
                $array = $existing->value;
                $sourceArray = $this->childArray($sourceArray, $name);
                continue;
            }

            if ($existing !== null) {
                throw new LangFileException("The key [{$key}] clashes with an existing key that isn't a text.");
            }

            $value = $last ? $this->stringNode($text) : new Array_([], ['kind' => $array->getAttribute('kind', Array_::KIND_SHORT)]);
            $item = new ArrayItem($value, new String_((string) $name));

            // The printer can't tell how an empty array is laid out, so it is printed anew.
            if (count($array->items) === 0) {
                $array->setAttribute('origNode', null);
            }

            array_splice($array->items, $this->position($array, (string) $name, $sourceArray), 0, [$item]);

            if (!$last) {
                $array = $value;
                $sourceArray = $this->childArray($sourceArray, $name);
            }
        }
    }

    /**
     * The key's path of array keys in the source-language file, or null when it isn't there.
     */
    protected function pathInSource(Array_ $array, string $key): ?array
    {
        foreach ($this->keyedItems($array) as $name => $item) {
            $name = (string) $name;

            if ($name === $key && !$item->value instanceof Array_) {
                return [$name];
            }

            if ($item->value instanceof Array_ && str_starts_with($key, $name . '.')) {
                $rest = $this->pathInSource($item->value, substr($key, strlen($name) + 1));
                if ($rest !== null) {
                    return array_merge([$name], $rest);
                }
            }
        }

        return null;
    }

    /**
     * Path for a key no file knows: follow existing parent arrays, then nest on dots.
     */
    protected function guessPath(Array_ $array, string $key): array
    {
        foreach ($this->keyedItems($array) as $name => $item) {
            $name = (string) $name;

            if ($item->value instanceof Array_ && str_starts_with($key, $name . '.')) {
                return array_merge([$name], $this->guessPath($item->value, substr($key, strlen($name) + 1)));
            }
        }

        return explode('.', $key);
    }

    protected function childArray(?Array_ $array, string|int $name): ?Array_
    {
        $item = $array ? ($this->keyedItems($array)[$name] ?? null) : null;

        return $item && $item->value instanceof Array_ ? $item->value : null;
    }

    /**
     * Where a new key goes: right after its nearest earlier neighbour in the
     * source-language file that this file also has. Without one, at the top
     * when the source knows the key, else at the end.
     */
    protected function position(Array_ $array, string $name, ?Array_ $sourceArray): int
    {
        $sourceNames = $sourceArray ? array_map('strval', array_keys($this->keyedItems($sourceArray))) : [];
        $index = array_search($name, $sourceNames, true);

        if ($index === false) {
            return count($array->items);
        }

        $own = $this->keyedItems($array);

        for ($i = $index - 1; $i >= 0; $i--) {
            $neighbour = $own[$sourceNames[$i]] ?? null;
            if ($neighbour !== null) {
                return array_search($neighbour, $array->items, true) + 1;
            }
        }

        return 0;
    }

    /**
     * A string node for the new text, in the quote style of the node it replaces.
     */
    protected function stringNode(string $text, ?Node $old = null): String_
    {
        $attributes = [];

        if ($old instanceof String_) {
            foreach (['kind', 'docLabel', 'docIndentation'] as $name) {
                if ($old->hasAttribute($name)) {
                    $attributes[$name] = $old->getAttribute($name);
                }
            }
        }

        return new String_($text, $attributes);
    }

}
