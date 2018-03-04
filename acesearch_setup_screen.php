<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/abstract_controls_screen.php';

require_once 'acesearch_tv.php';
require_once 'acesearch_config.php';

///////////////////////////////////////////////////////////////////////////

class AceSearchSetupScreen extends AbstractControlsScreen
{
    const ID = 'setup';

    ///////////////////////////////////////////////////////////////////////

    public function __construct()
    {
        parent::__construct(self::ID);
    }

    public function do_get_control_defs(&$plugin_cookies)
    {
        $defs = array();

        // clear history button
        $this->add_combobox($defs,
                'clear_history', T::t('clear_history'),
                'no', array('no'=>T::t('no'), 'yes'=>T::t('yes')),
                100, TRUE);
        
        $this->add_combobox($defs,
                'use_local_search_engine', T::t('use_local_search_engine'),
                AceSearchConfig::item('use_local_search_engine', $plugin_cookies) ? 'yes' : 'no',
                array('no'=>T::t('no'), 'yes'=>T::t('yes')),
                100, TRUE);
        
        if (!AceSearchConfig::item('use_local_search_engine', $plugin_cookies))
        {
            $this->add_text_field($defs,
                    'ace_proxy_ip', T::t('ace_proxy_ip'),
                    AceSearchConfig::item('ace_proxy_ip', $plugin_cookies),
                    TRUE, FALSE, FALSE, FALSE , 400, TRUE);

            $this->add_text_field($defs,
                    'ace_proxy_port',T::t('ace_proxy_port'),
                    AceSearchConfig::item('ace_proxy_port', $plugin_cookies),
                    TRUE, FALSE, FALSE, FALSE , 200, TRUE);

            $this->add_text_field($defs,
                    'remote_search_url', T::t('remote_search_url'),
                    AceSearchConfig::item('remote_search_url', $plugin_cookies),
                    FALSE, FALSE, FALSE, FALSE , 600, TRUE);

            $this->add_text_field($defs,
                    'remote_search_api_version', T::t('remote_search_api_version'),
                    AceSearchConfig::item('remote_search_api_version', $plugin_cookies),
                    TRUE, FALSE, FALSE, FALSE , 200, TRUE);

            $this->add_text_field($defs,
                    'remote_search_api_key', T::t('remote_search_api_key'),
                    AceSearchConfig::item('remote_search_api_key', $plugin_cookies),
                    FALSE, FALSE, FALSE, FALSE , 400, TRUE);
        }    

        $this->add_text_field($defs,
                'buffering_ms', T::t('buffering_sec'),
                intval(AceSearchConfig::item('buffering_ms', $plugin_cookies)/1000),
                TRUE, FALSE, FALSE, FALSE , 200, TRUE);

        $this->add_button($defs,
                'go2app', NULL, T::t('go2app'), 600);
        
        return $defs;
    }

    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs($plugin_cookies);
    }

    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
//        hd_print('Setup: handle_user_input:');
//        foreach ($user_input as $key => $value)
//            hd_print("  $key => $value");

        if ($user_input->action_type === 'confirm')
        {
            $control_id = $user_input->control_id;
            $new_value = $user_input->{$control_id};
            hd_print("Setup: changing $control_id value to $new_value");

            if ($control_id === 'clear_history') {
                if ($new_value === 'yes') {
                    AceSearchTv::history_clear($plugin_cookies);
                    return ActionFactory::show_title_dialog(
                        T::t('history_cleared'),
                        ActionFactory::reset_controls(
                            $this->do_get_control_defs($plugin_cookies)));
                }
            } elseif ($control_id === 'use_local_search_engine') {
                $plugin_cookies->use_local_search_engine = 
                    $new_value == 'yes' ? TRUE : FALSE;
            } else {
                if ($control_id === 'buffering_ms') { $new_value *= 1000; }
                $plugin_cookies->{$control_id} = $new_value;
                return ActionFactory::show_title_dialog(T::t('changes_saved'),
                    ActionFactory::invalidate_folders(
                        array(AceSearchTvGroupListScreen::ID)));
            }
        } elseif ($user_input->action_type === 'apply') {
            $control_id = $user_input->control_id;
            if ($control_id === 'go2app') {
                return array (
                        GuiAction::handler_string_id => LAUNCH_MEDIA_URL_ACTION_ID,
                        GuiAction::data => array(
                            LaunchMediaUrlActionData::url => 'plugin_launcher://acestreamsearch'
                        ),
                    );
            }
        }
        return ActionFactory::reset_controls(
            $this->do_get_control_defs($plugin_cookies));
    }
    
}

///////////////////////////////////////////////////////////////////////////
?>
