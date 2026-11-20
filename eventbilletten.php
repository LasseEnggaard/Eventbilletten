<?php
/**
 * Plugin Name:       Eventbilletten
 * Description:       Shows events from an Eventbilletten JSON feed together with manually created events. Region and category filters, "read more" popup, ticket button and Event schema. Insert with the shortcode [eventbilletten].
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Eventbilletten
 * Author URI:        https://eventbilletten.dk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eventbilletten
 */

if (!defined('ABSPATH')) exit;

define('EVENTBILLETTEN_VERSION', '1.0.0');
define('EVENTBILLETTEN_SITE_URL', 'https://www.eventbilletten.dk/');
define('EVENTBILLETTEN_DEMO_URL', 'https://meetings.hubspot.com/daniel1297/onlinemode30');
define('EVENTBILLETTEN_EMBED_URL', 'https://vivenu.com/web/deliver/js/v1/embed.js');
define('EVENTBILLETTEN_BTN_TEXT', 'KØB BILLET');

// Filens ændringstid som version, så browseren henter ny CSS og JS efter en opdatering
function eventbilletten_asset_version($file){
    $time = filemtime(plugin_dir_path(__FILE__).$file);
    return $time ? EVENTBILLETTEN_VERSION.'.'.$time : EVENTBILLETTEN_VERSION;
}

// Undersider i admin (slug => menutekst)
function eventbilletten_admin_pages(){
    return [
        'eventbilletten'              => 'Eventbilletten',
        'eventbilletten-manuel'       => 'Tilføj manuelt event',
        'eventbilletten-indstillinger'=> 'Indstillinger',
        'eventbilletten-guide'        => 'Sådan bruger du pluginnet',
    ];
}

function eventbilletten_admin_url($page = 'eventbilletten', $args = []){
    return add_query_arg($args, admin_url('admin.php?page='.$page));
}

function eventbilletten_is_plugin_page(){
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- læser kun hvilken adminside der vises
    $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
    return isset(eventbilletten_admin_pages()[$page]);
}


// ==========================
// MENUIKON (billet, farves automatisk af WordPress)
// ==========================
function eventbilletten_menu_icon(){
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><g transform="rotate(-35 10 10)">'
         . '<path fill="black" fill-rule="evenodd" d="M3.5 5.5H16.5A1.5 1.5 0 0 1 18 7V8.2A1.8 1.8 0 0 0 18 11.8V13A1.5 1.5 0 0 1 16.5 14.5H3.5A1.5 1.5 0 0 1 2 13V11.8A1.8 1.8 0 0 0 2 8.2V7A1.5 1.5 0 0 1 3.5 5.5ZM6.5 6.95h1v1h-1zM6.5 8.65h1v1h-1zM6.5 10.35h1v1h-1zM6.5 12.05h1v1h-1z"/>'
         . '</g></svg>';
    return 'data:image/svg+xml;base64,'.base64_encode($svg);
}


// ==========================
// ADMIN MENU
// ==========================
add_action('admin_menu', function(){
    add_menu_page('Eventbilletten', 'Eventbilletten', 'manage_options', 'eventbilletten', 'eventbilletten_page_feed', eventbilletten_menu_icon(), 26);

    add_submenu_page('eventbilletten', 'Eventbilletten', 'Eventbilletten', 'manage_options', 'eventbilletten', 'eventbilletten_page_feed');
    add_submenu_page('eventbilletten', 'Tilføj manuelt event', 'Tilføj manuelt event', 'manage_options', 'eventbilletten-manuel', 'eventbilletten_page_manual');
    add_submenu_page('eventbilletten', 'Indstillinger', 'Indstillinger', 'manage_options', 'eventbilletten-indstillinger', 'eventbilletten_page_settings');
    add_submenu_page('eventbilletten', 'Sådan bruger du pluginnet', 'Sådan bruger du pluginnet', 'manage_options', 'eventbilletten-guide', 'eventbilletten_page_guide');
});


// ==========================
// INDSTILLINGER (to grupper, så siderne ikke nulstiller hinanden)
// ==========================
function eventbilletten_sanitize_checkbox($v){ return $v ? 1 : 0; }

function eventbilletten_color_defaults(){
    return [
        'eventbilletten_btn_color'   => '#344772',
        'eventbilletten_title_color' => '#111827',
        'eventbilletten_date_color'  => '#aa0000',
    ];
}

function eventbilletten_get_color($option){
    $defaults = eventbilletten_color_defaults();
    $color = sanitize_hex_color(get_option($option, $defaults[$option]));
    return $color ?: $defaults[$option];
}

function eventbilletten_btn_text(){
    return get_option('eventbilletten_btn_text', EVENTBILLETTEN_BTN_TEXT) ?: EVENTBILLETTEN_BTN_TEXT;
}

add_action('admin_init', function(){
    register_setting('eventbilletten_feed', 'eventbilletten_json_url', [
        'sanitize_callback' => 'esc_url_raw',
        'default'           => '',
    ]);

    foreach(['eventbilletten_hide_past','eventbilletten_enable_readmore','eventbilletten_enable_filter','eventbilletten_enable_category'] as $opt){
        register_setting('eventbilletten_settings', $opt, ['sanitize_callback' => 'eventbilletten_sanitize_checkbox', 'default' => 0]);
    }
    register_setting('eventbilletten_settings', 'eventbilletten_enable_schema', ['sanitize_callback' => 'eventbilletten_sanitize_checkbox', 'default' => 1]);
    register_setting('eventbilletten_settings', 'eventbilletten_sort', [
        'sanitize_callback' => fn($v) => $v === 'manual' ? 'manual' : 'date',
        'default'           => 'date',
    ]);
    register_setting('eventbilletten_settings', 'eventbilletten_layout', [
        'sanitize_callback' => fn($v) => $v === 'grid' ? 'grid' : 'list',
        'default'           => 'list',
    ]);
    register_setting('eventbilletten_settings', 'eventbilletten_grid_columns', [
        'sanitize_callback' => fn($v) => in_array((int)$v, [2, 3, 4], true) ? (int)$v : 3,
        'default'           => 3,
    ]);
    register_setting('eventbilletten_settings', 'eventbilletten_btn_text', [
        'sanitize_callback' => fn($v) => sanitize_text_field($v) ?: EVENTBILLETTEN_BTN_TEXT,
        'default'           => EVENTBILLETTEN_BTN_TEXT,
    ]);

    foreach(eventbilletten_color_defaults() as $opt => $default){
        register_setting('eventbilletten_settings', $opt, [
            'sanitize_callback' => function($v) use ($default){ return sanitize_hex_color($v) ?: $default; },
            'default'           => $default,
        ]);
    }
});

// Ny feed-URL: ryd cache for både gammel og ny
add_action('update_option_eventbilletten_json_url', function($old, $new){
    if($old) delete_transient('eventbilletten_'.md5($old));
    if($new) delete_transient('eventbilletten_'.md5($new));
    delete_option('eventbilletten_feed_status');
}, 10, 2);


// ==========================
// DATO-HJÆLPERE
// ==========================
// Læser dd-mm-åååå (feed og gamle events) og åååå-mm-dd (kalenderfeltet)
function eventbilletten_parse_date($s){
    $s = trim((string)$s);
    if($s === '') return null;
    foreach(['!d-m-Y', '!Y-m-d', '!d.m.Y', '!d/m/Y'] as $f){
        $d = DateTime::createFromFormat($f, $s, wp_timezone());
        if($d) return $d;
    }
    return null;
}

// Returnerer altid tt:mm i 24-timers format; accepterer 19:30, 19.30 og 7:30 pm
function eventbilletten_normalize_time($t){
    $t = trim((string)$t);
    if(!preg_match('/^(\d{1,2})[:.]([0-5]\d)\s*(am|pm)?/i', $t, $m)) return '';

    $h = (int)$m[1];
    if(!empty($m[3])){
        $pm = strtolower($m[3]) === 'pm';
        if($h === 12)  $h = $pm ? 12 : 0;
        elseif($pm && $h < 12) $h += 12;
    }
    return $h <= 23 ? sprintf('%02d:%02d', $h, $m[2]) : '';
}

function eventbilletten_month_short($d){
    $months = ['jan','feb','mar','apr','maj','jun','jul','aug','sep','okt','nov','dec'];
    return $months[(int)$d->format('n') - 1];
}

function eventbilletten_day_name($d){
    $days = ['Søndag','Mandag','Tirsdag','Onsdag','Torsdag','Fredag','Lørdag'];
    return $days[(int)$d->format('w')];
}


// ==========================
// MANUELLE EVENTS (med fast id)
// ==========================
// Id'er skal kun bestå af små bogstaver og tal, fordi de læses med sanitize_key() ved ret, slet og rækkefølge
function eventbilletten_new_id(){
    return 'm'.strtolower(wp_generate_password(10, false, false));
}

function eventbilletten_get_manual_events(){
    $events = get_option('eventbilletten_manual_events', []);
    if(!is_array($events)) $events = [];

    $changed = false;
    $seen    = [];
    foreach($events as $i => $e){
        $id = sanitize_key($e['id'] ?? '');
        if($id === '' || isset($seen[$id])) $id = eventbilletten_new_id();
        $seen[$id] = true;
        if($id !== ($e['id'] ?? null)){
            $events[$i]['id'] = $id;
            $changed = true;
        }
    }
    if($changed) update_option('eventbilletten_manual_events', array_values($events));

    return array_values($events);
}

