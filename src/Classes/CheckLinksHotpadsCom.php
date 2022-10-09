<?php

namespace App\Classes;

/**
 * Class to check records relevance
 */
class CheckLinksHotpadsCom
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
     * @param object $content
     * @param array $task
     * @param boolean $error
     */
    public function __construct($content, $task, $error = false)
    {
        $this->task = $task;
        $this->error = $error;
        $this->content = $content;
    }

    /**
     * Check record relevance
     *
     * @return void
     */
    public function parse()
    {
        // If the response was 404
        if ($this->error) {
            $db = new MySQL('parsing','local');
            $db->update('properties', [
                'link' => $this->task['link'],
                'is_deleted' => 1
            ]);

            return true;
        }

        // If the answer is different from 404 but the ad does not exist anymore
        $header = isset($this->content->find('section.styles__AreaNavigation-fyba3j-1')[0]) ?
            $this->content->find('section.styles__AreaNavigation-fyba3j-1')[0] : '';
        
        if ($header) {
            $db = new MySQL('parsing','local');
            $db->update('properties', [
                'link' => $this->task['link'],
                'is_deleted' => 1
            ]);
        }
            
        return true;
    }
}
