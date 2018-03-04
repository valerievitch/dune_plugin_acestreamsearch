<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/tv/default_channel.php';

///////////////////////////////////////////////////////////////////////////

class AceSearchChannel extends DefaultChannel
{
    private $number;
    private $acestream_data;
    public static $buffering_ms = 0;
    ///////////////////////////////////////////////////////////////////////

    public function __construct(
        $id, $title, $icon_url, $streaming_url, $number, $acestream_data)
    {
        parent::__construct($id, $title, $icon_url, $streaming_url);

        $this->number = $number;
        $this->acestream_data = $acestream_data;
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_number()
    { return $this->number; }
    
    public function get_buffering_ms()
    { return self::$buffering_ms; }

    public function getEpg() {
        if (!$this->acestream_data)
            return NULL;
        if (!array_key_exists('epg', $this->acestream_data))
            return NULL;
        if ($epg = $this->acestream_data['epg']) {
            // hd_print(var_export($epg, TRUE));
            return new DefaultEpgItem($epg['name'], '', $epg['start'], $epg['stop']);
        }
        return NULL;
    }
    
    public function set_acestream_data($key, $value) {
        $this->acestream_data[$key] = $value;
    }
    
    public function get_acestream_data($key = false) {
        return $key ? $this->acestream_data[$key] : $this->acestream_data;
    }
}

///////////////////////////////////////////////////////////////////////////
?>
