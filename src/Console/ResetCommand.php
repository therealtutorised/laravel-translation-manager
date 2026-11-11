<?php 

namespace HighSolutions\TranslationManager\Console;

use Illuminate\Console\Command;
use HighSolutions\TranslationManager\Manager;

class ResetCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translations:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete this application\'s rows. Texts that are not committed yet lose that mark';

    /** @var \HighSolutions\TranslationManager\Manager  */
    protected $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->manager->truncateTranslations();
        $this->info("All translations are deleted");
    }

}
