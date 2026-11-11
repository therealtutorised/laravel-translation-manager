<?php 

namespace HighSolutions\TranslationManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One text: a key of a lang file in one language.
 *
 * @property integer $id
 * @property string  $app
 * @property string  $locale
 * @property string  $group
 * @property string  $key
 * @property string|null  $value
 * @property string|null  $base_value
 * @property string  $status
 * @property boolean $ai
 * @property integer|null $changed_by
 * @property string|null  $changed_by_name
 * @property \Carbon\Carbon|null $changed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Translation extends Model{

    /** Same as the lang file and version control. */
    const STATUS_SAVED = 'saved';
    /** Written to the lang file, not committed yet. */
    const STATUS_PUBLISHED = 'published';
    /** The lang file holds a third text; a person decides. */
    const STATUS_CONFLICT = 'conflict';

    protected $table = 'tm_translations';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $casts = ['ai' => 'boolean', 'changed_at' => 'datetime'];
    protected $attributes = ['status' => self::STATUS_SAVED, 'ai' => false];

    public function getConnectionName()
    {
        return config('translation-manager.connection');
    }

    protected static function booted()
    {
        // Applications can share the table; each one sees only its own rows.
        static::addGlobalScope('app', function (Builder $query) {
            $query->where($query->getModel()->getTable() . '.app', config('translation-manager.app'));
        });

        static::creating(function (Model $model) {
            $model->app = config('translation-manager.app');
        });
    }

    public function scopeOfTranslatedGroup($query, $group)
    {
        return $query->where('group', $group)->whereNotNull('value');
    }

    public function scopeOrderByGroupKeys($query, $ordered) 
    {
        if ($ordered) {
            $query->orderBy('group')->orderBy('key');
        }

        return $query;
    }

}
