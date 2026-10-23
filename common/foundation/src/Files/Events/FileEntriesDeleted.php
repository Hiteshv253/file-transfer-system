<?php

namespace Common\Files\Events;

class FileEntriesDeleted
{
    /**
     * @var array
     */
    public $entryIds;

    /**
     * @var bool
     */
    public $permanently;

    /**
     * @var array
     */
    public $entries;

    /**
     * @param array $entryIds
     * @param boolean $permanently
     * @param array $entries
     */
    public function __construct($entryIds, $permanently, $entries = [])
    {
        $this->entryIds = $entryIds;
        $this->permanently = $permanently;
        $this->entries = $entries;
    }
}
