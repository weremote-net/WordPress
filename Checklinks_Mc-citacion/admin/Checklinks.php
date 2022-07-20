<?php
/*Checklink Admin Page*/
    global $wpdb;
    $query ="SELECT * FROM problematic_links";
    $problematicLinks_list = $wpdb->get_results($query, ARRAY_A);
    if (empty($problematicLinks_list)){
        $problematicLinks_list = array();
    }
?>
<div    class="wrap">
    <?php
        echo "<h1>".get_admin_page_title()."</h1>";

    ?>
    <br><br><br>

    <table class="wp-list-table widefat fixed striped pages">
        <thead>
            <th>URL</th>
            <th>State</th>
            <th>Original  post</th>
        </thead>
        <tbody  id="the-list">
            <?php
                foreach($problematicLinks_list as $key => $value){
                    $urlLink = $value['URL'];
                    $URLPostLink = $value['URLPost'];
                    $stateLink = $value['State'];
                    $originLink = $value['Origin'];

                    echo"
                    <tr>
                        <td><a  href='$urlLink'>$urlLink</a></td>
                        <td><div style='color: #ff8c00;font-weight:bold;'>$stateLink</div></td>
                        <td><a href='$URLPostLink'><b>$originLink</b></a></td>
                    </tr>
                    ";
                }
            ?>
        </tbody>
    </table>
</div>
