<?php
/*
 * ============================================================
 * MDS - Musjid Display System
 * https://github.com/muhammedc/mds
 *
 * Original Script  : © Muhammed Cotwal 2016
 * Redesign         : © Muhammed Cotwal 2026
 * All rights reserved. Unauthorised copying or redistribution
 * of this file, via any medium, is strictly prohibited.
 * ============================================================
 */

date_default_timezone_set('Africa/Johannesburg');

/* ── Session (needed for simulator/admin — skip for musjid display) ── */
$is_musjid_display = (isset($_GET['display']) && $_GET['display'] === 'musjid'
        && !isset($_GET['sim']) && !isset($_GET['debuglog']) && !isset($_GET['poll']));
if (!$is_musjid_display) {
    if (session_status() === PHP_SESSION_NONE) session_start();
} else {
    /* Musjid display: no session needed, prevent proxy/CDN from caching or refreshing */
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* ── Lightweight poll endpoint for musjid display auto-refresh ── */
if (isset($_GET['poll']) && isset($_GET['display']) && $_GET['display'] === 'musjid') {
    $ts = '0';
    try {
        $pl = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
        $pl->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        /* Read the content_version key — bumped on every admin save/delete/toggle */
        $r = $pl->query("SELECT setting_value FROM site_settings WHERE setting_key='content_version' LIMIT 1");
        if ($r && $row = $r->fetch(PDO::FETCH_ASSOC)) $ts = $row['setting_value'];
        $pl = null;
    } catch (Exception $e) { /* ignore — return default '0' */ }
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['v' => $ts]);
    exit;
}

/* ── Image serve endpoint ── */
if (isset($_GET['action']) && $_GET['action'] === 'img' && isset($_GET['id'])) {
    $mid = (int)$_GET['id'];
    if ($mid > 0) {
        try {
            $il = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
            $il->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $r = $il->query("SELECT mime_type, data FROM media WHERE id='$mid' LIMIT 1");
            if ($r && $row = $r->fetch(PDO::FETCH_ASSOC)) {
                $mime = $row['mime_type'] ?: 'image/jpeg';
                header('Content-Type: ' . $mime);
                header('Cache-Control: public, max-age=86400');
                echo $row['data'];
                exit;
            }
        } catch (Exception $e) { /* fall through to 404 */ }
    }
    http_response_code(404);
    exit;
}


date_default_timezone_set('Africa/Johannesburg');

/* ── Simulator mode (superadmin only) ── */
$sim_active = false;
$sim_time   = '00:00:00'; // passed to JS
$sim_dow    = -1;          // day of week override for JS (-1 = not set)
$is_superadmin = (($_SESSION['admin_role'] ?? '') === 'superadmin');

if ($is_superadmin && isset($_GET['sim']) && $_GET['sim'] === '1') {
    $sim_active = true;
    $sim_date_raw = trim($_GET['sim_date'] ?? date('Y-m-d'));
    $sim_time_raw = trim($_GET['sim_time'] ?? '12:00:00');

    /* Validate and parse sim_date */
    $sim_ts = strtotime($sim_date_raw);
    if (!$sim_ts) $sim_ts = time();

    /* Override all PHP date variables from sim date */
    $month_number = (int)date('n', $sim_ts);
    $date_number  = (int)date('j', $sim_ts);
    $full_date    = date('l jS \o\f F Y', $sim_ts);
    $day_number   = (int)date('z', $sim_ts) + 1;
    $sim_time     = preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $sim_time_raw) ? $sim_time_raw : '12:00:00';
    if (strlen($sim_time) === 5) $sim_time .= ':00';
    $sim_dow      = (int)date('w', $sim_ts); // 0=Sun…4=Thu…5=Fri…6=Sat
} else {
    $month_number = date("n");
    $date_number  = date("j");
    $full_date    = date("l jS \of F Y");
    $day_number   = date('z') + 1;
}

/* ── Hijri date calculation ── */
function nmcGregorianToHijri($year, $month, $day) {
    $jd = gregoriantojd($month, $day, $year);
    $l  = $jd - 1948440 + 10632;
    $n  = (int)(($l - 1) / 10631);
    $l  = $l - 10631 * $n + 354;
    $j  = (int)((10985 - $l) / 5316) * (int)((50 * $l) / 17719)
            + (int)($l / 5670) * (int)((43 * $l) / 15238);
    $l  = $l - (int)((30 - $j) / 15) * (int)((17719 * $j) / 50)
            - (int)($j / 16) * (int)((15238 * $j) / 43) + 29;
    $hm = (int)((24 * $l) / 709);
    $hd = $l - (int)((709 * $hm) / 24);
    $hy = 30 * $n + $j - 30;
    return [$hy, $hm, $hd];
}
$hijriMonthNames = [
        1=>'Muharram', 2=>'Safar', 3=>"Rabi' al-Awwal", 4=>"Rabi' al-Thani",
        5=>"Jumada al-Awwal", 6=>"Jumada al-Thani", 7=>'Rajab', 8=>"Sha'ban",
        9=>'Ramadan', 10=>'Shawwal', 11=>"Dhu al-Qi'dah", 12=>'Dhu al-Hijjah'
];
$hijri_offset = 0; // overridden from DB below

$link  = null;
try {
    $link = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
    $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) { $link = null; }
$times = null;

/* ── Site name + Hijri offset + Madhab from DB ── */
$site_name    = 'Musjid Display System';
$madhab       = 'hanafi'; // default
$active_theme = 'green';
$custom_theme_json = '{}';
$remove_copyright = false;
$jummah    = ['azaan' => '12:20', 'khutbah' => '12:30', 'jamaat' => '13:00']; // default
if ($link) {
    $sn = @$link->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('site_name','hijri_offset','madhab','active_theme','custom_theme_json','remove_copyright')");
    if ($sn) while ($sn_row = $sn->fetch(PDO::FETCH_ASSOC)) {
        if ($sn_row['setting_key'] === 'site_name')         $site_name    = $sn_row['setting_value'];
        if ($sn_row['setting_key'] === 'hijri_offset')      $hijri_offset = (int)$sn_row['setting_value'];
        if ($sn_row['setting_key'] === 'madhab')            $madhab       = $sn_row['setting_value'];
        if ($sn_row['setting_key'] === 'active_theme')      $active_theme = $sn_row['setting_value'];
        if ($sn_row['setting_key'] === 'custom_theme_json') $custom_theme_json = $sn_row['setting_value'];
        if ($sn_row['setting_key'] === 'remove_copyright')  $remove_copyright = (bool)$sn_row['setting_value'];
    }
    $jr = @$link->query("SELECT azaan_time, khutbah_time, jamaat_time FROM jummah_settings WHERE id='1' LIMIT 1");
    if ($jr && $jrow = $jr->fetch(PDO::FETCH_ASSOC)) {
        $jummah = [
                'azaan'   => substr($jrow['azaan_time'],   0, 5),
                'khutbah' => substr($jrow['khutbah_time'], 0, 5),
                'jamaat'  => substr($jrow['jamaat_time'],  0, 5),
        ];
    }
}

/* ── Compute Hijri date with offset ── */
$hijri_base_ts = $sim_active ? $sim_ts : time();
$hijri_ts    = $hijri_base_ts + ($hijri_offset * 86400);
[$hijri_year, $hijri_month, $hijri_day] = nmcGregorianToHijri(
        (int)date('Y', $hijri_ts), (int)date('n', $hijri_ts), (int)date('j', $hijri_ts));
$hijri_date_str = $hijri_day . ' ' . $hijriMonthNames[$hijri_month] . ' ' . $hijri_year . ' AH';

if ($link) {
    $query = "SELECT * FROM perpetual_salaah_times WHERE month='$month_number' AND date='$date_number'";
    if ($result = @$link->query($query)) {
        if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $times = [
                    'sehri'   => substr($row['sehri_ends'],   0, -3),
                    'fajr'    => substr($row['fajr'],         0, -3),
                    'fajr_e'  => substr($row['e_fajr'],       0, -3),
                    'sunrise' => substr($row['sunrise'],      0, -3),
                    'zawaal'  => substr($row['zawaal'],       0, -3),
                    'zuhr'    => substr($row['zuhr'],         0, -3),
                    'zuhr_e'  => substr($row['e_zuhr'],       0, -3),
                    'asr'     => substr($row['asr'],          0, -3),
                    'asr_eh'  => substr($row['e_asr_hanafi'], 0, -3),
                    'asr_es'  => substr($row['e_asr_shafi'],  0, -3),
                    'sunset'  => substr($row['sunset'],       0, -3),
                    'maghrib' => substr($row['maghrib'],      0, -3),
                    'esha'    => substr($row['esha'],         0, -3),
                    'esha_e'  => substr($row['e_esha'],       0, -3),
            ];
        }
    }

    /* ── Ramadan override ── */
    $is_ramadan_active = false;
    $today_ymd = $sim_active ? date('Y-m-d', $sim_ts) : date('Y-m-d');
    $ram_chk = @$link->query("SELECT 1 FROM ramadan_schedule WHERE id='1' AND is_active=1 AND start_date <= '$today_ymd' AND end_date >= '$today_ymd' LIMIT 1");
    if ($ram_chk && $ram_chk->fetch()) {
        $is_ramadan_active = true;
        $ro = @$link->query("SELECT fajr, zuhr, asr, esha FROM ramadan_override WHERE prayer_date='$today_ymd' LIMIT 1");
        if ($ro && $rorow = $ro->fetch(PDO::FETCH_ASSOC)) {
            $times['fajr'] = substr($rorow['fajr'], 0, 5);
            $times['zuhr'] = substr($rorow['zuhr'], 0, 5);
            $times['asr']  = substr($rorow['asr'],  0, 5);
            $times['esha'] = substr($rorow['esha'], 0, 5);
        }
    }

    /* Fetch content_version while $link is still open */
    $cvq = @$link->query("SELECT setting_value FROM site_settings WHERE setting_key='content_version' LIMIT 1");
    $content_version_val = ($cvq && $crow = $cvq->fetch(PDO::FETCH_ASSOC)) ? $crow['setting_value'] : '0';
    $link = null;
}

/* ══════════════════════════════════════════════════════════════
   JAMAAT TIME CHANGE DETECTOR
   Looks ahead up to 3 days in perpetual_salaah_times and returns
   an array of changes relative to today's jamaats.
   *Now completely Ramadan-Aware*
   ══════════════════════════════════════════════════════════════ */
function nmcGetJamaatChanges(int $base_month, int $base_date, string $current_time_hhmm): array {
    try {
        $cl = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
        $cl->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) { return []; }

    $prayers = [
            'fajr'    => ['label' => 'Fajr',    'icon' => '🌅'],
            'zuhr'    => ['label' => 'Zuhr',    'icon' => '☀️'],
            'asr'     => ['label' => 'Asr',     'icon' => '🌤️'],
            'esha'    => ['label' => 'Esha',    'icon' => '🌃'],
    ];

    /* 1. Build a list of days: from Yesterday (-1) up to 3 days from now */
    $days = [];
    $check_ts = mktime(0, 0, 0, $base_month, $base_date, (int)date('Y'));

    for ($offset = -1; $offset <= 3; $offset++) {
        $ts = $check_ts + ($offset * 86400);
        $days[$offset] = [
                'offset'    => $offset,
                'month'     => (int)date('n', $ts),
                'date'      => (int)date('j', $ts),
                'ts'        => $ts,
                'day_name'  => date('l', $ts),
                'date_fmt'  => date('j M', $ts),
                'date_full' => date('Y-m-d', $ts),
        ];
    }

    /* 2. Fetch perpetual times for all 5 days in one query */
    $clauses = [];
    foreach ($days as $d) {
        $clauses[] = "(month='{$d['month']}' AND date='{$d['date']}')";
    }
    $where = implode(' OR ', $clauses);
    $r = @$cl->query("SELECT month, date, fajr, zuhr, asr, maghrib, esha
                              FROM perpetual_salaah_times WHERE {$where}");

    $rows = [];
    if ($r) {
        while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['month'] . '-' . $row['date'];
            foreach (['fajr','zuhr','asr','maghrib','esha'] as $p) {
                $row[$p] = substr($row[$p], 0, 5);
            }
            $rows[$key] = $row;
        }
    }

    /* 3. Layer the Ramadan Override on top (if active) */
    $ram_chk = @$cl->query("SELECT 1 FROM ramadan_schedule WHERE id='1' AND is_active=1 AND start_date <= '{$days[3]['date_full']}' AND end_date >= '{$days[-1]['date_full']}' LIMIT 1");

    if ($ram_chk && $ram_chk->fetch()) {
        $min_date = $days[-1]['date_full'];
        $max_date = $days[3]['date_full'];

        $ro = @$cl->query("SELECT prayer_date, fajr, zuhr, asr, esha FROM ramadan_override WHERE prayer_date BETWEEN '$min_date' AND '$max_date'");
        if ($ro) {
            while ($rorow = $ro->fetch(PDO::FETCH_ASSOC)) {
                $pt = strtotime($rorow['prayer_date']);
                $key = (int)date('n', $pt) . '-' . (int)date('j', $pt);
                if (isset($rows[$key])) {
                    $rows[$key]['fajr'] = substr($rorow['fajr'], 0, 5);
                    $rows[$key]['zuhr'] = substr($rorow['zuhr'], 0, 5);
                    $rows[$key]['asr']  = substr($rorow['asr'], 0, 5);
                    $rows[$key]['esha'] = substr($rorow['esha'], 0, 5);
                }
            }
        }
    }
    $cl = null;

    /* 4. Make sure today exists before running comparisons */
    $today_key = $days[0]['month'] . '-' . $days[0]['date'];
    $today_row = $rows[$today_key] ?? null;
    if (!$today_row) return [];

    $changes = [];
    $current_mins = ((int)substr($current_time_hhmm, 0, 2) * 60) + (int)substr($current_time_hhmm, 3, 2);

    /* 5. Compare future days (1, 2, and 3) against the day immediately prior */
    for ($offset = 1; $offset <= 3; $offset++) {
        $d = $days[$offset];
        $future_key = $d['month'] . '-' . $d['date'];
        $future_row = $rows[$future_key] ?? null;
        if (!$future_row) continue;

        $prev_ts  = $d['ts'] - 86400;
        $prev_key = (int)date('n', $prev_ts) . '-' . (int)date('j', $prev_ts);
        $prev_row = $rows[$prev_key] ?? $today_row;

        foreach ($prayers as $col => $info) {
            $from = $prev_row[$col];
            $to   = $future_row[$col];
            if ($from === $to) continue;

            $mins_from = ((int)substr($from,0,2)*60) + (int)substr($from,3,2);
            $mins_to   = ((int)substr($to,0,2)*60) + (int)substr($to,3,2);
            $diff_mins = $mins_to - $mins_from;

            $changes[] = [
                    'prayer'     => $info['label'],
                    'icon'       => $info['icon'],
                    'days_away'  => $offset,
                    'day_name'   => $d['day_name'],
                    'date_fmt'   => $d['date_fmt'],
                    'date_full'  => $d['date_full'],
                    'from_time'  => $from,
                    'to_time'    => $to,
                    'direction'  => $diff_mins < 0 ? 'earlier' : 'later',
                    'diff_mins'  => abs($diff_mins),
            ];
        }
    }

    /* 6. Compare Today against Yesterday (Urgent notices) */
    $yest_key = $days[-1]['month'] . '-' . $days[-1]['date'];
    if (isset($rows[$yest_key])) {
        $yest_row = $rows[$yest_key];
        foreach ($prayers as $col => $info) {
            $from = $yest_row[$col];
            $to   = $today_row[$col];
            if ($from === $to) continue;

            $prayer_mins = ((int)substr($to,0,2)*60) + (int)substr($to,3,2);
            if ($current_mins >= $prayer_mins) continue; // Time has already passed

            $mins_from = ((int)substr($from,0,2)*60) + (int)substr($from,3,2);
            $diff_mins = $prayer_mins - $mins_from;

            $changes[] = [
                    'prayer'     => $info['label'],
                    'icon'       => $info['icon'],
                    'days_away'  => 0,
                    'day_name'   => 'Today',
                    'date_fmt'   => date('j M', $check_ts),
                    'date_full'  => date('Y-m-d', $check_ts),
                    'from_time'  => $from,
                    'to_time'    => $to,
                    'direction'  => $diff_mins < 0 ? 'earlier' : 'later',
                    'diff_mins'  => abs($diff_mins),
            ];
        }
    }

    /* Sort: days_away ASC, then default prayer order */
    $order = ['Fajr'=>0,'Zuhr'=>1,'Asr'=>2,'Maghrib'=>3,'Esha'=>4];
    usort($changes, function($a, $b) use ($order) {
        if ($a['days_away'] !== $b['days_away']) return $a['days_away'] - $b['days_away'];
        return ($order[$a['prayer']] ?? 9) - ($order[$b['prayer']] ?? 9);
    });

    return $changes;
}

/* ── Helper: countdown label ── */
function nmcDaysLabel(int $days): string {
    if ($days === 0) return '<span class="jc-urgent">⚠️ Today — Please Take Note</span>';
    if ($days === 1) return '🗓️ <strong>Tomorrow</strong>';
    return "🗓️ <strong>In {$days} days</strong>";
}

