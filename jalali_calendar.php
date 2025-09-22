<?php
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = array(0,31,59,90,120,151,181,212,243,273,304,334);
    if($gm > 2) $gy2 = $gy + 1;
    else $gy2 = $gy;
    $days = 355666 + (365 * $gy) + floor(($gy2 + 3)/4) - floor(($gy2 + 99)/100)
          + floor(($gy2 + 399)/400) + $gd + $g_d_m[$gm-1];
    $jy = -1595 + (33 * intval($days/12053));
    $days %= 12053;
    $jy += 4 * intval($days/1461);
    $days %= 1461;
    if($days > 365){
        $jy += intval(($days-1)/365);
        $days = ($days-1) % 365;
    }
    if($days < 186){
        $jm = 1 + intval($days/31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intval(($days-186)/30);
        $jd = 1 + (($days-186) % 30);
    }
    return array($jy, $jm, $jd);
}

function jalali_to_gregorian($jy, $jm, $jd) {
    $jy = (int)$jy - 979;
    $jm = (int)$jm - 1;
    $jd = (int)$jd - 1;

    $j_day_no = 365 * $jy + (int)($jy / 33) * 8 + (int)((($jy % 33) + 3) / 4);
    for ($i = 0; $i < $jm; ++$i) {
        $j_day_no += ($i < 6) ? 31 : 30;
    }
    $j_day_no += $jd;

    $g_day_no = $j_day_no + 79;

    $gy = 1600 + 400 * (int)($g_day_no / 146097);
    $g_day_no %= 146097;

    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * (int)($g_day_no / 36524);
        $g_day_no %= 36524;

        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = false;
        }
    }

    $gy += 4 * (int)($g_day_no / 1461);
    $g_day_no %= 1461;

    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += (int)($g_day_no / 365);
        $g_day_no = $g_day_no % 365;
    }

    $gm = 0;
    $gd = 0;
    $g_month_days = [31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    for ($i = 0; $i < 12; $i++) {
        if ($g_day_no < $g_month_days[$i]) {
            $gm = $i + 1;
            $gd = $g_day_no + 1;
            break;
        }
        $g_day_no -= $g_month_days[$i];
    }

    return [$gy, $gm, $gd];
}

// مثال استفاده
list($jy, $jm, $jd) = gregorian_to_jalali(2025, 9, 19);
// echo "Jalali Date: $jy/$jm/$jd";
?>



<?php

function jalaali_to_gregorian($jy, $jm, $jd) {
    $jy = (int)$jy - 979;
    $jm = (int)$jm - 1;
    $jd = (int)$jd - 1;

    $j_day_no = 365*$jy + (int)($jy/33)*8 + (int)((($jy%33)+3)/4);
    for ($i=0; $i<$jm; ++$i) {
        $j_day_no += ($i<6)?31:30;
    }
    $j_day_no += $jd;

    $g_day_no = $j_day_no + 79;

    $gy = 1600 + 400*(int)($g_day_no/146097);
    $g_day_no %= 146097;

    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100*(int)($g_day_no/36524);
        $g_day_no %= 36524;

        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = false;
        }
    }

    $gy += 4*(int)($g_day_no/1461);
    $g_day_no %= 1461;

    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += (int)($g_day_no/365);
        $g_day_no = $g_day_no % 365;
    }

    $gm = 0;
    $gd = 0;
    $g_month_days = [31, ($leap?29:28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($i=0; $i<12; $i++) {
        if ($g_day_no < $g_month_days[$i]) {
            $gm = $i+1;
            $gd = $g_day_no+1;
            break;
        }
        $g_day_no -= $g_month_days[$i];
    }

    return [$gy, $gm, $gd];
}

?>