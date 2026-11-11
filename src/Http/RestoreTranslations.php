<?php

namespace HighSolutions\TranslationManager\Http;

use Closure;
use HighSolutions\TranslationManager\Importer;

/**
 * Before each request, checks whether lang files changed behind the tool's
 * back (a deploy resets them) and writes texts that are not committed yet
 * back into them, so the page already renders with the restored texts.
 */
class RestoreTranslations
{

    public function __construct(protected Importer $importer)
    {
    }

    public function handle($request, Closure $next)
    {
        try {
            $this->importer->importChanged();
        } catch (\Throwable $e) {
            // The page matters more than the restore; the next request tries again.
            report($e);
        }

        return $next($request);
    }

}
