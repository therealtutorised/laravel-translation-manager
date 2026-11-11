<?php

namespace HighSolutions\TranslationManager\Files;

use PhpParser\PrettyPrinter\Standard;

/**
 * Prints newly created arrays with one item per line, the way lang files are
 * written, and keeps a one-item array on several lines when it was written so.
 */
class MultilineArrayPrinter extends Standard
{

    protected function pMaybeMultiline(array $nodes, bool $trailingComma = false): string
    {
        if ($nodes === []) {
            return '';
        }

        return $this->pCommaSeparatedMultiline($nodes, true) . $this->nl;
    }

    /**
     * The parent can't tell for a single item; here it counts as multi-line
     * when the item starts on a line of its own.
     */
    protected function isMultiline(array $nodes): bool
    {
        $nodes = array_values(array_filter($nodes));

        if (count($nodes) === 1) {
            $start = $nodes[0]->getStartTokenPos();

            return $start > 0 && str_contains($this->origTokens->getTokenCode($start - 1, $start, 0), "\n");
        }

        return parent::isMultiline($nodes);
    }

}
