<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/default_dune_plugin.php';
require_once 'lib/utils.php';

require_once 'lib/tv/tv_group_list_screen.php';
require_once 'lib/tv/tv_favorites_screen.php';

require_once 'acesearch_config.php';

require_once 'acesearch_tv.php';
require_once 'acesearch_setup_screen.php';
require_once 'acesearch_tv_channel_list_screen.php';
require_once 'acesearch_tv_group_list_screen.php';
require_once 'acesearch_search_screen.php';

///////////////////////////////////////////////////////////////////////////

class AceSearchPlugin extends DefaultDunePlugin
{
    public function __construct()
    {
        $this->tv = new AceSearchTv();

        $this->add_screen(new AceSearchTvGroupListScreen($this->tv));
        $this->add_screen(new AceSearchTvChannelListScreen($this->tv));

        $this->add_screen(new AceSearchSetupScreen());
        
        $this->add_screen(new AceSearchScreen($this->tv));
    }
}

///////////////////////////////////////////////////////////////////////////
?>
