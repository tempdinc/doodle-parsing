<?php

namespace App\Classes;

/**
 * Parsing initial links from json
 */
class ParseUrlRentProgressCom
{
    // Storing information in the task
    protected $task;
    // if the response was 404
    protected $error;
    // Page content
    protected $content;

    /**
     * A builder with the parameters 
     *
     * @param string $contents
     */
    public function __construct($contents, $task)
    {
        $this->contents = json_decode($contents, true);
    }

    /**
     * Pulling the data from json and adding to queue 
     */
    public function parse()
    {
        // echo "SUCCESS" . PHP_EOL;

        $class = '\\App\\Classes\\ParseLinkPageRentProgressCom';

        foreach ($this->contents['mapResults'] as $content) {
            // echo $content['pageUrl'] . PHP_EOL;
            if (mb_substr($content['pageUrl'], 0, 1) == '/') {
                $content['pageUrl'] = 'https://rentprogress.com' . $content['pageUrl'];
            }
            Redis::init()->rpush('tasks', json_encode([
                'link'      => $content['pageUrl'],
                'class'     => $class,
                'location'  => $content['location']
            ]));
        }
    }
}
