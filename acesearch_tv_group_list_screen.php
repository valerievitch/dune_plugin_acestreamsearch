<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/tv/tv_group_list_screen.php';

class AceSearchTvGroupListScreen extends TvGroupListScreen
    implements UserInputHandler
{
    public static $sort_modes = array(
        'alphabet',
        'usage_count',
        'last_used',
        'added'
    );
    
    public static $sort_mode = 0;
    
    public function __construct($tv) {
        parent::__construct($tv);
        UserInputHandlerRegistry::get_instance()->
            register_handler($this);
    }
    
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies) {
        $view = parent::get_folder_view($media_url, $plugin_cookies);
        if ((AceSearchConfig::item('ace_proxy_ip', $plugin_cookies) == '')
            && !AceSearchConfig::item('use_local_search_engine', $plugin_cookies)
        ) {
            $timer = new GuiTimerDef();
            $timer->delay_ms = 100;
            $view[PluginFolderView::data][PluginRegularFolderView::timer] = 
                    $timer;
        }
        return $view;
    }


    public function get_action_map(MediaURL $media_url, &$plugin_cookies) {
        $actions = parent::get_action_map($media_url, $plugin_cookies);
        
        $action_sort = UserInputHandlerRegistry::create_action($this,
                'history_sort');
        $action_sort['caption'] = T::t('sort_by_'.self::$sort_modes[self::$sort_mode]);

        $action_delete = UserInputHandlerRegistry::create_action($this,
                'history_delete');
        $action_delete['caption'] = T::t('delete');
        
        $action_setup = UserInputHandlerRegistry::create_action($this, 'setup');
        $action_setup['caption'] = T::t('setup');
        
        $actions[GUI_EVENT_KEY_A_RED] = $action_sort;
        $actions[GUI_EVENT_KEY_D_BLUE] = $action_delete;
        $actions[GUI_EVENT_KEY_SETUP] = $action_setup;
        
        $actions[GUI_EVENT_TIMER] = UserInputHandlerRegistry::create_action(
                $this, 'timer');
        
        return $actions;
    }
    
    public function get_handler_id() {
        return self::ID;
    }
    
    public function handle_user_input(&$user_input, &$plugin_cookies) {
//        hd_print(__METHOD__);
//        foreach ($user_input as $key => $value)
//            hd_print("  $key => $value");

        $control_id = $user_input->control_id;

        if (($media_url = MediaURL::decode($user_input->selected_media_url))
                && isset($media_url->group_id))
        {
            $group = $this->tv->get_group($media_url->group_id);
        }
        
        if ($control_id === 'history_delete') {
            if (!isset($group) || empty($group))
                return NULL;
            // confirmation dialog
            $defs = array();
            ControlFactory::add_close_dialog_and_apply_button($defs,
                    $this, NULL,
                    'really_history_delete', T::t('ok'),
                    300);
            ControlFactory::add_close_dialog_button($defs, T::t('cancel'), 300);
            return ActionFactory::show_dialog(
                sprintf(
                    '%%ext%%<key_local>really_delete__1<p>[%s]?</p></key_local>',
                    $group->get_title()),
                $defs);
        } elseif ($control_id === 'really_history_delete') {
            $media_url = MediaURL::decode($user_input->selected_media_url);
            
            AceSearchTv::history_delete($group->get_title(), $plugin_cookies);
            return ActionFactory::invalidate_folders(array(self::ID));

        } elseif ($control_id === 'history_sort') {
            self::$sort_mode++;
            if (self::$sort_mode >= count(self::$sort_modes))
                self::$sort_mode = 0;
            return ActionFactory::invalidate_folders(array(self::ID));
        } elseif ($control_id === 'timer') {
            // force user fill ace proxy settings
            $defs = array();

            ControlFactory::add_close_dialog_and_apply_button($defs,
                    $this, NULL,
                    'setup', T::t('setup'),
                    300);
            
            return ActionFactory::show_dialog(T::t('enter_ace_proxy_ip'), $defs);
        } elseif ($control_id === 'setup') {
            return ActionFactory::open_folder('setup', T::t('setup'));
        }

        return null;
    }
}

///////////////////////////////////////////////////////////////////////////
?>
