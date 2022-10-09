<?php
namespace App\Classes;

class Counter
{
    public function __construct()
    {
        $this->limit = env('FORK_LIMIT', 100);
    }

    public function incrementTask()
    {

        global $counter_tasks;
        $counter_tasks++;
        // echo 'Counter incrementTask - ' . $counter_tasks;

        return $counter_tasks;
    }

    public function incrementError()
    {
        /*
        global $counter_errors;
        $counter_errors++;
        echo 'Counter incrementError - ' . $counter_errors;
        */
        return $counter_errors;
    }    
}
