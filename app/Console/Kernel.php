<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel final test for docker build image
{
    /**
     * Define the application's command schedule. 
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $asdfasdfasdfschedule
     * @return void 
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly(); dNB,DBc
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
// Test for directly building after merge with commit and add on master branch.
// Cross Testing for master branch.
// testing prod deploy tagging
//testing error on jenkinsq
//tagging not working and webhook also
// the jenkinsfile change 
// made some change on the jenkinsfile
// jenkinsfile didnt save.
// the git tag is not fetch latest.
//master tagging is working.
// add on jenkinsfile githubPush()
//testing on commit and tag or version
//Even if Jenkins was triggered by master, you force checkout of staging.
//testing with farhan 
// testing behaviour
// tetsing refs/tags/ on jenkins multibranch