function eventbilletten_find_manual_event($id){
    foreach(eventbilletten_get_manual_events() as $e){
        if($e['id'] === $id) return $e;
    }
    return null;
}

add_action('admin_post_eventbilletten_save_manual', function(){
    if(!current_user_can('manage_options')) wp_die('Ingen adgang');
    check_admin_referer('eb_manual_nonce');

    $date = eventbilletten_parse_date(sanitize_text_field(wp_unslash($_POST['eb_date'] ?? '')));
    if(!$date){
        wp_safe_redirect(eventbilletten_admin_url('eventbilletten-manuel', ['error' => 'date']));
        exit;
    }

    $id = sanitize_key(wp_unslash($_POST['eb_id'] ?? ''));

    $data = [
        'id'           => $id ?: eventbilletten_new_id(),
        'title'        => sanitize_text_field(wp_unslash($_POST['eb_title']   ?? '')),
        'date'         => $date->format('d-m-Y'),
        'time'         => eventbilletten_normalize_time(sanitize_text_field(wp_unslash($_POST['eb_time'] ?? ''))),
        'name'         => sanitize_text_field(wp_unslash($_POST['eb_name']    ?? '')),
        'address'      => sanitize_text_field(wp_unslash($_POST['eb_address'] ?? '')),
        'zip'          => sanitize_text_field(wp_unslash($_POST['eb_zip']     ?? '')),
        'city'         => sanitize_text_field(wp_unslash($_POST['eb_city']    ?? '')),
        'url'          => esc_url_raw(wp_unslash($_POST['eb_url']             ?? '')),
        'btn_text'     => sanitize_text_field(wp_unslash($_POST['eb_btn_text'] ?? '')),
        'desc'         => sanitize_textarea_field(wp_unslash($_POST['eb_desc'] ?? '')),
        'readmore_url' => esc_url_raw(wp_unslash($_POST['eb_readmore_url']   ?? '')),
        'category'     => sanitize_text_field(wp_unslash($_POST['eb_category'] ?? '')),
        'img_id'       => absint($_POST['eb_img_id'] ?? 0),
        'hidden'       => empty($_POST['eb_hidden']) ? 0 : 1,
    ];

    $events  = eventbilletten_get_manual_events();
    $updated = false;

    if($id){
        foreach($events as $i => $e){
            if($e['id'] === $id){
                $events[$i] = $data;
                $updated = true;
                break;
            }
        }
    }
    if(!$updated) $events[] = $data;

    update_option('eventbilletten_manual_events', array_values($events));

    wp_safe_redirect(eventbilletten_admin_url('eventbilletten-manuel', [$updated ? 'updated' : 'saved' => 1]));
    exit;
});

add_action('admin_post_eventbilletten_delete_manual', function(){
    if(!current_user_can('manage_options')) wp_die('Ingen adgang');
    check_admin_referer('eb_delete_nonce');

    $id     = sanitize_key(wp_unslash($_POST['eb_id'] ?? ''));
    $events = array_filter(eventbilletten_get_manual_events(), fn($e) => $e['id'] !== $id);
    update_option('eventbilletten_manual_events', array_values($events));

    wp_safe_redirect(eventbilletten_admin_url('eventbilletten-manuel', ['deleted' => 1]));
    exit;
});

add_action('admin_post_eventbilletten_toggle_manual', function(){
    if(!current_user_can('manage_options')) wp_die('Ingen adgang');
    check_admin_referer('eb_toggle_nonce');

    $id     = sanitize_key(wp_unslash($_POST['eb_id'] ?? ''));
    $events = eventbilletten_get_manual_events();
    $hidden = 0;

    foreach($events as $i => $e){
        if($e['id'] === $id){
            $hidden = empty($e['hidden']) ? 1 : 0;
            $events[$i]['hidden'] = $hidden;
            break;
        }
    }
    update_option('eventbilletten_manual_events', $events);

    wp_safe_redirect(eventbilletten_admin_url('eventbilletten-manuel', [$hidden ? 'hidden' : 'shown' => 1]));
    exit;
});


add_action('admin_post_eventbilletten_save_order', function(){
    if(!current_user_can('manage_options')) wp_die('Ingen adgang');
    check_admin_referer('eb_order_nonce');

    $events = eventbilletten_get_manual_events();

    if(!empty($_POST['eb_sort_by_date'])){
        usort($events, function($a, $b){
            $da = eventbilletten_parse_date($a['date']); $db = eventbilletten_parse_date($b['date']);
            return ($da ? $da->getTimestamp() : 0) <=> ($db ? $db->getTimestamp() : 0);
        });
    } else {
        $order  = array_map('sanitize_key', explode(',', sanitize_text_field(wp_unslash($_POST['eb_order'] ?? ''))));
        $pos    = array_flip($order);
        usort($events, fn($a, $b) => ($pos[$a['id']] ?? PHP_INT_MAX) <=> ($pos[$b['id']] ?? PHP_INT_MAX));
    }

    update_option('eventbilletten_manual_events', array_values($events));

    wp_safe_redirect(eventbilletten_admin_url('eventbilletten-manuel', ['order_saved' => 1]));
    exit;
});

// Billed-URL til et manuelt event
function eventbilletten_manual_image_url($me, $size = 'large'){
    $id = absint($me['img_id'] ?? 0);
    if(!$id) return '';
    $url = wp_get_attachment_image_url($id, $size);
    return $url ?: '';
}


// ==========================
// MANUELLE KATEGORIER
// ==========================
add_action('admin_post_eventbilletten_save_category', function(){
    if(!current_user_can('manage_options')) wp_die('Ingen adgang');
    check_admin_referer('eb_category_nonce');

    $cats = (array)get_option('eventbilletten_manual_categories', []);
    $name = sanitize_text_field(wp_unslash($_POST['eb_category_name'] ?? ''));

    if($name !== '' && !in_array($name, $cats, true)){
        $cats[] = $name;
        update_option('eventbilletten_manual_categories', $cats);
    }

    wp_safe_redirect(eventbilletten_admin_url('eventbilletten-manuel', ['cat_saved' => 1]));
    exit;
});

add_action('admin_post_eventbilletten_delete_category', function(){
    if(!current_user_can('manage_options')) wp_die('Ingen adgang');
    check_admin_referer('eb_delete_category_nonce');

    $name = sanitize_text_field(wp_unslash($_POST['eb_category_name'] ?? ''));
    $cats = array_filter((array)get_option('eventbilletten_manual_categories', []), fn($c) => $c !== $name);
    update_option('eventbilletten_manual_categories', array_values($cats));

    wp_safe_redirect(eventbilletten_admin_url('eventbilletten-manuel', ['cat_deleted' => 1]));
    exit;
});


// ==========================
// JSON-FEED (med timeout og cache af fejl)
// ==========================
function eventbilletten_get_feed_events(){
    $url = get_option('eventbilletten_json_url');
    if(!$url) return [];

    $key    = 'eventbilletten_'.md5($url);
    $cached = get_transient($key);
    if(is_array($cached) && isset($cached['events'])) return $cached['events'];

    $events = [];
    $error  = '';
    $res    = wp_remote_get($url, ['timeout' => 10]);

    if(is_wp_error($res)){
        $error = $res->get_error_message();
    } elseif(($code = wp_remote_retrieve_response_code($res)) !== 200){
        $error = 'Feedet svarede med HTTP '.$code;
    } else {
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if(!is_array($data)){
            $error = 'Feedet returnerede ikke gyldig JSON';
        } else {
            $events = $data['data']['events']['event'] ?? [];
            if(isset($events['url'])) $events = [$events];
            if(!is_array($events)) $events = [];
        }
    }

    // Fejl caches i 5 minutter, så et nede feed ikke rammes ved hver sidevisning
    set_transient($key, ['events' => $events], $error ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS);
    update_option('eventbilletten_feed_status', [
        'time'  => time(),
        'error' => $error,
        'count' => count($events),
    ], false);

    return $events;
}

add_action('admin_post_eventbilletten_clear_cache', function(){
    if(!current_user_can('manage_options')) wp_die('Ingen adgang');
    check_admin_referer('eb_clear_cache');

    $url = get_option('eventbilletten_json_url');
    if($url) delete_transient('eventbilletten_'.md5($url));
    delete_option('eventbilletten_feed_status');

    wp_safe_redirect(eventbilletten_admin_url('eventbilletten', ['cache_cleared' => 1]));
    exit;
});


// ==========================
// KATEGORI-HJÆLPERE
// ==========================
// Udtræk kategorinavne fra ét JSON-event (robust over for flere feed-formater)
function eventbilletten_extract_categories($e){
    $out = [];
    $candidates = ['category','categories','eventcategory','eventcategories','categoryname','genre'];

    foreach($candidates as $key){
        if(!isset($e[$key])) continue;
        $val = $e[$key];

        if(is_array($val) && isset($val['category'])) $val = $val['category'];

        if(is_string($val)){
            foreach(preg_split('/[,;|\/]/', $val) as $p){
                $p = trim($p);
                if($p !== '') $out[] = $p;
            }
        } elseif(is_array($val)){
            if(isset($val['name']) || isset($val['title']) || isset($val['categoryname'])){
                $out[] = $val['name'] ?? $val['title'] ?? $val['categoryname'];
            } else {
                foreach($val as $item){
                    if(is_string($item)){
                        $t = trim($item);
                        if($t !== '') $out[] = $t;
                    } elseif(is_array($item)){
                        $n = $item['name'] ?? $item['title'] ?? $item['categoryname'] ?? $item['value'] ?? null;
                        if($n) $out[] = trim($n);
                    }
                }
            }
        }
    }

    return array_values(array_unique(array_filter($out, fn($v) => $v !== '')));
}

