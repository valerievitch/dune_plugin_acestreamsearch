<?php

// $demo_config_m3u_file_url = dirname(__FILE__) . '/iptv.utf8.m3u';

class AceSearchConfig
{
    // EPG_PAGE_BY_LOCAL_TZ defines how EPG pages are formed:
    //   - true: programs start at 00:00 local time, finish at 23:59 local time;
    //   - false: the set of programs on the page defines by the server reply.
//    const EPG_PAGE_BY_LOCAL_TZ  = true;

    const ALL_CHANNEL_GROUP_CAPTION     = 'Все каналы';
    const ALL_CHANNEL_GROUP_ICON_PATH   = 'plugin_file://icons/all.png';

    // CHANNEL_SORT_FUNC_CB defines the function name to be used as a callback
    // to sort IPTV-channels.
    //
    // If it is null, sorting will not be performed.
    const CHANNEL_SORT_FUNC_CB = 'AceSearchConfig::sort_channels_cb';

    ///////////////////////////////////////////////////////////////////////
    // How to sort channels.

    public static function sort_channels_cb($a, $b)
    {
        // Sort by channel numbers.
        return strnatcasecmp($a->get_number(), $b->get_number());

        // Other options:
        // return strnatcasecmp($a->get_title(), $b->get_title());
    }

    ///////////////////////////////////////////////////////////////////////
    // Folder views.

    public static function GET_TV_GROUP_LIST_FOLDER_VIEWS()
    {
        return array(
            array
            (
                PluginRegularFolderView::async_icon_loading => false,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => TRUE,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => TRUE,
                    ViewItemParams::item_caption_dx => 48,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array (),
            ),
        );
    }

    public static function GET_TV_CHANNEL_LIST_FOLDER_VIEWS()
    {
        return array(
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 48,
                    ViewItemParams::icon_height => 36,
                    ViewItemParams::item_caption_width => 1100, //525,
                    ViewItemParams::item_caption_font_size => 0, //FONT_SIZE_SMALL,
                    ViewItemParams::icon_path => 'plugin_file://icons/channel_unset.png',
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array (),
            ),

        );
    }
    
    public static function defaults($name) {
        static $items = array(
            'use_local_search_engine'   =>  FALSE,
            'remote_search_url'         =>  'https://search.acestream.net',
            'remote_search_api_version' =>  '1.0',
            'remote_search_api_key'     =>  'test_api_key',
            'ace_proxy_ip'              =>  '',
            'ace_proxy_port'            =>  6878,
            'buffering_ms'              =>  10000,
        );
        if (array_key_exists($name, $items)) {
            return $items[$name];
        }
        return NULL;
    }

    public static function item($name, &$plugin_cookies) {
        if (isset($plugin_cookies->{$name})) {
            return $plugin_cookies->{$name};
        } else {
            return self::defaults($name);
        }
    }
}

?>
