<?php

namespace App\Classes;

/**
 * Class for collecting the ad links from pagination
 */
class PaginateLinkParseHotpadsCom
{
    // Constant with a link to the site
    const SITE_URL = 'https://hotpads.com';

    // Page contnet
    protected $content;
    // Task information
    protected $task;

    /**
     * A builder with the parameters
     *
     * @param object $content
     * @param array $task
     */
    public function __construct($content, $task)
    {
        $this->content = $content;
        $this->task = $task;
    }

    /**
     * Building the links for parser classes and passing to the queue
     *
     * @return void
     */
    public function parse()
    {
        // print_r($this->content);
        // Сollecting all the links on the page
        $allLinksOnPage = [];
        if (null !== $this->content->find('div.AreaListingsContainer')[0]) {
            $allLinksOnPage = $this->content->find('div.AreaListingsContainer')[0]->find('li.ListingWrapper');
        }


        foreach ($allLinksOnPage as $link) {
            $tempLink = $link->find('a')[0]->getAttribute('href');

            // echo self::SITE_URL . $tempLink . PHP_EOL;

            if (end(explode('/', $tempLink)) == 'building') {
                $class = 'App\\Classes\\BuildingParseHotpadsCom';
            } else {
                $class = 'App\\Classes\\PageUnitParseHotpadsCom';
            }

            Redis::init()->rpush('tasks', json_encode([
                'link' => self::SITE_URL . $tempLink,
                'class' => $class
            ]));
        }

        return true;
    }
}
