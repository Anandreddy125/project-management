<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel  
{
    /**
     * Define the application's command schedule. jfhlxkfd
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $asdfasdfasdfschedule
     * @return void 
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
    }

    //test for build auto asdfasdfasdfasdf

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
