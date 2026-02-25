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
$full_date  = date("l jS \of F Y");
$day_number = date('z') + 1;

$link        = null;
$hadith_ref  = null;
$hadith_text = null;

/* ── Site name from DB ── */
$site_name = 'Musjid Display System';
$site_url  = 'https://github.com/muhammedc/mds';
$active_theme = 'green';
$custom_theme_json = '{}';
try {
    $link = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
    $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) { $link = null; }

if ($link) {
    $sn = @$link->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('site_name','active_theme','custom_theme_json','site_url')");
    if ($sn) while ($sn_row = $sn->fetch(PDO::FETCH_ASSOC)) {
        if ($sn_row['setting_key'] === 'site_name')         $site_name = $sn_row['setting_value'];
        if ($sn_row['setting_key'] === 'site_url')          $site_url     = $sn_row['setting_value'];
        if ($sn_row['setting_key'] === 'active_theme')      $active_theme = $sn_row['setting_value'];
        if ($sn_row['setting_key'] === 'custom_theme_json') $custom_theme_json = $sn_row['setting_value'];
    }
}

if ($link) {
    $query = "SELECT * FROM hadith_db WHERE uid='$day_number'";
    if ($result = @$link->query($query)) {
        if ($row = $result->fetch(PDO::FETCH_NUM)) {
            $hadith_ref  = $row[1];
            $hadith_text = isset($row[2]) ? $row[2] : null;
        }
    }
    $link = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Musjid Display System – Daily Hadith">
    <meta name="theme-color" content="#07170A">
    <title>MDS – Daily Hadith</title>

    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Amiri:ital,wght@0,400;0,700;1,400&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
            --card-bg:     rgba(16,46,22,0.80);
            --card-border: rgba(201,168,76,0.22);
            --shadow:      0 8px 32px rgba(0,0,0,0.45);
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
                    radial-gradient(ellipse 90% 55% at 50% -5%, rgba(201,168,76,0.15) 0%, transparent 65%),
                    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Cg fill='none' stroke='rgba(201,168,76,0.055)' stroke-width='1'%3E%3Cpath d='M40 4 L76 40 L40 76 L4 40Z'/%3E%3Cpath d='M40 18 L62 40 L40 62 L18 40Z'/%3E%3Ccircle cx='40' cy='40' r='10'/%3E%3C/g%3E%3C/svg%3E");
            background-size: auto, 80px 80px;
            pointer-events: none;
            z-index: 0;
        }

        .page-wrap {
            position: relative;
            z-index: 1;
            max-width: 780px;
            margin: 0 auto;
            padding: 0 16px 100px;
        }

        /* ── Hero ── */
        .hero {
            text-align: center;
            padding: 44px 16px 18px;
        }
        .hero-icon {
            font-size: 52px;
            display: block;
            margin-bottom: 10px;
            animation: glow-pulse 3.5s ease-in-out infinite;
        }
        @keyframes glow-pulse {
            0%,100% { filter: drop-shadow(0 0 10px rgba(201,168,76,0.4)); }
            50%      { filter: drop-shadow(0 0 26px rgba(201,168,76,0.85)); }
        }
        .hero-title {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(15px, 3.8vw, 26px);
            color: var(--gold);
            letter-spacing: 2px;
            text-shadow: 0 2px 14px rgba(201,168,76,0.4);
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

        /* ── Day badge ── */
        .day-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--gold-dim), var(--gold));
            color: var(--green-dark);
            font-weight: 800;
            font-size: 12px;
            padding: 6px 20px;
            border-radius: 30px;
            letter-spacing: 1px;
            margin: 18px 0 28px;
        }

        /* ── Hadith Card ── */
        .hadith-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            overflow: hidden;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
            animation: fadein 0.7s ease both;
        }
        @keyframes fadein {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-top-rule {
            height: 3px;
            background: linear-gradient(to right, transparent, var(--gold), transparent);
        }

        .hadith-body-wrap {
            padding: 36px 40px 32px;
            position: relative;
        }

        /* decorative corner quotes */
        .hadith-body-wrap::before {
            content: '❝';
            position: absolute;
            top: 20px; left: 26px;
            font-size: 56px;
            color: var(--gold);
            opacity: 0.15;
            line-height: 1;
            font-family: Georgia, serif;
        }

        .hadith-ref {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(14px, 2.8vw, 20px);
            color: var(--gold-light);
            line-height: 1.55;
            margin-bottom: 20px;
            padding-left: 8px;
            border-left: 3px solid var(--gold);
        }

        .hadith-text {
            font-family: 'Amiri', serif;
            font-size: clamp(17px, 2.6vw, 21px);
            color: var(--cream);
            line-height: 2;
            margin-bottom: 0;
        }

        .card-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(201,168,76,0.3), transparent);
            margin: 28px 0;
        }

        /* ── Action row ── */
        .action-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .action-note {
            font-size: 11px;
            color: var(--gold-dim);
            letter-spacing: 1px;
        }

        .btn-group-custom { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            border: none;
            cursor: pointer;
            font-family: 'Nunito', sans-serif;
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
            text-decoration: none;
        }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.35); opacity: 0.92; }

        .btn-whatsapp { background: #25D366; color: #fff; }
        .btn-copy     { background: rgba(201,168,76,0.12); border: 1px solid var(--card-border); color: var(--cream); }

        /* ── Back link ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 28px;
            color: var(--gold-dim);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--gold-light); text-decoration: none; }

        @media (max-width: 520px) {
            .hadith-body-wrap { padding: 28px 22px 24px; }
            .action-row { flex-direction: column; align-items: flex-start; }
        }
    </style>
    <?php
    // Theme override injection
    function hadith_theme_css(string $t, string $cj): string {
        if ($t === 'green') return '';
        $p = [
                'blue'     => ['--gold'=>'#7EB8E8','--gold-light'=>'#A8D4F5','--gold-dim'=>'#3A6A96','--green-dark'=>'#060D17','--green-mid'=>'#0D1F33','--green'=>'#133558','--cream'=>'#E8F4FF','--cream-dim'=>'#8FB8D8','--card-bg'=>'rgba(13,31,51,0.80)','--card-border'=>'rgba(126,184,232,0.22)'],
                'burgundy' => ['--gold'=>'#D4A44C','--gold-light'=>'#E8C97A','--gold-dim'=>'#8A6232','--green-dark'=>'#150508','--green-mid'=>'#2A0B12','--green'=>'#5C1426','--cream'=>'#F5EDE8','--cream-dim'=>'#C8A898','--card-bg'=>'rgba(42,11,18,0.80)','--card-border'=>'rgba(212,164,76,0.22)'],
                'grey'     => ['--gold'=>'#B0C4D8','--gold-light'=>'#D4E4F0','--gold-dim'=>'#6A8499','--green-dark'=>'#0F1215','--green-mid'=>'#1C2229','--green'=>'#2E3A44','--cream'=>'#EDF2F5','--cream-dim'=>'#98ADB8','--card-bg'=>'rgba(28,34,41,0.80)','--card-border'=>'rgba(176,196,216,0.22)'],
                'white'    => ['--gold'=>'#B07D2A','--gold-light'=>'#C9A84C','--gold-dim'=>'#8A6432','--green-dark'=>'#F7F5F0','--green-mid'=>'#EDE9E0','--green'=>'#DDD6C8','--cream'=>'#2A2420','--cream-dim'=>'#5A524A','--card-bg'=>'rgba(237,233,224,0.80)','--card-border'=>'rgba(176,125,42,0.30)'],
        ];
        if ($t === 'custom') {
            $c = json_decode($cj, true) ?: [];
            $gold = $c['gold'] ?? '#C9A84C'; $mid = $c['bg_mid'] ?? '#102E16';
            list($mr,$mg,$mb)=[hexdec(substr(ltrim($mid,'#'),0,2)),hexdec(substr(ltrim($mid,'#'),2,2)),hexdec(substr(ltrim($mid,'#'),4,2))];
            list($ar,$ag,$ab)=[hexdec(substr(ltrim($gold,'#'),0,2)),hexdec(substr(ltrim($gold,'#'),2,2)),hexdec(substr(ltrim($gold,'#'),4,2))];
            $v=['--gold'=>$c['gold']??'#C9A84C','--gold-light'=>$c['gold_light']??'#E8C97A','--gold-dim'=>$c['gold_dim']??'#8A6E32','--green-dark'=>$c['bg_dark']??'#07170A','--green-mid'=>$mid,'--green'=>$c['bg_accent']??'#1B5E35','--cream'=>$c['cream']??'#F5EDD6','--cream-dim'=>$c['cream_dim']??'#C8B98A','--card-bg'=>"rgba({$mr},{$mg},{$mb},0.80)",'--card-border'=>"rgba({$ar},{$ag},{$ab},0.22)"];
        } else { $v = $p[$t] ?? []; }
        if (empty($v)) return '';
        $css = "<style id=\"nmc-theme-override\">\n:root {\n";
        foreach ($v as $k=>$val) $css.="    {$k}: {$val};\n";
        return $css."}\n</style>\n";
    }
    echo hadith_theme_css($active_theme, $custom_theme_json);
    ?>
</head>
<body>

<div class="page-wrap">

    <!-- Hero -->
    <div class="hero">
        <span class="hero-icon">📖</span>
        <h1 class="hero-title"><?= htmlspecialchars($site_name, ENT_QUOTES) ?></h1>
        <p class="hero-sub">Daily Hadith</p>
        <div class="gold-rule"></div>
    </div>

    <div style="text-align:center;">
        <span class="day-badge">Day <?= $day_number ?> of 365 &nbsp;·&nbsp; <?= $full_date ?></span>
    </div>

    <?php if ($hadith_ref): ?>

        <div class="hadith-card">
            <div class="card-top-rule"></div>
            <div class="hadith-body-wrap">

                <div class="hadith-ref"><?= htmlspecialchars($hadith_ref) ?></div>

                <?php if ($hadith_text): ?>
                    <div class="hadith-text"><?= nl2br(htmlspecialchars($hadith_text)) ?></div>
                <?php endif; ?>

                <div class="card-divider"></div>

                <div class="action-row">
                    <span class="action-note">Share today's Hadith</span>
                    <div class="btn-group-custom">
                        <button class="btn-action btn-copy" onclick="copyHadith()">📋 Copy</button>
                        <a class="btn-action btn-whatsapp" id="waBtn" href="#" target="_blank">📲 WhatsApp</a>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <p style="text-align:center;color:#E07070;padding:48px 0;">No Hadith found for today. Please contact the site administrator.</p>
    <?php endif; ?>

    <!-- Back to Salaah Times -->
    <a href="index.php" class="back-link">← Back to Salaah Times</a>

</div>

<script>

    const shareText = <?= json_encode(
            "📖 Hadith of the Day — Day " . $day_number . " of 365\n"
            . $full_date . "\n"
            . "─────────────────────────\n\n"
            . trim(($hadith_ref ?? '') . ($hadith_text ? "\n\n" . $hadith_text : ''))
            . "\n\n— " . $site_name . ($site_url ? "\n" . $site_url : "")
    ) ?>;

    document.getElementById('waBtn').href = 'https://wa.me/?text=' + encodeURIComponent(shareText);

    function copyHadith() {
        navigator.clipboard.writeText(shareText).then(() => {
            const btn = document.querySelector('.btn-copy');
            const orig = btn.innerHTML;
            btn.innerHTML = '✅ Copied!';
            setTimeout(() => btn.innerHTML = orig, 2200);
        }).catch(() => {
            /* fallback for older mobile browsers */
            const ta = document.createElement('textarea');
            ta.value = shareText;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        });
    }
</script>

</body>
</html