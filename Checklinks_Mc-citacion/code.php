<?php
/*
Plugin Name: Checklinks & Mc-citacion
Plugin URI:http://gonzalotestwp.ml/
Description: Plugin to check links and add citations to posts.
Version: 0.0.1
*/ 

/*Init new schedule to scan at 5seconds and create the problematic_links table*/
function    Activar(){
    if (! wp_next_scheduled('dcms_my_cron_hook')) {
        wp_schedule_event(current_time('timestamp'), '5seconds', 'dcms_my_cron_hook');
    }
    global $wpdb;
    $sql ="CREATE TABLE if not exists problematic_links(
        LinkId INT NOT NULL AUTO_INCREMENT,
        URL VARCHAR(2083) NULL,
        URLPost VARCHAR(2083) NULL,
        State VARCHAR(45) NULL,
        Origin VARCHAR(45) NULL,
        PRIMARY KEY (LinkId));";

    $wpdb->query($sql);
}

/*clear the schedule to scan and delete all tehe records on the problematic_links table*/
function    Desactivar(){
    wp_clear_scheduled_hook('dcms_my_cron_hook');

    global $wpdb;
    $sqlReset = "TRUNCATE TABLE problematic_links";
    $wpdb->query($sqlReset);
    
}

register_activation_hook(__FILE__,'Activar');
register_deactivation_hook(__FILE__,'Desactivar');

/*MC-CITATION*/
add_action('add_meta_boxes','wp_docs_meta_boxes');

function    wp_docs_meta_boxes(){
    add_meta_box('meta_box_id',__('Mc-citacion', 'textdomain'), 'wpdocs_my_display_callback', 'post');
}

function    wpdocs_my_display_callback(){

    global $post;
    $mcitation_textarea = get_post_meta($post->ID,'mcitation_textarea',true);
    $ID_POST_mcitation = get_post($post->ID);

    if(!$mcitation_textarea){
        $mcitation_textarea = '';
    }

    ?>
        <label  class="screen-reader-text" for="mcitation_textarea"> Citation</label>
        <textarea id ="mcitation_textarea"  name="mcitation_textarea" value="<?php echo $mcitation_textarea?>"  rows="1"  cols="40" style="width:100%"> <?php echo $mcitation_textarea?> </textarea>
    <?php
}

function mcitacionSavePost($post_id){
    if (isset($_POST['mcitation_textarea']) && !empty($_POST['mcitation_textarea'])) {
        update_post_meta($post_id,'mcitation_textarea', $_POST['mcitation_textarea']);
    }
}
add_action('save_post', 'mcitacionSavePost');

add_shortcode('Mc-itacion', 'shortcodeMcitacion');
function shortcodeMcitacion($atts){
    $actualPost = get_post();
    $atts = shortcode_atts(
        array(  'post_id'  => $actualPost->ID
                ), $atts
    );
    $mcitation_textarea = get_post_meta($atts['post_id'],'mcitation_textarea',true);

    return '<p><h4>Citacion:</h4></p></br><p>'.$mcitation_textarea.'</p>';
} 
/*Check links section/

/*Checklinks Menu*/

add_action('admin_menu','CrearMenu');

function CrearMenu(){
    add_menu_page(
        'Checklinks',
        'Checklinks Menu',
        'manage_options',
        plugin_dir_path(__FILE__).'admin/Checklinks.php',
        null,
        plugin_dir_url(__FILE__).'admin/img/Icon.png',
        '1'
    );
}
/*Checklinks Scan*/

add_action('dcms_my_cron_hook','dcms_my_process');

function    dcms_my_process(){
    $ScanLinks_args = array(
        'post_type'=>'post',
        'post_per_page'=>   -1,
        'meta_query' => array(
            'relation' => 'OR',
             array(
              'key' => 'AlreadyScan',
              'compare' => 'NOT EXISTS',
              'value' => ''
             ),
             array(
              'key' => 'AlreadyScan',
              'value' => 'true',
              'compare' => '!='
             )
             )
    );
    $ScanLinks_args['meta_query'][] = array('key' => 'AlreadyScan ', 'value' => 'true' , 'compare' => '!=');

    $ScanLinks_query = new WP_Query($ScanLinks_args);
    if ($ScanLinks_query->have_posts()) {
        while($ScanLinks_query->have_posts()) : $ScanLinks_query->the_post();
            $IDactualPost =  the_ID();
            $content = get_the_content();
            $title = get_the_title();
            $permalink = get_the_permalink();
            $regexTagA = '/<a\s[^>]*href\s*=\s*(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU';
            if(preg_match_all($regexTagA, $content, $matches, PREG_SET_ORDER)){
                foreach($matches as $linkMatches){
                    global $wpdb;
                    $LinkContent = $linkMatches[2];
                    $LinkProtocolCHeck = parse_url($LinkContent, PHP_URL_SCHEME);
                    $file_headers = @get_headers($LinkContent);
                    $state = 'Default state';

                    if(!$LinkContent || $LinkContent === ''){
                        $state = 'Empty link';
                        $LinkContent = 'empty_href';
                        $AddLink_sql ="INSERT INTO `problematic_links`(`URL`, `URLPost`, `State`, `Origin`) VALUES ('$LinkContent','$permalink','$state','$title')";
                        $wpdb->query($AddLink_sql);
                    }

                    if(!$LinkProtocolCHeck){
                        $state = 'Undefined protocol';
                        $AddLink_sql ="INSERT INTO `problematic_links`(`URL`, `URLPost`, `State`, `Origin`) VALUES ('$LinkContent','$permalink','$state','$title')";
                        $wpdb->query($AddLink_sql);
                    }
                    if($LinkProtocolCHeck === 'http'){
                        $state = 'Unsafe link';
                        $AddLink_sql ="INSERT INTO `problematic_links`(`URL`, `URLPost`, `State`, `Origin`) VALUES ('$LinkContent','$permalink','$state','$title')";
                        $wpdb->query($AddLink_sql);
                    }
                    if( $file_headers[0] !== 'HTTP/1.0 200 OK' &&   $file_headers[0] !== 'HTTP/1.1 200 OK') {
                        $state = $file_headers[0];
                        $AddLink_sql ="INSERT INTO `problematic_links`(`URL`, `URLPost`, `State`, `Origin`) VALUES ('$LinkContent','$permalink','$state','$title')";
                        $wpdb->query($AddLink_sql);
                    }
                    if (!filter_var($LinkContent, FILTER_VALIDATE_URL)) {
                        $state = 'Malformed link';
                        $AddLink_sql ="INSERT INTO `problematic_links`(`URL`, `URLPost`, `State`, `Origin`) VALUES ('$LinkContent','$permalink','$state','$title')";
                        $wpdb->query($AddLink_sql);
                    }
                }
            }
            update_post_meta(get_the_ID(), 'AlreadyScan', 'true', 'false' );

        endwhile;
    }
}

add_filter('cron_schedules', 'dcms_my_custom_schedule');

 function dcms_my_custom_schedule($schedules)
{
    $schedules['5seconds'] = array(
        'interval'  => 5,
        'display'   => __('5 seconds', 'dcms_lang_domain')
    );
    return $schedules;
}
?>