function eventbilletten_category_slug($name){
    $slug = sanitize_title($name);
    return $slug !== '' ? $slug : 'kat-'.substr(md5($name), 0, 6);
}

function eventbilletten_get_all_categories(){
    $cats = get_option('eventbilletten_manual_categories', []);
    foreach(eventbilletten_get_feed_events() as $e){
        foreach(eventbilletten_extract_categories($e) as $c) $cats[] = $c;
    }
    $cats = array_values(array_unique(array_filter(array_map('trim', $cats), fn($v) => $v !== '')));
    sort($cats, SORT_NATURAL | SORT_FLAG_CASE);
    return $cats;
}


// ==========================
// ADMIN: FÆLLES HEADER, NOTICES OG CSS
// ==========================
function eventbilletten_admin_header($active){
    ?>
    <div class="eb-head">
        <div class="eb-head-brand">
            <a href="<?php echo esc_url(EVENTBILLETTEN_SITE_URL); ?>" target="_blank" rel="noopener" class="eb-logo"><svg viewBox="0 0 245 40" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Eventbilletten"><path d="M54.5002 26.2664V22.4569H62.2002V17.7871H54.5002V14.2234H62.9702V9.49219H49.5723V30.9976H63.1242V26.2664H54.5002Z" fill="currentColor"></path><path d="M75.5757 15.6367L72.3725 25.3755L69.1693 15.6367H64.0566L69.7545 30.9977H74.9905L80.6884 15.6367H75.5757Z" fill="currentColor"></path><path d="M85.9092 25.1609H96.8431C96.9663 24.5772 97.0279 23.9627 97.0279 23.3176C97.0279 18.6786 93.7016 15.207 89.2048 15.207C84.3384 15.207 81.0121 18.74 81.0121 23.3176C81.0121 27.8951 84.2768 31.4282 89.5436 31.4282C92.4696 31.4282 94.7488 30.3529 96.2579 28.2638L92.562 26.144C91.946 26.8199 90.868 27.3114 89.6052 27.3114C87.9112 27.3114 86.4944 26.7584 85.9092 25.1609ZM85.8168 21.72C86.248 20.1532 87.4184 19.293 89.174 19.293C90.56 19.293 91.946 19.9382 92.4388 21.72H85.8168Z" fill="currentColor"></path><path d="M108.673 15.207C106.64 15.207 105.1 15.9443 104.268 17.081V15.6371H99.6483V30.9981H104.268V22.611C104.268 20.4297 105.439 19.4466 107.133 19.4466C108.611 19.4466 109.812 20.3375 109.812 22.2423V30.9981H114.432V21.5664C114.432 17.419 111.783 15.207 108.673 15.207Z" fill="currentColor"></path><path d="M126.629 20.0609V15.637H123.457V11.3359L118.837 12.7184V15.637H116.373V20.0609H118.837V25.4987C118.837 29.7998 120.592 31.6124 126.629 30.998V26.8198C124.596 26.9427 123.457 26.8198 123.457 25.4987V20.0609H126.629Z" fill="currentColor"></path><path d="M138.454 15.2065C136.39 15.2065 134.881 15.9131 133.865 17.0805V9.49219H129.245V30.9976H133.865V29.5536C134.881 30.7211 136.39 31.4277 138.454 31.4277C142.489 31.4277 145.815 27.8946 145.815 23.3171C145.815 18.7395 142.489 15.2065 138.454 15.2065ZM137.53 27.0652C135.405 27.0652 133.865 25.6212 133.865 23.3171C133.865 21.0129 135.405 19.569 137.53 19.569C139.655 19.569 141.195 21.0129 141.195 23.3171C141.195 25.6212 139.655 27.0652 137.53 27.0652Z" fill="currentColor"></path><path d="M150.745 14.2878C152.254 14.2878 153.517 13.0282 153.517 11.5228C153.517 10.0175 152.254 8.75781 150.745 8.75781C149.235 8.75781 147.973 10.0175 147.973 11.5228C147.973 13.0282 149.235 14.2878 150.745 14.2878ZM148.435 31.0006H153.055V15.6396H148.435V31.0006Z" fill="currentColor"></path><path d="M156.435 30.9974H161.055V8.57031H156.435V30.9974Z" fill="currentColor"></path><path d="M164.436 30.9974H169.056V8.57031H164.436V30.9974Z" fill="currentColor"></path><path d="M176.564 25.1609H187.498C187.621 24.5772 187.683 23.9627 187.683 23.3176C187.683 18.6786 184.356 15.207 179.86 15.207C174.993 15.207 171.667 18.74 171.667 23.3176C171.667 27.8951 174.932 31.4282 180.198 31.4282C183.124 31.4282 185.403 30.3529 186.913 28.2638L183.217 26.144C182.601 26.8199 181.523 27.3114 180.26 27.3114C178.566 27.3114 177.149 26.7584 176.564 25.1609ZM176.472 21.72C176.903 20.1532 178.073 19.293 179.829 19.293C181.215 19.293 182.601 19.9382 183.094 21.72H176.472Z" fill="currentColor"></path><path d="M198.876 20.0609V15.637H195.704V11.3359L191.084 12.7184V15.637H188.62V20.0609H191.084V25.4987C191.084 29.7998 192.839 31.6124 198.876 30.998V26.8198C196.843 26.9427 195.704 26.8198 195.704 25.4987V20.0609H198.876Z" fill="currentColor"></path><path d="M210.035 20.0609V15.637H206.863V11.3359L202.243 12.7184V15.637H199.779V20.0609H202.243V25.4987C202.243 29.7998 203.998 31.6124 210.035 30.998V26.8198C208.002 26.9427 206.863 26.8198 206.863 25.4987V20.0609H210.035Z" fill="currentColor"></path><path d="M216.477 25.1609H227.411C227.534 24.5772 227.596 23.9627 227.596 23.3176C227.596 18.6786 224.27 15.207 219.773 15.207C214.907 15.207 211.58 18.74 211.58 23.3176C211.58 27.8951 214.845 31.4282 220.112 31.4282C223.038 31.4282 225.317 30.3529 226.826 28.2638L223.13 26.144C222.514 26.8199 221.436 27.3114 220.173 27.3114C218.479 27.3114 217.062 26.7584 216.477 25.1609ZM216.385 21.72C216.816 20.1532 217.986 19.293 219.742 19.293C221.128 19.293 222.514 19.9382 223.007 21.72H216.385Z" fill="currentColor"></path><path d="M239.241 15.207C237.208 15.207 235.668 15.9443 234.836 17.081V15.6371H230.216V30.9981H234.836V22.611C234.836 20.4297 236.007 19.4466 237.701 19.4466C239.179 19.4466 240.38 20.3375 240.38 22.2423V30.9981H245V21.5664C245 17.419 242.352 15.207 239.241 15.207Z" fill="currentColor"></path><path d="M8.22011 6.58653C6.80939 6.58653 5.68081 5.43851 5.68081 4.00349H2.85938V20.1714H5.02248L9.39571 6.29953C9.06655 6.49087 8.64333 6.58653 8.22011 6.58653ZM21.1987 5.96469C20.6345 7.68672 18.8475 8.6434 17.1547 8.06939C15.4618 7.49538 14.5213 5.67768 15.0856 3.95565L11.6999 2.85547L5.58677 22.1805L7.84391 22.9458L23.738 6.8257L21.1987 5.96469ZM34.4595 15.7229C32.9077 17.3014 30.3684 17.3014 28.8166 15.7229C27.2648 14.1443 27.2648 11.5613 28.8166 9.98276L25.666 6.77787L8.03201 24.6679L11.1826 27.8727C12.7344 26.2942 15.2737 26.2942 16.8255 27.8727C18.3773 29.4513 18.3773 32.0343 16.8255 33.6129L19.9761 36.8177L37.6101 18.9278L34.4595 15.7229ZM23.2208 15.675C22.9857 15.9142 22.5624 15.9142 22.3273 15.675C22.1863 15.5315 22.1392 15.388 22.1392 15.2445C22.1392 15.101 22.1863 14.9097 22.3273 14.814C22.5624 14.5749 22.9857 14.5749 23.2208 14.814C23.4559 15.0532 23.4559 15.4359 23.2208 15.675ZM24.8196 17.3492C24.5845 17.5884 24.1613 17.5884 23.9261 17.3492C23.7851 17.2057 23.738 17.0622 23.738 16.9187C23.738 16.7752 23.7851 16.5839 23.9261 16.4882C24.1613 16.249 24.5845 16.249 24.8196 16.4882C25.1017 16.6796 25.1017 17.1101 24.8196 17.3492ZM26.4654 18.9756C26.2303 19.2148 25.8071 19.2148 25.572 18.9756C25.4309 18.8321 25.3839 18.6886 25.3839 18.5451C25.3839 18.4016 25.4309 18.2102 25.572 18.0667C25.8071 17.8276 26.2303 17.8276 26.4654 18.0667C26.7006 18.3538 26.7006 18.7364 26.4654 18.9756ZM28.0643 20.6498C27.8291 20.889 27.4059 20.889 27.1708 20.6498C27.0297 20.5063 26.9827 20.3628 26.9827 20.2193C26.9827 20.0758 27.0297 19.8844 27.1708 19.7888C27.4059 19.5496 27.8291 19.5496 28.0643 19.7888C28.3464 19.9801 28.3464 20.4106 28.0643 20.6498ZM29.7101 22.2762C29.475 22.5153 29.0518 22.5153 28.8166 22.2762C28.6756 22.1326 28.6285 21.9891 28.6285 21.8456C28.6285 21.7021 28.6756 21.5108 28.8166 21.4151C29.0518 21.176 29.475 21.176 29.7101 21.4151C29.9452 21.6543 29.9452 22.037 29.7101 22.2762Z" fill="currentColor"></path></svg></a>
            <div class="eb-collab">
                Lavet i samarbejde mellem
                <a href="https://webro.dk" target="_blank" rel="noopener">webro.dk</a> og
                <a href="<?php echo esc_url(EVENTBILLETTEN_SITE_URL); ?>" target="_blank" rel="noopener">eventbilletten.dk</a>
            </div>
        </div>
        <a class="eb-cta" href="<?php echo esc_url(EVENTBILLETTEN_SITE_URL); ?>" target="_blank" rel="noopener">Bliv kunde hos Eventbilletten &rarr;</a>
    </div>

    <nav class="nav-tab-wrapper eb-tabs">
        <?php foreach(eventbilletten_admin_pages() as $slug => $label): ?>
            <a href="<?php echo esc_url(eventbilletten_admin_url($slug)); ?>"
               class="nav-tab <?php echo esc_attr($slug === $active ? 'nav-tab-active' : ''); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <hr class="wp-header-end">
    <?php
}

