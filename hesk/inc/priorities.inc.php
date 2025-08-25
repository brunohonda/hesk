<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

// Load available priorities
hesk_load_priorities();

/*** FUNCTIONS ***/

function hesk_load_priorities($use_cache=1)
{
    global $hesk_settings, $hesklang;

    // Do we have a cached version available
    $cache_dir = dirname(dirname(__FILE__)).'/'.$hesk_settings['cache_dir'].'/';
    $cache_file = $cache_dir . 'priority_' . sha1($hesk_settings['language']).'.cache.php';

    if ($use_cache && file_exists($cache_file))
    {
        require($cache_file);
        return true;
    }

    // Define priorities array
    $hesk_settings['priorities'] = array();
    hesk_load_database_functions();
    hesk_dbConnect();

    $res = hesk_dbQuery("SELECT `id`, `name`, `color`, `can_customers_select`,`priority_order` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` ORDER BY `priority_order` ASC");
    while ($row = hesk_dbFetchAssoc($res))
    {
        // Let's set priority name for current language (or the first one we find)
        $names = json_decode($row['name'], true);
        //print_r($names);
        $p_names = "";
        if((!isset($names[$hesk_settings['language']]) && $row['id'] < 4) || (isset($names[$hesk_settings['language']]) && strtolower($names[$hesk_settings['language']]) == "null"  && $row['id'] < 4)){
            //Check for default priority name is NULL
            switch ($row['id']) {
                case 0:
                    $p_names = $hesklang['critical'];
                    break;
                case 1:
                    $p_names = $hesklang['high'];
                    break;
                case 2:
                    $p_names = $hesklang['medium'];
                    break;
                default:
                    $p_names = $hesklang['low'];
            }
        }else{
            $row['name'] = (isset($names[$hesk_settings['language']])) ? $names[$hesk_settings['language']] : reset($names);
            $p_names = $row['name'];
        }


        // Add to priorities array
        $hesk_settings['priorities'][$row['id']] = array(
            'id' => $row['id'],
            'name'  => $p_names,
            'color' => '#'.$row['color'],
            'can_customers_select' => $row['can_customers_select'],
            'priority_order' => $row['priority_order'],
        );
    }
    // Try to cache results
    if ($use_cache && (is_dir($cache_dir) || ( @mkdir($cache_dir, 0777) && is_writable($cache_dir) ) ) )
    {
        // Is there an index.htm file?
        if ( ! file_exists($cache_dir.'index.htm'))
        {
            @file_put_contents($cache_dir.'index.htm', '');
        }

        // Write data
        @file_put_contents($cache_file, '<?php if (!defined(\'IN_SCRIPT\')) {die();} $hesk_settings[\'priorities\']=' . var_export($hesk_settings['priorities'], true) . ';' );
    }

    return true;
} // END hesk_load_priorities()


function hesk_get_priority_select($ignore_priority = '', $can_select = true, $select_category = '', $select_category_multiple = array() )
{
    global $hesk_settings;

    $options = '';

    foreach ($hesk_settings['priorities'] as $k => $v)
    {
        $data_style = "";
        if ($k === $ignore_priority) {
            continue;
        }
        if ($can_select == true || $v['can_customers_select'] == "1") {
            $data_style ='border-top-color:'.$v['color'].';border-left-color:'.$v['color'].';border-bottom-color:'.$v['color'].';';
            $options .= '<option value="'.$v['id'].'" '.( ($v['id'] == $select_category || in_array($v['id'], $select_category_multiple) ) ? 'selected' : '').' data-class="priority_img priority_dwn" data-style="'.$data_style.'" >'.$v['name'].'</option>';
        }
    }

    return $options;

} // END hesk_get_priority_select()


function hesk_get_priority_checkboxes($selected = array())
{
    global $hesk_settings;

    $i = 0;

    echo '<div class="checkbox-group list">';

    $has_row = false;
    foreach ($hesk_settings['priorities'] as $k => $v) {

        if ($i % 3 === 0) {
            echo '<div class="row">';
            $has_row = true;
        }
        $data_style ='border-top-color:'.$v['color'].';border-left-color:'.$v['color'].';border-bottom-color:'.$v['color'].';';
        echo '
        <div class="checkbox-custom">
            <input type="checkbox" id="priority_'.$k.'" name="p'.$k.'" value="1" '.(isset($selected[$k]) ? 'checked' : '').'>
            <label for="priority_'.$k.'" class="td-flex"> <div class="priority_img" style='.$data_style.'></div> '.hesk_get_admin_ticket_priority($k).'</label>
        </div>';

        if ($i % 3 === 2) {
            echo '</div>';
            $has_row = false;
        }

        $i++;
    }
    if ($has_row) echo '</div>';
    echo '</div>';
} // END hesk_get_priority_checkboxes()


function hesk_get_priority_name($priority)
{
    global $hesk_settings, $hesklang;
    return isset($hesk_settings['priorities'][$priority]['name']) ? $hesk_settings['priorities'][$priority]['name'] : $hesklang['unknown'];
} // END hesk_get_priority_name()


function hesk_get_admin_ticket_priority($priority, $append = '')
{
    return hesk_get_ticket_priority($priority, $append, 0);
} // END hesk_get_admin_ticket_priority()


