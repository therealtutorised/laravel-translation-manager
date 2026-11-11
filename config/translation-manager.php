<?php

return array(

    /**
     * Switch the tool on. Off by default: the tables are not migrated and
     * lang files are not checked or written. Switch it on only on the server
     * where texts are edited.
     *
     * @type boolean
     */
    'enabled' => env('TRANSLATION_MANAGER_ENABLED', false),

    /**
     * Database connection for the tool's tables. Null uses the default connection.
     *
     * @type string|null
     */
    'connection' => env('TRANSLATION_MANAGER_CONNECTION'),

    /**
     * Name of this application, stored on every row. Several applications can
     * share the tables; each one sees only its own rows.
     *
     * @type string
     */
    'app' => env('TRANSLATION_MANAGER_APP', 'app'),

    /**
     * The language the texts are written in first. A key that is new in
     * another language is placed in the same spot as in this language's file.
     * Null uses the application's locale.
     *
     * @type string|null
     */
    'source_locale' => env('TRANSLATION_MANAGER_SOURCE_LOCALE'),

    /**
     * Folder for the tool's own files (the write lock and the list of lang
     * file signatures). It must survive deploys.
     *
     * @type string
     */
    'storage_path' => storage_path('app/translation-manager'),

    /**
     * Attribute of the signed-in user that the change log stores as the name.
     *
     * @type string
     */
    'user_name_attribute' => 'name',

    /*
    |--------------------------------------------------------------------------
    | Routes group config
    |--------------------------------------------------------------------------
    |
    | The default group settings for the elFinder routes.
    |
    */
    'route' => [
        'prefix' => 'translations',
        'namespace' => 'HighSolutions\TranslationManager',
        'middleware' => [
	        'web',
	        'auth',
		],
    ],

	/**
	 * Enable deletion of keys in the grid. It removes the tool's rows only;
	 * the lang files keep the key, and the next import brings it back.
	 *
	 * @type boolean
	 */
	'delete_enabled' => false,

	/**
	 * Exclude specific groups from Laravel Translation Manager. 
	 * This is useful if, for example, you want to avoid editing the official Laravel language files.
	 *
	 * @type array
	 *
	 * 	array(
	 *		'pagination',
	 *		'reminders',
	 *		'validation',
	 *	)
	 */
	'exclude_groups' => array(),

    /**
     * Exclude specific langs from Laravel Translation Manager.
     * This is useful if, for example, you want to avoid editing spare lang files or vendor catalog.
     *
     * @type array
     *
     *  array(
     *      'en',
     *      'vendor',
     *  )
     */
    'exclude_langs' => array(
        'vendor',
        // ...
    ),

	/**
	 * Define who and when can manage module.
	 * 
	 * @return bool
	 */
	'permissions' => env('APP_ENV') == 'local',

);
