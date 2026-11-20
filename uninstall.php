<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

$eventbilletten_url = get_option('eventbilletten_json_url');
if ($eventbilletten_url) delete_transient('eventbilletten_'.md5($eventbilletten_url));

foreach ([
    'eventbilletten_json_url',
    'eventbilletten_feed_status',
    'eventbilletten_manual_events',
    'eventbilletten_manual_categories',
    'eventbilletten_hide_past',
    'eventbilletten_enable_readmore',
    'eventbilletten_enable_filter',
    'eventbilletten_enable_category',
    'eventbilletten_enable_schema',
    'eventbilletten_sort',
    'eventbilletten_layout',
    'eventbilletten_grid_columns',
    'eventbilletten_btn_text',
    'eventbilletten_btn_color',
    'eventbilletten_title_color',
    'eventbilletten_date_color',
] as $eventbilletten_option) {
    delete_option($eventbilletten_option);
}
