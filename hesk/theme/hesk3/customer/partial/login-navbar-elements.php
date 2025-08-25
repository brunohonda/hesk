<?php
// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}
/* Added by Andraz - TODO note: renderCommonElementsAfterBody & renderNavbarLanguageSelect were added here,
    to ensure they're loaded in all currently relevant places. Should potentially ideally be decoupled a bit more,
    once/if customer HTML is modularized more in the future.
*/
function renderCommonElementsAfterBody() {
    global $hesk_settings, $hesklang;
    ?>
    <a href="#maincontent" class="skiplink"><?php echo $hesklang['skip_to_main_content']; ?></a>
    <?php
}

function renderLoginNavbarElements($userContext = null) {
    global $hesk_settings, $hesklang;

    if (!$hesk_settings['customer_accounts']) {
        //-- Customer accounts disabled; don't show anything.
        return;
    }

    if ($userContext !== null): ?>
        <div class="header__nav">
            <div class="profile__item profile__user out-close">
                <div class="user__ava" data-action="show-profile">
                    <?php
                    $letter = hesk_mb_substr($userContext['name'], 0, 1);
                    echo hesk_mb_strtoupper($letter);
                    ?>
                </div>
                <div class="user__name" data-action="show-profile">
                    <p>
                        <span><?php echo $userContext['name']; ?></span>
                        <svg class="icon icon-chevron-down">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                        </svg>
                    </p>
                </div>
                <section class="profile__menu">

                    <div class="profile--view">
                        <a href="my_tickets.php" class="btn btn-full" ripple="ripple"><?php echo $hesklang['customer_my_tickets_heading']; ?></a>
                    </div>

                    <div class="profile--view">
                        <a href="index.php?a=add" class="btn btn-full" ripple="ripple"><?php echo $hesklang['submit_ticket']; ?></a>
                    </div>


                    <div class="profile--view">
                        <a href="profile.php" class="btn btn-full" ripple="ripple"><?php echo $hesklang['customer_profile']; ?></a>
                    </div>
                    <div class="profile--logout">
                        <a href="login.php?a=logout">
                            <svg class="icon icon-log-out">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-log-out"></use>
                            </svg>
                            <span><?php echo $hesklang['customer_logout']; ?></span>
                        </a>
                    </div>
                </section>
            </div>
        </div>
    <?php else: ?>
    <div class="header__nav">
        <div class="nav-item">
            <a href="login.php"><?php echo $hesklang['customer_login']; ?></a>
        </div>
        <?php if ($hesk_settings['customer_accounts_customer_self_register']): ?>
        <div class="nav-item">
            <a href="register.php"><?php echo $hesklang['customer_register']; ?></a>
        </div>
        <?php endif; ?>
    </div>
<?php
    endif;
}

function renderNavbarLanguageSelect() {
    global $hesk_settings, $hesklang;

    if ($hesk_settings['can_sel_lang']) { ?>
        <div class="header__lang">
            <form method="get" action="" aria-label="<?php echo $hesklang['set_lang']; ?>" style="margin:0;padding:0;border:0;white-space:nowrap;">
                <div class="dropdown-select center out-close">
                    <select name="language" onchange="this.form.submit()">
                        <?php hesk_listLanguages(); ?>
                    </select>
                </div>
                <?php foreach (hesk_getCurrentGetParameters() as $key => $value): ?>
                    <input type="hidden" name="<?php echo hesk_htmlentities($key); ?>"
                           value="<?php echo hesk_htmlentities($value); ?>">
                <?php endforeach; ?>
            </form>
        </div>
    <?php }
}