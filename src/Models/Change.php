<?php

namespace HighSolutions\TranslationManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of the change log: what happened to a text, who did it and when.
 *
 * @property integer $id
 * @property string  $app
 * @property string  $locale
 * @property string  $group
 * @property string  $key
 * @property string  $action
 * @property string|null  $old_value
 * @property string|null  $new_value
 * @property integer|null $user_id
 * @property string|null  $user_name
 * @property \Carbon\Carbon $created_at
 */
class Change extends Model
{

    /** A person changed the text. */
    const ACTION_EDIT = 'edit';
    /** A key was added without a text. */
    const ACTION_ADD = 'add';
    /** The tool wrote its text back after the lang file was reset. */
    const ACTION_RESTORE = 'restore';
    /** The lang file held a third text. */
    const ACTION_CONFLICT = 'conflict';
    /** Version control now holds the tool's text. */
    const ACTION_COMMITTED = 'committed';

    /** Actions that put a text of the tool into a lang file. */
    const WRITES = [self::ACTION_EDIT];

    const UPDATED_AT = null;

    protected $table = 'tm_changes';
    protected $guarded = ['id'];

    public function getConnectionName()
    {
        return config('translation-manager.connection');
    }

    protected static function booted()
    {
        static::addGlobalScope('app', function (Builder $query) {
            $query->where($query->getModel()->getTable() . '.app', config('translation-manager.app'));
        });
    }

}
