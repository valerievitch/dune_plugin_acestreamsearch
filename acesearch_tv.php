<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/hashed_array.php';
require_once 'lib/tv/abstract_tv.php';
require_once 'lib/tv/default_epg_item.php';

require_once 'acesearch_channel.php';

///////////////////////////////////////////////////////////////////////////

class AceSearchTv extends AbstractTv
    implements UserInputHandler
{
    private static $playing_channel_id = NULL;
    
    public function __construct()
    {
        parent::__construct(
            AbstractTv::MODE_CHANNELS_1_TO_N,
            false, // TV_FAVORITES_SUPPORTED,
            false // is_stream_url
        );
        
        UserInputHandlerRegistry::get_instance()->
            register_handler($this);
    }

    public function get_fav_icon_url(&$plugin_cookies)
    {
        return NULL;
    }

    ///////////////////////////////////////////////////////////////////////
    
    public function add_special_groups(&$items, &$plugin_cookies) {
        array_unshift($items, array
            (
                PluginRegularFolderItem::media_url =>
                VodSearchScreen::get_media_url_str(),
                PluginRegularFolderItem::caption => T::t('new_search'),
                PluginRegularFolderItem::view_item_params => array
                (
                    ViewItemParams::icon_path => 'gui_skin://small_icons/torrents.aai',
                    ViewItemParams::item_detailed_icon_path => 'gui_skin://small_icons/torrents.aai'
                )
            )
        );
    }
    
    ///////////////////////////////////////////////////////////////////////
    
    public static function history_add($query, &$plugin_cookies) {
        if (!isset($plugin_cookies->history)) {
            $history = array();
        } else {
            try {
                $history = json_decode($plugin_cookies->history, TRUE);
            } catch (Exception $e) {
                hd_print("Error decoding history: ".$e->getMessage());
                return;
            }
        }
        if (empty($query)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        if (array_key_exists($query, $history)) {
            $history[$query]['usage_count'] += 1;
            $history[$query]['last_used'] = $now;
        } else {
            $history[$query] = array(
                'usage_count' => 1,
                'added' => $now,
                'last_used' => $now
            );
        }
        $plugin_cookies->history = json_encode($history);
    }
    
    public static function history_clear(&$plugin_cookies) {
        if (isset($plugin_cookies->history)) {
            unset($plugin_cookies->history);
            hd_print(__METHOD__.": success");
        } else {
            hd_print(__METHOD__.": nothing to do");
        }
    }
    
    public static function history_delete($query, &$plugin_cookies) {
        if (isset($plugin_cookies->history)) {
            try {
                $history = json_decode($plugin_cookies->history, TRUE);
            } catch (Exception $e) {
                hd_print("Error decoding history: ".$e->getMessage());
                return;
            }
            if (array_key_exists($query, $history)) {
                unset($history[$query]);
                $plugin_cookies->history = json_encode($history);
            }
        }
    }
    
    private static function history_sort_cb($a, $b) {
        $mode = AceSearchTvGroupListScreen::$sort_modes[AceSearchTvGroupListScreen::$sort_mode];
        if (array_key_exists($mode, $a)) {
//            if ($mode == 'usage_count')
//                return $b[$mode]-$a[$mode];
            return strnatcasecmp($b[$mode], $a[$mode]);
        } else {
            return 0;
        }
    }
    ///////////////////////////////////////////////////////////////////////
    
    public function folder_entered(MediaURL $media_url, &$plugin_cookies) {
        // hd_print(__METHOD__.": media_url = ".$media_url->get_raw_string());
        if ($media_url->get_raw_string() == AceSearchTvGroupListScreen::ID) {
            $this->load_groups($plugin_cookies);
        } elseif (isset($media_url->screen_id) 
                && ($media_url->screen_id == AceSearchTvChannelListScreen::ID))
        {
            if ($this->groups->has($media_url->group_id)) {
                $query = $this->get_group($media_url->group_id)->get_title();
            } elseif (isset($plugin_cookies->vod_search_pattern)) {
                $query = $plugin_cookies->vod_search_pattern;
            }
            $plugin_cookies->query = $query;
            self::history_add($query, $plugin_cookies);
            $this->unload_channels();
        }
    }
    ///////////////////////////////////////////////////////////////////////

    private static function ace_api_response($url, $assoc = true, $dont_unfold = true) {
        
        $json = file_get_contents($url);
        if ($json === FALSE) {
            throw new DuneException('HTTP network error', 0,
                ActionFactory::show_error(TRUE,
                    T::t('ace_proxy_http_error'),
                        array(T::t('check_ace_proxy_setup'),
                            T::t('current_settings'),
                            substr($url, 0, strpos($url, '/', 8)))));
        }
        $response = json_decode($json, $assoc);
            //hd_print(var_export($response, true));
        if (is_null($response)) {
            throw new DuneException('Response parsing error', 0,
                ActionFactory::show_error(TRUE,
                    'Cannot parse data from remote search engine'));
        }

        if ($dont_unfold)
            return $response;
        
        $error = is_object($response) ? $response->error : $response['error'];
        if ($error) {
            throw new DuneException('Ace API error: '.$error, 0,
            ActionFactory::show_error(FALSE,
                    'Ace API returned error',
                    array($error)));
        }
        return is_object($response) ? $response->response : $response['response'];
    }
    
    //////////////////////////////////////////////////////////
    
    private static function ace_search($query, &$plugin_cookies) {
        // hack :)
        AceSearchChannel::$buffering_ms =
                AceSearchConfig::item('buffering_ms', $plugin_cookies);
        // main
        $common_query_params = 'group_by_channels=1&show_epg=1&page_size=50';
        if (AceSearchConfig::item('use_local_search_engine', $plugin_cookies)) {
            $proxy_url = 'http://127.0.0.1:6878/server/api';
            // get access token
            $res = self::ace_api_response($proxy_url.'?method=get_api_access_token');
            $token = $res['result']['token'];
            // format search url
            $url = sprintf('%s?method=search&token=%s&%s&query=%s',
                    $proxy_url, $token,
                    $common_query_params, urlencode($query));
        } else {
            $url = sprintf('%s?method=search&api_version=%s&api_key=%s&%s&query=%s',
                    AceSearchConfig::item('remote_search_url', $plugin_cookies),
                    AceSearchConfig::item('remote_search_api_version', $plugin_cookies),
                    AceSearchConfig::item('remote_search_api_key', $plugin_cookies),
                    $common_query_params, urlencode($query));
        }
        // hd_print($url);

        return self::ace_api_response($url);
    }
    
    /////////////////////////////////////////////////////////////////////////
    
    private static function normalize_acestream_item($item, $icon, $epg) {
        $item['icon'] = $icon;
        $item['epg'] = $epg;
        if (!array_key_exists('categories', $item)) {
                $item['categories'] = array('empty');
        }
        if (!array_key_exists('bitrate', $item)) {
                $item['bitrate'] = 0;
        }
        $item['caption'] = sprintf('(%s) %s, %.2f/%s, bitrate %d',
                join(',', $item['categories']),
                $item['name'],
                $item['availability'],
                $item['status'] == 2 ? 'green' :
                    ($item['status'] == 1 ? 'yellow' : 'red' ),
                 $item['bitrate']);
        return $item;
    }
    
    /////////////////////////////////////////////////////////////////////////
    
    private function load_groups(&$plugin_cookies) {
        $this->groups = new HashedArray();

        if (!isset($plugin_cookies->history)) {
            $h =  array('Discovery', '"Футбол 1 HD"', '"Футбол 2 HD"'); // TODO
            foreach ($h as $value) {
                self::history_add($value, $plugin_cookies);
            }
        }
        if (isset($plugin_cookies->history)) {
            $history = json_decode($plugin_cookies->history, TRUE);
            if (AceSearchTvGroupListScreen::$sort_modes[AceSearchTvGroupListScreen::$sort_mode]
                    === 'alphabet')
            {
                ksort($history);
            } else {
                uasort($history, array('AceSearchTv', 'history_sort_cb'));
            }
        } else {
            return;
        }
        // hd_print(__METHOD__.' history: '.var_export($history, TRUE));
        foreach (array_keys($history) as $value) {
            try {
                $this->groups->put(
                    new DefaultGroup(
                        base64_encode($value),
                        $value,
                        NULL)
                );
            } catch (Exception $e) {
                hd_print('duplicate history item: '+$value);
            }
        }
        
    }

    /////////////////////////////////////////////////////////////////////////
    
    protected function load_channels(&$plugin_cookies)
    {
        // hd_print(__METHOD__."\ncookies = ".var_export($plugin_cookies, TRUE));
        $this->channels = new HashedArray();
        $this->load_groups($plugin_cookies);
        
        if (!isset($plugin_cookies->query))
            return;
        $query = strval($plugin_cookies->query);
        if ($query === '')
            return;

        $group = $this->groups->get(base64_encode($query));
        if (!$group)
            return;
        
        $search_results = self::ace_search($query, $plugin_cookies);

        $channel_number = 1;

        if ((AceSearchConfig::item('ace_proxy_ip', $plugin_cookies) == '')
                && !AceSearchConfig::item('use_local_search_engine', $plugin_cookies)
        ) {
            unset($plugin_cookies->query);
            throw new DuneException('Empty Ace Proxy address', 0,
                ActionFactory::show_error(TRUE, T::t('enter_ace_proxy_ip'),
                        array(T::t('check_ace_proxy_setup'))));
        }
        
        if (AceSearchConfig::item('use_local_search_engine', $plugin_cookies)) {
            $ace_proxy = 'http://127.0.0.1:6878';
        } else {
            $ace_proxy = sprintf('http://%s:%d',
                AceSearchConfig::item('ace_proxy_ip', $plugin_cookies),
                AceSearchConfig::item('ace_proxy_port', $plugin_cookies));
        }
        
        for ($i = 0; $i < $search_results['total']; $i++) {
            $res = $search_results['results'][$i];
            $icon = array_key_exists('icon', $res) ? $res['icon'] : NULL;
            $epg = array_key_exists('epg', $res) ? $res['epg'] : NULL;
            foreach ($res['items'] as $item) {
                $item = self::normalize_acestream_item($item, $icon, $epg);
                $item['url'] = sprintf('%s/ace/getstream?infohash=%s',
                        $ace_proxy, $item['infohash']);
                
                $channel = new AceSearchChannel(
                        strval($item['infohash'] /* id */),
                        strval($item['caption'] /* caption */),
                        strval($item['icon'] /* icon_url */),
                        strval($item['url'] /* streaming_url */),
                        intval($channel_number /* number */),
                        $item);
                $channel->add_group($group);
                $group->add_channel($channel);
                $this->channels->put($channel);
                $channel_number++;
            }
        }
//        if (AceSearchConfig::CHANNEL_SORT_FUNC_CB != null)
//            $this->channels->usort(AceSearchConfig::CHANNEL_SORT_FUNC_CB);
        unset($plugin_cookies->query);
        hd_print('load channels complete');
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_tv_group_list_folder_views(&$plugin_cookies)
    {
        return AceSearchConfig::GET_TV_GROUP_LIST_FOLDER_VIEWS();
    }

    public function get_tv_channel_list_folder_views(&$plugin_cookies)
    {
        return AceSearchConfig::GET_TV_CHANNEL_LIST_FOLDER_VIEWS();
    }
    
    //////////////////////////////////////////////////////////////////////
    
    protected function get_day_epg_iterator($channel_id, $day_start_ts, &$plugin_cookies) {
        $channel = $this->get_channel($channel_id);
        $epg_item = $channel->getEpg();
        return
            new EpgIterator(
                $epg_item ? array($epg_item) : array(),
                $day_start_ts,
                $day_start_ts + 86400);
    }

    /////////////////////////////////////////////////////////////////////
    
    public function get_tv_info(MediaURL $media_url, &$plugin_cookies) {
        // hd_print(__METHOD__.': ".var_export($media_url, true));
        
        $info = parent::get_tv_info($media_url, $plugin_cookies);
        
        $info[PluginTvInfo::actions] =
                $this->get_action_map($media_url, $plugin_cookies);

        $timer = new GuiTimerDef();
        $timer->delay_ms = 5000;
        $info[PluginTvInfo::timer] = $timer;

        // keep only current group, else plugin crashes
        if (isset($media_url->channel_id) && isset($media_url->group_id)) {
            $groups = array();
            foreach ($info[PluginTvInfo::groups] as $group) {
                if ($group[PluginTvGroup::id] == $media_url->group_id)
                    array_push ($groups, $group);
            }
            $info[PluginTvInfo::groups] = $groups;
            /*
            foreach ($info[PluginTvInfo::channels] as $channel) {
                $data = self::ace_api_response($item['url'].'&format=json', FALSE, FALSE);
                //hd_print(var_export($data, TRUE));
                $item['playback_url'] = str_replace('://', '://ts://',
                        $data->playback_url);
                $item['command_url'] = $data->command_url;
                $item['stat_url'] = $data->stat_url;
                // $item['stat'] = self::ace_api_response($data->stat_url, FALSE, FALSE);

            }
             
             */
        }
        return $info;
    }
    
    //////////////////////////////////////////////////////////////////////
    
    public function get_search_media_url_str($pattern) {
        return MediaURL::encode(array(
            'screen_id' => AceSearchTvChannelListScreen::ID,
            'group_id' => base64_encode($pattern)
        ));
    }

    //////////////////////////////////////////////////////////////////////
    
    public function get_tv_stream_url($media_url, &$plugin_cookies) {
        $channel_id = substr($media_url, strpos($media_url, 'infohash=')+9);
//        hd_print(__METHOD__.': channel_id='.$channel_id);
        $channel = $this->get_channel($channel_id);
//        hd_print(var_export($channel, TRUE));
        $data = self::ace_api_response($media_url.'&format=json', false, false);

        $channel->set_acestream_data('playback_url',
                str_replace('://', '://ts://', $data->playback_url));
        $channel->set_acestream_data('command_url', $data->command_url);
        $channel->set_acestream_data('stat_url', $data->stat_url);
        //hd_print($channel->get_acestream_data('playback_url'));
        self::$playing_channel_id = $channel_id;
        
        return $channel->get_acestream_data('playback_url');
    }

    //////////////////////////////////////////////////////////////////////
    
    public function get_action_map(MediaURL $media_url, &$plugin_cookies) {
        $actions = array();
        
        $actions[GUI_EVENT_TIMER] = 
                UserInputHandlerRegistry::create_action($this, 'timer');
        $actions[GUI_EVENT_PLAYBACK_STOP] = 
                UserInputHandlerRegistry::create_action($this, 'playback_stop');
//        $actions[GUI_EVENT_PLAYBACK_SWITCHED] = 
//                UserInputHandlerRegistry::create_action($this, 'playback_switched');
//        $actions[GUI_EVENT_PLAYBACK_GOING_TO_SWITCH] = 
//                UserInputHandlerRegistry::create_action($this, 'playback_going_to_switch');
        
        return $actions;
    }

    ////////////////////////////////////////////////////////////////////////
    
    public function handle_user_input(&$user_input, &$plugin_cookies) {
//        hd_print(__METHOD__);
//        foreach ($user_input as $key => $value)
//            hd_print("  $key => $value");

        $channel_id = self::$playing_channel_id;
        $channel = $this->get_channel($channel_id);
        
        if ($user_input->control_id === 'timer') {
            // hd_print('TIMER: '.__CLASS__);

            $stat = self::ace_api_response(
                    $channel->get_acestream_data('stat_url'), false, false);
            //hd_print(var_export($stat, TRUE));
            if (isset($stat->peers))
                $channel->set_acestream_data('peers', $stat->peers);
            if (isset($stat->speed_down))
                $channel->set_acestream_data('speed', $stat->speed_down);
            
            $update_info_action = array(
                    GuiAction::handler_string_id =>
                        PLUGIN_UPDATE_INFO_BLOCK_ACTION_ID,
                    GuiAction::data => array(
                        PluginUpdateInfoBlockActionData::text_above => 
                            sprintf("%d peers, %.2f MBit/s",
                                $channel->get_acestream_data('peers'),
                                $channel->get_acestream_data('speed')*8/1024
                            ),
                        PluginUpdateInfoBlockActionData::text_color => 19,
                        PluginUpdateInfoBlockActionData::text_halo => TRUE
                    )
                );

            $timer = new GuiTimerDef();
            $timer->delay_ms = 5000;

            $change_behaviour_action = array(
                    GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
                    GuiAction::data => array(
                        ChangeBehaviourActionData::timer => $timer,
                        ChangeBehaviourActionData::post_action =>
                            $update_info_action
                    )
                );

            return $change_behaviour_action;
        } elseif ($user_input->control_id === 'playback_stop') {
            if (($command_url = $channel->get_acestream_data('command_url'))) {
                $res = self::ace_api_response(
                        $command_url.'?method=stop', FALSE, FALSE);
                hd_print('PLAYBACK_STOP: '.var_export($res, TRUE));
            }
            self::$playing_channel_id = NULL;
            $update_info_action = array(
                    GuiAction::handler_string_id =>
                        PLUGIN_UPDATE_INFO_BLOCK_ACTION_ID,
                    GuiAction::data => array(
                        PluginUpdateInfoBlockActionData::text_above => ''
                    )
                );
            return $update_info_action;
        } elseif ($user_input->control_id === 'playback_switched') {
            hd_print('PLAYBACK_SWITCHED: ');
        } elseif ($user_input->control_id === 'playback_going_to_switch') {
            hd_print('PLAYBACK_GOING_TO_SWITCH: ');
        }
        return NULL;
    }
    ////////////////////////////////////////////////////////////////////////
    
    public function get_handler_id() {
        return 'acesearch_tv';
    }

}

///////////////////////////////////////////////////////////////////////////
?>
