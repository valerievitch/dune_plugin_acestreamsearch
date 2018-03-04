<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/tv/tv_channel_list_screen.php';

class AceSearchTvChannelListScreen extends TvChannelListScreen
{
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies) {
        $view = parent::get_folder_view($media_url, $plugin_cookies);
        // register timer event for updating tv_group_list
        $timer = new GuiTimerDef();
        $timer->delay_ms = 100;
        $view[PluginFolderView::data][PluginRegularFolderView::timer] = 
                $timer;
        return $view;
    }
    
    public function get_action_map(MediaURL $media_url, &$plugin_cookies) {
        $actions = parent::get_action_map($media_url, $plugin_cookies);
        
        $actions[GUI_EVENT_TIMER] = 
                UserInputHandlerRegistry::create_action($this, 'timer');
        
        return $actions;
    }
    
    public function handle_user_input(&$user_input, &$plugin_cookies) {
        // hd_print(__METHOD__);
        if ($user_input->control_id === 'timer') {
            $media_url = AceSearchTvGroupListScreen::ID;
            // hd_print('TIMER: '.$media_url);
            return ActionFactory::invalidate_folders(array($media_url));
        }
        return parent::handle_user_input($user_input, $plugin_cookies);
    }
}

///////////////////////////////////////////////////////////////////////////
?>