function hesk_get_admin_ticket_priority_for_list($priority, $append = '')
{
    global $hesk_settings;

    if ( ! isset($hesk_settings['priorities'][$priority])) {
        $priority = array_keys($hesk_settings['priorities'])[0];
    }

    $color = $hesk_settings['priorities'][$priority]['color'];
    $data_style ='border-top-color:'.$color.';border-left-color:'.$color.';border-bottom-color:'.$color.';';

    return '<div class="priority_img" style='.$data_style.'></div> '.hesk_get_ticket_priority($priority, $append, 0);
} // END hesk_get_admin_ticket_priority_for_list()


function hesk_get_ticket_priority($priority, $append = '', $check_change = 1)
{
    global $hesk_settings, $hesklang;

    // Is this a valid priority?
    if ( ! isset($hesk_settings['priorities'][$priority]['name']))
    {
        return $hesklang['unknown'];
    }

    // In the customer side check if this priority can be changed
    if ($check_change && ! hesk_can_customer_select_priority($priority))
    {
        if (isset($hesk_settings['priorities'][$priority]['color']))
        {
            return '<div class="ml5">'.$hesk_settings['priorities'][$priority]['name'].'</div>';
        }

        return $hesk_settings['priorities'][$priority]['name'];
    }

    // Does this priority have a color code?
    if (isset($hesk_settings['priorities'][$priority]['color']))
    {
        return '<div class="ml5">'.$hesk_settings['priorities'][$priority]['name'].'</div>' . $append;
    }

    // Just return the name if nothing matches
    return $hesk_settings['priorities'][$priority]['name'] . $append;

} // END hesk_get_ticket_priority()


function hesk_get_ticket_priority_from_DB($trackingID)
{
    global $hesk_settings, $hesklang;

    if (empty($trackingID)) {
        hesk_error($hesklang['no_trackID']);
    }

    $result = hesk_dbQuery("SELECT `priority` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");
    if (hesk_dbNumRows($result) != 1) {
        hesk_error($hesklang['ticket_not_found']);
    }

    return hesk_dbResult($result);
} // END hesk_get_ticket_priority_from_DB()


function hesk_can_customer_select_priority($priority)
{
    global $hesk_settings;
    return ( ! isset($hesk_settings['priorities'][$priority]['can_customers_select']) || $hesk_settings['priorities'][$priority]['can_customers_select'] == '1') ? true : false;
} // END hesk_can_customer_select_priority()


function hesk_print_priority_select_box_jquery()
{
    global $hesk_settings;
    ?>
    <script>
    $(document).ready(function() {
        <?php
        $data_options = array();
        foreach ($hesk_settings['priorities'] as $id => $data)
        {
            // Is this a default priority? Use style class to add color
            if (isset($data['class']))
            {
                $data_options[$id] = array('class' => $data['class']);
                echo '$("#ticket-status-div > div.dropdown-select > ul.dropdown-list > li[data-option=\''.$id.'\']").addClass("'.$data['class'].'");'."\n";
                echo '
                    $("#ticket-status-div > div.dropdown-select > div.label > span").filter(function () {
                        return $(this).text() == "'.addslashes($data['name']).'";
                    }).addClass("'.$data['class'].'");'."\n";
                echo '$("#submit-as-div > ul.dropdown-list > li[data-option=\'submit_as-'.$id.'\']").addClass("'.$data['class'].'");'."\n";
                continue;
            }

            // Does this priority have a color code?
            if (isset($data['color']))
            {
                $data_options[$id] = array('color' => $data['color']);
                echo '$("#ticket-status-div > div.dropdown-select > ul.dropdown-list > li[data-option=\''.$id.'\']").css("color", "'.$data['color'].'");'."\n";
                echo '
                    $("#ticket-status-div > div.dropdown-select > div.label > span").filter(function () {
                        return $(this).text() == "'.addslashes($data['name']).'";
                    }).css("color", "'.$data['color'].'");'."\n";
                echo '$("#submit-as-div > ul.dropdown-list > li[data-option=\'submit_as-'.$id.'\']").css("color", "'.$data['color'].'");'."\n";
            }
        }
        ?>
    });

    function hesk_update_priority_color(this_id)
    {
        $("#ticket-status-div > div.dropdown-select > div.label > span").removeClass();
        $("#ticket-status-div > div.dropdown-select > div.label > span").removeAttr('style');
        <?php
        foreach($data_options as $id => $data) {
            echo 'if (this_id == '.$id.') {';
            if (isset($data['class'])) {
                echo '$("#ticket-status-div > div.dropdown-select > div.label > span").addClass("'.$data['class'].'");';
            } else {
                echo '$("#ticket-status-div > div.dropdown-select > div.label > span").css("color", "'.$data['color'].'");';
            }
            echo 'return;}';
        }
        ?>
    }
    </script>
    <?php
} // END hesk_print_priority_select_box_jquery()


function hesk_possible_priorities(){
    global $hesk_settings;
    $possible_priority = [];
    foreach ($hesk_settings['priorities'] as $k => $v) {
        $possible_priority[$k] = $v['name'];
    }
    return $possible_priority;
}// END hesk_possible_priorities()


function hesk_possible_priorities_order(){
    global $hesk_settings;
    $res = hesk_dbQuery("SELECT `id`, `name`, `color`, `can_customers_select`,`priority_order` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` ORDER BY `priority_order` ASC");
    $possible_priority_order = [];
    while ($row = hesk_dbFetchAssoc($res))
    {
        // Let's set status name for current language (or the first one we find)
        $names = json_decode($row['name'], true);
        $row['name'] = (isset($names[$hesk_settings['language']])) ? $names[$hesk_settings['language']] : reset($names);
        $possible_priority_order[$row['priority_order']] = $row['id'];
    }    
    
    return $possible_priority_order;
}// END hesk_possible_priorities_order()
