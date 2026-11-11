<?php 

namespace HighSolutions\TranslationManager\Console;

use HighSolutions\TranslationManager\Manager;
use Illuminate\Console\Command;

class ImportCommand extends Command 
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translations:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check every lang file and bring the rows in line with them';

    /** @var  \HighSolutions\TranslationManager\Manager  */
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
        $counter = $this->manager->importTranslations();
        $this->info('Done importing, processed '.$counter. ' items!');

    }

}
