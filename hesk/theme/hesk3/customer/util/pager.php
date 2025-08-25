<?php
function output_pager($totalPages, $currentPage, $queryUrl) {
    global $hesklang;

    $prev_page = ($currentPage - 1 <= 0) ? 0 : $currentPage - 1;
    $next_page = ($currentPage + 1 > $totalPages) ? 0 : $totalPages + 1;
    $query_param = $queryUrl === '' ? '?' : '&';

    if ($totalPages <= 1) {
        return;
    }

    /* List pages */
    if ($totalPages >= 7) {
        if ($currentPage > 2) {
            echo '
                        <a href="'.$queryUrl.$query_param.'page-number=1" class="btn pagination__nav-btn">
                            <svg class="icon icon-chevron-left" style="margin-right:-6px">
                              <use xlink:href="'. TEMPLATE_PATH .'img/sprite.svg#icon-chevron-left"></use>
                            </svg>
                            <svg class="icon icon-chevron-left">
                              <use xlink:href="'. TEMPLATE_PATH .'img/sprite.svg#icon-chevron-left"></use>
                            </svg>
                            '.$hesklang['pager_first'].'
                        </a>';
        }

        if ($prev_page) {
            echo '
                        <a href="'.$queryUrl.$query_param.'page-number='.$prev_page.'" class="btn pagination__nav-btn">
                            <svg class="icon icon-chevron-left">
                              <use xlink:href="'. TEMPLATE_PATH .'img/sprite.svg#icon-chevron-left"></use>
                            </svg>
                            '.$hesklang['pager_previous'].'
                        </a>';
        }
    }

    echo '<ul class="pagination__list">';
    for ($i=1; $i<=$totalPages; $i++) {
        if ($i <= ($currentPage+5) && $i >= ($currentPage-5)) {
            if ($i == $currentPage) {
                echo '
                            <li class="pagination__item is-current">
                              <a href="javascript:" class="pagination__link">'.$i.'</a>
                            </li>';
            } else {
                echo '
                            <li class="pagination__item ">
                              <a href="'.$queryUrl.$query_param.'page-number='.$i.'" class="pagination__link">'.$i.'</a>
                            </li>';
            }
        }
    }
    echo '</ul>';

    if ($totalPages >= 7) {
        if ($next_page) {
            echo '
                        <a href="'.$queryUrl.$query_param.'page-number='.$next_page.'" class="btn pagination__nav-btn">
                            '.$hesklang['pager_next'].'
                            <svg class="icon icon-chevron-right">
                              <use xlink:href="'. TEMPLATE_PATH .'img/sprite.svg#icon-chevron-right"></use>
                            </svg>
                        </a>';
        }

        if ($currentPage < ($totalPages - 1)) {
            echo '
                        <a href="'.$queryUrl.$query_param.'page-number='.$totalPages.'" class="btn pagination__nav-btn">
                            '.$hesklang['pager_last'].'
                            <svg class="icon icon-chevron-right">
                              <use xlink:href="'. TEMPLATE_PATH .'img/sprite.svg#icon-chevron-right"></use>
                            </svg>
                            <svg class="icon icon-chevron-right" style="margin-left:-6px">
                              <use xlink:href="'. TEMPLATE_PATH .'img/sprite.svg#icon-chevron-right"></use>
                            </svg>
                        </a>';
        }
    }
}