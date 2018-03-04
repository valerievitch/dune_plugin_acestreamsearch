<?php

require_once 'lib/vod/vod_search_screen.php';

class AceSearchScreen extends VodSearchScreen
{
    public function __construct(Tv $tv)
    {
        AbstractControlsScreen::__construct(self::ID);

        $this->vod = $tv;
    }
    
}
