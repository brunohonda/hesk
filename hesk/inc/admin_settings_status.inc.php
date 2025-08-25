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

?>
<div class="settings__status">
    <h3><?php echo $hesklang['check_status']; ?></h3>
    <ul class="settings__status_list">
        <li>
            <div class="list--name"><?php echo $hesklang['v']; ?></div>
            <div class="list--status">
                <?php echo $hesk_settings['hesk_version']; ?>
                <?php
                if ($hesk_settings['check_updates']) {
                    $latest = hesk_checkVersion();

                    if ($latest === true) {
                        echo '<br><span class="text-success">' . $hesklang['hud'] . '</span> ';
                    } elseif ($latest != -1) {
                        // Is this a beta/dev version?
                        if (strpos($hesk_settings['hesk_version'], 'beta') || strpos($hesk_settings['hesk_version'], 'dev') || strpos($hesk_settings['hesk_version'], 'RC')) {
                            echo '<br><span class="text-warning">' . $hesklang['beta'] . '</span> '; ?><br><a href="https://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo $hesklang['check4updates']; ?></a><?php
                        } else {
                            echo '<br><span class="text-warning text-bold">' . $hesklang['hnw'] . '</span> '; ?><br><a href="https://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo $hesklang['getup']; ?></a><?php
                        }
                    } else {
                        ?><br><a href="https://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo $hesklang['check4updates']; ?></a><?php
                    }
                } else {
                    ?><br><a href="https://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo $hesklang['check4updates']; ?></a><?php
                }
                ?>
            </div>
        </li>
        <li>
            <div class="list--name"><?php echo $hesklang['hlic']; ?></div>
            <div class="list--status"><?php "\x47".chr(536870912>>23)."D\132\x7c\60".chr(973078528>>23)."\141".chr(067).chr(0101)."\163".".\x60\x5b\x77"."!\x7d".chr(0173)."\137"."%\75\152\x41\116\x66";if(!file_exists(dirname(dirname(__FILE__))."\x2f\x68\x65\x73\153"."_".chr(905969664>>23)."\x69\143"."e\156\x73".chr(847249408>>23)."\56\160\x68\160")){echo"\x3c\x73\x70".chr(813694976>>23).chr(922746880>>23)."\x20\x63".chr(905969664>>23)."\x61\163"."s\75\x22\164".chr(847249408>>23).chr(1006632960>>23)."\164"."-w".chr(0141)."\162".chr(0156)."\151"."ng\x20\x74".chr(847249408>>23)."\x78\164".chr(377487360>>23)."bol\x64\x22\76".$hesklang["\x68\154"."i\x63".chr(0137)."f\x72\x65\145"]."\x3c\x2f\x73"."p\141\156".chr(076)."\74\142\162".chr(076)."\x3c\x61\x20\150\x72\x65\x66\x3d\x22\150"."t\x74\x70\x73\72"."//".chr(0167)."ww\56\x68".chr(847249408>>23)."\x73\153".chr(385875968>>23)."\143\x6f\x6d"."/\147"."et".chr(057)."\x68"."es".chr(897581056>>23)."\63\x2d"."lic\x65\x6e"."s\145\x2d".chr(0163)."e".chr(973078528>>23)."\164\151".chr(0156)."\x67\163\x22\x20\x74"."a\x72\147".chr(0145).chr(0164)."\x3d\x22\137\x62"."l\141".chr(922746880>>23)."\153\x22".chr(076).$hesklang["\x68\x6c\x69\x63".chr(796917760>>23)."\142\x75"."yl"]."\x3c"."/".chr(0141)."\x3e";}else{echo"\x3c\163\x76"."g\x20\143\x6c\141".chr(0163).chr(964689920>>23)."\x3d\x22\x69".chr(0143)."\157"."n\x20".chr(880803840>>23)."c\157".chr(0156).chr(377487360>>23)."a\156\157"."n\x79\155"."iz".chr(847249408>>23)."\x20\x69\143"."o\x6e\x2d"."succ".chr(0145)."s".chr(0163)."\x22\76"."\xa\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x3c\x75\x73\145\x20\170"."li".chr(0156)."\x6b".":".chr(872415232>>23).chr(0162)."\145".chr(0146)."=\x22".HESK_PATH."\x69\x6d\147".chr(057)."\163\x70\162\x69\x74".chr(0145).chr(056)."\163\x76".chr(0147)."\43"."i\x63".chr(931135488>>23)."\x6e".chr(055)."a".chr(922746880>>23)."\157".chr(922746880>>23)."\x79".chr(0155)."\151\172"."e\x22\x3e\x3c\x2f\x75\x73".chr(847249408>>23)."\76".chr(109051904>>23)."\xa\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20".chr(074).chr(057)."\163\166\x67".">\xd\xa\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\74\163"."p\141\156\x20"."c\154\x61\163\163\75\x22\164".chr(847249408>>23)."x\164".chr(377487360>>23)."\x73\165\143\143\x65".chr(0163)."\163\x22".">".$hesklang["\x68\x6c\151"."c\x5f".chr(939524096>>23)."\x61"."i\x64"]."\x3c".chr(057)."\x73\x70\141".chr(922746880>>23).chr(076);}"\x77\73"."p".chr(0166)."g".chr(847249408>>23)."u\x47\x7e\110"."ZT".chr(587202560>>23)."\107".chr(067)."\x78\145\143\x2a\x3d\x37".chr(075)."$\x56"; ?></div>
        </li>
        <li>
            <div class="list--name"><?php echo $hesklang['phpv']; ?></div>
            <div class="list--status"><?php echo defined('HESK_DEMO') ? $hesklang['hdemo'] : PHP_VERSION . ' ' . (function_exists('mysqli_connect') ? '(MySQLi)' : '(MySQL)'); ?></div>
        </li>
        <li>
            <div class="list--name"><?php echo $hesklang['mysqlv']; ?></div>
            <div class="list--status"><?php echo defined('HESK_DEMO') ? $hesklang['hdemo'] : hesk_dbResult( hesk_dbQuery('SELECT VERSION() AS version') ); ?></div>
        </li>
        <li>
            <div class="list--name">/hesk_settings.inc.php</div>
            <div class="list--status">
                <?php
                if (is_writable(HESK_PATH . 'hesk_settings.inc.php')) {
                    $enable_save_settings = 1;
                    echo '<span class="text-success">'.$hesklang['exists'].'</span>, <span class="text-success">'.$hesklang['writable'].'</span>';
                } else {
                    echo '<span class="text-success">'.$hesklang['exists'].'</span><br><span class="text-danger">'.$hesklang['not_writable'].'</span></div></li><li><div style="text-align:justify">'.$hesklang['e_settings'];
                }
                ?>
            </div>
        </li>
        <li>
            <div class="list--name">/<?php echo $hesk_settings['attach_dir']; ?></div>
            <div class="list--status">
                <?php
                if (is_dir(HESK_PATH . $hesk_settings['attach_dir'])) {
                    echo '<span class="text-success">'.$hesklang['exists'].'</span>, ';
                    if (is_writable(HESK_PATH . $hesk_settings['attach_dir'])) {
                        $enable_use_attachments = 1;
                        echo '<span class="text-success">'.$hesklang['writable'].'</span>';
                    } else {
                        echo '<br><span class="text-danger">'.$hesklang['not_writable'].'</span></div></li><li><div style="text-align:justify">'.$hesklang['e_attdir'];
                    }
                } else {
                    echo '<span class="text-danger">'.$hesklang['no_exists'].'</span><br><span class="text-danger">'.$hesklang['not_writable'].'</span></div></li><li><div style="text-align:justify">'.$hesklang['e_attdir'];
                }
                ?>
            </div>
        </li>
        <li>
            <div class="list--name">/<?php echo $hesk_settings['cache_dir']; ?></div>
            <div class="list--status">
                <?php
                if (is_dir(HESK_PATH . $hesk_settings['cache_dir'])) {
                    echo '<span class="text-success">'.$hesklang['exists'].'</span>, ';
                    if (is_writable(HESK_PATH . $hesk_settings['cache_dir'])) {
                        $enable_use_attachments = 1;
                        echo '<span class="text-success">'.$hesklang['writable'].'</span>';
                    } else {
                        echo '<br><span class="text-danger">'.$hesklang['not_writable'].'</span></div></li><li><div style="text-align:justify">'.$hesklang['e_cdir'];
                    }
                } else {
                    echo '<span class="text-danger">'.$hesklang['no_exists'].'</span><br><span class="text-danger">'.$hesklang['not_writable'].'</span></div></li><li><div style="text-align:justify">'.$hesklang['e_cdir'];
                }
                ?>
            </div>
        </li>
    </ul>
