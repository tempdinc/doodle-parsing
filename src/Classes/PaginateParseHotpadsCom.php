<?php

namespace App\Classes;

class PaginateParseHotpadsCom
{
    // Page content
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
     * Generating links
     *
     * @return void
     */
    public function parse()
    {
        $countPage = (int)QueueHotpadsCom::clearText(explode('/', $this->content->find('section.styles__AreaNavigation-fyba3j-1 span strong')[0])[1]);
        // echo 'countPage - ' . $countPage;
        for ($i = 0; $i <= $countPage; $i++) {
            Redis::init()->rpush('tasks', json_encode([
                'link' => $this->task['link'] . '&page=' . $i,
                'class' => 'App\\Classes\\PaginateLinkParseHotpadsCom'
            ]));
        }

        return true;
    }
}
