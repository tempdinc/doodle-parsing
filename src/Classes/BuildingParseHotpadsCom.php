<?php

namespace App\Classes;

/**
 * Parsing building url
 */
class BuildingParseHotpadsCom
{
    // Constant with a link to the site
    const SITE_URL = 'https://hotpads.com';

    // Page content
    protected $content;
    // Task information
    protected $task;

    /**
     * A builder with the parameters
     *
     * @param  object $content
     * @param  array $task
     */
    public function __construct($content, $task)
    {
        $this->content = $content;
        $this->task = $task;
    }

    /**
     * Collecting all links from the page and passing to the queue
     *
     * @return void
     */
    public function parse()
    {

        $buildingContents = (null !== $this->content->xpath("//article[contains(@class, 'BuildingArticleGroup')")[0]) ? $this->content->xpath("//article[contains(@class, 'BuildingArticleGroup')")[0]->find('ul')[0]->find('li') : '';
        // $buildingContents = $this->content->find('div.BuildingHdp-content')[0]->find('a');
        if ($buildingContents != '') {
            foreach ($buildingContents as $unit) {
                $buildingContentsLink = $unit->find('a')[0];
                Redis::init()->rpush('tasks', json_encode([
                    'link' => $buildingContentsLink->getAttribute('href'),
                    'class' => 'App\\Classes\\PageUnitParseHotpadsCom'
                ]));
            }
        }
    }
}
