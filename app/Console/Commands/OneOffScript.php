<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OneOffScript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'script
                            {filename : path/filename.php of script to run relative to project root}
                            {args?* : Optional args to pass to script}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs a script inside a bootstrapped laravel environment.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {

        // Make $args (if any) available to the file being included
        $args = $this->argument('args');

        // include the script file to run
        require $this->argument('filename');
    }
}