function eventbilletten_admin_notices(){
    $messages = [
        'saved'            => ['success', 'Eventet er gemt.'],
        'updated'          => ['success', 'Ændringerne er gemt.'],
        'deleted'          => ['success', 'Eventet er slettet.'],
        'hidden'           => ['success', 'Eventet er skjult på hjemmesiden.'],
        'shown'            => ['success', 'Eventet vises igen på hjemmesiden.'],
        'cat_saved'        => ['success', 'Kategorien er gemt.'],
        'cat_deleted'      => ['success', 'Kategorien er slettet.'],
        'cache_cleared'    => ['success', 'Cachen er ryddet. Feedet hentes igen ved næste visning.'],
        'settings-updated' => ['success', 'Indstillingerne er gemt.'],
        'order_saved'      => ['success', 'Rækkefølgen er gemt.'],
    ];
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- viser kun en fast statusbesked
    foreach($messages as $key => [$type, $text]){
        if(!empty($_GET[$key])) echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html($text).'</p></div>';
    }
    $error = sanitize_key(wp_unslash($_GET['error'] ?? ''));
    // phpcs:enable
    if($error === 'date'){
        echo '<div class="notice notice-error"><p>Datoen kunne ikke læses. Vælg en dato i kalenderen.</p></div>';
    }
}


add_action('admin_enqueue_scripts', function(){
    if(!eventbilletten_is_plugin_page()) return;

    wp_enqueue_style('eventbilletten-admin', plugins_url('assets/css/admin.css', __FILE__), [], eventbilletten_asset_version('assets/css/admin.css'));

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- læser kun hvilken adminside der vises
    if(sanitize_key(wp_unslash($_GET['page'] ?? '')) !== 'eventbilletten-manuel') return;
    wp_enqueue_media();
    wp_enqueue_script('eventbilletten-admin', plugins_url('assets/js/admin.js', __FILE__), ['jquery', 'jquery-ui-sortable'], eventbilletten_asset_version('assets/js/admin.js'), true);
});


// ==========================
// SIDE 1: EVENTBILLETTEN (automatiske events)
// ==========================
function eventbilletten_page_feed(){
    $url    = get_option('eventbilletten_json_url');
    $status = get_option('eventbilletten_feed_status');
    ?>
<div class="wrap eb-wrap">
<?php eventbilletten_admin_header('eventbilletten'); eventbilletten_admin_notices(); ?>

<div class="eb-narrow">

<div class="eb-promo">
    <span class="eb-promo-icon">🎟️</span>
    <div>
        <strong>Automatiske events kræver en aftale med Eventbilletten</strong>
        <p>Når du sælger billetter gennem Eventbilletten, får du en feed-URL fra dem. Indsæt den herunder, så bliver alle dine events og billetter vist på hjemmesiden af sig selv. Dato, sted, billede og beskrivelse hentes direkte fra billetsystemet. Har du ikke en aftale endnu, kan du <a href="<?php echo esc_url(EVENTBILLETTEN_SITE_URL); ?>" target="_blank" rel="noopener">blive kunde hos Eventbilletten</a> eller bruge <a href="<?php echo esc_url(eventbilletten_admin_url('eventbilletten-manuel')); ?>">manuelle events</a> i mellemtiden.</p>
    </div>
</div>

<form method="post" action="options.php">
<?php settings_fields('eventbilletten_feed'); ?>
<div class="eb-card">
    <h2>JSON-feed</h2>
    <div class="eb-field">
        <label for="eb_json_url">URL til JSON-feed</label>
        <input type="url" id="eb_json_url" name="eventbilletten_json_url" value="<?php echo esc_attr($url); ?>" placeholder="https://...">
        <small>Den fulde URL du har fået af Eventbilletten</small>
    </div>
    <div class="eb-actions" style="margin-top:16px;">
        <?php submit_button('Gem feed-URL', 'primary', 'submit', false); ?>
    </div>
</div>
</form>

<div class="eb-card">
    <h2>Status</h2>
    <?php if(!$url): ?>
        <div class="eb-status"><span class="eb-dot none"></span> Der er ikke indsat en feed-URL endnu.</div>
    <?php elseif(!$status): ?>
        <div class="eb-status"><span class="eb-dot none"></span> Feedet er ikke hentet endnu. Det sker første gang en side med shortcoden vises.</div>
    <?php elseif(!empty($status['error'])): ?>
        <div class="eb-status"><span class="eb-dot err"></span> Feedet kunne ikke hentes <?php echo esc_html(wp_date('j. M Y \k\l. H:i', $status['time'])); ?>: <?php echo esc_html($status['error']); ?>. Der prøves igen om 5 minutter.</div>
    <?php else: ?>
        <div class="eb-status"><span class="eb-dot ok"></span> <?php echo esc_html((int)$status['count']); ?> events hentet <?php echo esc_html(wp_date('j. M Y \k\l. H:i', $status['time'])); ?>. Feedet opdateres hver time.</div>
    <?php endif; ?>

    <?php if($url): ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
        <input type="hidden" name="action" value="eventbilletten_clear_cache">
        <?php wp_nonce_field('eb_clear_cache'); ?>
        <?php submit_button('Ryd cache og hent igen', 'secondary', 'submit', false); ?>
    </form>
    <?php endif; ?>
</div>

</div>
</div>
    <?php
}


