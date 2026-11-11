<?php 

namespace HighSolutions\TranslationManager;

use HighSolutions\TranslationManager\Console\CleanCommand;
use HighSolutions\TranslationManager\Console\ExportCommand;
use HighSolutions\TranslationManager\Console\ImportCommand;
use HighSolutions\TranslationManager\Console\ResetCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ManagerServiceProvider extends ServiceProvider {
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->_basicRegister();

        $this->_commandsRegister();        

        $this->_managerRegister();    
    }

    private function _basicRegister() 
    {
        $configPath = __DIR__ . '/../config/translation-manager.php';
        $this->mergeConfigFrom($configPath, 'translation-manager');
        $this->publishes([
            $configPath => config_path('translation-manager.php')
        ], 'config');
    }

    private function _commandsRegister() 
    {
        foreach($this->commandsList() as $name => $class) {
            $this->initCommand($name, $class);
        }
    }

    protected function commandsList()
    {
        return [
            'reset' => ResetCommand::class,
            'import' => ImportCommand::class,
            'export' => ExportCommand::class,
            'clean' => CleanCommand::class,
        ];
    }

    private function initCommand($name, $class)
    {
        $this->app->singleton("command.translation-manager.{$name}", function($app) use ($class) {
            return new $class($app['translation-manager']);
        });

        $this->commands("command.translation-manager.{$name}");
    }

    private function _managerRegister() 
    {
        $this->app->singleton('translation-manager', function($app) {
            return $app->make(Manager::class);
        });
    }

    /**
	 * Bootstrap the application events.
	 *
     * @param  \Illuminate\Routing\Router  $router
	 * @return void
	 */
	public function boot(Router $router)
	{
        $this->loadViews();
        $this->loadMigrations();
        $this->loadTranslations();
        $this->loadRoutes($router);
	}

    protected function loadViews()
    {
        $viewPath = __DIR__.'/../resources/views';
        $this->loadViewsFrom($viewPath, 'translation-manager');
        $this->publishes([
            $viewPath => resource_path('views/vendor/translation-manager'),
        ], 'views');
    }

    protected function loadMigrations()
    {
        $migrationPath = __DIR__.'/../database/migrations';
        $this->publishes([
            $migrationPath => base_path('database/migrations'),
        ], 'migrations');
    }

    protected function loadTranslations()
    {
        $translationPath = __DIR__.'/../resources/lang';
        $this->loadTranslationsFrom($translationPath, 'translation-manager');
        
        $this->publishes([
            $translationPath => resource_path('lang/vendor/translation-manager'),
        ], 'translations');
    }

    public function loadRoutes($router) {        
        $config = $this->routeConfig();

        $router->group($config, function($router) {
            $router->get('/', 'Controller@getIndex')->name('translation-manager.index');
            $router->get('/view/{group?}/{group2?}/{group3?}/{group4?}/{group5?}', 'Controller@getView')->name('translation-manager.view');
            $router->post('/add/{group}/{group2?}/{group3?}/{group4?}/{group5?}', 'Controller@postAdd')->name('translation-manager.add');
            $router->post('/edit/{group}/{group2?}/{group3?}/{group4?}/{group5?}', 'Controller@postEdit')->name('translation-manager.edit');
            $router->post('/delete/{key}/{group}/{group2?}/{group3?}/{group4?}/{group5?}', 'Controller@postDelete')->name('translation-manager.delete');
            $router->post('/publish/{group}/{group2?}/{group3?}/{group4?}/{group5?}', 'Controller@postPublish')->name('translation-manager.publish');
            $router->post('/import', 'Controller@postImport')->name('translation-manager.import');
        });
    }

    private function routeConfig() {
        return $this->app['config']->get('translation-manager.route', []);
    }

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return [
            'translation-manager',
            'command.translation-manager.reset',
            'command.translation-manager.import',
            'command.translation-manager.export',
            'command.translation-manager.clean',
        ];
	}

}