/* ── Build the change notice HTML for musjid card / website banner ── */
function nmcBuildChangeNoticeHTML(array $changes, string $mode = 'musjid'): string {
    if (empty($changes)) return '';

    /* Determine dominant urgency for styling */
    $min_days = min(array_column($changes, 'days_away'));

    /* Group changes by days_away so same-day changes cluster together */
    $groups = [];
    foreach ($changes as $c) {
        $groups[$c['days_away']][] = $c;
    }

    if ($mode === 'musjid') {
        $html  = '<div class="jc-eyebrow">🔔 Jamāat Time Change</div>';
        $html .= '<div class="jc-divider"></div>';

        foreach ($groups as $days_away => $group) {
            /* Day header — only if multiple groups */
            if (count($groups) > 1) {
                $day_label = ($days_away === 0) ? 'Today' : (($days_away === 1) ? 'Tomorrow' : $group[0]['day_name']);
                $html .= '<div class="jc-day-header">' . $day_label . ' — ' . htmlspecialchars($group[0]['date_fmt']) . '</div>';
            }
            foreach ($group as $c) {
                $dir_label = $c['direction'] === 'earlier'
                        ? '<span class="jc-dir earlier">▼ ' . $c['diff_mins'] . ' min earlier</span>'
                        : '<span class="jc-dir later">▲ ' . $c['diff_mins'] . ' min later</span>';
                if (count($groups) === 1) {
                    /* Single day — show the date on each row */
                    $day_txt = ($days_away === 0) ? 'Today' : (($days_away === 1) ? 'Tomorrow' : $c['day_name'] . ' ' . htmlspecialchars($c['date_fmt']));
                    $html .= '<div class="jc-row">';
                    $html .= '<span class="jc-icon">' . $c['icon'] . '</span>';
                    $html .= '<span class="jc-prayer">' . $c['prayer'] . '</span>';
                    $html .= '<span class="jc-when">' . $day_txt . '</span>';
                    $html .= '<span class="jc-times"><span class="jc-from">' . $c['from_time'] . '</span><span class="jc-arrow">→</span><span class="jc-to">' . $c['to_time'] . '</span></span>';
                    $html .= $dir_label;
                    $html .= '</div>';
                } else {
                    $html .= '<div class="jc-row">';
                    $html .= '<span class="jc-icon">' . $c['icon'] . '</span>';
                    $html .= '<span class="jc-prayer">' . $c['prayer'] . '</span>';
                    $html .= '<span class="jc-times"><span class="jc-from">' . $c['from_time'] . '</span><span class="jc-arrow">→</span><span class="jc-to">' . $c['to_time'] . '</span></span>';
                    $html .= $dir_label;
                    $html .= '</div>';
                }
            }
        }

        $html .= '<div class="jc-divider"></div>';
        $html .= '<div class="jc-footer">' . nmcDaysLabel($min_days) . '</div>';
        return $html;

    } else {
        /* Website banner mode */
        $html  = '<div class="jcb-inner">';
        $html .= '<div class="jcb-left">';
        $html .= '<div class="jcb-eyebrow">🔔 Jamāat Time Change</div>';

        foreach ($groups as $days_away => $group) {
            if (count($groups) > 1) {
                $day_label = ($days_away === 0) ? 'Today' : (($days_away === 1) ? 'Tomorrow' : $group[0]['day_name'] . ' ' . htmlspecialchars($group[0]['date_fmt']));
                $html .= '<div class="jcb-day-header">' . $day_label . '</div>';
            }
            foreach ($group as $c) {
                $dir_label = $c['direction'] === 'earlier'
                        ? '<span class="jcb-dir earlier">▼ ' . $c['diff_mins'] . ' min earlier</span>'
                        : '<span class="jcb-dir later">▲ ' . $c['diff_mins'] . ' min later</span>';
                $day_txt = '';
                if (count($groups) === 1) {
                    $day_txt = ($days_away === 0) ? ' · <em>Today</em>' : (($days_away === 1) ? ' · <em>Tomorrow</em>' : ' · <em>' . $c['day_name'] . ' ' . htmlspecialchars($c['date_fmt']) . '</em>');
                }
                $html .= '<div class="jcb-row">';
                $html .= '<span class="jcb-icon">' . $c['icon'] . '</span>';
                $html .= '<span class="jcb-prayer">' . $c['prayer'] . $day_txt . '</span>';
                $html .= '<span class="jcb-times"><span class="jcb-from">' . $c['from_time'] . '</span><span class="jcb-arrow">→</span><span class="jcb-to">' . $c['to_time'] . '</span></span>';
                $html .= $dir_label;
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        $html .= '<div class="jcb-right">';
        $html .= '<div class="jcb-countdown">' . nmcDaysLabel($min_days) . '</div>';
        $html .= '<button class="jcb-dismiss" onclick="nmcDismissJamaatBanner()" aria-label="Dismiss">✕</button>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}

/* ── Run the detector ── */
$current_time_hhmm = $sim_active ? substr($sim_time, 0, 5) : date('H:i');
$jamaat_changes    = nmcGetJamaatChanges($month_number, $date_number, $current_time_hhmm);
$has_changes       = !empty($jamaat_changes);

/* ── Musjid display mode ── */
$musjid_mode = (isset($_GET['display']) && $_GET['display'] === 'musjid');

/* ── Ticker text (musjid mode only) ── */
$musjid_ticker = 'The Prophet ﷺ said: "The first matter that the slave will be brought to account for on the Day of Judgement is the prayer. If it is sound, then the rest of his deeds will be sound. And if it is corrupt, then the rest of his deeds will be corrupt." — (At-Tabarani)';

/* Musjid-specific DB data */
$musjid_hadith_ref  = null;
$musjid_hadith_text = null;

/* New multi-entry arrays */
$musjid_community_msgs = [];   // [{id,title,content_type,content_html,media_id,image_fit,display_secs}, …]
$musjid_funeral_list   = [];   // [{id,deceased_name,…,display_secs}, …]
$musjid_ticker_list    = [];   // [{id,message_text,display_secs}, …]

if ($musjid_mode) {
    $tlink = null;
    try {
        $tlink = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
        $tlink->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) { $tlink = null; }
    if ($tlink) {

        $now_sql = date('Y-m-d H:i:s');

        /* Community messages — active, within schedule window, ordered
           SELECT excludes media blob — only fetch media_id for URL generation */
        $r = @$tlink->query("SELECT id, title, content_type, content_html, media_id, image_fit,
                display_secs, start_dt, end_dt, is_active, sort_order, created_at
            FROM community_messages
            WHERE is_active=1
              AND (start_dt IS NULL OR start_dt <= '$now_sql')
              AND (end_dt   IS NULL OR end_dt   >= '$now_sql')
            ORDER BY sort_order ASC, created_at DESC");
        if ($r) while ($row = $r->fetch(PDO::FETCH_ASSOC)) $musjid_community_msgs[] = $row;

        /* Funeral notices — active, within schedule window */
        $r = @$tlink->query("SELECT * FROM funeral_notices
            WHERE is_active=1
              AND (start_dt IS NULL OR start_dt <= '$now_sql')
              AND (end_dt   IS NULL OR end_dt   >= '$now_sql')
            ORDER BY created_at DESC");
        if ($r) while ($row = $r->fetch(PDO::FETCH_ASSOC)) $musjid_funeral_list[] = $row;

        /* Ticker messages — active, within schedule window */
        $r = @$tlink->query("SELECT * FROM ticker_messages
            WHERE is_active=1
              AND (start_dt IS NULL OR start_dt <= '$now_sql')
              AND (end_dt   IS NULL OR end_dt   >= '$now_sql')
            ORDER BY sort_order ASC, created_at DESC");
        if ($r) while ($row = $r->fetch(PDO::FETCH_ASSOC)) $musjid_ticker_list[] = $row;

        /* Fetch today's hadith */
        $hr = @$tlink->query("SELECT * FROM hadith_db WHERE uid='$day_number' LIMIT 1");
        if ($hr && $hrow = $hr->fetch(PDO::FETCH_NUM)) {
            $musjid_hadith_ref  = null;               /* hadith_db has no separate ref column */
            $musjid_hadith_text = $hrow[1] ?? null;   /* full hadith is in column index 1 */
        }

        $tlink = null;
    }
}

/* Default community message if none configured */
if (empty($musjid_community_msgs)) {
    $musjid_community_msgs[] = [
            'id' => 0, 'title' => 'Community Notice',
            'content_type' => 'html', 'display_secs' => 30,
            'content_html' => '<p style="text-align:center;font-size:1.1em;">Jazākallāhu Khayran to all who contribute to our community.<br>May Allāh accept your efforts and bless this Masjid.</p>',
            'media_id' => null, 'image_fit' => 'contain'
    ];
}

/* ── Prepend jamaat change notice as first slide (if active) ── */
if ($has_changes) {
    $change_html = nmcBuildChangeNoticeHTML($jamaat_changes, 'musjid');
    array_unshift($musjid_community_msgs, [
            'id'           => -1,            // sentinel — not a real DB row
            'title'        => '',
            'content_type' => 'html',
            'content_html' => $change_html,
            'media_id'     => null,
            'image_fit'    => 'contain',
            'display_secs' => 60,
            '_jamaat_notice' => true,        // flag for rendering
    ]);
}

/* Default funeral notice if none */
if (empty($musjid_funeral_list)) {
    $musjid_funeral_list[] = null; /* null signals "no active funeral" */
}

/* Default ticker if none */
if (empty($musjid_ticker_list)) {
    $musjid_ticker_list[] = ['id'=>0,'message_text'=>$musjid_ticker,'display_secs'=>30];
}

/* ── Prepend copyright ticker if not removed ── */
if (!$remove_copyright) {
    array_unshift($musjid_ticker_list, [
        'id' => -999,
        'message_text' => 'Musjid Display System (MDS) © Copyright 2026 - Muhammed Cotwal - All Rights Reserved | github.com/muhammedc/mds',
        'display_secs' => 20
    ]);
}

/* 5 main prayers only — Sehri moves to events */
$prayer_js = $times ? json_encode([
        ['name' => 'Fajr',    'time' => $times['fajr'],    'icon' => '🌅'],
        ['name' => 'Zuhr',    'time' => $times['zuhr'],    'icon' => '☀️' ],
        ['name' => 'Asr',     'time' => $times['asr'],     'icon' => '🌤️'],
        ['name' => 'Maghrib', 'time' => $times['maghrib'], 'icon' => '🌇'],
        ['name' => 'Esha',    'time' => $times['esha'],    'icon' => '🌃'],
]) : '[]';

/* Time marker events — shown in the smaller event banner */
$events_js = $times ? json_encode([
        ['name' => 'Sehri Ends',     'time' => $times['sehri'],   'icon' => '🌙', 'verb' => 'ends in'],
        ['name' => 'Sunrise',        'time' => $times['sunrise'], 'icon' => '🌄', 'verb' => 'in'],
        ['name' => 'Zawaal',         'time' => $times['zawaal'],  'icon' => '🕛', 'verb' => 'in'],
        ['name' => 'Sunset / Iftaar','time' => $times['sunset'],  'icon' => '🌆', 'verb' => 'in'],
]) : '[]';

/*
 * Window boundaries passed separately so JS can determine
 * whether each prayer's valid window is open or closed.
 *
 * Hanafi rules:
 * Fajr    : fajr_time  → sunrise      (after sunrise = Qadha)
 * Zuhr    : zuhr_time  → asr_eh       (Hanafi Asr earliest closes Zuhr)
 * Asr     : asr_eh     → sunset       (after sunset = Qadha)
 * Maghrib : maghrib    → esha_e       (Esha earliest closes Maghrib)
 * Esha    : esha_e     → fajr (next)  (after next Fajr = Qadha)
 *
 * Shafi uses e_asr_shafi (earlier) as the Zuhr/Asr boundary instead.
 */
$asr_boundary = ($madhab === 'shafi') ? $times['asr_es'] : $times['asr_eh'];
$windows_js = $times ? json_encode([
        'fajr_e'       => $times['fajr_e'],    // Fajr window OPENS at earliest time
        'sunrise'      => $times['sunrise'],   // Fajr window CLOSES at sunrise
        'zawaal'       => $times['zawaal'],
        'zuhr_e'       => $times['zuhr_e'],    // Zuhr window OPENS at earliest time
        'asr_boundary' => $asr_boundary,       // Zuhr CLOSES / Asr OPENS at madhab earliest
        'asr_eh'       => $times['asr_eh'],
        'asr_es'       => $times['asr_es'],
        'sunset'       => $times['sunset'],    // Asr CLOSES at sunset
        'esha_e'       => $times['esha_e'],    // Maghrib CLOSES / Esha OPENS at esha earliest
        'fajr'         => $times['fajr'],      // Fajr jamaat (for countdown/display only)
        'zuhr'         => $times['zuhr'],      // Zuhr jamaat (for countdown/display only)
        'asr'          => $times['asr'],       // Asr jamaat
        'maghrib'      => $times['maghrib'],   // Maghrib opens at sunset (no separate earliest)
        'esha'         => $times['esha'],      // Esha jamaat
        'madhab'       => $madhab,
]) : '{}';

/* Jummah times passed to JS */
$jummah_js = json_encode([
        'azaan'   => $jummah['azaan'],
        'khutbah' => $jummah['khutbah'],
        'jamaat'  => $jummah['jamaat'],
]);

/* Simulator config passed to JS */
$sim_js = json_encode([
        'active'   => $sim_active,
        'time'     => $sim_time,      // e.g. "12:18:00"
        'dow'      => $sim_dow,       // 0=Sun…5=Fri…6=Sat, -1=real
        'date'     => $sim_active ? date('Y-m-d', $sim_ts) : date('Y-m-d'),
        'speed'    => 1,              // default — overridden by JS panel
]);

/* ── Theme CSS injection ── */
function nmc_hex_to_rgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return [(int)hexdec(substr($hex,0,2)), (int)hexdec(substr($hex,2,2)), (int)hexdec(substr($hex,4,2))];
}

function nmc_build_theme_vars(array $base): string {
    // $base keys: gold, gold_light, gold_dim, bg_dark, bg_mid, bg_accent, cream, cream_dim
    [$ar,$ag,$ab] = nmc_hex_to_rgb($base['gold']);
    [$dr,$dg,$db] = nmc_hex_to_rgb($base['bg_dark']);
    [$mr,$mg,$mb] = nmc_hex_to_rgb($base['bg_mid']);
    [$hr,$hg,$hb] = nmc_hex_to_rgb($base['bg_accent']);

    // Derive --card-active for website mode
    $card_active = "rgba({$ar},{$ag},{$ab},0.13)";

    // Detect light theme (bg_dark is light) — for white theme, invert some logic
    $is_light = (($dr + $dg + $db) / 3) > 128;

    if ($is_light) {
        // Light theme: panels are light-coloured
        $bg_deep    = "rgba({$dr},{$dg},{$db},0.95)";
        $bg_card_hi = "rgba({$mr},{$mg},{$mb},0.92)";
        $bg_card_lo = "rgba({$hr},{$hg},{$hb},0.60)";
        $bg_card_mid= "rgba({$mr},{$mg},{$mb},0.80)";
        $bg_marker  = "rgba({$mr},{$mg},{$mb},0.85)";
        $bg_ticker  = "rgba({$dr},{$dg},{$db},0.92)";
        $bg_sim     = "rgba({$dr},{$dg},{$db},0.97)";
        $card_border_str = "rgba({$ar},{$ag},{$ab},0.30)";
        $card_bg    = "rgba({$mr},{$mg},{$mb},0.82)";
    } else {
        // Dark theme
        $bg_deep    = "rgba({$dr},{$dg},{$db},0.92)";
        $bg_card_hi = "rgba({$mr},{$mg},{$mb},0.92)";
        $bg_card_lo = "rgba({$hr},{$hg},{$hb},0.42)";
        $bg_card_mid= "rgba({$mr},{$mg},{$mb},0.80)";
        $bg_marker  = "rgba({$dr},{$dg},{$db},0.78)";
        $bg_ticker  = "rgba({$dr},{$dg},{$db},0.92)";
        $bg_sim     = "rgba({$dr},{$dg},{$db},0.97)";
        $card_border_str = "rgba({$ar},{$ag},{$ab},0.22)";
        $card_bg    = "rgba({$mr},{$mg},{$mb},0.82)";
    }

    $vars = [
            '--gold'           => $base['gold'],
            '--gold-light'     => $base['gold_light'],
            '--gold-dim'       => $base['gold_dim'],
            '--green-dark'     => $base['bg_dark'],
            '--green-mid'      => $base['bg_mid'],
            '--green'          => $base['bg_accent'],
            '--cream'          => $base['cream'],
            '--cream-dim'      => $base['cream_dim'],
            '--card-bg'        => $card_bg,
            '--card-border'    => $card_border_str,
            '--card-active'    => $card_active,
            '--input-bg'       => $is_light ? "rgba(255,255,255,0.7)" : "rgba({$dr},{$dg},{$db},0.7)",
            '--input-border'   => "rgba({$ar},{$ag},{$ab},0.28)",
        // Musjid derived vars
            '--bg-deep'        => $bg_deep,
            '--bg-card-hi'     => $bg_card_hi,
            '--bg-card-lo'     => $bg_card_lo,
            '--bg-card-mid'    => $bg_card_mid,
            '--bg-marker'      => $bg_marker,
            '--bg-ticker'      => $bg_ticker,
            '--bg-sim'         => $bg_sim,
            '--bg-fab'         => $bg_sim, // FAB uses same deep-dark as sim panel
            '--accent-glow-sm' => "rgba({$ar},{$ag},{$ab},0.09)",
            '--accent-glow-bg' => "rgba({$ar},{$ag},{$ab},0.38)",
            '--accent-glow-hi' => "rgba({$ar},{$ag},{$ab},0.9)",
            '--accent-faint'   => "rgba({$ar},{$ag},{$ab},0.08)",
            '--accent-subtle'  => "rgba({$ar},{$ag},{$ab},0.13)",
            '--accent-low'     => "rgba({$ar},{$ag},{$ab},0.18)",
            '--accent-mid'     => "rgba({$ar},{$ag},{$ab},0.22)",
            '--accent-mod'     => "rgba({$ar},{$ag},{$ab},0.25)",
            '--accent-str'     => "rgba({$ar},{$ag},{$ab},0.30)",
            '--accent-brt'     => "rgba({$ar},{$ag},{$ab},0.50)",
            '--accent-glow30'  => "rgba({$ar},{$ag},{$ab},0.30)",
            '--accent-act'     => "rgba({$ar},{$ag},{$ab},0.10)",
            '--accent-act2'    => "rgba({$ar},{$ag},{$ab},0.05)",
            '--accent-shadow'  => "rgba({$ar},{$ag},{$ab},0.20)",
            '--accent-shadow2' => "rgba({$ar},{$ag},{$ab},0.2)",
    ];

    $css = "";
    foreach ($vars as $k => $v) $css .= "    {$k}: {$v};\n";
    return $css;
}

function nmcIndexThemeCSS(string $active_theme, string $custom_json): string {
    // Preset base colour definitions — 8 semantic values each
    $presets = [
            'green' => [
                    'gold'=>'#C9A84C','gold_light'=>'#E8C97A','gold_dim'=>'#8A6E32',
                    'bg_dark'=>'#07170A','bg_mid'=>'#102E16','bg_accent'=>'#1B5E35',
                    'cream'=>'#F5EDD6','cream_dim'=>'#C8B98A',
            ],
            'blue' => [
                    'gold'=>'#7EB8E8','gold_light'=>'#A8D4F5','gold_dim'=>'#3A6A96',
                    'bg_dark'=>'#050D18','bg_mid'=>'#0A1929','bg_accent'=>'#0F2A42',
                    'cream'=>'#E8F4FF','cream_dim'=>'#8FB8D8',
            ],
            'burgundy' => [
                    'gold'=>'#D4A44C','gold_light'=>'#E8C97A','gold_dim'=>'#8A6232',
                    'bg_dark'=>'#120407','bg_mid'=>'#240810','bg_accent'=>'#4A1020',
                    'cream'=>'#F5EDE8','cream_dim'=>'#C8A898',
            ],
            'grey' => [
                    'gold'=>'#A0A8B0','gold_light'=>'#C8D0D8','gold_dim'=>'#606870',
                    'bg_dark'=>'#0D0E0F','bg_mid'=>'#181A1C','bg_accent'=>'#252729',
                    'cream'=>'#E8EAEC','cream_dim'=>'#909498',
            ],
            'white' => [
                    'gold'=>'#B07D2A','gold_light'=>'#C9A84C','gold_dim'=>'#8A6432',
                    'bg_dark'=>'#F7F5F0','bg_mid'=>'#EDE9E0','bg_accent'=>'#DDD6C8',
                    'cream'=>'#2A2420','cream_dim'=>'#5A524A',
            ],
    ];

    if ($active_theme === 'custom') {
        $c = json_decode($custom_json, true) ?: [];
        $base = [
                'gold'       => $c['gold']       ?? '#C9A84C',
                'gold_light' => $c['gold_light'] ?? '#E8C97A',
                'gold_dim'   => $c['gold_dim']   ?? '#8A6E32',
                'bg_dark'    => $c['bg_dark']    ?? '#07170A',
                'bg_mid'     => $c['bg_mid']     ?? '#102E16',
                'bg_accent'  => $c['bg_accent']  ?? '#1B5E35',
                'cream'      => $c['cream']      ?? '#F5EDD6',
                'cream_dim'  => $c['cream_dim']  ?? '#C8B98A',
        ];
    } else {
        $base = $presets[$active_theme] ?? null;
        if (!$base || $active_theme === 'green') return ''; // green is default, no override needed
    }

    $inner = nmc_build_theme_vars($base);
    return "<style id=\"nmc-theme-override\">\n:root {\n{$inner}}\n</style>\n";
}
$_nmc_theme_css = nmcIndexThemeCSS($active_theme, $custom_theme_json);

?>
<?php if ($musjid_mode): ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=1920">
        <meta name="robots" content="noindex,nofollow">
        <meta name="theme-color" content="#07170A">
        <title>MDS – Salaah Times Display</title>
        <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Amiri:ital,wght@0,400;0,700;1,400&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
        <style>
            /* ══════════════════════════════════════════
               MUSJID DISPLAY MODE — 1920×1080 TV layout
               ══════════════════════════════════════════ */
            :root {
                --gold:        #C9A84C;
                --gold-light:  #E8C97A;
                --gold-dim:    #8A6E32;
                --green-dark:  #07170A;
                --green-mid:   #102E16;
                --green:       #1B5E35;
                --cream:       #F5EDD6;
                --cream-dim:   #C8B98A;
                --red-soft:    #E07070;
                --card-bg:     rgba(16,46,22,0.82);
                --card-border: rgba(201,168,76,0.22);
                /* ── Derived theme variables ── */
                --bg-deep:        rgba(7,23,10,0.92);
                --bg-card-hi:     rgba(16,46,22,0.92);
                --bg-card-lo:     rgba(27,94,53,0.42);
                --bg-card-mid:    rgba(16,46,22,0.80);
                --bg-marker:      rgba(10,24,12,0.78);
                --bg-ticker:      rgba(7,20,10,0.92);
                --bg-sim:         rgba(4,12,5,0.97);
                --accent-glow-sm: rgba(201,168,76,0.09);
                --accent-glow-bg: rgba(201,168,76,0.38);
                --accent-glow-hi: rgba(201,168,76,0.9);
                --accent-faint:   rgba(201,168,76,0.08);
                --accent-subtle:  rgba(201,168,76,0.13);
                --accent-low:     rgba(201,168,76,0.18);
                --accent-mid:     rgba(201,168,76,0.22);
                --accent-mod:     rgba(201,168,76,0.25);
                --accent-str:     rgba(201,168,76,0.30);
                --accent-brt:     rgba(201,168,76,0.50);
                --accent-glow30:  rgba(201,168,76,0.30);
                --accent-act:     rgba(201,168,76,0.10);
                --accent-act2:    rgba(201,168,76,0.05);
                --accent-shadow:  rgba(201,168,76,0.20);
                --accent-shadow2: rgba(201,168,76,0.2);
                --text-bright:    #ffffff;
            }
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            html, body { width: 100%; height: 100%; overflow: hidden; background-color: var(--green-dark); color: var(--cream); font-family: 'Nunito', sans-serif; }

            /* ── Animated background (burn-in layer 1) ── */
            body::before {
                content: '';
                position: fixed;
                inset: -60px;
                background-image:
                        radial-gradient(ellipse 80% 50% at 50% 0%, var(--accent-glow-sm) 0%, transparent 60%),
                        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cg fill='none' stroke='rgba(201,168,76,0.038)' stroke-width='1'%3E%3Cpath d='M60 6 L114 60 L60 114 L6 60Z'/%3E%3Cpath d='M60 26 L94 60 L60 94 L26 60Z'/%3E%3Ccircle cx='60' cy='60' r='16'/%3E%3C/g%3E%3C/svg%3E");
                background-size: auto, 120px 120px;
                pointer-events: none;
                z-index: 0;
                animation: bg-pan 90s linear infinite alternate;
            }
            @keyframes bg-pan {
                0%   { background-position: 0% 0%,   0px 0px;   }
                25%  { background-position: 30% 10%,  40px 20px; }
                50%  { background-position: 60% 20%,  80px 0px;  }
                75%  { background-position: 40% 50%,  20px 60px; }
                100% { background-position: 10% 30%,  60px 40px; }
            }

            /* ── Pixel-shift drift (burn-in layer 2) — entire UI moves ±12px over 4 min ── */
            @keyframes pixel-drift {
                0%   { transform: translate(0px,   0px);   }
                12%  { transform: translate(8px,   -5px);  }
                25%  { transform: translate(12px,  6px);   }
                37%  { transform: translate(-4px,  12px);  }
                50%  { transform: translate(-12px, 3px);   }
                62%  { transform: translate(-6px,  -10px); }
                75%  { transform: translate(5px,   -12px); }
                87%  { transform: translate(10px,  4px);   }
                100% { transform: translate(0px,   0px);   }
            }
            .drift-wrap {
                position: relative;
                z-index: 1;
                width: 100vw;
                height: 100vh;
                display: flex;
                flex-direction: column;
                padding: 16px 24px 12px;
                gap: 10px;
                animation: pixel-drift 240s ease-in-out infinite;
            }

            /* ══ TOP BAR ══ */
            .m-topbar {
                display: grid;
                grid-template-columns: 1fr auto 1fr;
                align-items: center;
                background: var(--card-bg);
                border: 1px solid var(--card-border);
                border-radius: 14px;
                padding: 10px 28px;
                flex-shrink: 0;
                position: relative;
            }
            .m-topbar::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 2px;
                border-radius: 14px 14px 0 0;
                background: linear-gradient(to right, transparent, var(--gold), transparent);
            }
            .m-date-block { display: flex; flex-direction: column; gap: 1px; }
            .m-date-label { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: var(--gold-dim); font-weight: 700; }
            .m-date-value { font-size: 20px; font-weight: 700; color: var(--cream-dim); letter-spacing: 0.3px; }

            /* Centre: icon + site name stacked */
            .m-mosque-center { display: flex; flex-direction: column; align-items: center; gap: 2px; }
            .m-mosque-icon { font-size: 36px; line-height: 1; animation: glow-pulse 3.5s ease-in-out infinite; }
            .m-site-name {
                font-family: 'Cinzel Decorative', serif;
                font-size: 13px;
                color: var(--gold);
                letter-spacing: 1.5px;
                text-align: center;
                white-space: nowrap;
            }
            @keyframes glow-pulse {
                0%,100% { filter: drop-shadow(0 0 8px var(--accent-glow-bg)); }
                50%      { filter: drop-shadow(0 0 22px var(--accent-glow-hi)); }
            }

            /* Clock block — right-aligned */
            .m-clock-block { display: flex; flex-direction: column; align-items: flex-end; gap: 1px; }
            .m-clock-label { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: var(--gold-dim); font-weight: 700; }
            .m-clock-digits {
                font-size: 48px;
                font-weight: 800;
                color: var(--gold-light);
                letter-spacing: 4px;
                font-variant-numeric: tabular-nums;
                line-height: 1;
            }

            /* ══ COUNTDOWN BANNER ══ */
            .m-countdown {
                background: linear-gradient(135deg, var(--bg-card-hi) 0%, var(--bg-card-lo) 100%);
                border: 1px solid var(--gold);
                border-radius: 14px;
                padding: 10px 30px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 24px;
                flex-shrink: 0;
                position: relative;
                overflow: hidden;
            }
            .m-countdown::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 2px;
                background: linear-gradient(to right, transparent, var(--gold), transparent);
            }
            .m-cd-left { display: flex; flex-direction: column; gap: 1px; }
            .m-cd-eyebrow { font-size: 10px; letter-spacing: 4px; text-transform: uppercase; color: var(--gold-dim); }
            .m-cd-name { font-family: 'Cinzel Decorative', serif; font-size: 26px; color: var(--gold-light); letter-spacing: 2px; line-height: 1.2; }
            .m-cd-since { font-size: 12px; color: var(--cream-dim); font-style: italic; margin-top: 1px; }
            .m-cd-right { text-align: right; }
            .m-cd-verb { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--cream-dim); margin-bottom: 2px; }
            .m-cd-digits {
                font-size: 56px;
                font-weight: 800;
                color: #fff;
                letter-spacing: 6px;
                font-variant-numeric: tabular-nums;
                line-height: 1;
                text-shadow: 0 0 40px var(--accent-glow30);
            }

            /* ══ PRAYER COLUMNS — CSS subgrid for perfect cross-column alignment ══
               .m-prayers is a 5-col × 8-row explicit grid.
               Each .m-prayer-col uses display:contents so its children become
               direct grid items — guaranteeing every row (pill, icon, name, desc,
               jamaat-label, jamaat-time, earliest-primary, earliest-shafi) aligns
               perfectly across all five columns.
               Row 8 (earliest-shafi) is only populated by Asr; all other columns
               place an empty spacer there.                                        */
            .m-prayers {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                /* Rows: pill | icon | name | desc | j-label | j-time | e-primary | e-shafi */
                grid-template-rows:
        [pill]          auto
        [icon]          auto
        [name]          auto
        [desc]          auto
        [j-label]       auto
        [j-time]        auto
        [e-primary]     auto
        [e-shafi]       auto;
                gap: 0 10px;
                flex-shrink: 0;
            }

            /* Each column spans all 8 rows as a visual card behind its children */
            .m-prayer-col {
                background: var(--card-bg);
                border: 1px solid var(--card-border);
                border-radius: 16px;
                display: contents;   /* children become grid items; card look via pseudo */
                position: relative;
                transition: border-color 0.4s, background 0.4s, box-shadow 0.4s;
            }

            /* Because display:contents removes the box, we fake the card background
               using a ::before on a wrapper div — instead, wrap each column's items
               in a real div that spans all 8 rows in its column via grid-row.      */
            .m-prayer-col { display: contents; }

            /* Column background: a grid-spanning div placed behind content */
            .m-col-bg {
                border-radius: 16px;
                background: var(--card-bg);
                border: 1px solid var(--card-border);
                grid-row: pill / span 8;
                position: relative;
                overflow: hidden;
                transition: border-color 0.4s, background 0.4s, box-shadow 0.4s;
                z-index: 0;
                pointer-events: none;
            }
            .m-col-bg::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 3px;
                background: linear-gradient(to right, transparent, var(--gold), transparent);
                opacity: 0;
                transition: opacity 0.4s;
            }

            /* Active / missed states target the bg div */
            .m-prayer-col.active   .m-col-bg { border-color: var(--gold); background: var(--accent-act); box-shadow: 0 0 48px var(--accent-shadow2), inset 0 0 28px var(--accent-act2); }
            .m-prayer-col.active   .m-col-bg::before { opacity: 1; }
            /* Missed: dim via data-col attribute set by JS on all children */
            [data-missed="1"] { opacity: 0.42; }

            /* Every direct child of display:contents is a grid item — assign rows */
            .m-col-bg        { grid-row: pill / span 8; z-index: 0; }
            .m-pill-row      { grid-row: pill;    z-index: 1; display: flex; justify-content: center; align-items: center; min-height: 28px; padding-top: 10px; }
            .m-prayer-icon   { grid-row: icon;    z-index: 1; font-size: clamp(32px, 3.5vh, 46px); line-height: 1; text-align: center; padding: 4px 0 0; }
            .m-prayer-name   { grid-row: name;    z-index: 1; font-family: 'Cinzel Decorative', serif; font-size: clamp(15px, 1.6vh, 22px); color: var(--gold); letter-spacing: 1.5px; text-align: center; padding: 2px 8px 0; }
            .m-prayer-desc   { grid-row: desc;    z-index: 1; font-size: clamp(9px, 0.9vh, 11px); color: var(--gold-dim); letter-spacing: 2px; text-transform: uppercase; text-align: center; padding-bottom: 2px; }
            .m-jamaat-label  { grid-row: j-label; z-index: 1; font-size: clamp(9px, 0.9vh, 11px); letter-spacing: 2px; text-transform: uppercase; color: var(--gold-dim); text-align: center; padding-top: clamp(6px, 0.8vh, 12px); }
            .m-jamaat-time   { grid-row: j-time;  z-index: 1; font-size: clamp(44px, 5.8vh, 76px); font-weight: 800; color: var(--text-bright); font-variant-numeric: tabular-nums; letter-spacing: 2px; line-height: 1; text-align: center; }
            .m-prayer-col.active .m-jamaat-time { color: var(--gold-light); }

            /* Earliest-primary row: shared by Fajr, Zuhr, Asr-Hanafi, Esha; empty for Maghrib */
            .m-earliest-primary {
                grid-row: e-primary;
                z-index: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                padding: clamp(5px, 0.6vh, 9px) 12px clamp(4px, 0.4vh, 6px);
            }
            /* Divider inside e-primary */
            .m-prayer-divider {
                width: 55%;
                height: 1px;
                background: linear-gradient(to right, transparent, var(--accent-mod), transparent);
                flex-shrink: 0;
            }
            .m-earliest-block {
                background: var(--accent-faint);
                border: 1px solid var(--accent-low);
                border-radius: 9px;
                padding: 5px 14px;
                text-align: center;
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            .m-earliest-label { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: var(--gold-dim); margin-bottom: 2px; }
            .m-earliest-time  { font-size: clamp(18px, 2.2vh, 28px); font-weight: 700; color: var(--gold-light); font-variant-numeric: tabular-nums; letter-spacing: 2px; line-height: 1; }

            /* Earliest-shafi row: only Asr; others use .m-shafi-spacer (empty) */
            .m-earliest-shafi {
                grid-row: e-shafi;
                z-index: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0 12px clamp(8px, 0.8vh, 12px);
            }
            .m-shafi-spacer {
                grid-row: e-shafi;
                z-index: 1;
                padding-bottom: clamp(8px, 0.8vh, 12px);
            }

            /* Status pill styles */
            .m-status-pill {
                font-size: 10px;
                font-weight: 800;
                letter-spacing: 2px;
                text-transform: uppercase;
                padding: 3px 12px;
                border-radius: 20px;
                display: inline-block;
            }
            .pill-now     { background: var(--gold); color: var(--green-dark); }
            .pill-next    { background: var(--accent-low); color: var(--gold-light); border: 1px solid var(--accent-brt); }
            .pill-missed  { background: rgba(192,57,43,0.18); color: #E07070; border: 1px solid rgba(192,57,43,0.35); }
            .pill-upcoming { background: rgba(255,255,255,0.05); color: var(--cream-dim); border: 1px solid rgba(255,255,255,0.08); }
            .pill-tomorrow { background: rgba(168,130,20,0.25); color: #d4aa30; border: 1px solid rgba(168,130,20,0.5); }


            /* ══ MIDDLE ZONE ══
               3fr left  = Community Notice (spans ~Fajr→half-Maghrib width)
               2fr right = Hadith of the Day (top half) + Funeral Notice (bottom half) */
            .m-middle {
                display: grid;
                grid-template-columns: 3fr 2fr;
                gap: 10px;
                flex: 1;
                min-height: 0;
            }

            /* Right column: stacks Hadith + Funeral vertically, each half height */
            .m-middle-right {
                display: flex;
                flex-direction: column;
                gap: 10px;
                min-height: 0;
            }

            /* Shared card base */
            .m-mid-card {
                border-radius: 16px;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                position: relative;
                padding: 14px 20px 12px;
            }
            .m-mid-card::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 2px;
                background: linear-gradient(to right, transparent, var(--gold), transparent);
                z-index: 10;
            }

            /* ── Community Notice card (left, full height) ── */
            .m-notice-card {
                background: linear-gradient(160deg, var(--bg-card-hi) 0%, var(--bg-deep) 100%);
                border: 1px solid var(--accent-str);
                flex: 1;
                position: relative;
                padding: 0 !important;  /* slides handle their own padding */
                display: flex;
                flex-direction: column;
                min-height: 0;
            }

            /* ── Slide progress bar — thin line at bottom of notice card ── */
            .m-slide-progress {
                position: absolute;
                bottom: 0; left: 0;
                height: 2px;
                width: 100%;
                z-index: 20;
                background: rgba(255,255,255,0.06);
                overflow: hidden;
                border-radius: 0 0 6px 6px;
            }
            @keyframes slide-progress-drain {
                from { transform: scaleX(1); }
                to   { transform: scaleX(0); }
            }
            .m-slide-progress-bar {
                height: 100%;
                width: 100%;
                background: linear-gradient(to right, var(--gold-dim), var(--gold));
                transform-origin: left center;
                transform: scaleX(1);
                animation: none;
            }
            .m-slide-progress-bar.animating {
                animation: slide-progress-drain var(--progress-dur, 30s) linear forwards;
            }
            .m-notice-eyebrow {
                font-size: 9px;
                letter-spacing: 4px;
                text-transform: uppercase;
                color: var(--gold-dim);
                font-weight: 700;
                flex-shrink: 0;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .m-notice-eyebrow::after {
                content: '';
                flex: 1;
                height: 1px;
                background: linear-gradient(to right, var(--accent-str), transparent);
            }
            .m-notice-heading {
                font-family: 'Cinzel Decorative', serif;
                font-size: clamp(14px, 1.8vh, 24px);
                color: var(--gold-light);
                letter-spacing: 1px;
                line-height: 1.3;
                flex-shrink: 0;
                margin-bottom: 10px;
            }
            .m-notice-body {
                font-size: clamp(13px, 1.6vh, 21px);
                color: var(--cream-dim);
                line-height: 1.75;
                overflow: hidden;
                flex: 1;
            }

            /* ── Hadith card (right top, flex:1 = 50% of right column) ── */
            .m-hadith-card {
                background: var(--card-bg);
                border: 1px solid var(--card-border);
                flex: 1;
                min-height: 0;
                display: flex;
                flex-direction: column;
                position: relative;
            }
            .m-hadith-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 8px;
                flex-shrink: 0;
            }
            .m-hadith-eyebrow {
                font-size: 9px;
                letter-spacing: 4px;
                text-transform: uppercase;
                color: var(--gold-dim);
                font-weight: 700;
            }
            .m-hadith-day {
                font-size: 9px;
                font-weight: 800;
                letter-spacing: 1.5px;
                background: linear-gradient(135deg, var(--gold-dim), var(--gold));
                color: var(--green-dark);
                padding: 3px 10px;
                border-radius: 20px;
            }
            .m-hadith-ref {
                font-family: 'Cinzel Decorative', serif;
                font-size: clamp(10px, 1.0vh, 13px);
                color: var(--gold-light);
                line-height: 1.4;
                border-left: 3px solid var(--gold);
                padding-left: 10px;
                flex-shrink: 0;
                margin-bottom: 6px;
            }
            .m-hadith-divider {
                height: 1px;
                background: linear-gradient(to right, transparent, var(--accent-mod), transparent);
                flex-shrink: 0;
                margin-bottom: 6px;
            }
            .m-hadith-text {
                flex: 1;
                min-height: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 6px 16px 6px 28px;
                overflow: hidden;
                position: relative;
            }
            .m-hadith-text::before {
                content: '❝';
                position: absolute;
                left: 4px; top: 6px;
                font-size: 32px;
                color: var(--gold);
                opacity: 0.2;
                font-family: Georgia, serif;
                line-height: 1;
                pointer-events: none;
            }
            .m-hadith-text-inner {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                width: 100%;
                gap: 6px;
            }
            .m-hadith-main {
                font-family: 'Amiri', serif;
                font-size: 18px;        /* JS overwrites this */
                color: var(--cream);
                line-height: 1.45;
                text-align: center;
                display: block;
                width: 100%;
            }
            .m-hadith-source {
                font-family: 'Cinzel Decorative', serif;
                font-size: 10px;        /* JS sets this relative to main */
                color: var(--gold-dim);
                text-align: center;
                letter-spacing: 0.5px;
                line-height: 1.4;
                display: block;
                width: 100%;
                flex-shrink: 0;
            }

            /* ── Funeral Notice card (right bottom, flex:1 = 50% of right column) ── */
            .m-funeral-card {
                background: linear-gradient(160deg, var(--bg-card-hi) 0%, var(--bg-deep) 100%);
                border: 1px solid rgba(192,57,43,0.28);
                flex: 1;
                min-height: 0;
            }
            .m-funeral-card::before {
                background: linear-gradient(to right, transparent, rgba(192,57,43,0.55), transparent);
            }
            .m-funeral-eyebrow {
                font-size: 9px;
                letter-spacing: 4px;
                text-transform: uppercase;
                color: rgba(192,57,43,0.7);
                font-weight: 700;
                flex-shrink: 0;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .m-funeral-eyebrow::after {
                content: '';
                flex: 1;
                height: 1px;
                background: linear-gradient(to right, rgba(192,57,43,0.3), transparent);
            }
            .m-funeral-heading {
                font-family: 'Cinzel Decorative', serif;
                font-size: clamp(12px, 1.4vh, 18px);
                color: #C8896A;
                letter-spacing: 1px;
                line-height: 1.3;
                flex-shrink: 0;
                margin-bottom: 8px;
            }
            .m-funeral-body {
                font-size: clamp(11px, 1.3vh, 17px);
                color: rgba(245,237,214,0.65);
                line-height: 1.7;
                overflow: hidden;
                flex: 1;
            }

            /* ══ MARKERS ROW — 4 time markers + next-event as 5th column ══ */
            .m-markers {
                display: grid;
                grid-template-columns: repeat(4, 1fr) 1.2fr;
                gap: 10px;
                flex-shrink: 0;
                align-items: stretch;
            }
            .m-marker-card {
                background: var(--bg-marker);
                border: 1px solid var(--accent-subtle);
                border-radius: 12px;
                padding: 10px 16px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .m-marker-icon { font-size: 24px; flex-shrink: 0; }
            .m-marker-name { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; color: var(--cream-dim); margin-bottom: 1px; }
            .m-marker-time { font-size: clamp(20px, 2.2vh, 28px); font-weight: 800; color: var(--red-soft); font-variant-numeric: tabular-nums; letter-spacing: 2px; line-height: 1; }
            .m-marker-note { font-size: 8px; color: var(--gold-dim); margin-top: 2px; }

            /* 5th column: next event card — same card style with countdown */
            .m-event-card {
                background: var(--bg-marker);
                border: 1px solid var(--accent-mid);
                border-radius: 12px;
                padding: 10px 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            .m-event-left { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
            .m-event-icon { font-size: 22px; }
            .m-event-eyebrow { font-size: 8px; letter-spacing: 2.5px; text-transform: uppercase; color: var(--gold-dim); margin-bottom: 1px; }
            .m-event-name { font-family: 'Cinzel Decorative', serif; font-size: clamp(10px, 1vh, 13px); color: var(--gold); line-height: 1.2; }
            .m-event-right { display: flex; flex-direction: column; align-items: flex-end; gap: 1px; flex-shrink: 0; }
            .m-event-verb { font-size: 8px; letter-spacing: 2px; text-transform: uppercase; color: var(--gold-dim); }
            .m-event-digits {
                font-size: clamp(20px, 2.2vh, 28px);
                font-weight: 800;
                color: var(--cream);
                font-variant-numeric: tabular-nums;
                letter-spacing: 2px;
                line-height: 1;
            }

            /* ══ TICKER STRIP ══ */
            .m-ticker-wrap {
                flex-shrink: 0;
                background: var(--bg-ticker);
                border: 1px solid var(--accent-low);
                border-radius: 10px;
                height: 38px;
                overflow: hidden;
                display: flex;
                align-items: center;
                position: relative;
            }
            .m-ticker-track {
                flex: 1;
                overflow: hidden;
                height: 100%;
                display: flex;
                align-items: center;
                position: relative;
            }
            .m-ticker-inner {
                display: inline-flex;
                align-items: center;
                white-space: nowrap;
                will-change: transform;
                position: absolute;
            }
            .m-ticker-text {
                font-size: 15px;
                font-weight: 600;
                color: var(--cream);
                letter-spacing: 0.3px;
                white-space: nowrap;
            }
            @keyframes ticker-scroll {
                from { transform: translateX(var(--ticker-start)); }
                to   { transform: translateX(var(--ticker-end)); }
            }

            /* ══ JAMAAT CHANGE NOTICE — musjid card styles ══ */
            /* The notice card uses .m-notice-card container (already styled).
               These classes style its inner content. */
            .m-notice-slide .jc-eyebrow {
                font-size: 9px;
                letter-spacing: 4px;
                text-transform: uppercase;
                color: #E8A030;
                font-weight: 800;
                flex-shrink: 0;
                margin-bottom: 6px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .m-notice-slide .jc-eyebrow::after {
                content: '';
                flex: 1;
                height: 1px;
                background: linear-gradient(to right, rgba(232,160,48,0.5), transparent);
            }
            .m-notice-slide .jc-divider {
                height: 1px;
                background: linear-gradient(to right, transparent, rgba(232,160,48,0.3), transparent);
                flex-shrink: 0;
                margin: 4px 0;
            }
            .m-notice-slide .jc-day-header {
                font-size: 9px;
                letter-spacing: 3px;
                text-transform: uppercase;
                color: rgba(232,160,48,0.7);
                font-weight: 700;
                margin: 6px 0 3px;
                flex-shrink: 0;
            }
            .m-notice-slide .jc-row {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 7px 10px;
                border-radius: 10px;
                background: rgba(232,160,48,0.07);
                border: 1px solid rgba(232,160,48,0.18);
                flex-shrink: 0;
                margin-bottom: 5px;
            }
            .m-notice-slide .jc-icon {
                font-size: clamp(18px, 2vh, 26px);
                flex-shrink: 0;
                width: 30px;
                text-align: center;
            }
            .m-notice-slide .jc-prayer {
                font-family: 'Cinzel Decorative', serif;
                font-size: clamp(12px, 1.5vh, 18px);
                color: #F5C842;
                font-weight: 700;
                flex: 1;
                white-space: nowrap;
            }
            .m-notice-slide .jc-when {
                font-size: clamp(9px, 1.1vh, 13px);
                color: var(--cream-dim);
                font-style: italic;
                flex: 1;
                text-align: center;
            }
            .m-notice-slide .jc-times {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-shrink: 0;
            }
            .m-notice-slide .jc-from {
                font-size: clamp(14px, 1.8vh, 22px);
                font-weight: 600;
                color: var(--cream-dim);
                text-decoration: line-through;
                font-variant-numeric: tabular-nums;
                opacity: 0.7;
            }
            .m-notice-slide .jc-arrow {
                font-size: clamp(14px, 1.8vh, 22px);
                color: #E8A030;
                font-weight: 700;
            }
            .m-notice-slide .jc-to {
                font-size: clamp(16px, 2.1vh, 26px);
                font-weight: 800;
                color: #F5C842;
                font-variant-numeric: tabular-nums;
                letter-spacing: 1px;
            }
            .m-notice-slide .jc-dir {
                font-size: clamp(9px, 1vh, 12px);
                font-weight: 700;
                padding: 2px 8px;
                border-radius: 20px;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .m-notice-slide .jc-dir.earlier {
                background: rgba(100,180,255,0.15);
                color: #8DC8F0;
                border: 1px solid rgba(100,180,255,0.25);
            }
            .m-notice-slide .jc-dir.later {
                background: rgba(255,160,60,0.15);
                color: #FFAA44;
                border: 1px solid rgba(255,160,60,0.25);
            }
            .m-notice-slide .jc-footer {
                font-size: clamp(11px, 1.3vh, 16px);
                font-weight: 700;
                color: #E8A030;
                text-align: center;
                flex-shrink: 0;
                margin-top: 4px;
                letter-spacing: 0.5px;
            }
            .m-notice-slide .jc-urgent {
                color: #FF8C42;
                animation: jc-pulse 2s ease-in-out infinite;
            }
            @keyframes jc-pulse {
                0%,100% { opacity: 1; }
                50%      { opacity: 0.6; }
            }

            /* Override the notice card border to amber when showing change notice */
            #m-notice-card.has-change-notice {
                border-color: rgba(232,160,48,0.45) !important;
            }
            #m-notice-card.has-change-notice::before {
                background: linear-gradient(to right, transparent, #E8A030, transparent) !important;
            }

            /* ══ SIMULATOR PANEL ══ */
            #sim-panel {
                position: fixed;
                bottom: 20px; right: 20px;
                width: 340px;
                background: var(--bg-sim);
                border: 1px solid var(--accent-brt);
                border-radius: 14px;
                z-index: 99999;
                font-family: 'Nunito', sans-serif;
                font-size: 12px;
                color: var(--cream);
                box-shadow: 0 8px 40px rgba(0,0,0,0.8), 0 0 0 1px var(--accent-faint);
                transition: box-shadow 0.2s;
                user-select: none;
                cursor: default;
            }
            #sim-panel.sim-dragging { box-shadow: 0 16px 60px rgba(0,0,0,0.9), 0 0 0 2px var(--gold); cursor: grabbing; }

            /* Collapsed = mini floating bar — just header visible */
            #sim-panel.sim-collapsed { width: auto; min-width: 200px; border-radius: 30px; }
            #sim-panel.sim-collapsed #sim-body { display: none; }
            #sim-panel.sim-collapsed #sim-header { border-radius: 30px; border-bottom: none; padding: 8px 16px; }

            #sim-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px 16px;
                border-bottom: 1px solid var(--accent-shadow);
                cursor: grab;
                border-radius: 14px 14px 0 0;
                background: var(--accent-faint);
            }
            #sim-header:active { cursor: grabbing; }
            .sim-header-left { display: flex; align-items: center; gap: 8px; }
            .sim-title { font-family: 'Cinzel Decorative', serif; font-size: 10px; color: var(--gold); letter-spacing: 2px; white-space: nowrap; }
            .sim-badge {
                background: rgba(100,100,100,0.8); color: #fff;
                border-radius: 4px; padding: 1px 7px; font-size: 9px;
                font-weight: 800; letter-spacing: 1px; white-space: nowrap;
            }
            #sim-panel.sim-running .sim-badge { background: var(--green); }
            #sim-collapse-btn {
                font-size: 13px; color: var(--gold-dim); cursor: pointer;
                padding: 2px 6px; margin-left: 4px; border-radius: 4px;
                background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
                line-height: 1; flex-shrink: 0;
            }
            #sim-collapse-btn:hover { background: var(--accent-act); color: var(--gold); }
            #sim-body { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
            .sim-row { display: flex; flex-direction: column; gap: 4px; }
            .sim-label { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: var(--gold-dim); }
            .sim-controls { display: flex; gap: 8px; align-items: center; }
            .sim-input {
                background: rgba(255,255,255,0.06);
                border: 1px solid var(--accent-mod);
                border-radius: 6px; color: var(--cream);
                padding: 5px 8px; font-size: 12px; font-family: 'Nunito', sans-serif;
                outline: none; width: 100%;
            }
            .sim-input:focus { border-color: var(--gold); }
            .sim-speed-btn {
                background: rgba(255,255,255,0.05);
                border: 1px solid var(--accent-shadow);
                border-radius: 6px; color: var(--cream-dim);
                padding: 4px 10px; font-size: 11px; cursor: pointer;
                font-family: 'Nunito', sans-serif; transition: all 0.15s;
            }
            .sim-speed-btn:hover { border-color: var(--gold); color: var(--gold); }
            .sim-speed-btn.active { background: var(--accent-act); border-color: var(--gold); color: var(--gold); font-weight: 800; }
            .sim-divider { height: 1px; background: var(--accent-subtle); }
            .sim-scenario-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
            .sim-scenario-btn {
                background: rgba(255,255,255,0.04);
                border: 1px solid var(--accent-subtle);
                border-radius: 6px; color: var(--cream-dim);
                padding: 5px 8px; font-size: 10px; cursor: pointer;
                font-family: 'Nunito', sans-serif; text-align: center;
                transition: all 0.15s; line-height: 1.3;
            }
            .sim-scenario-btn:hover { border-color: var(--gold-dim); color: var(--cream); background: var(--accent-faint); }
            .sim-action-row { display: flex; gap: 8px; }
            .sim-btn {
                flex: 1; padding: 7px 10px; border-radius: 8px; font-size: 11px;
                font-family: 'Nunito', sans-serif; font-weight: 700;
                cursor: pointer; border: none; transition: all 0.15s; letter-spacing: 0.5px;
            }
            .sim-btn-primary { background: var(--gold); color: var(--green-dark); }
            .sim-btn-primary:hover { background: var(--gold-light); }
            .sim-btn-secondary { background: rgba(255,255,255,0.07); color: var(--cream-dim); border: 1px solid rgba(255,255,255,0.12); }
            .sim-btn-secondary:hover { background: rgba(255,255,255,0.12); color: var(--cream); }
            .sim-btn-danger { background: rgba(192,57,43,0.3); color: #E07070; border: 1px solid rgba(192,57,43,0.4); }
            .sim-btn-danger:hover { background: rgba(192,57,43,0.5); }
            #sim-clock-display {
                font-size: 22px; font-weight: 800; color: var(--gold-light);
                font-variant-numeric: tabular-nums; letter-spacing: 3px; text-align: center;
                padding: 6px 0; border: 1px solid var(--accent-shadow);
                border-radius: 8px; background: var(--accent-act2);
            }
            #sim-status { font-size: 10px; color: var(--cream-dim); text-align: center; min-height: 14px; }

            .m-no-data { display: flex; align-items: center; justify-content: center; height: 100vh; font-size: 28px; color: var(--red-soft); text-align: center; padding: 40px; }

            /* ══ ZAWAAL OVERLAY ══ */
            #zawaal-overlay {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: none;                       /* hidden by default; JS controls visibility */
                pointer-events: none;
                /* Red hue layer */
                background: rgba(120, 0, 0, 0.55);
                /* Smooth fade in/out via opacity transition */
                opacity: 0;
                transition: opacity 1.2s ease;
            }
            #zawaal-overlay.zawaal-active {
                display: flex;
                opacity: 1;
            }
            /* Watermark text centred across the full screen */
            #zawaal-overlay::before {
                content: 'WARNING\A ZAWAAL';
                white-space: pre;
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Cinzel Decorative', serif;
                font-size: clamp(80px, 12vw, 180px);
                font-weight: 700;
                color: rgba(255, 80, 80, 0.18);
                text-align: center;
                line-height: 1.15;
                letter-spacing: 12px;
                text-transform: uppercase;
                pointer-events: none;
                /* Slow breathe pulse */
                animation: zawaal-pulse 3s ease-in-out infinite;
            }
            @keyframes zawaal-pulse {
                0%,100% { color: rgba(255, 80, 80, 0.14); }
                50%      { color: rgba(255, 80, 80, 0.26); }
            }
            /* Small label strip at the top of the overlay */
            #zawaal-label {
                position: absolute;
                top: 0; left: 0; right: 0;
                background: rgba(160, 0, 0, 0.75);
                border-bottom: 2px solid rgba(255,100,100,0.5);
                text-align: center;
                padding: 10px 0;
                font-family: 'Cinzel Decorative', serif;
                font-size: clamp(16px, 2vw, 28px);
                font-weight: 700;
                letter-spacing: 6px;
                color: rgba(255,200,200,0.95);
                text-transform: uppercase;
            }
        </style>
        <?php echo $_nmc_theme_css; ?>
    </head>
    <body>

    <!-- ── Zawaal Warning Overlay ── -->
    <div id="zawaal-overlay">
        <div id="zawaal-label">⚠️ &nbsp; Zawaal &nbsp; — &nbsp; Prayer is Makrooh &nbsp; ⚠️</div>
    </div>

    <?php if ($sim_active): ?>
        <!-- ══ DEBUG SIM TOOL (superadmin only) ══ -->
        <div id="sim-panel" class="sim-collapsed">
            <div id="sim-header">
                <div class="sim-header-left">
                    <span style="font-size:16px;">🧪</span>
                    <span class="sim-title">Debug Sim Tool</span>
                    <span class="sim-badge" id="sim-badge">PAUSED</span>
                </div>
                <button id="sim-collapse-btn" onclick="simToggleCollapse(event)" title="Expand/Collapse">▲</button>
            </div>
            <div id="sim-body">

                <!-- Live sim clock -->
                <div id="sim-clock-display">--:--:--</div>
                <div id="sim-status">Loading…</div>

                <div class="sim-divider"></div>

                <!-- Date picker -->
                <div class="sim-row">
                    <span class="sim-label">📅 Simulate Date</span>
                    <input type="date" class="sim-input" id="sim-date-input"
                           value="<?= htmlspecialchars(date('Y-m-d', $sim_ts), ENT_QUOTES) ?>">
                </div>

                <!-- Time scrubber -->
                <div class="sim-row">
                    <span class="sim-label">🕐 Simulate Time</span>
                    <div class="sim-controls">
                        <input type="range" id="sim-slider" min="0" max="86399" step="60"
                               value="<?= (function($t){ $p=explode(':',$t); return (int)$p[0]*3600+(int)$p[1]*60+(isset($p[2])?(int)$p[2]:0); })($sim_time) ?>"
                               style="flex:1;accent-color:var(--gold);"
                               oninput="simSliderMove(this.value)">
                        <input type="text" class="sim-input" id="sim-time-input"
                               value="<?= substr($sim_time,0,5) ?>"
                               style="width:68px;text-align:center;"
                               oninput="simTimeTextChange(this.value)">
                    </div>
                </div>

                <!-- Speed -->
                <div class="sim-row">
                    <span class="sim-label">⚡ Speed</span>
                    <div class="sim-controls" id="sim-speed-btns">
                        <button class="sim-speed-btn active" data-speed="1"   onclick="simSetSpeed(1)">1×</button>
                        <button class="sim-speed-btn"        data-speed="5"   onclick="simSetSpeed(5)">5×</button>
                        <button class="sim-speed-btn"        data-speed="30"  onclick="simSetSpeed(30)">30×</button>
                        <button class="sim-speed-btn"        data-speed="60"  onclick="simSetSpeed(60)">60×</button>
                        <button class="sim-speed-btn"        data-speed="300" onclick="simSetSpeed(300)">300×</button>
                    </div>
                </div>

                <div class="sim-divider"></div>

                <!-- Quick scenarios -->
                <div class="sim-row">
                    <span class="sim-label">🎯 Quick Scenarios</span>
                    <div class="sim-scenario-grid">
                        <button class="sim-scenario-btn" onclick="simScenario('fajr_window')">🌅 Fajr Opens</button>
                        <button class="sim-scenario-btn" onclick="simScenario('zawaal')">🔴 Zawaal</button>
                        <button class="sim-scenario-btn" onclick="simScenario('zuhr_window')">☀️ Zuhr Opens</button>
                        <button class="sim-scenario-btn" onclick="simScenario('asr_window')">🌤️ Asr Opens</button>
                        <button class="sim-scenario-btn" onclick="simScenario('maghrib_window')">🌇 Maghrib Opens</button>
                        <button class="sim-scenario-btn" onclick="simScenario('esha_window')">🌃 Esha Opens</button>
                        <button class="sim-scenario-btn" onclick="simScenario('thu_maghrib')">🕌 Thu→Jummah</button>
                        <button class="sim-scenario-btn" onclick="simScenario('fri_zuhr')">🕌 Fri Zuhr Now</button>
                    </div>
                </div>

                <div class="sim-divider"></div>

                <!-- Actions -->
                <div class="sim-action-row">
                    <button class="sim-btn sim-btn-primary"   onclick="simApply()">▶ Apply</button>
                    <button class="sim-btn sim-btn-secondary" onclick="simPause()" id="sim-pause-btn">⏸ Pause</button>
                    <button class="sim-btn sim-btn-danger"    onclick="simExit()">✕ Exit</button>
                </div>

            </div><!-- /sim-body -->
        </div><!-- /sim-panel -->
    <?php endif; ?>

    <?php if ($times): ?>
        <div class="drift-wrap">

        <div class="m-topbar">
            <div class="m-date-block">
                <span class="m-date-label">Today</span>
                <span class="m-date-value"><?= htmlspecialchars($full_date, ENT_QUOTES) ?> <span style="color:var(--gold-dim);font-size:0.6em;font-weight:400;margin:0 4px;">|</span><span style="color:var(--gold);font-size:0.68em;font-weight:600;"><?= htmlspecialchars($hijri_date_str, ENT_QUOTES) ?></span></span>
            </div>
            <div class="m-mosque-center">
                <span class="m-mosque-icon">🕌</span>
                <span class="m-site-name"><?= htmlspecialchars($site_name, ENT_QUOTES) ?></span>
                <?php if ($is_ramadan_active): ?>
                    <span style="font-size: 10px; color: var(--gold-light); letter-spacing: 3px; text-transform: uppercase; margin-top: 4px; font-weight: 700;">☪️ Ramadan Schedule Active</span>
                <?php endif; ?>
            </div>
            <div class="m-clock-block">
                <span class="m-clock-label">Current Time</span>
                <span class="m-clock-digits" id="m-liveClock">--:--:--</span>
            </div>
        </div>

        <div class="m-countdown">
            <div class="m-cd-left">
                <span class="m-cd-eyebrow">Next Prayer</span>
                <span class="m-cd-name" id="m-cdName">–</span>
                <span class="m-cd-since" id="m-cdSince"></span>
            </div>
            <div class="m-cd-right">
                <div class="m-cd-verb">begins in</div>
                <div class="m-cd-digits" id="m-cdDigits">--:--:--</div>
            </div>
        </div>

        <div class="m-prayers">

            <div class="m-prayer-col" id="m-card-Fajr" style="grid-column:1;" data-col="Fajr">
                <div class="m-col-bg" style="grid-column:1;" data-col="Fajr"></div>
                <div class="m-pill-row" style="grid-column:1;" data-col="Fajr"><span class="m-status-pill" id="m-pill-Fajr"></span></div>
                <span class="m-prayer-icon" style="grid-column:1;" data-col="Fajr">🌅</span>
                <div class="m-prayer-name" style="grid-column:1;" data-col="Fajr">Fajr</div>
                <div class="m-prayer-desc" style="grid-column:1;" data-col="Fajr">Dawn Prayer</div>
                <div class="m-jamaat-label" style="grid-column:1;" data-col="Fajr">Jamaat</div>
                <div class="m-jamaat-time" style="grid-column:1;" data-col="Fajr"><?= $times['fajr'] ?></div>
                <div class="m-earliest-primary" style="grid-column:1;" data-col="Fajr">
                    <div class="m-prayer-divider"></div>
                    <div class="m-earliest-block">
                        <div class="m-earliest-label">Earliest</div>
                        <div class="m-earliest-time"><?= $times['fajr_e'] ?></div>
                    </div>
                </div>
                <div class="m-shafi-spacer" style="grid-column:1;" data-col="Fajr"></div>
            </div>

            <div class="m-prayer-col" id="m-card-Zuhr" style="grid-column:2;" data-col="Zuhr">
                <div class="m-col-bg" style="grid-column:2;" data-col="Zuhr"></div>
                <div class="m-pill-row" style="grid-column:2;" data-col="Zuhr"><span class="m-status-pill" id="m-pill-Zuhr"></span></div>
                <span class="m-prayer-icon" style="grid-column:2;" data-col="Zuhr" id="m-icon-Zuhr">☀️</span>
                <div class="m-prayer-name" style="grid-column:2;" data-col="Zuhr" id="m-name-Zuhr">Zuhr</div>
                <div class="m-prayer-desc" style="grid-column:2;" data-col="Zuhr" id="m-desc-Zuhr">Midday Prayer</div>
                <div class="m-jamaat-label" style="grid-column:2;" data-col="Zuhr">Jamaat</div>
                <div class="m-jamaat-time" style="grid-column:2;" data-col="Zuhr" id="m-jtime-Zuhr"><?= $times['zuhr'] ?></div>
                <div class="m-earliest-primary" style="grid-column:2;" data-col="Zuhr" id="m-earliest-Zuhr">
                    <div class="m-prayer-divider"></div>
                    <!-- Normal (non-Jummah): single Earliest block -->
                    <div class="m-earliest-block" id="m-zuhr-earliest-block">
                        <div class="m-earliest-label">Earliest</div>
                        <div class="m-earliest-time"><?= $times['zuhr_e'] ?></div>
                    </div>
                    <!-- Jummah: Earliest only (full width) — Azaan+Khutbah go in e-shafi row below -->
                    <div id="m-jum-earliest" style="display:none;width:100%;">
                        <div class="m-earliest-block">
                            <div class="m-earliest-label">Earliest</div>
                            <div class="m-earliest-time"><?= $times['zuhr_e'] ?></div>
                        </div>
                    </div>
                </div>
                <!-- e-shafi row for Zuhr: Azaan+Khutbah side by side (shown on Jummah, hidden otherwise) -->
                <div class="m-earliest-shafi" style="grid-column:2;display:none;" data-col="Zuhr" id="m-jum-pills">
                    <div style="display:flex;gap:4px;width:100%;height:100%;">
                        <div class="m-earliest-block" style="flex:1;">
                            <div class="m-earliest-label">Azaan</div>
                            <div class="m-earliest-time" id="m-jum-azaan"><?= $jummah['azaan'] ?></div>
                        </div>
                        <div class="m-earliest-block" style="flex:1;">
                            <div class="m-earliest-label">Khutbah</div>
                            <div class="m-earliest-time" id="m-jum-khutbah"><?= $jummah['khutbah'] ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="m-prayer-col" id="m-card-Asr" style="grid-column:3;" data-col="Asr">
                <div class="m-col-bg" style="grid-column:3;" data-col="Asr"></div>
                <div class="m-pill-row" style="grid-column:3;" data-col="Asr"><span class="m-status-pill" id="m-pill-Asr"></span></div>
                <span class="m-prayer-icon" style="grid-column:3;" data-col="Asr">🌤️</span>
                <div class="m-prayer-name" style="grid-column:3;" data-col="Asr">Asr</div>
                <div class="m-prayer-desc" style="grid-column:3;" data-col="Asr">Afternoon Prayer</div>
                <div class="m-jamaat-label" style="grid-column:3;" data-col="Asr">Jamaat</div>
                <div class="m-jamaat-time" style="grid-column:3;" data-col="Asr"><?= $times['asr'] ?></div>
                <div class="m-earliest-primary" style="grid-column:3;" data-col="Asr">
                    <div class="m-prayer-divider"></div>
                    <div class="m-earliest-block">
                        <div class="m-earliest-label">Earliest Hanafi</div>
                        <div class="m-earliest-time"><?= $times['asr_eh'] ?></div>
                    </div>
                </div>
                <div class="m-earliest-shafi" style="grid-column:3;" data-col="Asr">
                    <div class="m-earliest-block">
                        <div class="m-earliest-label">Earliest Shafi</div>
                        <div class="m-earliest-time"><?= $times['asr_es'] ?></div>
                    </div>
                </div>
            </div>

            <div class="m-prayer-col" id="m-card-Maghrib" style="grid-column:4;" data-col="Maghrib">
                <div class="m-col-bg" style="grid-column:4;" data-col="Maghrib"></div>
                <div class="m-pill-row" style="grid-column:4;" data-col="Maghrib"><span class="m-status-pill" id="m-pill-Maghrib"></span></div>
                <span class="m-prayer-icon" style="grid-column:4;" data-col="Maghrib">🌇</span>
                <div class="m-prayer-name" style="grid-column:4;" data-col="Maghrib">Maghrib</div>
                <div class="m-prayer-desc" style="grid-column:4;" data-col="Maghrib">Sunset Prayer</div>
                <div class="m-jamaat-label" style="grid-column:4;" data-col="Maghrib">Jamaat</div>
                <div class="m-jamaat-time" style="grid-column:4;" data-col="Maghrib"><?= $times['maghrib'] ?></div>
                <div class="m-earliest-primary" style="grid-column:4;" data-col="Maghrib">
                    <div class="m-prayer-divider"></div>
                </div>
                <div class="m-shafi-spacer" style="grid-column:4;" data-col="Maghrib"></div>
            </div>

            <div class="m-prayer-col" id="m-card-Esha" style="grid-column:5;" data-col="Esha">
                <div class="m-col-bg" style="grid-column:5;" data-col="Esha"></div>
                <div class="m-pill-row" style="grid-column:5;" data-col="Esha"><span class="m-status-pill" id="m-pill-Esha"></span></div>
                <span class="m-prayer-icon" style="grid-column:5;" data-col="Esha">🌃</span>
                <div class="m-prayer-name" style="grid-column:5;" data-col="Esha">Esha</div>
                <div class="m-prayer-desc" style="grid-column:5;" data-col="Esha">Night Prayer</div>
                <div class="m-jamaat-label" style="grid-column:5;" data-col="Esha">Jamaat</div>
                <div class="m-jamaat-time" style="grid-column:5;" data-col="Esha"><?= $times['esha'] ?></div>
                <div class="m-earliest-primary" style="grid-column:5;" data-col="Esha">
                    <div class="m-prayer-divider"></div>
                    <div class="m-earliest-block">
                        <div class="m-earliest-label">Earliest</div>
                        <div class="m-earliest-time"><?= $times['esha_e'] ?></div>
                    </div>
                </div>
                <div class="m-shafi-spacer" style="grid-column:5;" data-col="Esha"></div>
            </div>

        </div><div class="m-middle">

            <div class="m-mid-card m-notice-card<?= ($has_changes && ($musjid_community_msgs[0]['_jamaat_notice'] ?? false)) ? ' has-change-notice' : '' ?>" id="m-notice-card">
                <div class="m-slide-progress" id="m-slide-progress">
                    <div class="m-slide-progress-bar" id="m-slide-progress-bar"></div>
                </div>
                <?php foreach ($musjid_community_msgs as $idx => $cm): ?>
                    <?php $isImg = ($cm['content_type'] === 'image' && !empty($cm['media_id'])); ?>
                    <?php $isJamaatNotice = !empty($cm['_jamaat_notice']); ?>
                    <div class="m-notice-slide" id="m-notice-<?= $idx ?>" style="<?= $idx > 0 ? 'display:none;' : 'display:flex;' ?>flex-direction:column;<?= $isImg ? 'position:absolute;inset:0;' : 'flex:1;width:100%;overflow:hidden;' ?>">
                        <?php if ($isJamaatNotice): ?>
                            <div style="display:flex;flex-direction:column;width:100%;flex:1;padding:14px 20px 12px;overflow:hidden;min-height:0;">
                                <?= $cm['content_html'] ?>
                            </div>
                        <?php elseif ($isImg): ?>
                            <div style="flex-shrink:0;padding:8px 14px 5px;background:rgba(0,0,0,0.45);position:relative;z-index:1;">
                                <div class="m-notice-eyebrow" style="margin-bottom:3px;">📢 Community Notice</div>
                                <?php if (!empty($cm['title'])): ?>
                                    <div class="m-notice-heading" style="margin-bottom:3px;"><?= htmlspecialchars($cm['title'], ENT_QUOTES) ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;min-height:0;">
                                <img src="index.php?action=img&id=<?= (int)$cm['media_id'] ?>"
                                     style="width:100%;height:100%;display:block;<?= $cm['image_fit'] === 'fill' ? '' : 'object-fit:'.htmlspecialchars($cm['image_fit'],ENT_QUOTES).';' ?>">
                            </div>
                        <?php else: /* html/text content */ ?>
                            <?php if (!empty($cm['title'])): ?>
                                <div class="m-notice-eyebrow" style="padding:14px 20px 0;">📢 Community Notice</div>
                                <div class="m-notice-heading" style="padding:0 20px;"><?= htmlspecialchars($cm['title'], ENT_QUOTES) ?></div>
                            <?php else: ?>
                                <div class="m-notice-eyebrow" style="padding:14px 20px 0;">📢 Community Notice</div>
                            <?php endif; ?>
                            <div class="m-notice-body" style="flex:1;overflow:hidden;padding:0 20px 12px;"><?= $cm['content_html'] ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="m-middle-right">

                <div class="m-mid-card m-hadith-card">
                    <div class="m-hadith-top">
                        <span class="m-hadith-eyebrow">📖 Hadith of the Day</span>
                        <span class="m-hadith-day">Day <?= $day_number ?> of 365</span>
                    </div>
                    <?php if ($musjid_hadith_text):
                        /* Split: main text is everything before the first '('
                           source is from the first '(' to the end               */
                        $first_paren = strpos($musjid_hadith_text, '(');
                        if ($first_paren !== false) {
                            $hadith_main   = trim(substr($musjid_hadith_text, 0, $first_paren));
                            $hadith_source = trim(substr($musjid_hadith_text, $first_paren));
                        } else {
                            $hadith_main   = $musjid_hadith_text;
                            $hadith_source = null;
                        }
                        ?>
                        <div class="m-hadith-text" id="m-hadith-text">
                            <div class="m-hadith-text-inner" id="m-hadith-inner">
                                <span class="m-hadith-main" id="m-hadith-main"><?= nl2br(htmlspecialchars($hadith_main, ENT_QUOTES)) ?></span>
                                <?php if ($hadith_source): ?>
                                    <span class="m-hadith-source" id="m-hadith-source"><?= htmlspecialchars($hadith_source, ENT_QUOTES) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="m-hadith-text" style="opacity:0.4;font-family:'Nunito',sans-serif;font-size:13px;">No hadith found for today</div>
                    <?php endif; ?>
                </div>

                <div class="m-mid-card m-funeral-card" id="m-funeral-card">
                    <div class="m-funeral-eyebrow">◆ Funeral Notice</div>
                    <?php
                    $active_funerals = array_filter($musjid_funeral_list, fn($f) => $f !== null);
                    if (empty($active_funerals)):
                        ?>
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:10px;">
                            <div class="m-funeral-heading">No Active Funeral Notices</div>
                            <div class="m-funeral-body" style="font-size:clamp(11px,1.2vh,15px);">No funeral announcements at this time.<br>May Allāh grant all the deceased Jannatul Firdaus. Āmeen.</div>
                            <div style="font-size:clamp(14px,1.6vh,20px);color:var(--gold);font-family:'Amiri',serif;margin-top:6px;direction:rtl;">إِنَّا لِلَّٰهِ وَإِنَّا إِلَيْهِ رَاجِعُونَ</div>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_values($active_funerals) as $fidx => $fn): ?>
                            <div class="m-funeral-slide" id="m-funeral-<?= $fidx ?>" style="<?= $fidx > 0 ? 'display:none;' : 'display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;' ?>">
                                <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:8px;padding:4px 0;">
                                    <div class="m-funeral-heading" style="font-size:clamp(16px,2vh,24px);"><?= htmlspecialchars($fn['deceased_name'], ENT_QUOTES) ?></div>
                                    <?php if ($fn['family_details']): ?>
                                        <div style="font-size:clamp(10px,1.1vh,14px);color:rgba(200,180,138,0.75);line-height:1.4;"><?= htmlspecialchars($fn['family_details'], ENT_QUOTES) ?></div>
                                    <?php endif; ?>
                                    <div style="width:60%;height:1px;background:var(--accent-mod);margin:4px auto;"></div>
                                    <div style="font-size:clamp(11px,1.25vh,15px);color:var(--cream);line-height:1.7;">
                                        <?php if ($fn['leave_from']): ?><div>🏠 <strong>Leaves from:</strong> <?= htmlspecialchars($fn['leave_from'], ENT_QUOTES) ?><?= $fn['departure_time'] ? ' at <strong>' . htmlspecialchars($fn['departure_time'], ENT_QUOTES) . '</strong>' : '' ?></div><?php endif; ?>
                                        <?php if ($fn['janazah_location']): ?><div>🕌 <strong>Janazah:</strong> <?= htmlspecialchars($fn['janazah_location'], ENT_QUOTES) ?><?= $fn['janazah_time'] ? ' at <strong>' . htmlspecialchars($fn['janazah_time'], ENT_QUOTES) . '</strong>' : '' ?></div><?php endif; ?>
                                        <?php if ($fn['proceeding_to']): ?><div>⚰️ <strong>Proceeding to:</strong> <?= htmlspecialchars($fn['proceeding_to'], ENT_QUOTES) ?></div><?php endif; ?>
                                        <?php if ($fn['funeral_date_en']): ?><div style="font-size:clamp(10px,1.1vh,13px);color:var(--cream-dim);margin-top:2px;">📅 <?= htmlspecialchars($fn['funeral_date_en'], ENT_QUOTES) ?><?= $fn['funeral_date_hijri'] ? ' · ' . htmlspecialchars($fn['funeral_date_hijri'], ENT_QUOTES) : '' ?></div><?php endif; ?>
                                    </div>
                                    <div style="font-size:clamp(13px,1.5vh,18px);color:var(--gold);font-family:'Amiri',serif;margin-top:6px;direction:rtl;">إِنَّا لِلَّٰهِ وَإِنَّا إِلَيْهِ رَاجِعُونَ</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div></div><div class="m-markers">
            <div class="m-marker-card">
                <span class="m-marker-icon">🌙</span>
                <div>
                    <div class="m-marker-name">Sehri Ends</div>
                    <div class="m-marker-time"><?= $times['sehri'] ?></div>
                    <div class="m-marker-note">Last time to eat before fast</div>
                </div>
            </div>
            <div class="m-marker-card">
                <span class="m-marker-icon">🌄</span>
                <div>
                    <div class="m-marker-name">Sunrise</div>
                    <div class="m-marker-time"><?= $times['sunrise'] ?></div>
                    <div class="m-marker-note">Salah not permissible after this</div>
                </div>
            </div>
            <div class="m-marker-card">
                <span class="m-marker-icon">🕛</span>
                <div>
                    <div class="m-marker-name">Zawaal</div>
                    <div class="m-marker-time"><?= $times['zawaal'] ?></div>
                    <div class="m-marker-note">Solar noon — prayer is Makrooh</div>
                </div>
            </div>
            <div class="m-marker-card">
                <span class="m-marker-icon">🌆</span>
                <div>
                    <div class="m-marker-name">Sunset / Iftaar</div>
                    <div class="m-marker-time"><?= $times['sunset'] ?></div>
                    <div class="m-marker-note">Time of Iftaar</div>
                </div>
            </div>
            <div class="m-event-card">
                <div class="m-event-left">
                    <span class="m-event-icon" id="m-evIcon">–</span>
                    <div>
                        <div class="m-event-eyebrow">Next Marker</div>
                        <div class="m-event-name" id="m-evName">–</div>
                    </div>
                </div>
                <div class="m-event-right">
                    <div class="m-event-verb">begins in</div>
                    <div class="m-event-digits" id="m-evDigits">--:--:--</div>
                </div>
            </div>
        </div><div class="m-ticker-wrap">
            <div class="m-ticker-track" id="m-ticker-track">
                <div class="m-ticker-inner" id="m-ticker-inner">
                    <span class="m-ticker-text" id="m-ticker-text"><?= htmlspecialchars($musjid_ticker_list[0]['message_text'] ?? '', ENT_QUOTES) ?></span>
                </div>
            </div>
        </div>

        </div><?php else: ?>
        <div class="m-no-data">⚠️ No Salaah times found for today.<br>Please contact the site administrator.</div>
    <?php endif; ?>

    <script>

        /* ── Cycle data from PHP ── */
        const communitySlides = <?= json_encode(array_map(fn($m) => [
                'id'           => $m['id'],
                'display_secs' => (int)$m['display_secs'],
        ], $musjid_community_msgs)) ?>;

        const funeralSlides = <?= json_encode(array_values(array_map(fn($f) => [
                'id'           => $f['id'],
                'display_secs' => (int)($f['display_secs'] ?? 30),
        ], array_filter($musjid_funeral_list, fn($f) => $f !== null)))) ?>;

        const tickerMessages = <?= json_encode(array_map(fn($t) => [
                'text'         => $t['message_text'],
                'display_secs' => (int)$t['display_secs'],
        ], $musjid_ticker_list)) ?>;

        /* ── Prayer / event data from PHP ── */
        const prayers = <?= $prayer_js ?>;
        const events  = <?= $events_js ?>;
        const W       = <?= $windows_js ?>;
        const JUM     = <?= $jummah_js ?>; // Jummah times
        const SIM     = <?= $sim_js ?>;    // Simulator config

        /* ══ SIMULATOR ENGINE ══
         * When SIM.active, nowSec() and simDow() return overridden values.
         * The sim clock starts at SIM.time and advances at SIM.speed × real time.
         * All prayer logic (tick, Jummah mode, Zawaal) reads through these functions
         * so nothing else needs to change.
         */
        const _SIM = {
            active:    SIM.active,
            startSec:  SIM.active ? (function(){ const p=SIM.time.split(':').map(Number); return p[0]*3600+p[1]*60+(p[2]||0); })() : 0,
            loadedAt:  Date.now(),
            speed:     SIM.speed || 1,
            paused:    false,
            pausedAt:  0,        // simSec value when pause was pressed
            dow:       SIM.dow,  // day-of-week override
        };

        /* ── Helpers ── */
        function toSec(t) { const [h,m] = t.split(':').map(Number); return h*3600 + m*60; }
        function pad(v)   { return String(v).padStart(2,'0'); }
        function fmtSince(s) {
            const h = Math.floor(s/3600), m = Math.floor((s%3600)/60);
            return h > 0 ? `${h}h ${m}m ago` : `${m} min ago`;
        }

        /* nowSec() — returns whole seconds-since-midnight for either real or simulated time */
        function nowSec() {
            if (!_SIM.active) {
                const n = new Date();
                return n.getHours()*3600 + n.getMinutes()*60 + n.getSeconds();
            }
            if (_SIM.paused) return Math.floor(_SIM.pausedAt) % 86400;
            const elapsed = (Date.now() - _SIM.loadedAt) / 1000 * _SIM.speed;
            return Math.floor(_SIM.startSec + elapsed) % 86400;
        }

        /* simDow() — returns day-of-week 0=Sun…5=Fri…6=Sat */
        function simDow() {
            if (_SIM.active && _SIM.dow >= 0) return _SIM.dow;
            return new Date().getDay();
        }

        /* fmtCountdown — always floor inputs to avoid float bleed-through */
        function fmtCountdown(s) {
            s = Math.floor(Math.abs(s));
            return `${pad(Math.floor(s/3600))}:${pad(Math.floor((s%3600)/60))}:${pad(s%60)}`;
        }

        /* ── Prayer windows — OPEN = earliest valid time, CLOSE = window end ──
         * Fajr    : opens e_fajr        → closes sunrise
         * Zuhr    : opens e_zuhr        → closes asr_boundary (madhab-dependent)
         * Asr     : opens asr_boundary  → closes sunset
         * Maghrib : opens maghrib       → closes e_esha  (no separate earliest for Maghrib)
         * Esha    : opens e_esha        → closes e_fajr next day
         */
        const prayerWindows = {
            Fajr:    { open: toSec(W.fajr_e),       close: toSec(W.sunrise)        },
            Zuhr:    { open: toSec(W.zuhr_e),       close: toSec(W.asr_boundary)   },
            Asr:     { open: toSec(W.asr_boundary), close: toSec(W.sunset)         },
            Maghrib: { open: toSec(W.maghrib),      close: toSec(W.esha_e)         },
            Esha:    { open: toSec(W.esha_e),       close: toSec(W.fajr_e) + 86400 },
        };

        /* ── Live clock ── */
        function updateClock() {
            if (_SIM.active) {
                const s = Math.floor(nowSec());
                document.getElementById('m-liveClock').textContent =
                    `${pad(Math.floor(s/3600))}:${pad(Math.floor((s%3600)/60))}:${pad(s%60)}`;
            } else {
                const n = new Date();
                document.getElementById('m-liveClock').textContent =
                    `${pad(n.getHours())}:${pad(n.getMinutes())}:${pad(n.getSeconds())}`;
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        /* ── Main tick (prayer states + countdowns) ── */

        /*
         * Jummah mode is active when:
         *   - It is Friday (day=5) and Zuhr window is not yet closed (i.e. before Asr opens)
         *   - OR it is Thursday (day=4) and time >= Maghrib (previewing tomorrow's Jummah)
         * In Jummah mode:
         *   - Zuhr card label/icon/desc/jamaat-time switch to Jummah values
         *   - Earliest block hides, Azaan+Khutbah pills show
         *   - If Thursday after Maghrib → pill = "Tomorrow" (amber)
         *   - If Friday, window not yet open → pill = "Upcoming"
         */
        function getJummahMode(now) {
            const dow = simDow(); // sim-aware day of week
            const maghribSec = toSec(W.maghrib);
            const asrBoundarySec = prayerWindows.Asr.open;
            if (dow === 5 && now < asrBoundarySec) return 'friday';
            if (dow === 4 && now >= maghribSec)    return 'tomorrow';
            return 'none';
        }

        function applyJummahCard(jMode) {
            const nameEl         = document.getElementById('m-name-Zuhr');
            const iconEl         = document.getElementById('m-icon-Zuhr');
            const descEl         = document.getElementById('m-desc-Zuhr');
            const jtimeEl        = document.getElementById('m-jtime-Zuhr');
            const normalEarliest = document.getElementById('m-zuhr-earliest-block'); // normal Zuhr single pill (e-primary)
            const jumEarliest    = document.getElementById('m-jum-earliest');        // Jummah Earliest pill (e-primary)
            const jumPills       = document.getElementById('m-jum-pills');           // Azaan+Khutbah in e-shafi row

            if (jMode !== 'none') {
                if (nameEl)         nameEl.textContent  = 'Jummah';
                if (iconEl)         iconEl.textContent  = '🕌';
                if (descEl)         descEl.textContent  = 'Friday Prayer';
                if (jtimeEl)        jtimeEl.textContent = JUM.jamaat;
                if (normalEarliest) normalEarliest.style.display = 'none';
                if (jumEarliest)    jumEarliest.style.display    = '';      // show Earliest in e-primary
                if (jumPills)       jumPills.style.display       = 'flex';  // show Azaan+Khutbah in e-shafi
            } else {
                if (nameEl)         nameEl.textContent  = 'Zuhr';
                if (iconEl)         iconEl.textContent  = '☀️';
                if (descEl)         descEl.textContent  = 'Midday Prayer';
                if (jtimeEl)        jtimeEl.textContent = W.zuhr;
                if (normalEarliest) normalEarliest.style.display = '';
                if (jumEarliest)    jumEarliest.style.display    = 'none';
                if (jumPills)       jumPills.style.display       = 'none';
            }
        }

        function tick() {
            const now = nowSec();

            /* ── Zawaal overlay ── */
            const zawaalOverlay = document.getElementById('zawaal-overlay');
            if (zawaalOverlay) {
                /* Active from zawaal time (solar noon) up to zuhr_e (earliest Zuhr = window opens) */
                const inZawaal = now >= toSec(W.zawaal) && now < toSec(W.zuhr_e);
                if (inZawaal) {
                    zawaalOverlay.style.display = 'flex';
                    requestAnimationFrame(() => zawaalOverlay.classList.add('zawaal-active'));
                } else {
                    zawaalOverlay.classList.remove('zawaal-active');
                    /* Hide after fade-out transition completes */
                    setTimeout(() => {
                        if (!zawaalOverlay.classList.contains('zawaal-active'))
                            zawaalOverlay.style.display = 'none';
                    }, 1300);
                }
            }

            /* ── Jummah mode check ── */
            const jMode = getJummahMode(now);
            applyJummahCard(jMode);

            /* Next prayer countdown — if Friday and Jummah not yet started, count to Jummah jamaat */
            let nextPrayer = null, minPrayerDiff = Infinity;
            prayers.forEach(p => {
                let pTime = p.name === 'Zuhr' && jMode !== 'none' ? JUM.jamaat : p.time;
                let d = toSec(pTime) - now;
                if (d <= 0) d += 86400;
                if (d < minPrayerDiff) { minPrayerDiff = d; nextPrayer = {...p, displayName: p.name === 'Zuhr' && jMode !== 'none' ? 'Jummah' : p.name}; }
            });
            if (nextPrayer) {
                document.getElementById('m-cdName').textContent   = `${nextPrayer.icon} ${nextPrayer.displayName || nextPrayer.name}`;
                document.getElementById('m-cdDigits').textContent = fmtCountdown(minPrayerDiff);
            }

            /* Last prayer since label */
            let lastPrayer = null, minPast = Infinity;
            prayers.forEach(p => {
                let past = now - toSec(p.time);
                if (past < 0) past += 86400;
                if (past < minPast) { minPast = past; lastPrayer = p; }
            });
            if (lastPrayer) {
                const lName = lastPrayer.name === 'Zuhr' && jMode !== 'none' ? 'Jummah' : lastPrayer.name;
                document.getElementById('m-cdSince').textContent =
                    `${lastPrayer.icon} ${lName} was ${fmtSince(minPast)}`;
            }

            /* Next event marker (Sehri, Sunrise, Zawaal, Sunset) */
            let nextEvent = null, minEventDiff = Infinity;
            events.forEach(e => {
                let d = toSec(e.time) - now;
                if (d <= 0) d += 86400;
                if (d < minEventDiff) { minEventDiff = d; nextEvent = e; }
            });
            if (nextEvent) {
                document.getElementById('m-evIcon').textContent   = nextEvent.icon;
                document.getElementById('m-evName').textContent   = nextEvent.name;
                document.getElementById('m-evDigits').textContent = fmtCountdown(minEventDiff);
            }

            /* Prayer card states */
            const mainNames = ['Fajr','Zuhr','Asr','Maghrib','Esha'];

            function isOpen(name) {
                const w = prayerWindows[name];
                if (name === 'Esha') return now >= w.open || now < toSec(W.fajr_e);
                return now >= w.open && now < w.close;
            }
            function isMissed(name) {
                if (name === 'Esha') return false;
                if (isOpen(name))    return false;
                return now >= prayerWindows[name].close;
            }

            let nextName = null, nextDiff = Infinity;
            mainNames.forEach(name => {
                const w = prayerWindows[name];
                let d = w.open - now;
                if (d <= 0) d += 86400;
                if (!isOpen(name) && !isMissed(name) && d < nextDiff) { nextDiff = d; nextName = name; }
            });

            mainNames.forEach(name => {
                const card = document.getElementById('m-card-' + name);
                const pill = document.getElementById('m-pill-' + name);
                if (!card || !pill) return;

                card.classList.remove('active', 'missed');
                pill.className   = 'm-status-pill';
                pill.textContent = '';
                document.querySelectorAll(`[data-col="${name}"]`).forEach(el => el.removeAttribute('data-missed'));

                if (name === 'Zuhr' && jMode === 'tomorrow') {
                    /* Thursday after Maghrib: Jummah is tomorrow */
                    pill.classList.add('pill-tomorrow');
                    pill.textContent = 'Tomorrow';
                } else if (name === 'Zuhr' && jMode === 'friday' && !isOpen('Zuhr')) {
                    /* Friday but Zuhr window not yet open */
                    pill.classList.add('pill-upcoming');
                    pill.textContent = 'Upcoming';
                } else if (isOpen(name)) {
                    card.classList.add('active');
                    pill.classList.add('pill-now');
                    pill.textContent = 'Now';
                } else if (isMissed(name)) {
                    card.classList.add('missed');
                    pill.classList.add('pill-missed');
                    /* On Friday after Asr, Zuhr/Jummah is already reverted to Zuhr by applyJummahCard */
                    pill.textContent = 'Qadha';
                    document.querySelectorAll(`[data-col="${name}"]`).forEach(el => el.setAttribute('data-missed','1'));
                } else if (name === nextName) {
                    pill.classList.add('pill-next');
                    pill.textContent = 'Next';
                } else {
                    pill.classList.add('pill-upcoming');
                    pill.textContent = 'Upcoming';
                }
            });
        }
        setInterval(tick, 1000);
        tick();

        /* ══════════════════════════════════════════════
           CONTENT CYCLING ENGINE
           ══════════════════════════════════════════════ */

        /* ── Community Message Cycler ── */
        (function(){
            var slides = communitySlides;



            var current = 0, shownAt = Date.now(), timer = null;
            function getDur(i){ return (slides[i] && slides[i].display_secs >= 1) ? slides[i].display_secs * 1000 : 30000; }
            /* Expose state globally so the poll reload can wait for slide boundary */
            window._nmcCycler = { get current(){ return current; }, get shownAt(){ return shownAt; }, getDur: getDur };

            /* ── Slide progress bar ── */
            function startProgressBar(durationMs){
                var bar = document.getElementById('m-slide-progress-bar');
                if (!bar) return;
                /* Remove animating class to reset */
                bar.classList.remove('animating');
                bar.style.animation = 'none';
                bar.style.transform = 'scaleX(1)';
                /* Force reflow so browser registers the reset */
                void bar.offsetWidth;
                /* Set duration via CSS variable then start animation */
                bar.style.setProperty('--progress-dur', (durationMs / 1000).toFixed(1) + 's');
                bar.style.animation = '';
                bar.classList.add('animating');
            }

            function showSlide(i){
                slides.forEach(function(_,j){
                    var e = document.getElementById('m-notice-'+j);
                    if (e) e.style.display = 'none';
                });
                current = i % slides.length;
                var el = document.getElementById('m-notice-' + current);
                if (el) {
                    el.style.display = 'flex';
                    el.style.flexDirection = 'column';
                    if (el.style.position !== 'absolute') el.style.flex = '1';
                }
                shownAt = Date.now();
                startProgressBar(getDur(current));
            }

            function scheduleAdvance(){
                if (timer) clearTimeout(timer);
                var remaining = getDur(current) - (Date.now() - shownAt);
                if (remaining < 50) remaining = 50;
                timer = setTimeout(function(){ showSlide((current+1) % slides.length); scheduleAdvance(); }, remaining);
            }

            document.addEventListener('visibilitychange', function(){
                if (!document.hidden) {
                    var elapsed = Date.now() - shownAt;
                    var overdue = elapsed - getDur(current);
                    if (overdue > 2000) {
                        showSlide((current + 1) % slides.length);
                        scheduleAdvance();
                    } else if (!timer) {
                        scheduleAdvance();
                    }
                }
            });

            showSlide(0);
            scheduleAdvance();
        })();

        /* ── Funeral Notice Cycler ── */
        (function(){
            var slides = funeralSlides;
            if (!slides || slides.length <= 1) return;
            var current = 0, shownAt = Date.now(), timer = null;
            function getDur(i){ return (slides[i] && slides[i].display_secs >= 1) ? slides[i].display_secs * 1000 : 30000; }
            function showSlide(i){
                slides.forEach(function(_,j){ var e=document.getElementById('m-funeral-'+j); if(e) e.style.display='none'; });
                current = i % slides.length;
                var el = document.getElementById('m-funeral-' + current);
                if (el) { el.style.display='flex'; el.style.flexDirection='column'; }
                shownAt = Date.now();
            }
            function scheduleAdvance(){
                if (timer) clearTimeout(timer);
                var remaining = getDur(current) - (Date.now() - shownAt);
                if (remaining < 50) remaining = 50;
                timer = setTimeout(function(){ showSlide((current+1) % slides.length); scheduleAdvance(); }, remaining);
            }
            document.addEventListener('visibilitychange', function(){ if (!document.hidden) scheduleAdvance(); });
            shownAt = Date.now();
            scheduleAdvance();
        })();

        /* ── Ticker Message Cycler ── */
        (function(){
            var tidx  = 0;
            var track = document.getElementById('m-ticker-track');
            var inner = document.getElementById('m-ticker-inner');
            var span  = document.getElementById('m-ticker-text');
            if (!track || !inner || !span || !tickerMessages.length) return;

            var PX_PER_SEC = 100;

            function runTicker(txt){
                /* Set text, kill any running animation */
                span.textContent = txt;
                inner.style.animation = 'none';
                inner.style.transform  = '';

                /* Reflow to get real measurements */
                void inner.offsetWidth;

                var trackW = track.clientWidth;           /* visible width of track */
                var textW  = inner.offsetWidth;           /* width of the text span */

                /* Start just off the RIGHT edge, end fully off the LEFT edge */
                var startX = trackW;                      /* +ve = right of track */
                var endX   = -textW;                      /* -ve = fully left of track */
                var travel = startX - endX;               /* total pixels to move */
                var dur    = travel / PX_PER_SEC;

                inner.style.setProperty('--ticker-start', startX + 'px');
                inner.style.setProperty('--ticker-end',   endX   + 'px');

                /* Force position before animating */
                inner.style.transform = 'translateX(' + startX + 'px)';
                void inner.offsetWidth;

                inner.style.animation = 'ticker-scroll ' + dur.toFixed(2) + 's linear 1 forwards';

                /* When scroll finishes, immediately start next message */
                inner.addEventListener('animationend', function handler(){
                    inner.removeEventListener('animationend', handler);
                    if (tickerMessages.length > 1) tidx = (tidx + 1) % tickerMessages.length;
                    runTicker(tickerMessages[tidx].text);
                });
            }

            /* Small delay on first load so layout is settled */
            setTimeout(function(){ runTicker(tickerMessages[0].text); }, 300);
        })();

        /* ── Hadith text: fill panel with largest font that fits, centred ── */
        (function(){
            var wrap   = document.getElementById('m-hadith-text');
            var inner  = document.getElementById('m-hadith-inner');
            var main   = document.getElementById('m-hadith-main');
            var source = document.getElementById('m-hadith-source'); /* may be null */
            if (!wrap || !inner || !main) return;

            function fitText(){
                var card = wrap.closest('.m-hadith-card');
                if (!card || card.clientHeight < 10) return;

                var cardCS  = window.getComputedStyle(card);
                var cardPad = parseFloat(cardCS.paddingTop) + parseFloat(cardCS.paddingBottom);

                var used = 0;
                Array.from(card.children).forEach(function(c){
                    if (c === wrap) return;
                    var cs = window.getComputedStyle(c);
                    used += c.offsetHeight + parseFloat(cs.marginTop) + parseFloat(cs.marginBottom);
                });

                var wrapCS  = window.getComputedStyle(wrap);
                var wrapPad = parseFloat(wrapCS.paddingTop) + parseFloat(wrapCS.paddingBottom);
                var available = card.clientHeight - cardPad - used - wrapPad - 8;
                if (available < 20) return;

                /* Pin width so text wraps consistently during search */
                var spanW = wrap.clientWidth - parseFloat(wrapCS.paddingLeft) - parseFloat(wrapCS.paddingRight);
                inner.style.width = spanW + 'px';
                wrap.style.overflow = 'visible';

                /* Binary search on main font size; source is always 28% of main */
                var lo = 12, hi = 82, best = 14;
                while (lo <= hi) {
                    var mid = (lo + hi) >> 1;
                    main.style.fontSize   = mid + 'px';
                    main.style.lineHeight = mid > 30 ? '1.3' : '1.45';
                    if (source) {
                        source.style.fontSize = Math.max(9, Math.round(mid * 0.28)) + 'px';
                    }
                    if (inner.scrollHeight <= available) { best = mid; lo = mid + 1; }
                    else                                  { hi  = mid - 1; }
                }

                main.style.fontSize   = best + 'px';
                main.style.lineHeight = best > 30 ? '1.3' : '1.45';
                if (source) {
                    source.style.fontSize = Math.max(9, Math.round(best * 0.28)) + 'px';
                }
                inner.style.width   = '';
                wrap.style.overflow = 'hidden';
            }

            function tryFit(n){
                requestAnimationFrame(function(){
                    var card = wrap.closest('.m-hadith-card');
                    if (card && card.clientHeight > 10) {
                        fitText();
                        if (document.fonts && document.fonts.ready)
                            document.fonts.ready.then(fitText);
                    } else if (n > 0) {
                        tryFit(n - 1);
                    }
                });
            }
            tryFit(40);
            window.addEventListener('resize', fitText);
        })();

        /* ── Auto-refresh at midnight (disabled in simulator) ── */
        if (!_SIM.active) {
            (function() {
                const n  = new Date();
                const ms = new Date(n.getFullYear(), n.getMonth(), n.getDate()+1, 0, 0, 5) - n;
                setTimeout(() => location.reload(), ms);
            })();
        }

        /* ── Content change polling (disabled in simulator) ── */
        if (!_SIM.active) {
            (function(){
                var baseline = <?= json_encode($content_version_val ?? '0') ?>;
                function checkForChanges(){
                    fetch('index.php?display=musjid&poll=1', { cache: 'no-store' })
                        .then(function(r){ return r.ok ? r.json() : null; })
                        .catch(function(){ return null; })
                        .then(function(data){
                            if (!data || data.v === undefined) return;
                            if (data.v !== baseline) {
                                /* Wait until the current slide finishes its display time
                                   before reloading — avoids cutting a slide short         */
                                var slideEl   = document.getElementById('m-notice-card');
                                var waitMs    = 500; // default: reload almost immediately
                                if (window._nmcCycler) {
                                    var elapsed   = Date.now() - window._nmcCycler.shownAt;
                                    var dur       = window._nmcCycler.getDur(window._nmcCycler.current);
                                    var remaining = dur - elapsed;
                                    if (remaining > 0 && remaining < 35000) waitMs = remaining + 200;
                                }
                                setTimeout(function(){ location.reload(); }, waitMs);
                            }
                        });
                }
                setInterval(checkForChanges, 60000);
            })();
        }

        <?php if ($sim_active): ?>
        /* ══════════════════════════════════════════════════════
           DEBUG SIM TOOL — PANEL LOGIC
           ══════════════════════════════════════════════════════ */

        /* ── Draggable panel ── */
        (function(){
            const panel  = document.getElementById('sim-panel');
            const header = document.getElementById('sim-header');
            if (!panel || !header) return;

            let dragging = false, ox = 0, oy = 0;

            header.addEventListener('mousedown', function(e) {
                /* Don't start drag if user clicked the collapse button */
                if (e.target.closest('#sim-collapse-btn')) return;
                dragging = true;
                ox = e.clientX - panel.offsetLeft;
                oy = e.clientY - panel.offsetTop;
                panel.classList.add('sim-dragging');
                /* Switch to top/left absolute positioning */
                panel.style.right  = 'auto';
                panel.style.bottom = 'auto';
                panel.style.left   = panel.offsetLeft + 'px';
                panel.style.top    = panel.offsetTop  + 'px';
                e.preventDefault();
            });

            document.addEventListener('mousemove', function(e) {
                if (!dragging) return;
                let nx = e.clientX - ox;
                let ny = e.clientY - oy;
                /* Clamp to viewport */
                nx = Math.max(0, Math.min(nx, window.innerWidth  - panel.offsetWidth));
                ny = Math.max(0, Math.min(ny, window.innerHeight - panel.offsetHeight));
                panel.style.left = nx + 'px';
                panel.style.top  = ny + 'px';
            });

            document.addEventListener('mouseup', function() {
                if (dragging) {
                    dragging = false;
                    panel.classList.remove('sim-dragging');
                }
            });

            /* Touch support for tablets */
            header.addEventListener('touchstart', function(e) {
                if (e.target.closest('#sim-collapse-btn')) return;
                const t = e.touches[0];
                dragging = true;
                ox = t.clientX - panel.offsetLeft;
                oy = t.clientY - panel.offsetTop;
                panel.style.right  = 'auto';
                panel.style.bottom = 'auto';
                panel.style.left   = panel.offsetLeft + 'px';
                panel.style.top    = panel.offsetTop  + 'px';
            }, { passive: true });

            document.addEventListener('touchmove', function(e) {
                if (!dragging) return;
                const t = e.touches[0];
                let nx = t.clientX - ox;
                let ny = t.clientY - oy;
                nx = Math.max(0, Math.min(nx, window.innerWidth  - panel.offsetWidth));
                ny = Math.max(0, Math.min(ny, window.innerHeight - panel.offsetHeight));
                panel.style.left = nx + 'px';
                panel.style.top  = ny + 'px';
            }, { passive: true });

            document.addEventListener('touchend', function() { dragging = false; });
        })();

        /* ── Panel collapse/expand ── */
        function simToggleCollapse(e) {
            if (e) e.stopPropagation();
            const panel = document.getElementById('sim-panel');
            const btn   = document.getElementById('sim-collapse-btn');
            const collapsed = panel.classList.toggle('sim-collapsed');
            btn.textContent = collapsed ? '▲' : '▼';
        }

        /* ── Speed control ── */
        function simSetSpeed(s) {
            const curSec  = nowSec();
            _SIM.startSec = curSec;
            _SIM.loadedAt = Date.now();
            _SIM.speed    = s;
            _SIM.paused   = false;
            document.querySelectorAll('.sim-speed-btn').forEach(b => {
                b.classList.toggle('active', parseInt(b.dataset.speed) === s);
            });
            simUpdateBadge();
        }

        /* ── Pause / resume ── */
        function simPause() {
            const btn = document.getElementById('sim-pause-btn');
            if (!_SIM.paused) {
                _SIM.pausedAt = nowSec();
                _SIM.paused   = true;
                if (btn) btn.textContent = '▶ Resume';
            } else {
                _SIM.startSec = _SIM.pausedAt;
                _SIM.loadedAt = Date.now();
                _SIM.paused   = false;
                if (btn) btn.textContent = '⏸ Pause';
            }
            simUpdateBadge();
        }

        /* ── Time slider moved ── */
        function simSliderMove(val) {
            const sec = parseInt(val);
            document.getElementById('sim-time-input').value =
                pad(Math.floor(sec/3600)) + ':' + pad(Math.floor((sec%3600)/60));
        }

        /* ── Manual time text input ── */
        function simTimeTextChange(val) {
            const m = val.match(/^(\d{1,2}):(\d{2})$/);
            if (m) {
                document.getElementById('sim-slider').value =
                    parseInt(m[1])*3600 + parseInt(m[2])*60;
            }
        }

        /* ── Apply new date+time (reloads page with sim params) ── */
        function simApply() {
            const dateVal  = document.getElementById('sim-date-input').value;
            const timeVal  = document.getElementById('sim-time-input').value || '00:00';
            location.href  = `?display=musjid&sim=1&sim_date=${encodeURIComponent(dateVal)}&sim_time=${encodeURIComponent(timeVal)}:00`;
        }

        /* ── Quick scenario shortcuts ── */
        function simScenario(key) {
            const dateInput = document.getElementById('sim-date-input');
            const timeInput = document.getElementById('sim-time-input');

            function addMins(t, mins) {
                const parts = t.split(':').map(Number);
                let total = parts[0]*60 + parts[1] + mins;
                if (total < 0)     total += 1440;
                if (total >= 1440) total -= 1440;
                return pad(Math.floor(total/60)) + ':' + pad(total % 60);
            }
            function wt(k) { return W[k] ? W[k].substring(0,5) : '00:00'; }

            const simDateStr = dateInput.value;
            const simDateObj = new Date(simDateStr + 'T12:00:00');
            function dateForDow(targetDow) {
                let diff = targetDow - simDateObj.getDay();
                if (diff < 0) diff += 7;
                const d = new Date(simDateObj);
                d.setDate(d.getDate() + diff);
                return d.toISOString().substring(0, 10);
            }

            switch (key) {
                case 'fajr_window':    timeInput.value = addMins(wt('fajr_e'),       -2); break;
                case 'zawaal':         timeInput.value = addMins(wt('zawaal'),        -1); break;
                case 'zuhr_window':    timeInput.value = addMins(wt('zuhr_e'),        -2); break;
                case 'asr_window':     timeInput.value = addMins(wt('asr_boundary'),  -2); break;
                case 'maghrib_window': timeInput.value = addMins(wt('maghrib'),       -2); break;
                case 'esha_window':    timeInput.value = addMins(wt('esha_e'),        -2); break;
                case 'thu_maghrib':
                    dateInput.value = dateForDow(4);
                    timeInput.value = addMins(wt('maghrib'), -2); break;
                case 'fri_zuhr':
                    dateInput.value = dateForDow(5);
                    timeInput.value = addMins(wt('zuhr_e'), -2); break;
            }
            const m = timeInput.value.match(/^(\d{1,2}):(\d{2})$/);
            if (m) document.getElementById('sim-slider').value = parseInt(m[1])*3600 + parseInt(m[2])*60;
        }

        /* ── Exit simulator ── */
        function simExit() {
            window.location.href = '?display=musjid';
        }

        /* ── Badge + pause button state ── */
        function simUpdateBadge() {
            const badge = document.getElementById('sim-badge');
            const panel = document.getElementById('sim-panel');
            const btn   = document.getElementById('sim-pause-btn');
            if (_SIM.paused) {
                if (badge) { badge.textContent = 'PAUSED'; badge.style.background = 'rgba(100,100,100,0.8)'; }
                if (btn)   btn.textContent = '▶ Resume';
                if (panel) panel.classList.remove('sim-running');
            } else {
                if (badge) { badge.textContent = `${_SIM.speed}× SIM`; badge.style.background = getComputedStyle(document.documentElement).getPropertyValue('--green').trim(); }
                if (btn)   btn.textContent = '⏸ Pause';
                if (panel) panel.classList.add('sim-running');
            }
        }

        /* ── Live sim clock display (4× per second) ── */
        function simUpdatePanel() {
            const s   = Math.floor(nowSec());
            const hh  = pad(Math.floor(s/3600));
            const mm  = pad(Math.floor((s%3600)/60));
            const ss  = pad(s % 60);
            const clk = document.getElementById('sim-clock-display');
            if (clk) clk.textContent = `${hh}:${mm}:${ss}`;

            const dow  = simDow();
            const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            const dateStr = document.getElementById('sim-date-input')?.value || SIM.date;
            let status = `${days[dow]} ${dateStr}`;
            if (_SIM.paused) status += ' · PAUSED';
            else             status += ` · ${_SIM.speed}× speed`;
            const st = document.getElementById('sim-status');
            if (st) st.textContent = status;

            /* Sync slider while running */
            const slider = document.getElementById('sim-slider');
            if (slider && !_SIM.paused && document.activeElement !== slider) {
                slider.value = s;
            }
        }
        setInterval(simUpdatePanel, 250);
        simUpdatePanel();
        simUpdateBadge();

        /* Auto-expand on load */
        document.addEventListener('DOMContentLoaded', function(){
            const panel = document.getElementById('sim-panel');
            const btn   = document.getElementById('sim-collapse-btn');
            if (panel) panel.classList.remove('sim-collapsed');
            if (btn)   btn.textContent = '▼';
        });
        <?php endif; // sim_active ?>
    </script>
    </body>
    </html>
    <?php exit; endif; /* end musjid mode */ ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Musjid Display System – Daily Salaah Times">
    <meta name="theme-color" content="#07170A">
    <title>MDS – Salaah Times</title>

    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --gold:        #C9A84C;
            --gold-light:  #E8C97A;
            --gold-dim:    #8A6E32;
            --green-dark:  #07170A;
            --green-mid:   #102E16;
            --green:       #1B5E35;
            --cream:       #F5EDD6;
            --cream-dim:   #C8B98A;
            --red-soft:    #E07070;
            --card-bg:     rgba(16,46,22,0.80);
            --card-border: rgba(201,168,76,0.22);
            --card-active: rgba(201,168,76,0.13);
            --shadow:      0 8px 32px rgba(0,0,0,0.45);
            --bg-deep:        rgba(7,23,10,0.92);
            --bg-card-hi:     rgba(16,46,22,0.92);
            --bg-card-lo:     rgba(27,94,53,0.42);
            --bg-card-mid:    rgba(16,46,22,0.80);
            --bg-marker:      rgba(10,24,12,0.78);
            --bg-ticker:      rgba(7,20,10,0.92);
            --bg-fab:         rgba(7,23,10,0.93);
            --accent-glow-sm: rgba(201,168,76,0.09);
            --accent-glow-bg: rgba(201,168,76,0.38);
            --accent-glow-hi: rgba(201,168,76,0.9);
            --accent-faint:   rgba(201,168,76,0.08);
            --accent-subtle:  rgba(201,168,76,0.13);
            --accent-low:     rgba(201,168,76,0.18);
            --accent-mid:     rgba(201,168,76,0.22);
            --accent-mod:     rgba(201,168,76,0.25);
            --accent-str:     rgba(201,168,76,0.30);
            --accent-brt:     rgba(201,168,76,0.50);
            --accent-glow30:  rgba(201,168,76,0.30);
            --accent-act:     rgba(201,168,76,0.10);
            --accent-act2:    rgba(201,168,76,0.05);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            background-color: var(--green-dark);
            color: var(--cream);
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                    radial-gradient(ellipse 90% 55% at 50% -5%, var(--accent-glow-sm) 0%, transparent 65%),
                    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Cg fill='none' stroke='rgba(201,168,76,0.055)' stroke-width='1'%3E%3Cpath d='M40 4 L76 40 L40 76 L4 40Z'/%3E%3Cpath d='M40 18 L62 40 L40 62 L18 40Z'/%3E%3Ccircle cx='40' cy='40' r='10'/%3E%3C/g%3E%3C/svg%3E");
            background-size: auto, 80px 80px;
            pointer-events: none;
            z-index: 0;
        }

        .page-wrap {
            position: relative;
            z-index: 1;
            max-width: 960px;
            margin: 0 auto;
            padding: 0 16px 110px;
        }

        /* ── Hero ── */
        .hero {
            text-align: center;
            padding: 44px 16px 18px;
        }
        .hero-mosque {
            font-size: 54px;
            display: block;
            margin-bottom: 10px;
            animation: glow-pulse 3.5s ease-in-out infinite;
        }
        @keyframes glow-pulse {
            0%,100% { filter: drop-shadow(0 0 10px var(--accent-glow-bg)); }
            50%      { filter: drop-shadow(0 0 26px var(--accent-glow-hi)); }
        }
        .hero-title {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(15px, 3.8vw, 27px);
            color: var(--gold);
            letter-spacing: 2px;
            text-shadow: 0 2px 14px var(--accent-glow-bg);
            margin-bottom: 4px;
        }
        .hero-sub {
            font-size: 11px;
            color: var(--cream-dim);
            letter-spacing: 4px;
            text-transform: uppercase;
        }
        .gold-rule {
            width: 150px;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--gold), transparent);
            margin: 14px auto 0;
        }

        /* ── Top bar ── */
        .top-bar {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            justify-content: space-between;
            gap: 8px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 14px 22px;
            margin: 18px 0 18px;
            backdrop-filter: blur(10px);
        }

        .top-bar-date {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            font-size: 13px;
            color: var(--cream-dim);
            line-height: 1.5;
        }
        .top-bar-date strong {
            font-size: 10px;
            color: var(--gold-dim);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .top-bar-clock {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: flex-end;
        }
        .top-bar-clock strong {
            font-size: 10px;
            color: var(--gold-dim);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .live-clock {
            font-size: 26px;
            font-weight: 800;
            color: var(--gold);
            letter-spacing: 3px;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }

        /* Mobile: stack vertically, left-align both */
        @media (max-width: 520px) {
            .top-bar {
                flex-direction: column;
                flex-wrap: wrap;
                gap: 12px;
            }
            .top-bar-clock {
                align-items: flex-start;
            }
            .live-clock {
                font-size: 32px;
                letter-spacing: 2px;
            }
        }

        /* ── Countdown Banner ── */
        .countdown-banner {
            background: linear-gradient(135deg, var(--green-mid) 0%, var(--bg-card-lo) 100%);
            border: 1px solid var(--gold);
            border-radius: 20px;
            padding: 24px 28px 20px;
            margin-bottom: 26px;
            text-align: center;
            box-shadow: var(--shadow), 0 0 0 1px var(--accent-faint);
            animation: fadein 0.7s ease both;
            position: relative;
            overflow: hidden;
        }
        .countdown-banner::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(to right, transparent, var(--gold), transparent);
        }
        @keyframes fadein {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .cd-eyebrow {
            font-size: 10px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--gold-dim);
            margin-bottom: 4px;
        }
        .cd-context-label {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .cd-verb {
            font-size: clamp(13px, 2.8vw, 16px);
            color: var(--cream-dim);
            font-weight: 600;
            display: block;
        }
        .cd-prayer-name {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(18px, 4vw, 26px);
            color: var(--gold-light);
            display: block;
        }
        .cd-digits {
            font-size: clamp(38px, 10vw, 64px);
            font-weight: 800;
            color: #fff;
            letter-spacing: 5px;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            text-shadow: 0 0 30px var(--accent-glow30);
        }
        .cd-units {
            display: flex;
            justify-content: center;
            gap: clamp(20px, 7vw, 58px);
            margin-top: 5px;
        }
        .cd-unit-label {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gold-dim);
        }
        .cd-since {
            font-size: 12px;
            color: var(--cream-dim);
            margin-top: 12px;
            opacity: 0.65;
            font-style: italic;
        }

        /* ── Event Banner (smaller, below prayer banner) ── */
        .event-banner {
            background: var(--bg-marker);
            border: 1px solid var(--accent-low);
            border-radius: 14px;
            padding: 13px 20px;
            margin-bottom: 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            backdrop-filter: blur(8px);
            animation: fadein 0.7s ease both;
            animation-delay: 0.15s;
        }
        .event-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .event-icon { font-size: 22px; flex-shrink: 0; }
        .event-eyebrow {
            font-size: 9px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold-dim);
            margin-bottom: 1px;
        }
        .event-name {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(11px, 2vw, 14px);
            color: var(--gold);
            letter-spacing: 0.5px;
            line-height: 1.2;
        }
        .event-verb {
            font-size: 11px;
            color: var(--cream-dim);
            margin-top: 2px;
        }
        .event-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
            flex-shrink: 0;
        }
        .event-digits {
            font-size: clamp(20px, 4vw, 26px);
            font-weight: 800;
            color: var(--cream);
            letter-spacing: 2px;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .event-units {
            display: flex;
            gap: 12px;
        }
        .event-unit {
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold-dim);
        }

        /* ── Section heading ── */
        .section-head {
            font-family: 'Cinzel Decorative', serif;
            font-size: 10px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--gold-dim);
            text-align: center;
            margin: 30px 0 14px;
        }
        .section-head::before, .section-head::after { content: ' ✦ '; opacity: 0.5; }

        /* ── Prayer Cards ── */
        .prayer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(265px, 1fr));
            gap: 14px;
        }
        .prayer-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 20px 22px 18px;
            backdrop-filter: blur(8px);
            transition: transform 0.22s, border-color 0.22s, box-shadow 0.22s;
            animation: fadein 0.55s ease both;
            position: relative;
        }
        .prayer-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(to right, transparent, var(--gold), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .prayer-card:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(0,0,0,0.5); border-color: var(--accent-brt); }
        .prayer-card:hover::after { opacity: 1; }
        .prayer-card.active { border-color: var(--gold); background: var(--card-active); box-shadow: 0 0 28px var(--accent-shadow); }
        .prayer-card.active::after { opacity: 1; }

        /* Status pill */
        .status-pill {
            position: absolute;
            top: 14px; right: 14px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .pill-now    { background: var(--gold); color: var(--green-dark); }
        .pill-next   { background: var(--accent-low); color: var(--gold-light); border: 1px solid var(--accent-str); }
        .pill-passed  { background: rgba(255,255,255,0.05); color: var(--cream-dim); }
        .pill-missed  { background: rgba(192,57,43,0.18); color: #E07070; border: 1px solid rgba(192,57,43,0.3); }
        .pill-upcoming { background: var(--accent-faint); color: var(--cream-dim); border: 1px solid var(--accent-subtle); }
        .pill-tomorrow { background: rgba(168,130,20,0.25); color: #d4aa30; border: 1px solid rgba(168,130,20,0.5); }
        /* Dim card when prayer window has closed */
        .prayer-card.missed { opacity: 0.55; }

        .card-top { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px; }
        .card-icon { font-size: 26px; line-height: 1; margin-top: 2px; }
        .card-name {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(13px, 2.5vw, 16px);
            color: var(--gold);
            letter-spacing: 1px;
        }
        .card-desc { font-size: 11px; color: var(--cream-dim); letter-spacing: 1px; margin-top: 2px; }

        /* ── Time row: main time left, earliest pill right ── */
        .time-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--gold-dim);
            margin-bottom: 3px;
        }

        /* Wrapper that sits main time and earliest side-by-side */
        .time-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .time-main {
            font-size: clamp(30px, 6vw, 40px);
            font-weight: 800;
            color: #fff;
            letter-spacing: 1px;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            flex-shrink: 0;
        }

        /* Earliest pill — right side of the same row */
        .earliest-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            background: var(--accent-glow-sm);
            border: 1px solid var(--accent-mid);
            border-radius: 10px;
            padding: 7px 12px;
            text-align: center;
            flex-shrink: 0;
        }
        .earliest-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--gold-dim);
            white-space: nowrap;
        }
        .earliest-time {
            font-size: 18px;
            font-weight: 700;
            color: var(--gold-light);
            font-variant-numeric: tabular-nums;
            letter-spacing: 1px;
            line-height: 1;
        }

        /* Asr dual: stacked vertically, right-aligned */
        .earliest-dual {
            display: flex;
            flex-direction: column;
            gap: 5px;
            justify-content: flex-end;
            flex-shrink: 0;
        }
        .earliest-dual .earliest-block {
            align-items: center;
            min-width: 0;
            /* Each pill: label left, time right on same line */
            flex-direction: row;
            justify-content: space-between;
            gap: 12px;
            padding: 5px 10px;
            width: 100%;
        }
        /* Time-only pill: no label, so right-justify the time */
        .earliest-dual .earliest-block.time-only {
            justify-content: flex-end;
        }
        .madhab-tag {
            font-size: 9px;
            letter-spacing: 1.5px;
            color: var(--gold-dim);
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* Label row above time-row — same style as .time-label */
        .time-label-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 3px;
        }
        .time-label-row .time-label { margin-bottom: 0; }

        /* ── Marker Cards ── */
        .marker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(195px, 1fr));
            gap: 12px;
        }
        .marker-card {
            background: var(--bg-marker);
            border: 1px solid var(--accent-subtle);
            border-radius: 14px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            backdrop-filter: blur(6px);
            transition: border-color 0.2s;
            animation: fadein 0.55s ease both;
        }
        .marker-card:hover { border-color: var(--accent-str); }
        .marker-icon { font-size: 26px; flex-shrink: 0; }
        .marker-name { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: var(--cream-dim); margin-bottom: 2px; }
        .marker-time { font-size: 22px; font-weight: 800; color: var(--red-soft); font-variant-numeric: tabular-nums; letter-spacing: 1px; }
        .marker-note { font-size: 10px; color: var(--gold-dim); margin-top: 2px; }

        /* ── Floating Hadith button ── */
        .hadith-fab {
            position: fixed;
            bottom: 26px;
            right: 20px;
            z-index: 999;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-fab);
            border: 1px solid var(--gold);
            color: var(--gold-light);
            font-family: 'Nunito', sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 11px 20px 11px 16px;
            border-radius: 40px;
            text-decoration: none;
            box-shadow: 0 4px 22px rgba(0,0,0,0.55), 0 0 14px var(--accent-subtle);
            backdrop-filter: blur(14px);
            transition: box-shadow 0.25s, transform 0.2s, background 0.2s;
        }
        .hadith-fab::before {
            content: '';
            position: absolute;
            inset: -5px;
            border-radius: 46px;
            border: 1px solid var(--accent-mod);
            animation: ring-pulse 3.2s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes ring-pulse {
            0%,100% { opacity: 0.7; transform: scale(1); }
            55%      { opacity: 0; transform: scale(1.09); }
        }
        .hadith-fab:hover {
            background: var(--green-mid);
            box-shadow: 0 6px 30px rgba(0,0,0,0.6), 0 0 22px var(--accent-mod);
            transform: translateY(-3px);
            color: var(--gold-light);
            text-decoration: none;
        }
        .fab-icon { font-size: 18px; }

        /* ── Stagger delays ── */
        .prayer-card:nth-child(1) { animation-delay: 0.05s; }
        .prayer-card:nth-child(2) { animation-delay: 0.10s; }
        .prayer-card:nth-child(3) { animation-delay: 0.15s; }
        .prayer-card:nth-child(4) { animation-delay: 0.20s; }
        .prayer-card:nth-child(5) { animation-delay: 0.25s; }
        .marker-card:nth-child(1) { animation-delay: 0.30s; }
        .marker-card:nth-child(2) { animation-delay: 0.35s; }
        .marker-card:nth-child(3) { animation-delay: 0.40s; }
        .marker-card:nth-child(4) { animation-delay: 0.45s; }

        /* ── Responsive ── */
        @media (max-width: 520px) {
            .prayer-grid { grid-template-columns: 1fr; }
            .marker-grid { grid-template-columns: 1fr 1fr; }
            .countdown-banner { padding: 20px 16px 16px; }
        }
        @media (max-width: 360px) {
            .marker-grid { grid-template-columns: 1fr; }
        }

        /* ══ JAMAAT CHANGE NOTICE — website banner ══ */
        .jamaat-change-banner {
            background: linear-gradient(135deg, rgba(40,25,5,0.92) 0%, rgba(30,20,5,0.96) 100%);
            border: 1px solid rgba(232,160,48,0.45);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            animation: fadein 0.6s ease both;
            position: relative;
            overflow: hidden;
        }
        .jamaat-change-banner::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(to right, transparent, #E8A030, transparent);
        }
        .jcb-inner {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .jcb-left { flex: 1; min-width: 0; }
        .jcb-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            flex-shrink: 0;
        }
        .jcb-eyebrow {
            font-size: 9px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #E8A030;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .jcb-day-header {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(232,160,48,0.6);
            font-weight: 700;
            margin: 6px 0 3px;
        }
        .jcb-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 8px;
            border-radius: 8px;
            background: rgba(232,160,48,0.06);
            border: 1px solid rgba(232,160,48,0.14);
            margin-bottom: 4px;
            flex-wrap: wrap;
        }
        .jcb-icon { font-size: 16px; flex-shrink: 0; }
        .jcb-prayer {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(10px, 2vw, 12px);
            color: #F5C842;
            font-weight: 700;
            flex: 1;
            min-width: 80px;
        }
        .jcb-prayer em {
            font-family: 'Nunito', sans-serif;
            font-style: italic;
            font-size: 10px;
            color: var(--cream-dim);
            font-weight: 400;
        }
        .jcb-times {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
        }
        .jcb-from {
            font-size: 13px;
            font-weight: 600;
            color: var(--cream-dim);
            text-decoration: line-through;
            font-variant-numeric: tabular-nums;
            opacity: 0.65;
        }
        .jcb-arrow { font-size: 13px; color: #E8A030; font-weight: 700; }
        .jcb-to {
            font-size: 15px;
            font-weight: 800;
            color: #F5C842;
            font-variant-numeric: tabular-nums;
        }
        .jcb-dir {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 20px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .jcb-dir.earlier {
            background: rgba(100,180,255,0.12);
            color: #8DC8F0;
            border: 1px solid rgba(100,180,255,0.22);
        }
        .jcb-dir.later {
            background: rgba(255,160,60,0.12);
            color: #FFAA44;
            border: 1px solid rgba(255,160,60,0.22);
        }
        .jcb-countdown {
            font-size: 11px;
            font-weight: 700;
            color: #E8A030;
            white-space: nowrap;
            text-align: right;
        }
        .jcb-countdown .jc-urgent {
            color: #FF8C42;
            animation: jcb-pulse 2s ease-in-out infinite;
        }
        @keyframes jcb-pulse {
            0%,100% { opacity: 1; }
            50%      { opacity: 0.55; }
        }
        .jcb-dismiss {
            background: rgba(232,160,48,0.1);
            border: 1px solid rgba(232,160,48,0.25);
            color: #E8A030;
            border-radius: 6px;
            width: 26px; height: 26px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
            flex-shrink: 0;
            padding: 0;
        }
        .jcb-dismiss:hover { background: rgba(232,160,48,0.22); }

        /* ══ DEBUG SIM TOOL (standard page) ══ */
        #sim-panel {
            position: fixed;
            bottom: 20px; right: 20px;
            width: 320px;
            background: var(--bg-sim);
            border: 1px solid var(--accent-brt);
            border-radius: 14px;
            z-index: 99999;
            font-family: inherit;
            font-size: 12px;
            color: var(--cream);
            box-shadow: 0 8px 40px rgba(0,0,0,0.7);
            user-select: none;
        }
        #sim-panel.sim-dragging { box-shadow: 0 16px 60px rgba(0,0,0,0.9), 0 0 0 2px var(--gold); }
        #sim-panel.sim-collapsed { width: auto; min-width: 180px; border-radius: 30px; }
        #sim-panel.sim-collapsed #sim-body { display: none; }
        #sim-panel.sim-collapsed #sim-header { border-radius: 30px; border-bottom: none; padding: 8px 14px; }
        #sim-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; border-bottom: 1px solid var(--accent-shadow);
            cursor: grab; border-radius: 14px 14px 0 0;
            background: var(--accent-faint);
        }
        #sim-header:active { cursor: grabbing; }
        .sim-header-left { display: flex; align-items: center; gap: 8px; }
        .sim-title { font-size: 10px; color: var(--gold); letter-spacing: 2px; text-transform: uppercase; font-weight: 700; white-space: nowrap; }
        .sim-badge { border-radius: 4px; padding: 1px 7px; font-size: 9px; font-weight: 800; letter-spacing: 1px; white-space: nowrap; background: rgba(100,100,100,0.8); color: #fff; }
        #sim-panel.sim-running .sim-badge { background: var(--green); }
        #sim-collapse-btn { font-size: 12px; color: var(--gold-dim); cursor: pointer; padding: 2px 6px; margin-left: 4px; border-radius: 4px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); line-height: 1; flex-shrink: 0; }
        #sim-collapse-btn:hover { background: var(--accent-act); color: var(--gold); }
        #sim-body { padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; }
        .sim-row { display: flex; flex-direction: column; gap: 3px; }
        .sim-label { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: var(--gold-dim); }
        .sim-controls { display: flex; gap: 6px; align-items: center; }
        .sim-input { background: rgba(255,255,255,0.06); border: 1px solid var(--accent-mod); border-radius: 6px; color: var(--cream); padding: 5px 8px; font-size: 12px; outline: none; width: 100%; }
        .sim-input:focus { border-color: var(--gold); }
        .sim-speed-btn { background: rgba(255,255,255,0.05); border: 1px solid var(--accent-shadow); border-radius: 6px; color: var(--cream-dim); padding: 4px 8px; font-size: 11px; cursor: pointer; transition: all 0.15s; }
        .sim-speed-btn:hover { border-color: var(--gold); color: var(--gold); }
        .sim-speed-btn.active { background: var(--accent-act); border-color: var(--gold); color: var(--gold); font-weight: 800; }
        .sim-divider { height: 1px; background: var(--accent-subtle); }
        .sim-scenario-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .sim-scenario-btn { background: rgba(255,255,255,0.04); border: 1px solid var(--accent-subtle); border-radius: 6px; color: var(--cream-dim); padding: 5px 6px; font-size: 10px; cursor: pointer; text-align: center; transition: all 0.15s; line-height: 1.3; }
        .sim-scenario-btn:hover { border-color: var(--gold-dim); color: var(--cream); background: var(--accent-faint); }
        .sim-action-row { display: flex; gap: 6px; }
        .sim-btn { flex: 1; padding: 7px 8px; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; border: none; transition: all 0.15s; }
        .sim-btn-primary { background: var(--gold); color: var(--green-dark); }
        .sim-btn-primary:hover { background: var(--gold-light); }
        .sim-btn-secondary { background: rgba(255,255,255,0.07); color: var(--cream-dim); border: 1px solid rgba(255,255,255,0.12); }
        .sim-btn-secondary:hover { background: rgba(255,255,255,0.12); color: var(--cream); }
        .sim-btn-danger { background: rgba(192,57,43,0.3); color: #E07070; border: 1px solid rgba(192,57,43,0.4); }
        .sim-btn-danger:hover { background: rgba(192,57,43,0.5); }
        #sim-clock-display { font-size: 20px; font-weight: 800; color: var(--gold-light); font-variant-numeric: tabular-nums; letter-spacing: 3px; text-align: center; padding: 5px 0; border: 1px solid var(--accent-shadow); border-radius: 8px; background: var(--accent-act2); }
        #sim-status { font-size: 10px; color: var(--cream-dim); text-align: center; min-height: 14px; }
    </style>
    <?php echo $_nmc_theme_css; ?>
</head>
<body>

<?php if ($sim_active): ?>
    <!-- ══ DEBUG SIM TOOL (standard page, superadmin only) ══ -->
    <div id="sim-panel" class="sim-collapsed">
        <div id="sim-header">
            <div class="sim-header-left">
                <span style="font-size:15px;">🧪</span>
                <span class="sim-title">Debug Sim Tool</span>
                <span class="sim-badge" id="sim-badge">PAUSED</span>
            </div>
            <button id="sim-collapse-btn" onclick="simToggleCollapse(event)" title="Expand/Collapse">▲</button>
        </div>
        <div id="sim-body">
            <div id="sim-clock-display">--:--:--</div>
            <div id="sim-status">Loading…</div>
            <div class="sim-divider"></div>
            <div class="sim-row">
                <span class="sim-label">📅 Simulate Date</span>
                <input type="date" class="sim-input" id="sim-date-input"
                       value="<?= htmlspecialchars(date('Y-m-d', $sim_ts), ENT_QUOTES) ?>">
            </div>
            <div class="sim-row">
                <span class="sim-label">🕐 Simulate Time</span>
                <div class="sim-controls">
                    <input type="range" id="sim-slider" min="0" max="86399" step="60"
                           value="<?= (function($t){ $p=explode(':',$t); return (int)$p[0]*3600+(int)$p[1]*60+(isset($p[2])?(int)$p[2]:0); })($sim_time) ?>"
                           style="flex:1;accent-color:#C9A84C;"
                           oninput="simSliderMove(this.value)">
                    <input type="text" class="sim-input" id="sim-time-input"
                           value="<?= substr($sim_time,0,5) ?>"
                           style="width:64px;text-align:center;"
                           oninput="simTimeTextChange(this.value)">
                </div>
            </div>
            <div class="sim-row">
                <span class="sim-label">⚡ Speed</span>
                <div class="sim-controls" id="sim-speed-btns">
                    <button class="sim-speed-btn active" data-speed="1"   onclick="simSetSpeed(1)">1×</button>
                    <button class="sim-speed-btn"        data-speed="5"   onclick="simSetSpeed(5)">5×</button>
                    <button class="sim-speed-btn"        data-speed="30"  onclick="simSetSpeed(30)">30×</button>
                    <button class="sim-speed-btn"        data-speed="60"  onclick="simSetSpeed(60)">60×</button>
                    <button class="sim-speed-btn"        data-speed="300" onclick="simSetSpeed(300)">300×</button>
                </div>
            </div>
            <div class="sim-divider"></div>
            <div class="sim-row">
                <span class="sim-label">🎯 Quick Scenarios</span>
                <div class="sim-scenario-grid">
                    <button class="sim-scenario-btn" onclick="simScenario('fajr_window')">🌅 Fajr Opens</button>
                    <button class="sim-scenario-btn" onclick="simScenario('zawaal')">🔴 Zawaal</button>
                    <button class="sim-scenario-btn" onclick="simScenario('zuhr_window')">☀️ Zuhr Opens</button>
                    <button class="sim-scenario-btn" onclick="simScenario('asr_window')">🌤️ Asr Opens</button>
                    <button class="sim-scenario-btn" onclick="simScenario('maghrib_window')">🌇 Maghrib Opens</button>
                    <button class="sim-scenario-btn" onclick="simScenario('esha_window')">🌃 Esha Opens</button>
                    <button class="sim-scenario-btn" onclick="simScenario('thu_maghrib')">🕌 Thu→Jummah</button>
                    <button class="sim-scenario-btn" onclick="simScenario('fri_zuhr')">🕌 Fri Zuhr Now</button>
                </div>
            </div>
            <div class="sim-divider"></div>
            <div class="sim-action-row">
                <button class="sim-btn sim-btn-primary"   onclick="simApply()">▶ Apply</button>
                <button class="sim-btn sim-btn-secondary" onclick="simPause()" id="sim-pause-btn">⏸ Pause</button>
                <button class="sim-btn sim-btn-danger"    onclick="simExit()">✕ Exit</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="page-wrap">

    <div class="hero">
        <span class="hero-mosque">🕌</span>
        <h1 class="hero-title"><?= htmlspecialchars($site_name, ENT_QUOTES) ?></h1>
        <p class="hero-sub">Salaah Times</p>
        <?php if ($is_ramadan_active): ?>
            <div style="display:inline-block; margin-top: 10px; background: rgba(201,168,76,0.12); border: 1px solid rgba(201,168,76,0.4); border-radius: 20px; padding: 4px 14px; font-size: 10px; font-weight: 800; color: var(--gold-light); letter-spacing: 2px; text-transform: uppercase;">
                ☪️ Ramadan Schedule Active
            </div>
        <?php endif; ?>
        <div class="gold-rule"></div>
    </div>

    <div class="top-bar">
        <div class="top-bar-date">
            <strong>Today</strong>
            <div> <?= $full_date ?> <span style="color:rgba(255,255,255,0.3);margin:0 6px;">|</span><span style="color:var(--gold,#c9a84c);font-weight:600;"><?= htmlspecialchars($hijri_date_str, ENT_QUOTES) ?></span>
            </div>
        </div>
        <div class="top-bar-clock">
            <strong>Current Time</strong>
            <div class="live-clock" id="liveClock">--:--:--</div>
        </div>
    </div>

    <?php if ($times): ?>

        <?php if ($has_changes): ?>
            <div class="jamaat-change-banner" id="jamaat-change-banner">
                <?= nmcBuildChangeNoticeHTML($jamaat_changes, 'website') ?>
            </div>
        <?php endif; ?>

        <div class="countdown-banner">
            <div class="cd-eyebrow">Next Prayer</div>
            <div class="cd-context-label">
                <span class="cd-prayer-name" id="cdName">–</span>
                <span class="cd-verb" id="cdVerb">–</span>
            </div>
            <div class="cd-digits" id="cdDigits">--:--:--</div>
            <div class="cd-units">
                <span class="cd-unit-label">hours</span>
                <span class="cd-unit-label">minutes</span>
                <span class="cd-unit-label">seconds</span>
            </div>
            <div class="cd-since" id="cdSince"></div>
        </div>

        <div class="event-banner">
            <div class="event-left">
                <span class="event-icon" id="evIcon">–</span>
                <div>
                    <div class="event-eyebrow">Next Event</div>
                    <div class="event-name" id="evName">–</div>
                    <div class="event-verb" id="evVerb">–</div>
                </div>
            </div>
            <div class="event-right">
                <div class="event-digits" id="evDigits">--:--:--</div>
                <div class="event-units">
                    <span class="event-unit">hrs</span>
                    <span class="event-unit">min</span>
                    <span class="event-unit">sec</span>
                </div>
            </div>
        </div>

        <div class="section-head">Five Daily Prayers</div>
        <div class="prayer-grid">

            <div class="prayer-card" id="card-Fajr">
                <span class="status-pill" id="pill-Fajr"></span>
                <div class="card-top">
                    <span class="card-icon">🌅</span>
                    <div><div class="card-name">Fajr</div><div class="card-desc">Dawn Prayer</div></div>
                </div>
                <div class="time-label-row">
                    <span class="time-label">Jamaat Time</span>
                    <span class="time-label">Earliest Time</span>
                </div>
                <div class="time-row">
                    <div class="time-main"><?= $times['fajr'] ?></div>
                    <div class="earliest-block">
                        <span class="earliest-time"><?= $times['fajr_e'] ?></span>
                    </div>
                </div>
            </div>

            <div class="prayer-card" id="card-Zuhr">
                <span class="status-pill" id="pill-Zuhr"></span>
                <div class="card-top">
                    <span class="card-icon" id="icon-Zuhr">☀️</span>
                    <div>
                        <div class="card-name" id="name-Zuhr">Zuhr</div>
                        <div class="card-desc" id="desc-Zuhr">Midday Prayer</div>
                    </div>
                </div>
                <!-- Normal (non-Friday) layout -->
                <div id="zuhr-normal-layout">
                    <div class="time-label-row">
                        <span class="time-label">Jamaat Time</span>
                        <span class="time-label">Earliest Time</span>
                    </div>
                    <div class="time-row">
                        <div class="time-main" id="jtime-Zuhr"><?= $times['zuhr'] ?></div>
                        <div class="earliest-block">
                            <span class="earliest-time"><?= $times['zuhr_e'] ?></span>
                        </div>
                    </div>
                </div>
                <!-- Jummah (Friday / Thursday after Maghrib) layout — hidden by default -->
                <div id="zuhr-jummah-layout" style="display:none;">
                    <div class="time-label-row">
                        <span class="time-label">Jamaat Time</span>
                        <span class="time-label">Earliest Time</span>
                    </div>
                    <div class="time-row">
                        <div class="time-main" id="jtime-Zuhr-jum"><?= $jummah['jamaat'] ?></div>
                        <div class="earliest-dual">
                            <!-- Top pill: time only, no label — mirrors Hanafi pill in Asr -->
                            <div class="earliest-block time-only">
                                <span class="earliest-time"><?= $times['zuhr_e'] ?></span>
                            </div>
                            <!-- Bottom pill: AZAAN label + time — mirrors Shafi pill in Asr -->
                            <div class="earliest-block">
                                <span class="madhab-tag">Azaan</span>
                                <span class="earliest-time" id="jum-azaan-time"><?= $jummah['azaan'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="prayer-card" id="card-Asr">
                <span class="status-pill" id="pill-Asr"></span>
                <div class="card-top">
                    <span class="card-icon">🌤️</span>
                    <div><div class="card-name">Asr</div><div class="card-desc">Afternoon Prayer</div></div>
                </div>
                <div class="time-label-row">
                    <span class="time-label">Jamaat Time</span>
                    <span class="time-label">Earliest Time</span>
                </div>
                <div class="time-row">
                    <div class="time-main"><?= $times['asr'] ?></div>
                    <div class="earliest-dual">
                        <div class="earliest-block">
                            <span class="madhab-tag">Hanafi</span>
                            <span class="earliest-time"><?= $times['asr_eh'] ?></span>
                        </div>
                        <div class="earliest-block">
                            <span class="madhab-tag">Shafi</span>
                            <span class="earliest-time"><?= $times['asr_es'] ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="prayer-card" id="card-Maghrib">
                <span class="status-pill" id="pill-Maghrib"></span>
                <div class="card-top">
                    <span class="card-icon">🌇</span>
                    <div><div class="card-name">Maghrib</div><div class="card-desc">Sunset Prayer</div></div>
                </div>
                <div class="time-label">Jamaat Time</div>
                <div class="time-row">
                    <div class="time-main"><?= $times['maghrib'] ?></div>
                </div>
            </div>

            <div class="prayer-card" id="card-Esha">
                <span class="status-pill" id="pill-Esha"></span>
                <div class="card-top">
                    <span class="card-icon">🌃</span>
                    <div><div class="card-name">Esha</div><div class="card-desc">Night Prayer</div></div>
                </div>
                <div class="time-label-row">
                    <span class="time-label">Jamaat Time</span>
                    <span class="time-label">Earliest Time</span>
                </div>
                <div class="time-row">
                    <div class="time-main"><?= $times['esha'] ?></div>
                    <div class="earliest-block">
                        <span class="earliest-time"><?= $times['esha_e'] ?></span>
                    </div>
                </div>
            </div>

        </div>

        <div class="section-head">Time Markers</div>
        <div class="marker-grid">

            <div class="marker-card">
                <span class="marker-icon">🌙</span>
                <div>
                    <div class="marker-name">Sehri Ends</div>
                    <div class="marker-time"><?= $times['sehri'] ?></div>
                    <div class="marker-note">Last time to eat before fast</div>
                </div>
            </div>

            <div class="marker-card">
                <span class="marker-icon">🌄</span>
                <div>
                    <div class="marker-name">Sunrise</div>
                    <div class="marker-time"><?= $times['sunrise'] ?></div>
                    <div class="marker-note">Salah not permissible after this</div>
                </div>
            </div>

            <div class="marker-card">
                <span class="marker-icon">🕛</span>
                <div>
                    <div class="marker-name">Zawaal</div>
                    <div class="marker-time"><?= $times['zawaal'] ?></div>
                    <div class="marker-note">Solar noon — prayer is Makrooh</div>
                </div>
            </div>

            <div class="marker-card">
                <span class="marker-icon">🌆</span>
                <div>
                    <div class="marker-name">Sunset / Iftaar</div>
                    <div class="marker-time"><?= $times['sunset'] ?></div>
                    <div class="marker-note">Time of Iftaar</div>
                </div>
            </div>

        </div>

    <?php else: ?>
        <p style="text-align:center;color:var(--red-soft);padding:48px 0;">No Salaah times found for today. Please contact the site administrator.</p>
    <?php endif; ?>

    <?php if (!$remove_copyright): ?>
        <div style="text-align:center;font-size:11px;color:var(--gold-dim);margin-top:40px;opacity:0.6;line-height:1.6;">
            Musjid Display System (MDS) &copy; Copyright 2026 - Muhammed Cotwal<br>
            All Rights Reserved &middot; <a href="https://github.com/muhammedc/mds" target="_blank" style="color:inherit;text-decoration:none;">github.com/muhammedc/mds</a>
        </div>
    <?php endif; ?>

</div><a href="hadith.php" class="hadith-fab">
    <span class="fab-icon">📖</span> Daily Hadith
</a>

<script>

    /* ── Helpers ── */
    function toSec(t) { const [h,m] = t.split(':').map(Number); return h*3600 + m*60; }
    function pad(v)   { return String(v).padStart(2,'0'); }
    function fmtCountdown(s) { s=Math.floor(Math.abs(s)); return `${pad(Math.floor(s/3600))}:${pad(Math.floor((s%3600)/60))}:${pad(s%60)}`; }
    function fmtSince(s) {
        const h = Math.floor(s/3600), m = Math.floor((s%3600)/60);
        return h > 0 ? `${h}h ${m}m ago` : `${m} min ago`;
    }

    /* ── Data from PHP ── */
    const prayers = <?= $prayer_js ?>;  // 5 main prayers only
    const events  = <?= $events_js ?>;  // Sehri, Sunrise, Zawaal, Sunset
    const W       = <?= $windows_js ?>; // Window boundaries (madhab-aware)
    const JUM     = <?= $jummah_js ?>; // Jummah times
    const SIM     = <?= $sim_js ?>;    // Simulator config

    /* ══ SIMULATOR ENGINE (shared with musjid mode) ══ */
    const _SIM = {
        active:    SIM.active,
        startSec:  SIM.active ? (function(){ const p=SIM.time.split(':').map(Number); return p[0]*3600+p[1]*60+(p[2]||0); })() : 0,
        loadedAt:  Date.now(),
        speed:     SIM.speed || 1,
        paused:    false,
        pausedAt:  0,
        dow:       SIM.dow,
    };

    /* nowSec() — real or simulated whole seconds since midnight */
    function nowSec() {
        if (!_SIM.active) {
            const n = new Date();
            return n.getHours()*3600 + n.getMinutes()*60 + n.getSeconds();
        }
        if (_SIM.paused) return Math.floor(_SIM.pausedAt) % 86400;
        const elapsed = (Date.now() - _SIM.loadedAt) / 1000 * _SIM.speed;
        return Math.floor(_SIM.startSec + elapsed) % 86400;
    }

    /* simDow() — real or overridden day-of-week */
    function simDow() {
        if (_SIM.active && _SIM.dow >= 0) return _SIM.dow;
        return new Date().getDay();
    }

    /*
     * Prayer validity windows — OPEN = earliest valid time, CLOSE = window end
     * The boundary between Zuhr and Asr uses the madhab setting (W.asr_boundary):
     *   Hanafi → e_asr_hanafi (later)   Shafi → e_asr_shafi (earlier)
     *
     * Fajr    : opens e_fajr        → closes sunrise
     * Zuhr    : opens e_zuhr        → closes asr_boundary
     * Asr     : opens asr_boundary  → closes sunset
     * Maghrib : opens maghrib       → closes e_esha  (no separate earliest for Maghrib)
     * Esha    : opens e_esha        → closes e_fajr next day
     */
    const prayerWindows = {
        Fajr:    { open: toSec(W.fajr_e),       close: toSec(W.sunrise)        },
        Zuhr:    { open: toSec(W.zuhr_e),       close: toSec(W.asr_boundary)   },
        Asr:     { open: toSec(W.asr_boundary), close: toSec(W.sunset)         },
        Maghrib: { open: toSec(W.maghrib),      close: toSec(W.esha_e)         },
        Esha:    { open: toSec(W.esha_e),       close: toSec(W.fajr_e) + 86400 },
    };

    /* ── Live clock ── */
    function updateClock() {
        if (_SIM.active) {
            const s = Math.floor(nowSec());
            document.getElementById('liveClock').textContent =
                `${pad(Math.floor(s/3600))}:${pad(Math.floor((s%3600)/60))}:${pad(s%60)}`;
        } else {
            const n = new Date();
            document.getElementById('liveClock').textContent =
                `${pad(n.getHours())}:${pad(n.getMinutes())}:${pad(n.getSeconds())}`;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    /* ── Jamaat change banner dismiss (session-scoped) ── */
    (function(){
        var banner = document.getElementById('jamaat-change-banner');
        if (!banner) return;
        try {
            if (sessionStorage.getItem('nmc_jc_dismissed') === '1') {
                banner.style.display = 'none';
            }
        } catch(e) {}
    })();
    function nmcDismissJamaatBanner() {
        var b = document.getElementById('jamaat-change-banner');
        if (b) {
            b.style.transition = 'opacity 0.3s ease';
            b.style.opacity = '0';
            setTimeout(function(){ b.style.display = 'none'; }, 320);
        }
        try { sessionStorage.setItem('nmc_jc_dismissed', '1'); } catch(e) {}
    }

    /* ── Jummah helpers ── */
    function getJummahMode(now) {
        const dow = simDow(); // sim-aware
        const asrBoundarySec = prayerWindows.Asr.open;
        if (dow === 5 && now < asrBoundarySec) return 'friday';
        if (dow === 4 && now >= toSec(W.maghrib)) return 'tomorrow';
        return 'none';
    }

    function applyJummahCard(jMode) {
        const nameEl         = document.getElementById('name-Zuhr');
        const iconEl         = document.getElementById('icon-Zuhr');
        const descEl         = document.getElementById('desc-Zuhr');
        const normalLayout   = document.getElementById('zuhr-normal-layout');
        const jummahLayout   = document.getElementById('zuhr-jummah-layout');
        const jtimeEl        = document.getElementById('jtime-Zuhr');       // normal layout time
        const jtimeJumEl     = document.getElementById('jtime-Zuhr-jum');   // jummah layout time

        if (jMode !== 'none') {
            if (nameEl)       nameEl.textContent = 'Jummah';
            if (iconEl)       iconEl.textContent = '🕌';
            if (descEl)       descEl.textContent = 'Friday Prayer';
            if (normalLayout) normalLayout.style.display = 'none';
            if (jummahLayout) jummahLayout.style.display = '';
            // keep normal jtime in sync in case mode reverts mid-session
            if (jtimeEl)      jtimeEl.textContent = JUM.jamaat;
        } else {
            if (nameEl)       nameEl.textContent = 'Zuhr';
            if (iconEl)       iconEl.textContent = '☀️';
            if (descEl)       descEl.textContent = 'Midday Prayer';
            if (normalLayout) normalLayout.style.display = '';
            if (jummahLayout) jummahLayout.style.display = 'none';
            if (jtimeEl)      jtimeEl.textContent = W.zuhr;
        }
    }

    /* ── Main tick ── */
    function tick() {
        const now = nowSec();

        /* ── Jummah mode ── */
        const jMode = getJummahMode(now);
        applyJummahCard(jMode);

        /* ── PRAYER BANNER: next of the 5 main prayers ── */
        let nextPrayer = null, minPrayerDiff = Infinity;
        prayers.forEach(p => {
            let pTime = p.name === 'Zuhr' && jMode !== 'none' ? JUM.jamaat : p.time;
            let d = toSec(pTime) - now;
            if (d <= 0) d += 86400;
            if (d < minPrayerDiff) {
                minPrayerDiff = d;
                nextPrayer = {...p, displayName: p.name === 'Zuhr' && jMode !== 'none' ? 'Jummah' : p.name};
            }
        });

        if (nextPrayer) {
            document.getElementById('cdName').textContent   = `${nextPrayer.icon} ${nextPrayer.displayName || nextPrayer.name}`;
            document.getElementById('cdVerb').textContent   = 'begins in';
            document.getElementById('cdDigits').textContent = fmtCountdown(minPrayerDiff);
        }

        /* "Since" — last prayer that passed */
        let lastPrayer = null, minPast = Infinity;
        prayers.forEach(p => {
            let past = now - toSec(p.time);
            if (past < 0) past += 86400;
            if (past < minPast) { minPast = past; lastPrayer = p; }
        });
        if (lastPrayer) {
            const lName = lastPrayer.name === 'Zuhr' && jMode !== 'none' ? 'Jummah' : lastPrayer.name;
            document.getElementById('cdSince').textContent =
                `${lastPrayer.icon} ${lName} was ${fmtSince(minPast)}`;
        }

        /* ── EVENT BANNER: next of the time markers ── */
        let nextEvent = null, minEventDiff = Infinity;
        events.forEach(e => {
            let d = toSec(e.time) - now;
            if (d <= 0) d += 86400;
            if (d < minEventDiff) { minEventDiff = d; nextEvent = e; }
        });

        if (nextEvent) {
            document.getElementById('evIcon').textContent   = nextEvent.icon;
            document.getElementById('evName').textContent   = nextEvent.name;
            document.getElementById('evVerb').textContent   = nextEvent.verb;
            document.getElementById('evDigits').textContent = fmtCountdown(minEventDiff);
        }

        /* ── Card status pills ── */
        const mainNames = ['Fajr','Zuhr','Asr','Maghrib','Esha'];

        function isOpen(name) {
            const w = prayerWindows[name];
            if (name === 'Esha') return now >= w.open || now < toSec(W.fajr_e);
            return now >= w.open && now < w.close;
        }

        function isMissed(name) {
            if (name === 'Esha') return false;
            if (isOpen(name))    return false;
            return now >= prayerWindows[name].close;
        }

        let nextName = null, nextDiff = Infinity;
        mainNames.forEach(name => {
            const w = prayerWindows[name];
            let d = w.open - now;
            if (d <= 0) d += 86400;
            if (!isOpen(name) && !isMissed(name) && d < nextDiff) {
                nextDiff = d; nextName = name;
            }
        });

        mainNames.forEach(name => {
            const card = document.getElementById('card-' + name);
            const pill = document.getElementById('pill-' + name);
            if (!card || !pill) return;

            card.classList.remove('active','missed');
            pill.className   = 'status-pill';
            pill.textContent = '';

            if (name === 'Zuhr' && jMode === 'tomorrow') {
                pill.classList.add('pill-tomorrow');
                pill.textContent = 'Tomorrow';
            } else if (name === 'Zuhr' && jMode === 'friday' && !isOpen('Zuhr')) {
                pill.classList.add('pill-upcoming');
                pill.textContent = 'Upcoming';
            } else if (isOpen(name)) {
                card.classList.add('active');
                pill.classList.add('pill-now');
                pill.textContent = 'Now';
            } else if (isMissed(name)) {
                card.classList.add('missed');
                pill.classList.add('pill-missed');
                pill.textContent = 'Qadha';
            } else if (name === nextName) {
                pill.classList.add('pill-next');
                pill.textContent = 'Next';
            } else {
                pill.classList.add('pill-upcoming');
                pill.textContent = 'Upcoming';
            }
        });
    }

    setInterval(tick, 1000);
    tick();

    /* Auto-refresh at midnight (disabled in simulator) */
    if (!_SIM.active) {
        (function() {
            const n  = new Date();
            const ms = new Date(n.getFullYear(), n.getMonth(), n.getDate()+1, 0, 0, 5) - n;
            setTimeout(() => location.reload(), ms);
        })();
    }

    <?php if ($sim_active): ?>
    /* ══ DEBUG SIM TOOL — standard page panel logic ══ */
    (function(){
        const panel  = document.getElementById('sim-panel');
        const header = document.getElementById('sim-header');
        if (!panel || !header) return;
        let dragging = false, ox = 0, oy = 0;
        header.addEventListener('mousedown', function(e) {
            if (e.target.closest('#sim-collapse-btn')) return;
            dragging = true; ox = e.clientX - panel.offsetLeft; oy = e.clientY - panel.offsetTop;
            panel.classList.add('sim-dragging');
            panel.style.right = 'auto'; panel.style.bottom = 'auto';
            panel.style.left = panel.offsetLeft + 'px'; panel.style.top = panel.offsetTop + 'px';
            e.preventDefault();
        });
        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            let nx = Math.max(0, Math.min(e.clientX - ox, window.innerWidth  - panel.offsetWidth));
            let ny = Math.max(0, Math.min(e.clientY - oy, window.innerHeight - panel.offsetHeight));
            panel.style.left = nx + 'px'; panel.style.top = ny + 'px';
        });
        document.addEventListener('mouseup', function() { if (dragging) { dragging = false; panel.classList.remove('sim-dragging'); } });
        /* touch */
        header.addEventListener('touchstart', function(e) {
            if (e.target.closest('#sim-collapse-btn')) return;
            const t = e.touches[0]; dragging = true; ox = t.clientX - panel.offsetLeft; oy = t.clientY - panel.offsetTop;
            panel.style.right = 'auto'; panel.style.bottom = 'auto';
            panel.style.left = panel.offsetLeft + 'px'; panel.style.top = panel.offsetTop + 'px';
        }, { passive: true });
        document.addEventListener('touchmove', function(e) {
            if (!dragging) return; const t = e.touches[0];
            panel.style.left = Math.max(0, Math.min(t.clientX - ox, window.innerWidth  - panel.offsetWidth))  + 'px';
            panel.style.top  = Math.max(0, Math.min(t.clientY - oy, window.innerHeight - panel.offsetHeight)) + 'px';
        }, { passive: true });
        document.addEventListener('touchend', function() { dragging = false; });
    })();

    function simToggleCollapse(e) {
        if (e) e.stopPropagation();
        const panel = document.getElementById('sim-panel');
        const btn   = document.getElementById('sim-collapse-btn');
        const collapsed = panel.classList.toggle('sim-collapsed');
        btn.textContent = collapsed ? '▲' : '▼';
    }
    function simSetSpeed(s) {
        _SIM.startSec = nowSec(); _SIM.loadedAt = Date.now(); _SIM.speed = s; _SIM.paused = false;
        document.querySelectorAll('.sim-speed-btn').forEach(b => b.classList.toggle('active', parseInt(b.dataset.speed) === s));
        simUpdateBadge();
    }
    function simPause() {
        const btn = document.getElementById('sim-pause-btn');
        if (!_SIM.paused) { _SIM.pausedAt = nowSec(); _SIM.paused = true; if (btn) btn.textContent = '▶ Resume'; }
        else { _SIM.startSec = _SIM.pausedAt; _SIM.loadedAt = Date.now(); _SIM.paused = false; if (btn) btn.textContent = '⏸ Pause'; }
        simUpdateBadge();
    }
    function simSliderMove(val) {
        const sec = parseInt(val);
        document.getElementById('sim-time-input').value = pad(Math.floor(sec/3600)) + ':' + pad(Math.floor((sec%3600)/60));
    }
    function simTimeTextChange(val) {
        const m = val.match(/^(\d{1,2}):(\d{2})$/);
        if (m) document.getElementById('sim-slider').value = parseInt(m[1])*3600 + parseInt(m[2])*60;
    }
    function simApply() {
        const d = document.getElementById('sim-date-input').value;
        const t = document.getElementById('sim-time-input').value || '00:00';
        location.href = `?sim=1&sim_date=${encodeURIComponent(d)}&sim_time=${encodeURIComponent(t)}:00`;
    }
    function simScenario(key) {
        const dateInput = document.getElementById('sim-date-input');
        const timeInput = document.getElementById('sim-time-input');
        function addMins(t, m) { const p=t.split(':').map(Number); let x=p[0]*60+p[1]+m; if(x<0)x+=1440; if(x>=1440)x-=1440; return pad(Math.floor(x/60))+':'+pad(x%60); }
        function wt(k) { return W[k]?W[k].substring(0,5):'00:00'; }
        const d = new Date(dateInput.value + 'T12:00:00');
        function dateForDow(dow) { let diff=dow-d.getDay(); if(diff<0)diff+=7; const x=new Date(d); x.setDate(x.getDate()+diff); return x.toISOString().substring(0,10); }
        switch(key) {
            case 'fajr_window':    timeInput.value = addMins(wt('fajr_e'),      -2); break;
            case 'zawaal':         timeInput.value = addMins(wt('zawaal'),       -1); break;
            case 'zuhr_window':    timeInput.value = addMins(wt('zuhr_e'),       -2); break;
            case 'asr_window':     timeInput.value = addMins(wt('asr_boundary'), -2); break;
            case 'maghrib_window': timeInput.value = addMins(wt('maghrib'),      -2); break;
            case 'esha_window':    timeInput.value = addMins(wt('esha_e'),       -2); break;
            case 'thu_maghrib': dateInput.value = dateForDow(4); timeInput.value = addMins(wt('maghrib'), -2); break;
            case 'fri_zuhr':    dateInput.value = dateForDow(5); timeInput.value = addMins(wt('zuhr_e'),  -2); break;
        }
        const m = timeInput.value.match(/^(\d{1,2}):(\d{2})$/);
        if (m) document.getElementById('sim-slider').value = parseInt(m[1])*3600 + parseInt(m[2])*60;
    }
    function simExit() { window.location.href = window.location.pathname; }
    function simUpdateBadge() {
        const badge = document.getElementById('sim-badge');
        const panel = document.getElementById('sim-panel');
        const btn   = document.getElementById('sim-pause-btn');
        if (_SIM.paused) {
            if (badge) { badge.textContent = 'PAUSED'; badge.style.background = 'rgba(100,100,100,0.8)'; }
            if (btn)   btn.textContent = '▶ Resume';
            if (panel) panel.classList.remove('sim-running');
        } else {
            if (badge) { badge.textContent = `${_SIM.speed}× SIM`; badge.style.background = getComputedStyle(document.documentElement).getPropertyValue('--green').trim(); }
            if (btn)   btn.textContent = '⏸ Pause';
            if (panel) panel.classList.add('sim-running');
        }
    }
    function simUpdatePanel() {
        const s = Math.floor(nowSec());
        const clk = document.getElementById('sim-clock-display');
        if (clk) clk.textContent = pad(Math.floor(s/3600))+':'+pad(Math.floor((s%3600)/60))+':'+pad(s%60);
        const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        const dateStr = document.getElementById('sim-date-input')?.value || SIM.date;
        let status = `${days[simDow()]} ${dateStr}`;
        status += _SIM.paused ? ' · PAUSED' : ` · ${_SIM.speed}× speed`;
        const st = document.getElementById('sim-status');
        if (st) st.textContent = status;
        const slider = document.getElementById('sim-slider');
        if (slider && !_SIM.paused && document.activeElement !== slider) slider.value = s;
    }
    setInterval(simUpdatePanel, 250);
    simUpdatePanel();
    simUpdateBadge();
    document.addEventListener('DOMContentLoaded', function(){
        const panel = document.getElementById('sim-panel');
        const btn   = document.getElementById('sim-collapse-btn');
        if (panel) panel.classList.remove('sim-collapsed');
        if (btn)   btn.textContent = '▼';
    });
    <?php endif; // sim_active standard page ?>
</script>

</body>
</html>