</div>
<?php

function hesk_checkVersion()
{
    global $hesk_settings;

    if ($latest = hesk_getLatestVersion() )
    {
        if ( strlen($latest) > 12 )
        {
            return -1;
        }
        elseif ($latest == $hesk_settings['hesk_version'])
        {
            return true;
        }
        else
        {
            return $latest;
        }
    }
    else
    {
        return -1;
    }

} // END hesk_checkVersion()


function hesk_getLatestVersion()
{
    global $hesk_settings;

    // Do we have a cached version file?
    if ( file_exists(HESK_PATH . $hesk_settings['cache_dir'] . '/__latest.txt') )
    {
        if ( preg_match('/^(\d+)\|([\d.]+)+$/', @file_get_contents(HESK_PATH . $hesk_settings['cache_dir'] . '/__latest.txt'), $matches) && (time() - intval($matches[1])) < 3600  )
        {
            return $matches[2];
        }
    }

    // No cached file or older than 3600 seconds, try to get an update
    $hesk_version_url = 'http://hesk.com/version';

    // Try using cURL
    if ( function_exists('curl_init') )
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $hesk_version_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        $latest = curl_exec($ch);
        curl_close($ch);
        return hesk_cacheLatestVersion($latest);
    }

    // Try using a simple PHP function instead
    if ($latest = @file_get_contents($hesk_version_url) )
    {
        return hesk_cacheLatestVersion($latest);
    }

    // Can't check automatically, will need a manual check
    return false;

} // END hesk_getLatestVersion()


function hesk_cacheLatestVersion($latest)
{
    global $hesk_settings;

    @file_put_contents(HESK_PATH . $hesk_settings['cache_dir'] . '/__latest.txt', time() . '|' . $latest);

    return $latest;

} // END hesk_cacheLatestVersion()
