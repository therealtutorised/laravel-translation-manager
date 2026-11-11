<?php

return array(

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
	 * Enable deletion of translations
	 *
	 * @type boolean
	 */
	'delete_enabled' => true,

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
	 * Export translations with keys output alphabetically.
	 */
	'sort_keys ' => false,

	/**
	 * Define who and when can manage module.
	 * 
	 * @return bool
	 */
	'permissions' => env('APP_ENV') == 'local',

);
