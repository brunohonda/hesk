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
<button type="button" class="btn btn--blue-border show_language" ripple="ripple">
<?php echo $hesklang['click_to_show_available_language']; ?> <div class="ripple--container"></div></button>
<div class="inner_content_lan">
    <div class="lan-msg"></div>
</div>
<input type="hidden" name="current_hesk_version" id="current_hesk_version" value="<?php echo $hesk_settings['hesk_version'];?>">
<input type="hidden" name="install_lan" id="install_lan" value="<?php echo $hesklang['install_lan']; ?>">
<input type="hidden" name="remove_lan" id="remove_lan" value="<?php echo $hesklang['remove_lan']; ?>">
<input type="hidden" name="upgrade_lan" id="upgrade_lan" value="<?php echo $hesklang['upgrade_lan']; ?>">

<input type="hidden" id="ajax_action" value="0">

<div class="append_lan_html"></div>
<script>
    var title_lan,description_lan,completed_lan,success_install_lan_txt,error_install_lan_txt,success_remove_lan_txt,error_remove_lan_txt,remove_default_language_error,no_language_availble;
    var upgrade_lan,success_upgrade_lan_txt,error_upgrade_lan_txt,success_txt,error_txt;
    var close_html = '<a href="javascript:;" class="close" data-dismiss="alert" aria-label="<?php echo $hesklang['close_button_text']; ?>">×</a>';
    title_lan = "<?php echo $hesklang['title_lan'] ?>";
    description_lan = "<?php echo $hesklang['description_lan'] ?>";
    completed_lan = "<?php echo $hesklang['completed_lan'] ?>";
    success_install_lan_txt = "<?php echo $hesklang['success_install_lan_txt']; ?>" + close_html;
    error_install_lan_txt = "<?php echo $hesklang['error_install_lan_txt'].". <a target='_blank' href='https://www.hesk.com/knowledgebase/?article=89'>".$hesklang['click_for_manual_step']."</a>" ?>" + close_html;
    success_remove_lan_txt = "<?php echo $hesklang['success_remove_lan_txt'] ?>" + close_html;
    error_remove_lan_txt = "<?php $hesklang['error_remove_lan_txt'] ?>" + close_html;
    remove_default_language_error = "<?php echo $hesklang['sm_error'].": ".$hesklang['remove_default_language_error'] ?>" + close_html;
    no_language_availble = "<?php echo $hesklang['sm_error'].": ".$hesklang['no_language_availble'] .". <a target='_blank' href='https://www.hesk.com/knowledgebase/?article=89'>".$hesklang['click_for_manual_step']."</a>" ?>" + close_html;
    upgrade_lan = "<?php echo $hesklang['upgrade_lan'] ?>";
    success_upgrade_lan_txt = "<?php echo $hesklang['success_upgrade_lan_txt']; ?>" + close_html;
    error_upgrade_lan_txt = "<?php echo $hesklang['error_upgrade_lan_txt'].". <a target='_blank' href='https://www.hesk.com/knowledgebase/?article=89'>".$hesklang['click_for_manual_step']."</a>" ?>" + close_html;
    success_txt = "<?php echo $hesklang['sm_success'].": "?>";
    error_txt = "<?php echo $hesklang['sm_error'].": "?>";

    var unsaved_action = 0;

    // Show available languages
    $("body").on("click",".show_language",function(){
        if($("#ajax_action").val() == "0"){
            $("#overlay_loader").fadeIn(300);
            $.ajax({
                url:"ajax/language_list.php",
                type: "post",
                dataType: 'json',
                data: {},
                success:function(result){
                    $("#overlay_loader").fadeOut(300);
                    //Append language layout
                    if(result.status == "Success"){
                        $(".append_lan_html").html("");
                        $(".append_lan_html").append(result.data);
                        $("#ajax_action").val("1");
                        $(".show_language").addClass("d-none");
                        handleError();
                    }else{
                        $(".lan-msg").html(no_language_availble);
                        $(".lan-msg").addClass("error-msg");
                    }
                }
            });
        }
    });

    // Install a Language
    $("body").on("click",".install_language",function(){
        $("#overlay_loader").fadeIn(300);
        var tag = $(this).attr("data-tag");
        var title = $(this).attr("data-description");
        var tl = $(this).attr("data-title");
        $.ajax({
            url:"ajax/language_download.php",
            type: "post",
            dataType: 'json',
            data: {action: "install",tag:$(this).attr("data-tag"),version:$(this).attr("data-version")},
            success:function(result){
                $("#overlay_loader").fadeOut(300);
                $(".lan-msg").removeClass("success-msg");
                $(".lan-msg").removeClass("error-msg");
                $(".lan-msg").html("");
                if(result.status == "Success"){
                    $(".lan-msg").html(success_txt+tl+" "+success_install_lan_txt);
                    $(".lan-msg").addClass("success-msg").fadeIn(300);
                    //Show/hide install remove button
                    $("."+tag+"_install").removeClass('d-inline-flex').addClass('d-none');
                    $("."+tag+"_remove").removeClass('d-none').addClass('d-inline-flex');
                    $("."+tag+"_upgrade").removeClass('d-none').addClass('d-inline-flex');;
                    //Auto enable multiple languages checkbox
                    /*if ($('input[name=s_can_sel_lang]').is(':not(:checked)')){
                        $('input[name=s_can_sel_lang]').prop("checked",true);
                    }*/
                    //Append install language to select option
                    $('select[name=s_language]').next().next('ul').append('<li data-option="' + tag+'|'+title+ '">' + title + '</li>');
                    $('select[name=s_language]').append('<option value="' + tag+'|'+title+ '">' + title + '</option>');
                }
                if(result.status == "Error"){
                    $(".lan-msg").html(error_txt+tl+" "+error_install_lan_txt);
                    $(".lan-msg").addClass("error-msg").fadeIn(300);
                }
            }
        });
    });
    // Remove Language
    $("body").on("click",".remove_language",function(){
        
        var tag = $(this).attr("data-tag");
        var title = $(this).attr("data-description");
        var tl = $(this).attr("data-title");
        var selected_lan = $('select[name=s_language] option:selected').text();
        //Checked for default language
        if(selected_lan == title){
            $(".lan-msg").html(remove_default_language_error);
            $(".lan-msg").addClass("error-msg");
            return false;
        }

        $("#overlay_loader").fadeIn(300);
        
        $.ajax({
            url:"ajax/language_download.php",
            type: "post",
            dataType: 'json',
            data: {action: "remove",tag:tag},
            success:function(result){
                $("#overlay_loader").fadeOut(300);
                $(".lan-msg").removeClass("success-msg");
                $(".lan-msg").removeClass("error-msg");
                $(".lan-msg").html("");
                if(result.status == "Success"){
                    $(".lan-msg").html(success_txt+tl+" "+success_remove_lan_txt);
                    $(".lan-msg").addClass("success-msg").fadeIn(300);
                    //Show/hide install remove button
                    $("."+tag+"_install").removeClass('d-none').addClass("d-inline-flex");
                    $("."+tag+"_remove").addClass("d-none").removeClass("d-inline-flex");
                    $("."+tag+"_upgrade").addClass("d-none").removeClass("d-inline-flex");
                    //Delete remove language from select option
                    $('select[name=s_language]').next().next('ul').find('li[data-option="'+tag+'|'+title+'"]').remove();
                    $('select[name=s_language]').find('option[value="'+tag+'|'+title+'"]').remove();
                }
                if(result.status == "Error"){
                    $(".lan-msg").html(error_txt+tl+" "+error_remove_lan_txt);
                    $(".lan-msg").addClass("error-msg").fadeIn(300);
                }
                //Auto disable multiple languages checkbox
                /*var k = 0;
                $( ".remove_language" ).each(function( index ) {
                    if($(this).css("display") == "inline-flex"){
                        k = k + 1;
                    }
                });
                if(k==1){
                    $('input[name=s_can_sel_lang]').prop("checked",false);
                }*/
                //Auto enable multiple languages checkbox
            }
        });
    });
    //Upgrade Language
    $("body").on("click",".upgrade_language",function(){
        $("#overlay_loader").fadeIn(300);
        var tag = $(this).attr("data-tag");
        var title = $(this).attr("data-description");
        var tl = $(this).attr("data-title");
        $.ajax({
            url:"ajax/language_download.php",
            type: "post",
            dataType: 'json',
            data: {action: "upgrade",tag:$(this).attr("data-tag"),version:$(this).attr("data-version")},
            success:function(result){
                $("#overlay_loader").fadeOut(300);
                $(".lan-msg").removeClass("success-msg");
                $(".lan-msg").removeClass("error-msg");
                $(".lan-msg").html("");
                if(result.status == "Success"){
                    $(".lan-msg").html(success_txt+tl+" "+success_upgrade_lan_txt);
                    $(".lan-msg").addClass("success-msg").fadeIn(300);
                }
                if(result.status == "Error"){
                    $(".lan-msg").html(error_txt+tl+" "+error_upgrade_lan_txt);
                    $(".lan-msg").addClass("error-msg").fadeIn(300);
                }
            }
        });
    });

    $("body").on("click",".close",function(){
        handleError();
    })

    function handleError(){
        setTimeout(function(){
            $(".lan-msg").html("");
            $(".lan-msg").removeClass("success-msg");
            $(".lan-msg").removeClass("error-msg");
        });
    }

    <?php
    /*
    $(document).on('change', ':input,select,textarea,:checkbox,:radio', function(){
        unsaved_action = 1;
    });

    $(window).on('beforeunload', function() {
        if(unsaved_action == 1){
            return false;
        }
    });
    */
    ?>
</script>

