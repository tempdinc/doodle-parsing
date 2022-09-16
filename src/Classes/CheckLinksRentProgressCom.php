<?php

namespace App\Classes;

use DiDom\Document;

/**
 * Class to check records relevance
 */
class CheckLinksRentProgressCom
{
    // Storing information in the task
    protected $task;
    // Page content
    protected $content;

    /**
     * A builder with parameters
     *
     * @param  string $content
     * @param  string $task
     */
    public function __construct($content, $task)
    {
        $this->task = $task;
        $this->content = $content;
    }

    /**
     * Parsing data from $content
     *
     * @return void
     */
    public function parse()
    {
        $content = new Document($this->content);

        // Looking for the address string
        $address = isset($content->find('p.info-address-item')[0]) ?
            $this->clearText($content->find('p.info-address-item')[0]->text()) : '';
        // Looking for 'Page Not Found'
        $notFound = isset($content->find('span.subHeader')[0]) ?
            $this->clearText($content->find('span.subHeader')[0]->text()) : '';
        // Updating is_deleted=0 column
        $date = date('Y-m-d H:i:s');
        if ($address == 'No Street' || $notFound == 'Page Not Found' || ($address == '' && $notFound == '')) {
            $db = new MySQL('parsing','local');
            $db->update('properties', [
                'link'          => $this->task['link'],
                'is_deleted'    => 1,
                'last_update'  => $date
            ]);
        }

        return true;
    }

    /**
     * Cleaning the text
     *
     * @param string $text
     * @return string
     */
    protected function clearText($text)
    {
        $text = preg_replace('/(?:&nbsp;|\h)+/u', ' ', $text);
        $text = preg_replace('/\h*(\R)\s*/u', '$1', $text);
        $text = trim($text);

        return $text;
    }
}
