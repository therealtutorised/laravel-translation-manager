<?php

namespace HighSolutions\TranslationManager;

/**
 * Who makes a change: the signed-in user, or nobody for the tool's own work.
 */
class Actor
{

    public function __construct(public ?int $id = null, public ?string $name = null)
    {
    }

    public static function current(): self
    {
        $user = auth()->user();
        if ($user === null) {
            return new self;
        }

        $name = $user->{config('translation-manager.user_name_attribute', 'name')} ?? null;

        return new self((int) $user->getAuthIdentifier(), $name === null ? null : (string) $name);
    }

}