// ==========================
// SIDE 2: TILFØJ / RET MANUELT EVENT
// ==========================
function eventbilletten_page_manual(){
    $events     = eventbilletten_get_manual_events();
    $manual_cat = get_option('eventbilletten_manual_categories', []);
    $all_cats   = eventbilletten_get_all_categories();

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- vælger kun hvilket event der vises i formularen
    $edit_id = sanitize_key(wp_unslash($_GET['edit'] ?? ''));
    $edit    = $edit_id ? eventbilletten_find_manual_event($edit_id) : null;

    $v = function($key) use ($edit){ return $edit[$key] ?? ''; };
    $date_value = '';
    if($edit && ($d = eventbilletten_parse_date($edit['date']))) $date_value = $d->format('Y-m-d');

    $today       = new DateTime('today', wp_timezone());
    $img_id      = absint($edit['img_id'] ?? 0);
    $img_url     = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
    $sort_mode   = get_option('eventbilletten_sort', 'date');
    $btn_default = eventbilletten_btn_text();
    ?>
<div class="wrap eb-wrap">
<?php eventbilletten_admin_header('eventbilletten-manuel'); eventbilletten_admin_notices(); ?>

<div class="eb-layout">
<div>

<div class="eb-card" id="eb-form">
    <h2><?php echo esc_html($edit ? 'Ret event' : 'Tilføj manuelt event'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="eventbilletten_save_manual">
        <input type="hidden" name="eb_id" value="<?php echo esc_attr($v('id')); ?>">
        <?php wp_nonce_field('eb_manual_nonce'); ?>

        <div class="eb-manual-grid">
            <div class="eb-field eb-field-full">
                <label for="eb_title">Titel</label>
                <input type="text" id="eb_title" name="eb_title" required placeholder="Eventets navn" value="<?php echo esc_attr($v('title')); ?>">
            </div>
            <div class="eb-field">
                <label for="eb_date">Dato</label>
                <input type="date" id="eb_date" name="eb_date" required value="<?php echo esc_attr($date_value); ?>">
            </div>
            <div class="eb-field">
                <label for="eb_time">Tidspunkt <small>(valgfrit, 24-timers format)</small></label>
                <input type="text" id="eb_time" name="eb_time" inputmode="numeric" maxlength="5" placeholder="19:30"
                       pattern="([01]?[0-9]|2[0-3])[:.][0-5][0-9]" title="Skriv tidspunktet som tt:mm, fx 19:30"
                       value="<?php echo esc_attr($v('time')); ?>">
            </div>
            <div class="eb-field eb-field-full">
                <label for="eb_name">Lokationsnavn</label>
                <input type="text" id="eb_name" name="eb_name" placeholder="Teatret ved Søen" value="<?php echo esc_attr($v('name')); ?>">
            </div>
            <div class="eb-field eb-field-full">
                <label for="eb_address">Adresse</label>
                <input type="text" id="eb_address" name="eb_address" placeholder="Søgade 12" value="<?php echo esc_attr($v('address')); ?>">
            </div>
            <div class="eb-field">
                <label for="eb_zip">Postnummer</label>
                <input type="text" id="eb_zip" name="eb_zip" placeholder="8000" value="<?php echo esc_attr($v('zip')); ?>">
            </div>
            <div class="eb-field">
                <label for="eb_city">By</label>
                <input type="text" id="eb_city" name="eb_city" placeholder="Aarhus" value="<?php echo esc_attr($v('city')); ?>">
            </div>
            <div class="eb-field eb-field-full">
                <label>Billede <small>(valgfrit, vises ved siden af eventet)</small></label>
                <div class="eb-img-field">
                    <div class="eb-img-preview <?php echo esc_attr($img_url ? 'has-img' : ''); ?>" id="eb_img_preview"
                         style="<?php echo esc_attr($img_url ? 'background-image:url('.esc_url($img_url).')' : ''); ?>">Intet billede</div>
                    <div class="eb-img-buttons">
                        <button type="button" class="button" id="eb_img_select"><?php echo esc_html($img_url ? 'Skift billede' : 'Vælg billede'); ?></button>
                        <button type="button" class="button-link eb-delete-btn" id="eb_img_remove" style="<?php echo esc_attr($img_url ? '' : 'display:none;'); ?>">Fjern</button>
                    </div>
                    <input type="hidden" name="eb_img_id" id="eb_img_id" value="<?php echo esc_attr($img_id ?: ''); ?>">
                </div>
            </div>
            <div class="eb-field eb-field-full">
                <label for="eb_url">Billetlink <small>(uden link vises der ingen købsknap)</small></label>
                <input type="url" id="eb_url" name="eb_url" placeholder="https://..." value="<?php echo esc_attr($v('url')); ?>">
            </div>
            <div class="eb-field eb-field-full">
                <label for="eb_btn_text">Tekst på købsknappen <small>(valgfrit, ellers bruges teksten fra indstillingerne)</small></label>
                <input type="text" id="eb_btn_text" name="eb_btn_text" placeholder="<?php echo esc_attr($btn_default); ?>" value="<?php echo esc_attr($v('btn_text')); ?>">
            </div>
            <div class="eb-field eb-field-full">
                <label for="eb_desc">Kort beskrivelse <small>(vises i "Læs mere"-popup)</small></label>
                <textarea id="eb_desc" name="eb_desc" rows="4" placeholder="Kort beskrivelse af eventet..."><?php echo esc_textarea($v('desc')); ?></textarea>
            </div>
            <div class="eb-field eb-field-full">
                <label for="eb_readmore_url">"Læs mere"-link <small>(valgfrit, bruges i stedet for popup)</small></label>
                <input type="url" id="eb_readmore_url" name="eb_readmore_url" placeholder="https://..." value="<?php echo esc_attr($v('readmore_url')); ?>">
            </div>
            <div class="eb-field eb-field-full">
                <label for="eb_category">Kategori <small>(valgfrit)</small></label>
                <input type="text" id="eb_category" name="eb_category" list="eb_category_list" value="<?php echo esc_attr($v('category')); ?>"
                    placeholder="<?php echo esc_attr(empty($all_cats) ? 'Opret en kategori til højre' : 'Vælg eller skriv en kategori'); ?>">
                <datalist id="eb_category_list">
                    <?php foreach($all_cats as $c): ?>
                        <option value="<?php echo esc_attr($c); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="eb-field-full">
                <label class="eb-check">
                    <input type="checkbox" name="eb_hidden" value="1" <?php checked(1, (int)($edit['hidden'] ?? 0)); ?>>
                    Skjul eventet på hjemmesiden <small>(eventet gemmes, men vises ikke før du fjerner fluebenet)</small>
                </label>
            </div>
        </div>

        <div class="eb-actions" style="margin-top:18px;">
            <?php submit_button($edit ? 'Gem ændringer' : 'Tilføj event', 'primary', 'submit', false); ?>
            <?php if($edit): ?>
                <a class="button" href="<?php echo esc_url(eventbilletten_admin_url('eventbilletten-manuel')); ?>">Annuller</a>
            <?php endif; ?>
        </div>
    </form>
</div>

</div>
<div>

<div class="eb-card">
    <h2>Manuelle events (<?php echo esc_html(count($events)); ?>)</h2>
    <?php if(empty($events)): ?>
        <p>Du har ikke oprettet nogen manuelle events endnu.</p>
    <?php else: ?>
    <div class="eb-list-tools">
        <span>Træk i ☰ for at ændre rækkefølgen.<?php if($sort_mode !== 'manual'): ?> Rækkefølgen bruges på siden når <a href="<?php echo esc_url(eventbilletten_admin_url('eventbilletten-indstillinger')); ?>">sortering</a> står til manuel.<?php endif; ?></span>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="eventbilletten_save_order">
            <input type="hidden" name="eb_sort_by_date" value="1">
            <?php wp_nonce_field('eb_order_nonce'); ?>
            <button type="submit" class="eb-link-btn">Sorter efter dato</button>
        </form>
    </div>
    <table class="eb-table">
        <thead><tr><th></th><th></th><th>Dato</th><th>Titel</th><th>By</th><th>Kategori</th><th></th></tr></thead>
        <tbody id="eb_sortable">
        <?php foreach($events as $me):
            $d      = eventbilletten_parse_date($me['date']);
            $past   = $d && $d < $today;
            $hidden = !empty($me['hidden']);
            $cls    = trim(($past || $hidden ? 'eb-past ' : '').($edit_id === $me['id'] ? 'eb-editing' : ''));
        ?>
            <tr class="<?php echo esc_attr($cls); ?>" data-id="<?php echo esc_attr($me['id']); ?>">
                <td class="eb-handle" title="Træk for at flytte">☰</td>
                <td><?php $thumb = eventbilletten_manual_image_url($me, 'thumbnail'); if($thumb): ?><img class="eb-thumb" src="<?php echo esc_url($thumb); ?>" alt=""><?php else: ?><span class="eb-thumb"></span><?php endif; ?></td>
                <td style="white-space:nowrap;"><?php echo $d ? esc_html($d->format('j').'. '.eventbilletten_month_short($d).' '.$d->format('Y')) : esc_html($me['date']); ?><?php if($past): ?><span class="eb-badge">Afholdt</span><?php endif; if($hidden): ?><span class="eb-badge">Skjult</span><?php endif; ?></td>
                <td><?php echo esc_html($me['title']); ?></td>
                <td><?php echo esc_html($me['city'] ?? ''); ?></td>
                <td><?php echo esc_html($me['category'] ?? ''); ?></td>
                <td>
                    <div class="eb-row-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="eventbilletten_toggle_manual">
                            <input type="hidden" name="eb_id" value="<?php echo esc_attr($me['id']); ?>">
                            <?php wp_nonce_field('eb_toggle_nonce'); ?>
                            <button type="submit" class="eb-link-btn"><?php echo esc_html($hidden ? 'Vis' : 'Skjul'); ?></button>
                        </form>
                        <a class="eb-link-btn" href="<?php echo esc_url(eventbilletten_admin_url('eventbilletten-manuel', ['edit' => $me['id']]).'#eb-form'); ?>">Ret</a>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-eb-confirm="Slet dette event?">
                            <input type="hidden" name="action" value="eventbilletten_delete_manual">
                            <input type="hidden" name="eb_id" value="<?php echo esc_attr($me['id']); ?>">
                            <?php wp_nonce_field('eb_delete_nonce'); ?>
                            <button type="submit" class="eb-link-btn eb-delete-btn">Slet</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eb-order-bar" id="eb_order_bar">
        <input type="hidden" name="action" value="eventbilletten_save_order">
        <input type="hidden" name="eb_order" id="eb_order" value="">
        <?php wp_nonce_field('eb_order_nonce'); ?>
        <span style="flex:1;">Rækkefølgen er ændret.</span>
        <a class="button" href="<?php echo esc_url(eventbilletten_admin_url('eventbilletten-manuel')); ?>">Fortryd</a>
        <?php submit_button('Gem rækkefølge', 'primary', 'submit', false); ?>
    </form>
    <?php endif; ?>
</div>

<div class="eb-card">
    <h2>Kategorier</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="eventbilletten_save_category">
        <?php wp_nonce_field('eb_category_nonce'); ?>
        <div class="eb-field">
            <label for="eb_category_name">Opret ny kategori</label>
            <div style="display:flex;gap:10px;">
                <input type="text" id="eb_category_name" name="eb_category_name" required placeholder="F.eks. Koncert" style="flex:1;">
                <?php submit_button('Tilføj', 'secondary', 'submit', false); ?>
            </div>
            <small>Kategorier kan vælges på dine manuelle events og bruges i kategorifilteret.</small>
        </div>
    </form>

    <?php if(!empty($manual_cat)): ?>
    <table class="eb-table" style="margin-top:14px;">
        <tbody>
        <?php foreach($manual_cat as $cn): ?>
            <tr>
                <td><?php echo esc_html($cn); ?></td>
                <td>
                    <div class="eb-row-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-eb-confirm="Slet denne kategori?">
                            <input type="hidden" name="action" value="eventbilletten_delete_category">
                            <input type="hidden" name="eb_category_name" value="<?php echo esc_attr($cn); ?>">
                            <?php wp_nonce_field('eb_delete_category_nonce'); ?>
                            <button type="submit" class="eb-link-btn eb-delete-btn">Slet</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</div>
</div>
</div>

    <?php
}


// ==========================
// SIDE 3: INDSTILLINGER
// ==========================
function eventbilletten_page_settings(){
    ?>
<div class="wrap eb-wrap">
<?php eventbilletten_admin_header('eventbilletten-indstillinger'); eventbilletten_admin_notices(); ?>

<div class="eb-narrow">
<form method="post" action="options.php">
<?php settings_fields('eventbilletten_settings'); ?>

<div class="eb-card">
    <h2>Visning</h2>
    <div class="eb-checks">
        <label class="eb-check">
            <input type="checkbox" name="eventbilletten_hide_past" value="1" <?php checked(1, (int)get_option('eventbilletten_hide_past')); ?>>
            Skjul tidligere events automatisk
        </label>
        <label class="eb-check">
            <input type="checkbox" name="eventbilletten_enable_readmore" value="1" <?php checked(1, (int)get_option('eventbilletten_enable_readmore')); ?>>
            Vis "Læs mere"-popup med eventbeskrivelse
        </label>
        <label class="eb-check">
            <input type="checkbox" name="eventbilletten_enable_filter" value="1" <?php checked(1, (int)get_option('eventbilletten_enable_filter')); ?>>
            Vis regionfilter over events
        </label>
        <label class="eb-check">
            <input type="checkbox" name="eventbilletten_enable_category" value="1" <?php checked(1, (int)get_option('eventbilletten_enable_category')); ?>>
            Vis kategorifilter over events
        </label>
        <label class="eb-check">
            <input type="checkbox" name="eventbilletten_enable_schema" value="1" <?php checked(1, (int)get_option('eventbilletten_enable_schema', 1)); ?>>
            Udskriv Event-schema til Google og AI-søgning <small>(anbefalet)</small>
        </label>
    </div>
</div>

<div class="eb-card">
    <h2>Layout</h2>
    <div class="eb-checks">
        <label class="eb-check">
            <input type="radio" name="eventbilletten_layout" value="list" <?php checked('list', get_option('eventbilletten_layout', 'list')); ?>>
            Liste <small>(ét event pr. række med billedet til venstre)</small>
        </label>
        <label class="eb-check">
            <input type="radio" name="eventbilletten_layout" value="grid" <?php checked('grid', get_option('eventbilletten_layout', 'list')); ?>>
            Gitter <small>(kort i kolonner med billedet øverst)</small>
        </label>
    </div>
    <div class="eb-field" style="max-width:240px;margin-top:18px;">
        <label for="eb_grid_columns">Kolonner i gittervisning</label>
        <select id="eb_grid_columns" name="eventbilletten_grid_columns">
            <?php foreach([2, 3, 4] as $n): ?>
                <option value="<?php echo esc_attr($n); ?>" <?php selected($n, (int)get_option('eventbilletten_grid_columns', 3)); ?>><?php echo esc_html($n); ?> kolonner</option>
            <?php endforeach; ?>
        </select>
        <small>På tablet vises højst 2 kolonner og på mobil 1. Kan også styres pr. side med <code class="eb-code">[eventbilletten layout="grid" columns="2"]</code>.</small>
    </div>
</div>

<div class="eb-card">
    <h2>Sortering</h2>
    <div class="eb-checks">
        <label class="eb-check">
            <input type="radio" name="eventbilletten_sort" value="date" <?php checked('date', get_option('eventbilletten_sort', 'date')); ?>>
            Efter dato <small>(alle events samlet, tidligste først)</small>
        </label>
        <label class="eb-check">
            <input type="radio" name="eventbilletten_sort" value="manual" <?php checked('manual', get_option('eventbilletten_sort', 'date')); ?>>
            Manuel rækkefølge <small>(manuelle events i din rækkefølge, derefter events fra Eventbilletten efter dato)</small>
        </label>
    </div>
</div>

<div class="eb-card">
    <h2>Købsknap</h2>
    <div class="eb-field" style="max-width:320px;">
        <label for="eb_btn_text">Tekst på knappen</label>
        <input type="text" id="eb_btn_text" name="eventbilletten_btn_text" value="<?php echo esc_attr(eventbilletten_btn_text()); ?>">
        <small>Bruges på alle events. På manuelle events kan teksten ændres for det enkelte event.</small>
    </div>
</div>

<div class="eb-card">
    <h2>Farver</h2>
    <div class="eb-colors">
        <div class="eb-color-field">
            <input type="color" name="eventbilletten_btn_color" value="<?php echo esc_attr(eventbilletten_get_color('eventbilletten_btn_color')); ?>">
            Knap
        </div>
        <div class="eb-color-field">
            <input type="color" name="eventbilletten_title_color" value="<?php echo esc_attr(eventbilletten_get_color('eventbilletten_title_color')); ?>">
            Titel
        </div>
        <div class="eb-color-field">
            <input type="color" name="eventbilletten_date_color" value="<?php echo esc_attr(eventbilletten_get_color('eventbilletten_date_color')); ?>">
            Dato
        </div>
    </div>
</div>

<div class="eb-actions">
    <?php submit_button('Gem indstillinger', 'primary', 'submit', false); ?>
</div>

</form>
</div>
</div>
    <?php
}


// ==========================
// SIDE 4: SÅDAN BRUGER DU PLUGINNET
// ==========================
function eventbilletten_page_guide(){
    ?>
<div class="wrap eb-wrap">
<?php eventbilletten_admin_header('eventbilletten-guide'); eventbilletten_admin_notices(); ?>

<div class="eb-layout">
<div>

<div class="eb-card">
    <h2>Sådan bruger du pluginnet</h2>
    <p>Indsæt shortcoden <code class="eb-code">[eventbilletten]</code> på den side eller det indlæg, hvor events skal vises.</p>
    <ol class="eb-steps">
        <li>Har du en aftale med Eventbilletten, indsætter du feed-URL'en under fanen <a href="<?php echo esc_url(eventbilletten_admin_url('eventbilletten')); ?>">Eventbilletten</a>.</li>
        <li>Events uden for Eventbilletten opretter du under <a href="<?php echo esc_url(eventbilletten_admin_url('eventbilletten-manuel')); ?>">Tilføj manuelt event</a>.</li>
        <li>Liste eller gitter, filtre, popup, knaptekst og farver vælger du under <a href="<?php echo esc_url(eventbilletten_admin_url('eventbilletten-indstillinger')); ?>">Indstillinger</a>.</li>
        <li>Indsæt shortcoden på siden. Automatiske og manuelle events vises samlet og sorteret efter dato.</li>
    </ol>
</div>

<div class="eb-card">
    <h2>Vis kun et udvalg</h2>
    <p>Shortcoden kan begrænses til én region eller én kategori. Filterknapperne skjules så automatisk. Layoutet kan også vælges pr. side.</p>
    <table class="eb-table">
        <thead><tr><th>Shortcode</th><th>Viser</th></tr></thead>
        <tbody>
            <tr><td><code class="eb-code">[eventbilletten]</code></td><td>Alle events</td></tr>
            <tr><td><code class="eb-code">[eventbilletten region="fyn"]</code></td><td>Kun events på Fyn</td></tr>
            <tr><td><code class="eb-code">[eventbilletten category="Koncert"]</code></td><td>Kun events i kategorien Koncert</td></tr>
            <tr><td><code class="eb-code">[eventbilletten region="sydjylland" category="Koncert"]</code></td><td>Begge dele på én gang</td></tr>
            <tr><td><code class="eb-code">[eventbilletten layout="grid"]</code></td><td>Events i gitter uanset indstillingen</td></tr>
            <tr><td><code class="eb-code">[eventbilletten layout="grid" columns="2"]</code></td><td>Gitter med 2 kolonner</td></tr>
            <tr><td><code class="eb-code">[eventbilletten layout="list"]</code></td><td>Events som liste uanset indstillingen</td></tr>
        </tbody>
    </table>
    <p style="margin-top:14px;">Regioner: <code class="eb-code">hovedstaden</code> <code class="eb-code">sjaelland</code> <code class="eb-code">fyn</code> <code class="eb-code">nordjylland</code> <code class="eb-code">midtjylland</code> <code class="eb-code">sydjylland</code>. Regionen findes ud fra byen på eventet.</p>
</div>

</div>
<div>

<div class="eb-ad">
    <p class="eb-ad-kicker">Eventbilletten</p>
    <h2>Lad dine events komme ind af sig selv</h2>
    <p>Sælger du billetter gennem Eventbilletten, opretter du eventet ét sted. Derefter vises det her på hjemmesiden uden at du skal taste noget ind igen. Ændrer du tidspunkt, sted eller beskrivelse i billetsystemet, bliver hjemmesiden opdateret automatisk. Så passer oplysningerne altid, og kunderne køber billet direkte på din side.</p>
    <ul>
        <li>Dato, tid, adresse, billede og beskrivelse hentes direkte fra billetsystemet</li>
        <li>Køberen betaler med MobilePay, Apple Pay, Google Pay, Dankort, Visa eller Mastercard</li>
        <li>Du kan starte billetsalget for 0 kr. og selv vælge om kunden eller du betaler billetgebyret</li>
        <li>Salsplan med pladsreservation, sæsonkort, tidsbooking, gavekort og rabatkoder når du får brug for det</li>
        <li>Bruges af sportsklubber, foreninger, smagninger, koncerter, festivaler, konferencer og foredragsholdere</li>
    </ul>
    <div class="eb-ad-actions">
        <a class="eb-cta" href="<?php echo esc_url(EVENTBILLETTEN_SITE_URL); ?>" target="_blank" rel="noopener">Bliv kunde hos Eventbilletten &rarr;</a>
        <a class="eb-ad-secondary" href="<?php echo esc_url(EVENTBILLETTEN_DEMO_URL); ?>" target="_blank" rel="noopener">Book en demo</a>
    </div>
    <p class="eb-ad-note">Spørgsmål? Ring til Eventbilletten på <a href="tel:+4570305840" style="color:#f3a3c8;">70 30 58 40</a>. Se aktuelle priser på eventbilletten.dk.</p>
</div>

</div>
</div>
</div>
    <?php
}


// ==========================
// REGION
// ==========================
function eventbilletten_get_region($city){
    $city = mb_strtolower((string)$city, 'UTF-8');

    $map = [
        // Hovedstaden
        'københavn' => 'hovedstaden', 'frederiksberg' => 'hovedstaden', 'gentofte' => 'hovedstaden',
        'gladsaxe' => 'hovedstaden', 'lyngby' => 'hovedstaden', 'helsingør' => 'hovedstaden',
        'hillerød' => 'hovedstaden', 'hellerup' => 'hovedstaden', 'hvidovre' => 'hovedstaden',
        'brøndby' => 'hovedstaden', 'rødovre' => 'hovedstaden', 'ballerup' => 'hovedstaden',
        'allerød' => 'hovedstaden', 'fredensborg' => 'hovedstaden', 'frederiksværk' => 'hovedstaden',
        'bornholm' => 'hovedstaden', 'rønne' => 'hovedstaden',
        // Sjælland
        'roskilde' => 'sjaelland', 'køge' => 'sjaelland', 'næstved' => 'sjaelland',
        'slagelse' => 'sjaelland', 'holbæk' => 'sjaelland', 'ringsted' => 'sjaelland',
        'vordingborg' => 'sjaelland', 'nykøbing f' => 'sjaelland', 'nykøbing s' => 'sjaelland',
        'kalundborg' => 'sjaelland', 'sorø' => 'sjaelland', 'greve' => 'sjaelland', 'solrød' => 'sjaelland',
        // Fyn
        'odense' => 'fyn', 'kerteminde' => 'fyn', 'svendborg' => 'fyn', 'middelfart' => 'fyn',
        'nyborg' => 'fyn', 'assens' => 'fyn', 'faaborg' => 'fyn', 'ærø' => 'fyn',
        'rudkøbing' => 'fyn', 'bogense' => 'fyn',
        // Nordjylland
        'aalborg' => 'nordjylland', 'frederikshavn' => 'nordjylland', 'hjørring' => 'nordjylland',
        'thisted' => 'nordjylland', 'morsø' => 'nordjylland', 'nykøbing m' => 'nordjylland',
        'brønderslev' => 'nordjylland', 'jammerbugt' => 'nordjylland', 'læsø' => 'nordjylland',
        'rebild' => 'nordjylland', 'mariagerfjord' => 'nordjylland', 'vesthimmerland' => 'nordjylland',
        // Midtjylland
        'aarhus' => 'midtjylland', 'århus' => 'midtjylland', 'randers' => 'midtjylland',
        'silkeborg' => 'midtjylland', 'herning' => 'midtjylland', 'viborg' => 'midtjylland',
        'horsens' => 'midtjylland', 'skive' => 'midtjylland', 'holstebro' => 'midtjylland',
        'struer' => 'midtjylland', 'lemvig' => 'midtjylland', 'ringkøbing' => 'midtjylland',
        'ikast' => 'midtjylland', 'skanderborg' => 'midtjylland', 'favrskov' => 'midtjylland',
        'grenaa' => 'midtjylland', 'djursland' => 'midtjylland', 'ebeltoft' => 'midtjylland',
        // Sydjylland
        'esbjerg' => 'sydjylland', 'vejle' => 'sydjylland', 'kolding' => 'sydjylland',
        'fredericia' => 'sydjylland', 'aabenraa' => 'sydjylland', 'sønderborg' => 'sydjylland',
        'haderslev' => 'sydjylland', 'tønder' => 'sydjylland', 'billund' => 'sydjylland',
        'fanø' => 'sydjylland', 'varde' => 'sydjylland', 'vejen' => 'sydjylland',
        'give' => 'sydjylland', 'grindsted' => 'sydjylland',
    ];

    foreach($map as $k => $r){
        if(mb_strpos($city, $k) !== false) return $r;
    }
    return 'andet';
}


// ==========================
// BESKRIVELSE FRA FEED (JSON → HTML)
// ==========================
function eventbilletten_extract_text($children){
    $text = '';
    foreach((array)$children as $child){
        if(isset($child['type']) && $child['type'] === 'link'){
            $link_text = eventbilletten_extract_text($child['children'] ?? []);
            $text .= "<a href='".esc_url($child['url'] ?? '')."' target='_blank' rel='noopener'>{$link_text}</a>";
        } else {
            $t = esc_html($child['text'] ?? '');
            if(!empty($child['bold']))   $t = "<strong>$t</strong>";
            if(!empty($child['italic'])) $t = "<em>$t</em>";
            $text .= $t;
        }
    }
    return $text;
}

function eventbilletten_format_description($json){
    $data = json_decode($json, true);
    if(!is_array($data)) return '';

    $html = '';
    foreach($data as $block){
        $text = eventbilletten_extract_text($block['children'] ?? []);
        switch($block['type'] ?? ''){
            case 'subheading-1':    $html .= "<h2>$text</h2>"; break;
            case 'subheading-2':    $html .= "<h3>$text</h3>"; break;
            case 'horizontal-rule': $html .= "<hr>"; break;
            default:                $html .= $text ? "<p>$text</p>" : '';
        }
    }
    return $html;
}


// ==========================
// FRONTEND-ASSETS (kun på sider med shortcoden)
// ==========================
// Købsknappen åbner kun Eventbillettens billetvindue når der er indsat en feed-URL
function eventbilletten_use_embed(){
    return (bool)get_option('eventbilletten_json_url');
}

add_action('wp_enqueue_scripts', function(){
    wp_register_style('eventbilletten', plugins_url('assets/css/frontend.css', __FILE__), [], eventbilletten_asset_version('assets/css/frontend.css'));
    wp_register_script('eventbilletten', plugins_url('assets/js/frontend.js', __FILE__), [], eventbilletten_asset_version('assets/js/frontend.js'), true);
    wp_register_script('eventbilletten-embed', EVENTBILLETTEN_EMBED_URL, [], EVENTBILLETTEN_VERSION, true);

    global $post;
    if(is_singular() && $post instanceof WP_Post && has_shortcode($post->post_content, 'eventbilletten')){
        eventbilletten_enqueue_assets();
    }
});

function eventbilletten_enqueue_assets(){
    static $done = false;
    if($done) return;
    $done = true;

    wp_enqueue_style('eventbilletten');
    wp_add_inline_style('eventbilletten', eventbilletten_frontend_css());
    wp_enqueue_script('eventbilletten');
    if(eventbilletten_use_embed()) wp_enqueue_script('eventbilletten-embed');
}

function eventbilletten_frontend_css(){
    return ':root{--eventbilletten-btn:'.eventbilletten_get_color('eventbilletten_btn_color')
         . ';--eventbilletten-title:'.eventbilletten_get_color('eventbilletten_title_color')
         . ';--eventbilletten-date:'.eventbilletten_get_color('eventbilletten_date_color').';}';
}


// ==========================
// RENDER ÉT EVENT
// ==========================
function eventbilletten_render_event($e, $readmore){
    $out  = "<div class='jg-ticket' data-region='".esc_attr($e['region'])."' data-categories='".esc_attr(implode('|', $e['cat_slugs']))."'>";

    $out .= "<div class='jg-ticket-left'>"
          . "<div class='jg-date-line'><span class='jg-date-main'>".esc_html($e['date_main'])."</span><span class='jg-year'>".esc_html($e['year'])."</span></div>"
          . "<div class='jg-date-sub'>".esc_html($e['date_sub'])."</div>"
          . "</div>";

    if($e['img']){
        $out .= "<div class='jg-ticket-img'><img src='".esc_url($e['img'])."' alt='".esc_attr($e['title'])."' loading='lazy'></div>";
    }

    $out .= "<div class='jg-ticket-middle'>"
          . "<h3 class='jg-title'>".esc_html($e['title'])."</h3>";
    if($e['name'])    $out .= "<div class='jg-city'>".esc_html($e['name'])."</div>";
    if($e['address']) $out .= "<div class='jg-address'>".esc_html($e['address'])."</div>";
    if($e['zip'] || $e['city']) $out .= "<div class='jg-zip'>".esc_html(trim($e['zip'].' '.$e['city']))."</div>";

    if($e['readmore_url']){
        $out .= "<a class='jg-readmore-link' href='".esc_url($e['readmore_url'])."' target='_blank' rel='noopener'>Læs mere</a>";
    } elseif($readmore && $e['desc']){
        $out .= "<button type='button' class='jg-readmore' data-content='".esc_attr($e['desc'])."'>Læs mere</button>";
    }
    $out .= "</div>";

    if($e['url']){
        $embed = eventbilletten_use_embed();
        $link  = $embed ? add_query_arg('useEmbed', 'true', $e['url']) : $e['url'];
        $out  .= "<div class='jg-ticket-right'><a class='".($embed ? 'vivenu-btn ' : '')."jg-btn' href='".esc_url($link)."'>".esc_html($e['btn_text'])."</a></div>";
    }

    return $out."</div>";
}


// ==========================
// EVENT-SCHEMA (JSON-LD)
// ==========================
function eventbilletten_schema($events){
    $graph = [];
    foreach($events as $e){
        $item = [
            '@type'               => 'Event',
            'name'                => $e['title'],
            'startDate'           => $e['iso'],
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location'            => [
                '@type'   => 'Place',
                'name'    => $e['name'] ?: $e['city'],
                'address' => array_filter([
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => $e['address'],
                    'postalCode'      => $e['zip'],
                    'addressLocality' => $e['city'],
                    'addressCountry'  => 'DK',
                ]),
            ],
        ];
        if($e['img']) $item['image'] = $e['img'];

        $desc = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($e['desc'])));
        if($desc !== '') $item['description'] = mb_strlen($desc) > 300 ? mb_substr($desc, 0, 297).'...' : $desc;

        if($e['url']) $item['offers'] = ['@type' => 'Offer', 'url' => $e['url']];
        if($e['readmore_url']) $item['url'] = $e['readmore_url'];

        $graph[] = $item;
    }
    if(!$graph) return '';

    return '<script type="application/ld+json">'
         . wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
         . '</script>';
}


// ==========================
// SHORTCODE
// ==========================
function eventbilletten_build_event($d, $time, array $fields){
    if($time){
        [$h, $m] = array_map('intval', explode(':', $time));
        $d->setTime($h, $m);
    }
    return array_merge([
        'ts'        => $d->getTimestamp(),
        'iso'       => $time ? $d->format('c') : $d->format('Y-m-d'),
        'date_main' => $d->format('j').'. '.eventbilletten_month_short($d),
        'year'      => $d->format('Y'),
        'date_sub'  => eventbilletten_day_name($d).($time ? ', kl. '.$time : ''),
    ], $fields);
}

function eventbilletten_render_shortcode($atts){
    eventbilletten_enqueue_assets();

    $atts            = shortcode_atts(['region' => '', 'category' => '', 'layout' => '', 'columns' => ''], $atts);
    $atts['region']  = sanitize_key($atts['region']);
    $hide            = (int)get_option('eventbilletten_hide_past');
    $readmore        = (int)get_option('eventbilletten_enable_readmore');
    $cat_slug_filter = $atts['category'] ? eventbilletten_category_slug($atts['category']) : '';
    $today           = new DateTime('today', wp_timezone());
    $btn_default     = eventbilletten_btn_text();

    $layout  = in_array($atts['layout'], ['list', 'grid'], true) ? $atts['layout'] : get_option('eventbilletten_layout', 'list');
    $columns = (int)($atts['columns'] ?: get_option('eventbilletten_grid_columns', 3));
    if(!in_array($columns, [2, 3, 4], true)) $columns = 3;

    $all_events = [];

    // Events fra feedet
    foreach(eventbilletten_get_feed_events() as $e){
        $d = eventbilletten_parse_date($e['startdate'] ?? '');
        if(!$d) continue;
        if($hide && $d < $today) continue;

        $city   = $e['location']['locationzipcity'] ?? '';
        $region = eventbilletten_get_region($city);
        if($atts['region'] && $atts['region'] !== $region) continue;

        $cat_names = eventbilletten_extract_categories($e);
        $cat_slugs = array_map('eventbilletten_category_slug', $cat_names);
        if($cat_slug_filter && !in_array($cat_slug_filter, $cat_slugs, true)) continue;

        $time = eventbilletten_normalize_time(!empty($e['time']) ? explode('-', $e['time'])[0] : '');

        $all_events[] = eventbilletten_build_event($d, $time, [
            'title'        => $e['title'] ?? '',
            'name'         => $e['location']['locationname'] ?? '',
            'address'      => $e['location']['locationaddress'] ?? '',
            'zip'          => $e['location']['locationzipcode'] ?? '',
            'city'         => $city,
            'url'          => $e['url'] ?? '',
            'btn_text'     => $btn_default,
            'region'       => $region,
            'desc'         => !empty($e['longdescription']) ? eventbilletten_format_description($e['longdescription']) : '',
            'img'          => $e['pictureurl'] ?? '',
            'readmore_url' => '',
            'pos'          => PHP_INT_MAX,
            'cat_names'    => $cat_names,
            'cat_slugs'    => $cat_slugs,
        ]);
    }

    // Manuelle events
    foreach(eventbilletten_get_manual_events() as $pos => $me){
        if(!empty($me['hidden'])) continue;
        $d = eventbilletten_parse_date($me['date'] ?? '');
        if(!$d) continue;
        if($hide && $d < $today) continue;

        $region = eventbilletten_get_region($me['city'] ?? '');
        if($atts['region'] && $atts['region'] !== $region) continue;

        $cat_names = !empty($me['category']) ? [$me['category']] : [];
        $cat_slugs = array_map('eventbilletten_category_slug', $cat_names);
        if($cat_slug_filter && !in_array($cat_slug_filter, $cat_slugs, true)) continue;

        $all_events[] = eventbilletten_build_event($d, eventbilletten_normalize_time($me['time'] ?? ''), [
            'title'        => $me['title'] ?? '',
            'name'         => $me['name'] ?? '',
            'address'      => $me['address'] ?? '',
            'zip'          => $me['zip'] ?? '',
            'city'         => $me['city'] ?? '',
            'url'          => $me['url'] ?? '',
            'btn_text'     => !empty($me['btn_text']) ? $me['btn_text'] : $btn_default,
            'region'       => $region,
            'desc'         => !empty($me['desc']) ? '<p>'.nl2br(esc_html($me['desc'])).'</p>' : '',
            'img'          => eventbilletten_manual_image_url($me),
            'readmore_url' => $me['readmore_url'] ?? '',
            'pos'          => $pos,
            'cat_names'    => $cat_names,
            'cat_slugs'    => $cat_slugs,
        ]);
    }

    if(empty($all_events)){
        return "<p class='jg-empty'>Der er ingen kommende events lige nu.</p>";
    }

    if(get_option('eventbilletten_sort', 'date') === 'manual'){
        usort($all_events, fn($a, $b) => [$a['pos'], $a['ts']] <=> [$b['pos'], $b['ts']]);
    } else {
        usort($all_events, fn($a, $b) => [$a['ts'], $a['pos']] <=> [$b['ts'], $b['pos']]);
    }

    // Regioner til filter
    $region_labels = [
        'hovedstaden' => 'Hovedstaden',
        'sjaelland'   => 'Sjælland',
        'fyn'         => 'Fyn',
        'nordjylland' => 'Nordjylland',
        'midtjylland' => 'Midtjylland',
        'sydjylland'  => 'Sydjylland',
        'andet'       => 'Andet',
    ];

    $active_regions = [];
    $andet_cities   = [];
    foreach($all_events as $e){
        $active_regions[$e['region']] = true;
        if($e['region'] === 'andet' && $e['city'] !== ''){
            $c = mb_convert_case(trim($e['city']), MB_CASE_TITLE, 'UTF-8');
            $andet_cities[$c] = $c;
        }
    }
    if($andet_cities) $region_labels['andet'] = implode(' / ', $andet_cities);

    // Kategorier til filter
    $category_labels = [];
    foreach($all_events as $e){
        foreach($e['cat_slugs'] as $i => $slug){
            if(!isset($category_labels[$slug])) $category_labels[$slug] = $e['cat_names'][$i] ?? $slug;
        }
    }
    uasort($category_labels, fn($a, $b) => strcasecmp($a, $b));

    $show_region   = get_option('eventbilletten_enable_filter')   && !$atts['region']   && count($active_regions)  > 1;
    $show_category = get_option('eventbilletten_enable_category') && !$atts['category'] && count($category_labels) > 1;

    $uid = 'eb_'.wp_unique_id();
    $out = "<div class='jg-list-wrap' data-eb-list='".esc_attr($uid)."'>";

    if($show_region){
        $out .= "<div class='jg-filter' data-dim='region'><button type='button' class='jg-filter-btn' data-value='alle'>Alle</button>";
        foreach($region_labels as $key => $label){
            if(!empty($active_regions[$key])){
                $out .= "<button type='button' class='jg-filter-btn' data-value='".esc_attr($key)."'>".esc_html($label)."</button>";
            }
        }
        $out .= "</div>";
    }

    if($show_category){
        $out .= "<div class='jg-filter' data-dim='category'><button type='button' class='jg-filter-btn' data-value='alle'>Alle kategorier</button>";
        foreach($category_labels as $slug => $label){
            $out .= "<button type='button' class='jg-filter-btn' data-value='".esc_attr($slug)."'>".esc_html($label)."</button>";
        }
        $out .= "</div>";
    }

    $list_class = $layout === 'grid' ? " class='jg-list-grid jg-cols-{$columns}'" : '';
    $out .= "<div id='".esc_attr($uid)."'{$list_class}>";
    foreach($all_events as $e){
        $out .= eventbilletten_render_event($e, $readmore);
    }
    $out .= "</div></div>";

    // Popup udskrives én gang pr. side
    static $popup_printed = false;
    if($readmore && !$popup_printed){
        $popup_printed = true;
        $out .= "<div id='jg-popup' role='dialog' aria-modal='true'><div class='jg-popup-box'>"
              . "<button type='button' class='jg-popup-close' aria-label='Luk'>&#10005;</button>"
              . "<div class='jg-popup-content'></div></div></div>";
    }

    if(get_option('eventbilletten_enable_schema', 1)){
        $out .= eventbilletten_schema($all_events);
    }

    return $out;
}
add_shortcode('eventbilletten', 'eventbilletten_render_shortcode');