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

session_start();
date_default_timezone_set('Africa/Johannesburg');

/* ══════════════════════════════════════════════
   DATABASE CONNECTION
   ══════════════════════════════════════════════ */
try {
    $db = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

/* ── Load site settings (available everywhere after this point) ── */
function loadSiteSettings($db): array {
    $settings = ['site_name' => 'Musjid Display System', 'site_url' => '', 'tinymce_api_key' => '', 'hijri_offset' => '0', 'madhab' => 'hanafi'];
    if (!$db) return $settings;
    $r = $db->query("SELECT setting_key, setting_value FROM site_settings");
    if ($r) while ($row = $r->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];
    return $settings;
}
$site = loadSiteSettings($db);
$site_name = $site['site_name'];

/* ══════════════════════════════════════════════
   THEME SYSTEM
   ══════════════════════════════════════════════ */

// Shared preset colour definitions — single source of truth for all files
function nmcThemePresets(): array {
    return [
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
}

function nmcGetThemeVars(array $site): array {
    $presets = nmcThemePresets();
    $active  = $site['active_theme'] ?? 'green';
    if ($active === 'custom') {
        $custom = json_decode($site['custom_theme_json'] ?? '{}', true) ?: [];
        return array_merge($presets['green'], $custom);
    }
    return $presets[$active] ?? $presets['green'];
}

function nmcHexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return [(int)hexdec(substr($hex,0,2)), (int)hexdec(substr($hex,2,2)), (int)hexdec(substr($hex,4,2))];
}

function nmcRenderThemeCSS(array $site): string {
    $active = $site['active_theme'] ?? 'green';
    if ($active === 'green') return '';
    $v = nmcGetThemeVars($site);

    [$ar,$ag,$ab] = nmcHexToRgb($v['gold']);
    [$mr,$mg,$mb] = nmcHexToRgb($v['bg_mid']);
    [$dr,$dg,$db] = nmcHexToRgb($v['bg_dark']);
    [$hr,$hg,$hb] = nmcHexToRgb($v['bg_accent']);

    $is_light = (($dr + $dg + $db) / 3) > 128;

    if ($is_light) {
        $card_bg      = "rgba({$mr},{$mg},{$mb},0.82)";
        $card_border  = "rgba({$ar},{$ag},{$ab},0.30)";
        $input_bg     = "rgba(255,255,255,0.7)";
        $input_border = "rgba({$ar},{$ag},{$ab},0.35)";
        $bg_deep      = "rgba({$dr},{$dg},{$db},0.95)";
        $bg_card_hi   = "rgba({$mr},{$mg},{$mb},0.92)";
        $bg_card_lo   = "rgba({$hr},{$hg},{$hb},0.60)";
        $bg_marker    = "rgba({$mr},{$mg},{$mb},0.85)";
        $bg_sim       = "rgba({$dr},{$dg},{$db},0.97)";
    } else {
        $card_bg      = "rgba({$mr},{$mg},{$mb},0.82)";
        $card_border  = "rgba({$ar},{$ag},{$ab},0.22)";
        $input_bg     = "rgba({$dr},{$dg},{$db},0.7)";
        $input_border = "rgba({$ar},{$ag},{$ab},0.28)";
        $bg_deep      = "rgba({$dr},{$dg},{$db},0.92)";
        $bg_card_hi   = "rgba({$mr},{$mg},{$mb},0.92)";
        $bg_card_lo   = "rgba({$hr},{$hg},{$hb},0.42)";
        $bg_marker    = "rgba({$dr},{$dg},{$db},0.78)";
        $bg_sim       = "rgba({$dr},{$dg},{$db},0.97)";
    }

    $all_vars = [
            '--gold'           => $v['gold'],
            '--gold-light'     => $v['gold_light'],
            '--gold-dim'       => $v['gold_dim'],
            '--green-dark'     => $v['bg_dark'],
            '--green-mid'      => $v['bg_mid'],
            '--green'          => $v['bg_accent'],
            '--cream'          => $v['cream'],
            '--cream-dim'      => $v['cream_dim'],
            '--card-bg'        => $card_bg,
            '--card-border'    => $card_border,
            '--card-active'    => "rgba({$ar},{$ag},{$ab},0.13)",
            '--input-bg'       => $input_bg,
            '--input-border'   => $input_border,
            '--bg-deep'        => $bg_deep,
            '--bg-card-hi'     => $bg_card_hi,
            '--bg-card-lo'     => $bg_card_lo,
            '--bg-card-mid'    => "rgba({$mr},{$mg},{$mb},0.80)",
            '--bg-marker'      => $bg_marker,
            '--bg-ticker'      => "rgba({$dr},{$dg},{$db},0.92)",
            '--bg-sim'         => $bg_sim,
            '--bg-fab'         => $bg_sim,
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

    $vars = '';
    foreach ($all_vars as $k => $val) $vars .= "    {$k}: {$val};\n";
    return "<style id=\"nmc-theme-override\">\n:root {\n{$vars}}\n</style>\n";
}


/* ══════════════════════════════════════════════
   PASSWORD POLICY
   ══════════════════════════════════════════════ */
function validatePassword(string $pw): array {
    $errors = [];
    if (strlen($pw) < 10)              $errors[] = 'At least 10 characters';
    if (!preg_match('/[A-Z]/', $pw))   $errors[] = 'At least one uppercase letter';
    if (!preg_match('/[a-z]/', $pw))   $errors[] = 'At least one lowercase letter';
    if (!preg_match('/[0-9]/', $pw))   $errors[] = 'At least one digit';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) $errors[] = 'At least one special character (!@#$%^&* …)';
    return $errors;
}

/* ══════════════════════════════════════════════
   CSRF
   ══════════════════════════════════════════════ */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrfCheck(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        die('Invalid CSRF token.');
    }
}

/* ══════════════════════════════════════════════
   HELPERS
   ══════════════════════════════════════════════ */
function isLoggedIn(): bool { return !empty($_SESSION['admin_id']); }
function isSuperAdmin(): bool { return ($_SESSION['admin_role'] ?? '') === 'superadmin'; }
function redirect(string $url): void { header("Location: $url"); exit; }
function flash(string $key, string $msg): void { $_SESSION['flash'][$key] = $msg; }
function getFlash(string $key): string {
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function dbEsc($db, $s): string { return str_replace("'", "''", $s); }
function bumpContentVersion($db): void {
    /* Increment a counter in site_settings — polled by the musjid display to detect changes */
    $r = @$db->query("SELECT setting_value FROM site_settings WHERE setting_key='content_version' LIMIT 1");
    $current = ($r && $row = $r->fetch(PDO::FETCH_ASSOC)) ? (int)$row['setting_value'] : 0;
    $next = $current + 1;
    if ($current === 0) {
        @$db->exec("INSERT INTO site_settings (setting_key, setting_value) VALUES ('content_version', '$next')");
    } else {
        @$db->exec("UPDATE site_settings SET setting_value='$next', updated_at=datetime('now') WHERE setting_key='content_version'");
    }
}
function upsertSetting($db, string $key, string $value): void {
    /* Safe upsert that works even without PRIMARY KEY constraint on setting_key */
    $k = str_replace("'", "''", $key);
    $v = str_replace("'", "''", $value);
    $r = @$db->query("SELECT COUNT(*) FROM site_settings WHERE setting_key='$k'");
    if ($r && $r->fetchColumn() > 0) {
        $db->exec("UPDATE site_settings SET setting_value='$v', updated_at=datetime('now') WHERE setting_key='$k'");
    } else {
        $db->exec("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$k', '$v')");
    }
}

function getSystemVersion(): string {
    $ver = '1.0.0';
    if (function_exists('shell_exec') && is_dir(__DIR__ . '/.git')) {
        $count = @shell_exec('git rev-list --count HEAD');
        $hash  = @shell_exec('git rev-parse --short HEAD');
        $ts    = @shell_exec('git show -s --format=%ct HEAD');
        if ($count) {
            $ver = '1.0.' . trim($count) . ($hash ? '-' . trim($hash) : '');
            if ($ts) {
                $ver .= ' (' . date('d M Y', (int)trim($ts)) . ')';
            }
        }
    }
    return $ver;
}

/* ══════════════════════════════════════════════
   TIME VALIDATION (HH:MM or HH:MM:SS)
   ══════════════════════════════════════════════ */
function validTime(string $t): bool {
    return (bool) preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $t);
}
function normaliseTime(string $t): string {
    // Store as HH:MM:SS
    return preg_match('/^\d{2}:\d{2}$/', $t) ? $t . ':00' : $t;
}

/* ══════════════════════════════════════════════
   ACTION ROUTING
   ══════════════════════════════════════════════ */

// Normalise the action — treat missing/empty as 'login' when not authenticated,
// or 'dashboard' when already authenticated.  This prevents redirect loops.
$raw_action = trim($_REQUEST['action'] ?? '');
if ($raw_action === '') {
    $action = 'dashboard';   // will be redirected to login below if not authed
} else {
    $action = $raw_action;
}

$errors  = [];
$success = '';

/* ---------- LOGIN ---------- */
if (($action === 'login' || $action === 'dashboard' && !isLoggedIn()) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = 'login'; // normalise
    csrfCheck();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $res = $db->query("SELECT * FROM admin_users WHERE username='" . dbEsc($db,$username) . "' LIMIT 1");
    $user = $res ? $res->fetch(PDO::FETCH_ASSOC) : null;

    $lockout = false;
    if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $lockout = true;
        $errors[] = 'Account locked due to too many failed attempts. Try again after ' . $user['locked_until'] . '.';
    }

    if (!$lockout && $user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
        // Success
        $db->exec("UPDATE admin_users SET last_login=datetime('now'), login_attempts=0, locked_until=NULL WHERE id='{$user['id']}'");
        session_regenerate_id(false); // false = keep old session file briefly (safer on shared hosts)
        $_SESSION['admin_id']       = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role']     = $user['role'];
        redirect('admin.php?action=dashboard');
    } elseif (!$lockout) {
        if ($user) {
            $attempts = (int)$user['login_attempts'] + 1;
            $lock_sql = $attempts >= 5 ? ", locked_until = datetime('now', '+15 minutes'), login_attempts=0" : ", login_attempts=$attempts";
            $db->exec("UPDATE admin_users SET login_attempts=$attempts $lock_sql WHERE id='{$user['id']}'");
        }
        $errors[] = 'Invalid username or password.';
    }
}

/* ---------- LOGOUT ---------- */
if ($action === 'logout') {
    session_destroy();
    redirect('admin.php?action=login');
}

/* Require login for all other actions */
if (!isLoggedIn() && $action !== 'login') {
    redirect('admin.php?action=login');
}

/* ---------- UPDATE SALAAH TIMES (Jamaat only) ---------- */
if ($action === 'save_times' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $month = (int)($_POST['month'] ?? 0);
    $date  = (int)($_POST['date']  ?? 0);
    if ($month < 1 || $month > 12 || $date < 1 || $date > 31) {
        $errors[] = 'Invalid month or date.';
    } else {
        $editable = ['fajr','zuhr','asr','maghrib','esha'];
        $setParts = [];
        $valid = true;
        foreach ($editable as $f) {
            $val = trim($_POST[$f] ?? '');
            if (!validTime($val)) { $errors[] = "Invalid time for $f: \"$val\""; $valid = false; }
            else $setParts[] = "`$f` = '" . dbEsc($db, normaliseTime($val)) . "'";
        }
        if ($valid) {
            $set = implode(', ', $setParts);
            $sql = "UPDATE perpetual_salaah_times SET $set WHERE month='$month' AND date='$date'";
            $stmt = $db->exec($sql);
            if ($stmt !== false && $db->query("SELECT changes()")->fetchColumn() > 0) {
                flash('success', 'Jamaat times updated for ' . date('F', mktime(0,0,0,$month,1)) . " $date.");
            } else {
                flash('error', 'No record found for that date, or nothing changed.');
            }
            redirect('admin.php?action=times&month=' . $month . '&date=' . $date . '&view=' . ($_POST['view'] ?? 'day'));
        }
    }
}

/* ---------- BULK GRID SAVE (whole month jamaat times) ---------- */
if ($action === 'save_grid' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $month = (int)($_POST['month'] ?? 0);
    if ($month < 1 || $month > 12) {
        $errors[] = 'Invalid month.';
    } else {
        $editable = ['fajr','zuhr','asr','maghrib','esha'];
        $saved = 0; $errs = 0;
        // rows are posted as times[date][field]
        foreach ($_POST['times'] as $date_str => $fields) {
            $date = (int)$date_str;
            if ($date < 1 || $date > 31) continue;
            $setParts = []; $valid = true;
            foreach ($editable as $f) {
                $val = trim($fields[$f] ?? '');
                if (!validTime($val)) { $valid = false; $errs++; }
                else $setParts[] = "`$f` = '" . dbEsc($db, normaliseTime($val)) . "'";
            }
            if ($valid && !empty($setParts)) {
                $set = implode(', ', $setParts);
                if ($db->exec("UPDATE perpetual_salaah_times SET $set WHERE month='$month' AND date='$date'") !== false) {
                    $saved++;
                } else { $errs++; }
            }
        }
        if ($errs === 0) flash('success', "Grid saved — $saved rows updated.");
        else flash('error', "$saved rows saved, $errs had errors.");
        redirect('admin.php?action=times&month=' . $month . '&view=grid');
    }
}

/* ---------- SITE SETTINGS (superadmin only) ---------- */
if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST' && isSuperAdmin()) {
    csrfCheck();
    $new_name = trim($_POST['site_name'] ?? '');
    if ($new_name === '') {
        $errors[] = 'Site name cannot be empty.';
        $action   = 'settings';
    } elseif (mb_strlen($new_name) > 120) {
        $errors[] = 'Site name must be 120 characters or fewer.';
        $action   = 'settings';
    } else {
        upsertSetting($db, 'site_name', $new_name);
        $site_name = $new_name;
        $site['site_name'] = $new_name;

        $site_url = trim($_POST['site_url'] ?? '');
        upsertSetting($db, 'site_url', $site_url);
        $site['site_url'] = $site_url;

        $rem_copy = !empty($_POST['remove_copyright']) ? '1' : '0';
        upsertSetting($db, 'remove_copyright', $rem_copy);
        $site['remove_copyright'] = (int)$rem_copy;
        bumpContentVersion($db);

        flash('success', 'Site settings updated.');
        redirect('admin.php?action=settings');
    }
}

/* ---------- SAVE THEME (all admins) ---------- */
if ($action === 'save_theme' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $allowed_themes = ['green','blue','burgundy','grey','white','custom'];
    $new_theme = trim($_POST['theme'] ?? 'green');
    if (!in_array($new_theme, $allowed_themes)) $new_theme = 'green';

    upsertSetting($db, 'active_theme', $new_theme);
    $site['active_theme'] = $new_theme;

    if ($new_theme === 'custom') {
        $custom_keys = ['gold','gold_light','gold_dim','bg_dark','bg_mid','bg_accent','cream','cream_dim'];
        $custom = [];
        foreach ($custom_keys as $k) {
            $val = trim($_POST['custom_' . $k] ?? '');
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $val)) $custom[$k] = $val;
        }
        $json = json_encode($custom);
        upsertSetting($db, 'custom_theme_json', $json);
        $site['custom_theme_json'] = $json;
    }

    bumpContentVersion($db);
    flash('success', 'Theme updated successfully.');
    redirect('admin.php?action=themes');
}

/* ---------- SAVE HIJRI OFFSET (all admins) ---------- */
if ($action === 'save_hijri_offset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $offset = (int)($_POST['hijri_offset'] ?? 0);
    if ($offset < -2 || $offset > 2) {
        flash('error', 'Hijri offset must be between -2 and +2.');
    } else {
        upsertSetting($db, 'hijri_offset', (string)(int)$offset);
        $site['hijri_offset'] = $offset;
        flash('success', 'Hijri date offset set to ' . ($offset >= 0 ? '+' : '') . $offset . ' day(s).');
    }
    redirect('admin.php?action=hijri_date');
}

/* ---------- SAVE MADHAB (superadmin only) ---------- */
if ($action === 'save_madhab' && $_SERVER['REQUEST_METHOD'] === 'POST' && isSuperAdmin()) {
    csrfCheck();
    $madhab = in_array($_POST['madhab'] ?? '', ['hanafi','shafi']) ? $_POST['madhab'] : 'hanafi';
    upsertSetting($db, 'madhab', $madhab);
    $site['madhab'] = $madhab;
    flash('success', 'Madhab set to ' . ucfirst($madhab) . '. Asr window will now use the ' . ucfirst($madhab) . ' earliest time.');
    redirect('admin.php?action=settings');
}

/* ---------- SAVE JUMMAH SETTINGS (all admins) ---------- */
if ($action === 'save_jummah' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    try { $db->exec("ALTER TABLE jummah_settings ADD COLUMN talk_by TEXT DEFAULT ''"); } catch(Exception $e) {}
    $j_azaan   = trim($_POST['azaan_time']   ?? '');
    $j_khutbah = trim($_POST['khutbah_time'] ?? '');
    $j_jamaat  = trim($_POST['jamaat_time']  ?? '');
    $j_talk    = trim($_POST['talk_by']      ?? '');
    $time_re   = '/^\d{2}:\d{2}$/';
    if (!preg_match($time_re,$j_azaan) || !preg_match($time_re,$j_khutbah) || !preg_match($time_re,$j_jamaat)) {
        flash('error', 'All three Jummah times are required in HH:MM format.');
    } elseif ($j_azaan >= $j_khutbah || $j_khutbah >= $j_jamaat) {
        flash('error', 'Times must be in order: Azaan < Khutbah < Jamaat.');
    } else {
        $esc_a = dbEsc($db,$j_azaan.':00');
        $esc_k = dbEsc($db,$j_khutbah.':00');
        $esc_j = dbEsc($db,$j_jamaat.':00');
        $esc_t = dbEsc($db,$j_talk);
        $r = @$db->query("SELECT COUNT(*) FROM jummah_settings WHERE id='1'");
        if ($r && $r->fetchColumn() > 0) {
            $db->exec("UPDATE jummah_settings SET azaan_time='$esc_a', khutbah_time='$esc_k', jamaat_time='$esc_j', talk_by='$esc_t', updated_at=datetime('now') WHERE id='1'");
        } else {
            $db->exec("INSERT INTO jummah_settings (id,azaan_time,khutbah_time,jamaat_time,talk_by) VALUES (1,'$esc_a','$esc_k','$esc_j','$esc_t')");
        }
        flash('success', "Jummah settings saved.");
    }
    redirect('admin.php?action=jummah');
}

/* ---------- RESTORE FROM BACKUP (superadmin only) ---------- */

if ($action === 'do_restore' && $_SERVER['REQUEST_METHOD'] === 'POST' && isSuperAdmin()) {
    csrfCheck();

    $confirm_phrase = trim($_POST['confirm_phrase'] ?? '');
    $required       = 'RESTORE FROM BACKUP';

    if ($confirm_phrase !== $required) {
        $errors[] = 'Confirmation phrase did not match. Type exactly: ' . $required;
        $action   = 'restore'; // fall through to restore page with error
    } else {
        // Run inside a transaction so it's atomic
        $ok = false;
        $db->beginTransaction();
        try {
            // 1. Wipe the live table data
            $r1 = $db->exec("DELETE FROM perpetual_salaah_times");
            if ($r1 === false) throw new Exception("DELETE failed");

            // 2. Copy every row from the backup
            $r2 = $db->exec("
                INSERT INTO perpetual_salaah_times
                SELECT * FROM perpetual_salaah_times_orig_2016
            ");
            if ($r2 === false) throw new Exception("INSERT failed");

            $rows = $r2;
            $db->commit();
            $ok = true;

            // Log the restore
            // Audit log the restore
            $audit_user   = $_SESSION['admin_username'] ?? 'unknown';
            $audit_role   = $_SESSION['admin_role']     ?? 'unknown';
            $audit_ip     = $_SERVER['REMOTE_ADDR']     ?? '';
            $audit_ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            $audit_detail = "$rows rows copied from perpetual_salaah_times_orig_2016 into perpetual_salaah_times";
            $audit_json   = json_encode(['rows_restored' => $rows, 'source_table' => 'perpetual_salaah_times_orig_2016', 'target_table' => 'perpetual_salaah_times']);
            $stmt = $db->prepare("
                INSERT INTO audit_log (admin_user, admin_role, action, target_table, detail, ip_address, user_agent, result, extra_json)
                VALUES (?, ?, 'restore_backup', 'perpetual_salaah_times', ?, ?, ?, 'success', ?)
            ");
            $stmt->execute([$audit_user, $audit_role, $audit_detail, $audit_ip, $audit_ua, $audit_json]);
            flash('success', "✅ Restore complete. $rows rows copied from backup into live table. Performed by: " . $_SESSION['admin_username'] . " at " . date('Y-m-d H:i:s'));
        } catch (Exception $ex) {
            $db->rollBack();
            flash('error', '❌ Restore failed and was rolled back. Reason: ' . $ex->getMessage());
        }

        redirect('admin.php?action=restore');
    }
}

/* ══════════════════════════════════════════════
   COMMUNITY MESSAGES — CRUD
   ══════════════════════════════════════════════ */
function handleImageUpload(string $fieldName, $db): ?int {
    /* Returns media.id on success, null if no file or invalid */
    if (empty($_FILES[$fieldName]['tmp_name'])) return null;
    $f = $_FILES[$fieldName];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($f['type'], $allowed)) return null;
    $data = file_get_contents($f['tmp_name']);
    if ($data === false) return null;
    $mime = dbEsc($db, $f['type']);
    $stmt = $db->prepare("INSERT INTO media (mime_type, data) VALUES (:mime, :data)");
    $stmt->bindValue(':mime', $f['type'], PDO::PARAM_STR);
    $stmt->bindValue(':data', $data,      PDO::PARAM_LOB);
    $stmt->execute();
    return (int)$db->lastInsertId();
}

if (in_array($action, ['save_community_msg']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $id          = (int)($_POST['msg_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $ctype       = ($_POST['content_type'] ?? 'html') === 'image' ? 'image' : 'html';
    $content_html = $_POST['content_html'] ?? '';
    $image_fit   = in_array($_POST['image_fit'] ?? 'contain', ['contain', 'cover', 'fill']) ? $_POST['image_fit'] : 'contain';
    $display_s   = max(5, (int)($_POST['display_secs'] ?? 30));
    $start_dt    = trim($_POST['start_dt'] ?? '') ?: null;
    $end_dt      = trim($_POST['end_dt']   ?? '') ?: null;
    $is_active   = (int)(!empty($_POST['is_active']));
    $sort_order  = (int)($_POST['sort_order'] ?? 0);

    $media_id = null;
    if ($ctype === 'image') {
        $new_media_id = handleImageUpload('image_file', $db);
        if ($new_media_id) {
            $media_id = $new_media_id;
        } elseif ($id > 0) {
            /* keep existing media_id if no new upload */
            $existing = $db->query("SELECT media_id FROM community_messages WHERE id='$id' LIMIT 1");
            if ($existing && $row = $existing->fetch(PDO::FETCH_ASSOC)) $media_id = $row['media_id'];
        }
    }

    $title_esc  = dbEsc($db, $title);
    $html_esc   = dbEsc($db, $content_html);
    $media_sql  = $media_id ? (int)$media_id : 'NULL';
    $start_sql  = $start_dt ? "'" . dbEsc($db, $start_dt) . "'" : 'NULL';
    $end_sql    = $end_dt   ? "'" . dbEsc($db, $end_dt)   . "'" : 'NULL';

    if ($id > 0) {
        $db->exec("UPDATE community_messages SET
            title='$title_esc', content_type='$ctype', content_html='$html_esc',
            media_id=$media_sql, image_fit='$image_fit', display_secs=$display_s,
            start_dt=$start_sql, end_dt=$end_sql, is_active=$is_active, sort_order=$sort_order
            WHERE id='$id'");
        bumpContentVersion($db);
        flash('success', 'Community message updated.');
    } else {
        $db->exec("INSERT INTO community_messages
            (title, content_type, content_html, media_id, image_fit, display_secs, start_dt, end_dt, is_active, sort_order)
            VALUES ('$title_esc','$ctype','$html_esc',$media_sql,'$image_fit',$display_s,$start_sql,$end_sql,$is_active,$sort_order)");
        bumpContentVersion($db);
        flash('success', 'Community message created.');
    }
    redirect('admin.php?action=community_messages');
}

if ($action === 'delete_community_msg') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { $db->exec("DELETE FROM community_messages WHERE id='$id'"); bumpContentVersion($db); flash('success','Deleted.'); }
    redirect('admin.php?action=community_messages');
}
if ($action === 'toggle_community_msg') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { $db->exec("UPDATE community_messages SET is_active = 1 - is_active WHERE id='$id'"); bumpContentVersion($db); }
    redirect('admin.php?action=community_messages');
}

/* ══════════════════════════════════════════════
   FUNERAL NOTICES — CRUD
   ══════════════════════════════════════════════ */
if ($action === 'save_funeral' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $id      = (int)($_POST['funeral_id'] ?? 0);
    $fields  = ['funeral_date_en','funeral_date_hijri','deceased_name','family_details','leave_from','departure_time','proceeding_to','janazah_location','janazah_time'];
    $vals    = [];
    foreach ($fields as $f) $vals[$f] = trim($_POST[$f] ?? '');
    $display_s = max(5, (int)($_POST['display_secs'] ?? 30));
    $start_dt  = trim($_POST['start_dt'] ?? '') ?: null;
    $end_dt    = trim($_POST['end_dt']   ?? '') ?: null;
    $is_active = (int)(!empty($_POST['is_active']));

    $sets = [];
    foreach ($fields as $f) $sets[] = "$f='" . dbEsc($db, $vals[$f]) . "'";
    $sets[] = "display_secs=$display_s";
    $sets[] = "start_dt=" . ($start_dt ? "'" . dbEsc($db,$start_dt) . "'" : 'NULL');
    $sets[] = "end_dt="   . ($end_dt   ? "'" . dbEsc($db,$end_dt)   . "'" : 'NULL');
    $sets[] = "is_active=$is_active";
    $setstr = implode(', ', $sets);

    if ($id > 0) {
        $db->exec("UPDATE funeral_notices SET $setstr WHERE id='$id'");
        bumpContentVersion($db);
        flash('success', 'Funeral notice updated.');
    } else {
        $flds = implode(',', $fields) . ',display_secs,start_dt,end_dt,is_active';
        $fvals = implode(',', array_map(fn($f) => "'" . dbEsc($db,$vals[$f]) . "'", $fields));
        $fvals .= ",$display_s";
        $fvals .= ',' . ($start_dt ? "'" . dbEsc($db,$start_dt) . "'" : 'NULL');
        $fvals .= ',' . ($end_dt   ? "'" . dbEsc($db,$end_dt)   . "'" : 'NULL');
        $fvals .= ",$is_active";
        $db->exec("INSERT INTO funeral_notices ($flds) VALUES ($fvals)");
        bumpContentVersion($db);
        flash('success', 'Funeral notice created.');
    }
    redirect('admin.php?action=funeral_notices');
}
if ($action === 'delete_funeral') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { $db->exec("DELETE FROM funeral_notices WHERE id='$id'"); bumpContentVersion($db); flash('success','Deleted.'); }
    redirect('admin.php?action=funeral_notices');
}
if ($action === 'toggle_funeral') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { $db->exec("UPDATE funeral_notices SET is_active = 1 - is_active WHERE id='$id'"); bumpContentVersion($db); }
    redirect('admin.php?action=funeral_notices');
}

/* ══════════════════════════════════════════════
   TICKER MESSAGES — CRUD
   ══════════════════════════════════════════════ */
if ($action === 'save_ticker' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $id          = (int)($_POST['ticker_id'] ?? 0);
    $msg         = trim($_POST['message_text'] ?? '');
    $display_s   = max(5, (int)($_POST['display_secs'] ?? 30));
    $start_dt    = trim($_POST['start_dt'] ?? '') ?: null;
    $end_dt      = trim($_POST['end_dt']   ?? '') ?: null;
    $is_active   = (int)(!empty($_POST['is_active']));
    $sort_order  = (int)($_POST['sort_order'] ?? 0);

    $msg_esc     = dbEsc($db, $msg);
    $start_sql   = $start_dt ? "'" . dbEsc($db,$start_dt) . "'" : 'NULL';
    $end_sql     = $end_dt   ? "'" . dbEsc($db,$end_dt)   . "'" : 'NULL';

    if ($id > 0) {
        $db->exec("UPDATE ticker_messages SET message_text='$msg_esc', display_secs=$display_s,
            start_dt=$start_sql, end_dt=$end_sql, is_active=$is_active, sort_order=$sort_order
            WHERE id='$id'");
        bumpContentVersion($db);
        flash('success', 'Ticker message updated.');
    } else {
        $db->exec("INSERT INTO ticker_messages (message_text,display_secs,start_dt,end_dt,is_active,sort_order)
            VALUES ('$msg_esc',$display_s,$start_sql,$end_sql,$is_active,$sort_order)");
        bumpContentVersion($db);
        flash('success', 'Ticker message created.');
    }
    redirect('admin.php?action=ticker_messages');
}
if ($action === 'delete_ticker') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { $db->exec("DELETE FROM ticker_messages WHERE id='$id'"); bumpContentVersion($db); flash('success','Deleted.'); }
    redirect('admin.php?action=ticker_messages');
}
if ($action === 'toggle_ticker') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { $db->exec("UPDATE ticker_messages SET is_active = 1 - is_active WHERE id='$id'"); bumpContentVersion($db); }
    redirect('admin.php?action=ticker_messages');
}

if ($action === 'save_user' && $_SERVER['REQUEST_METHOD'] === 'POST' && isSuperAdmin()) {
    csrfCheck();
    $uid      = (int)($_POST['uid'] ?? 0);
    $uname    = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $role     = in_array($_POST['role'] ?? '', ['superadmin','admin']) ? $_POST['role'] : 'admin';
    $active   = (int)(!empty($_POST['is_active']));
    $pw       = $_POST['new_password'] ?? '';
    $pw2      = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $uname)) $errors[] = 'Username: 3–30 alphanumeric chars / underscore.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    $pw_errors = [];
    if ($pw !== '') {
        if ($pw !== $pw2) $errors[] = 'Passwords do not match.';
        $pw_errors = validatePassword($pw);
        foreach ($pw_errors as $pe) $errors[] = $pe;
    }

    if (empty($errors)) {
        if ($uid > 0) {
            // Edit existing
            $set = "username='" . dbEsc($db,$uname) . "', email='" . dbEsc($db,$email) . "', role='$role', is_active=$active";
            if ($pw !== '') $set .= ", password_hash='" . dbEsc($db, password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12])) . "', login_attempts=0, locked_until=NULL";
            $db->exec("UPDATE admin_users SET $set WHERE id='$uid'");
            flash('success', 'User updated.');
        } else {
            // New user – password required
            if ($pw === '') { $errors[] = 'Password is required for new users.'; }
            else {
                $hash = dbEsc($db, password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]));
                try {
                    $db->exec("INSERT INTO admin_users (username,password_hash,email,role,is_active)
                        VALUES ('" . dbEsc($db,$uname) . "','$hash','" . dbEsc($db,$email) . "','$role',$active)");
                    flash('success', 'User created.');
                } catch (Exception $e) {
                    $errors[] = 'Username already exists or DB error.';
                }
            }
        }
        if (empty($errors)) redirect('admin.php?action=users');
    }
}

if ($action === 'delete_user' && isSuperAdmin()) {
    $uid = (int)($_GET['uid'] ?? 0);
    if ($uid && $uid !== (int)$_SESSION['admin_id']) {
        $db->exec("DELETE FROM admin_users WHERE id='$uid'");
        flash('success', 'User deleted.');
    }
    redirect('admin.php?action=users');
}

if ($action === 'unlock_user' && isSuperAdmin()) {
    $uid = (int)($_GET['uid'] ?? 0);
    if ($uid) {
        $db->exec("UPDATE admin_users SET login_attempts=0, locked_until=NULL WHERE id='$uid'");
        flash('success', 'User unlocked.');
    }
    redirect('admin.php?action=users');
}

/* ══════════════════════════════════════════════
   RAMADAN OVERRIDE — CRUD
   ══════════════════════════════════════════════ */

/* ---------- SETUP / REGENERATE RAMADAN SCHEDULE ---------- */
if ($action === 'ramadan_setup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $start_raw = trim($_POST['start_date'] ?? '');
    $end_raw   = trim($_POST['end_date']   ?? '');

    $start_ts = strtotime($start_raw);
    $end_ts   = strtotime($end_raw);

    if (!$start_ts || !$end_ts) {
        flash('error', 'Invalid start or end date.');
        redirect('admin.php?action=ramadan');
    }

    if ($end_ts <= $start_ts) {
        flash('error', 'End date must be after start date.');
        redirect('admin.php?action=ramadan');
    }

    $days_count = (int)(($end_ts - $start_ts) / 86400) + 1;

    if ($days_count < 29 || $days_count > 31) {
        flash('error', "Ramadan schedule must be between 29 and 31 consecutive days. You selected $days_count days.");
        redirect('admin.php?action=ramadan');
    }

    $start_sql = date('Y-m-d', $start_ts);
    $end_sql   = date('Y-m-d', $end_ts);

    /* Wipe existing override rows and schedule */
    $db->exec("DELETE FROM ramadan_override");
    $db->exec("DELETE FROM ramadan_schedule");

    /* Insert new schedule control row */
    $db->exec("INSERT INTO ramadan_schedule (id, start_date, end_date, is_active)
        VALUES (1, '$start_sql', '$end_sql', 0)");

    /* Populate override rows from perpetual_salaah_times */
    $inserted = 0;
    $missing  = 0;
    for ($i = 0; $i < $days_count; $i++) {
        $day_ts    = $start_ts + ($i * 86400);
        $p_date    = date('Y-m-d', $day_ts);
        $p_month   = (int)date('n', $day_ts);
        $p_day     = (int)date('j', $day_ts);
        $src = $db->query("SELECT fajr, zuhr, asr, esha FROM perpetual_salaah_times
                                   WHERE month='$p_month' AND date='$p_day' LIMIT 1");
        if ($src && $srow = $src->fetch(PDO::FETCH_ASSOC)) {
            $fajr_e  = dbEsc($db, $srow['fajr']);
            $zuhr_e  = dbEsc($db, $srow['zuhr']);
            $asr_e   = dbEsc($db, $srow['asr']);
            $esha_e  = dbEsc($db, $srow['esha']);
            $ro_chk = @$db->query("SELECT COUNT(*) FROM ramadan_override WHERE prayer_date='$p_date'");
            if ($ro_chk && $ro_chk->fetchColumn() > 0) {
                $db->exec("UPDATE ramadan_override SET fajr='$fajr_e', zuhr='$zuhr_e', asr='$asr_e', esha='$esha_e' WHERE prayer_date='$p_date'");
            } else {
                $db->exec("INSERT INTO ramadan_override (prayer_date, fajr, zuhr, asr, esha) VALUES ('$p_date', '$fajr_e', '$zuhr_e', '$asr_e', '$esha_e')");
            }
            $inserted++;
        } else {
            /* If no matching row (e.g. Feb 30), insert zeros */
            $db->exec("INSERT OR IGNORE INTO ramadan_override (prayer_date, fajr, zuhr, asr, esha)
                VALUES ('$p_date', '00:00:00', '00:00:00', '00:00:00', '00:00:00')");
            $missing++;
        }
    }

    bumpContentVersion($db);
    $msg = "Ramadan schedule created for $start_sql → $end_sql ($days_count days). $inserted rows populated from main timetable.";
    if ($missing) $msg .= " $missing date(s) had no matching source row (set to 00:00).";
    flash('success', $msg);
    redirect('admin.php?action=ramadan');
}

/* ---------- SAVE RAMADAN GRID ---------- */
if ($action === 'ramadan_save_grid' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $fields  = ['fajr','zuhr','asr','esha'];
    $saved   = 0;
    $errs    = 0;
    $rows_in = $_POST['times'] ?? [];
    foreach ($rows_in as $date_str => $vals) {
        /* Basic date sanity */
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) { $errs++; continue; }
        $setParts = [];
        $valid = true;
        foreach ($fields as $f) {
            $val = trim($vals[$f] ?? '');
            if (!validTime($val)) { $valid = false; $errs++; }
            else $setParts[] = "`$f` = '" . dbEsc($db, normaliseTime($val)) . "'";
        }
        if ($valid && !empty($setParts)) {
            $set   = implode(', ', $setParts);
            $d_esc = dbEsc($db, $date_str);
            if ($db->exec("UPDATE ramadan_override SET $set WHERE prayer_date='$d_esc'") !== false) {
                $saved++;
            } else { $errs++; }
        }
    }
    bumpContentVersion($db);
    if ($errs === 0) flash('success', "Ramadan grid saved — $saved rows updated.");
    else             flash('error',   "$saved rows saved, $errs had errors. Check time formats (HH:MM).");
    redirect('admin.php?action=ramadan');
}

/* ---------- TOGGLE RAMADAN ACTIVE ---------- */
if ($action === 'ramadan_toggle') {
    csrfCheck();
    /* Read current state */
    $rs = $db->query("SELECT id, is_active, end_date FROM ramadan_schedule WHERE id='1' LIMIT 1");
    if ($rs && $rrow = $rs->fetch(PDO::FETCH_ASSOC)) {
        $currently_active = (int)$rrow['is_active'];
        $end_date         = $rrow['end_date'];
        if (!$currently_active) {
            /* Activating — check it hasn't already expired */
            if (date('Y-m-d') > $end_date) {
                flash('error', 'Cannot activate — the Ramadan schedule end date has already passed.');
            } else {
                $db->exec("UPDATE ramadan_schedule SET is_active=1, updated_at=datetime('now') WHERE id='1'");
                bumpContentVersion($db);
                flash('success', '🌙 Ramadan Override activated. Jamaat times will now use the Ramadan timetable.');
            }
        } else {
            /* Deactivating */
            $db->exec("UPDATE ramadan_schedule SET is_active=0, updated_at=datetime('now') WHERE id='1'");
            bumpContentVersion($db);
            flash('success', 'Ramadan Override deactivated. Regular timetable is now active.');
        }
    } else {
        flash('error', 'No Ramadan schedule found. Please set up a schedule first.');
    }
    redirect('admin.php?action=ramadan');
}

/* ---------- RESET RAMADAN SCHEDULE ---------- */
if ($action === 'ramadan_reset') {
    csrfCheck();
    $db->exec("DELETE FROM ramadan_override");
    $db->exec("DELETE FROM ramadan_schedule");
    bumpContentVersion($db);
    flash('success', 'Ramadan schedule has been reset. You can now create a new one.');
    redirect('admin.php?action=ramadan');
}

/* ══════════════════════════════════════════════
   DATA FETCHING
   ══════════════════════════════════════════════ */
$times_row   = null;
$month_rows  = [];   // for grid view — all days of chosen month
$edit_view   = $_GET['view'] ?? 'day';   // 'day' or 'grid'
$edit_month  = (int)($_GET['month'] ?? date('n'));
$edit_date   = (int)($_GET['date']  ?? date('j'));

if ($action === 'times' || $action === 'save_times' || $action === 'save_grid') {
    if ($edit_view === 'grid') {
        // Load all rows for the month
        $res = $db->query("SELECT * FROM perpetual_salaah_times WHERE month='$edit_month' ORDER BY CAST(date AS INTEGER) ASC");
        if ($res) while ($r = $res->fetch(PDO::FETCH_ASSOC)) $month_rows[$r['date']] = $r;
    } else {
        $res = $db->query("SELECT * FROM perpetual_salaah_times WHERE month='$edit_month' AND date='$edit_date' LIMIT 1");
        if ($res) $times_row = $res->fetch(PDO::FETCH_ASSOC);
    }
}

$users_list = [];
if ($action === 'users' && isSuperAdmin()) {
    $res = $db->query("SELECT * FROM admin_users ORDER BY role DESC, username ASC");
    if ($res) while ($row = $res->fetch(PDO::FETCH_ASSOC)) $users_list[] = $row;
}

$edit_user = null;
if ($action === 'edit_user' && isSuperAdmin()) {
    $uid = (int)($_GET['uid'] ?? 0);
    if ($uid) {
        $res = $db->query("SELECT * FROM admin_users WHERE id='$uid' LIMIT 1");
        if ($res) $edit_user = $res->fetch(PDO::FETCH_ASSOC);
    }
}

/* ── Community messages list ── */
$community_msgs = [];
if (in_array($action, ['community_messages','edit_community_msg','new_community_msg'])) {
    $r = $db->query("SELECT * FROM community_messages ORDER BY sort_order ASC, created_at DESC");
    if ($r) while ($row = $r->fetch(PDO::FETCH_ASSOC)) $community_msgs[] = $row;
}
$edit_community_msg = null;
if ($action === 'edit_community_msg') {
    $eid = (int)($_GET['id'] ?? 0);
    foreach ($community_msgs as $m) if ($m['id'] == $eid) { $edit_community_msg = $m; break; }
}

/* ── Funeral notices list ── */
$funeral_list = [];
if (in_array($action, ['funeral_notices','edit_funeral','new_funeral'])) {
    $r = $db->query("SELECT * FROM funeral_notices ORDER BY created_at DESC");
    if ($r) while ($row = $r->fetch(PDO::FETCH_ASSOC)) $funeral_list[] = $row;
}
$edit_funeral = null;
if ($action === 'edit_funeral') {
    $eid = (int)($_GET['id'] ?? 0);
    foreach ($funeral_list as $f) if ($f['id'] == $eid) { $edit_funeral = $f; break; }
}

/* ── Ticker messages list ── */
$ticker_list = [];
if (in_array($action, ['ticker_messages','edit_ticker','new_ticker'])) {
    $r = $db->query("SELECT * FROM ticker_messages ORDER BY sort_order ASC, created_at DESC");
    if ($r) while ($row = $r->fetch(PDO::FETCH_ASSOC)) $ticker_list[] = $row;
}
$edit_ticker = null;
if ($action === 'edit_ticker') {
    $eid = (int)($_GET['id'] ?? 0);
    foreach ($ticker_list as $t) if ($t['id'] == $eid) { $edit_ticker = $t; break; }
}

/* ── Ramadan schedule + override rows ── */
$ramadan_schedule = null;
$ramadan_rows     = [];
if (in_array($action, ['ramadan','ramadan_setup','ramadan_save_grid','ramadan_toggle','ramadan_reset'])) {
    $rs = $db->query("SELECT * FROM ramadan_schedule WHERE id='1' LIMIT 1");
    if ($rs) $ramadan_schedule = $rs->fetch(PDO::FETCH_ASSOC);
    if ($ramadan_schedule) {
        /* Auto-deactivate if expired */
        if ($ramadan_schedule['is_active'] && date('Y-m-d') > $ramadan_schedule['end_date']) {
            $db->exec("UPDATE ramadan_schedule SET is_active=0, updated_at=datetime('now') WHERE id='1'");
            $ramadan_schedule['is_active'] = 0;
        }
        /* Load all override rows ordered by date — SQLite uses strftime instead of MONTH()/DAY() */
        $rr = $db->query("SELECT ro.*, pst.fajr AS orig_fajr, pst.zuhr AS orig_zuhr,
                                         pst.asr AS orig_asr, pst.esha AS orig_esha
                                  FROM ramadan_override ro
                                  LEFT JOIN perpetual_salaah_times pst
                                         ON CAST(strftime('%m', ro.prayer_date) AS INTEGER) = CAST(pst.month AS INTEGER)
                                        AND CAST(strftime('%d', ro.prayer_date) AS INTEGER) = CAST(pst.date AS INTEGER)
                                  ORDER BY ro.prayer_date ASC");
        if ($rr) while ($row = $rr->fetch(PDO::FETCH_ASSOC)) $ramadan_rows[] = $row;
    }
}

/* ══════════════════════════════════════════════
   FLASH MESSAGES
   ══════════════════════════════════════════════ */
$flash_success = getFlash('success');
$flash_error   = getFlash('error');

/* ══════════════════════════════════════════════
   TIME FIELDS META
   Only Jamaat times are editable. All other
   columns are read-only (displayed for reference).
   ══════════════════════════════════════════════ */

// Editable jamaat fields only
$jamaat_fields = [
        ['key'=>'fajr',    'label'=>'Fajr',    'icon'=>'🌅'],
        ['key'=>'zuhr',    'label'=>'Zuhr',    'icon'=>'☀️'],
        ['key'=>'asr',     'label'=>'Asr',     'icon'=>'🌤️'],
        ['key'=>'maghrib', 'label'=>'Maghrib', 'icon'=>'🌇'],
        ['key'=>'esha',    'label'=>'Esha',    'icon'=>'🌃'],
];

// Read-only reference fields (displayed but not editable)
$readonly_fields = [
        ['key'=>'sehri_ends',   'label'=>'Sehri Ends',           'icon'=>'🌙'],
        ['key'=>'e_fajr',       'label'=>'Fajr Earliest',        'icon'=>'🌅'],
        ['key'=>'sunrise',      'label'=>'Sunrise',              'icon'=>'🌄'],
        ['key'=>'zawaal',       'label'=>'Zawaal',               'icon'=>'🕛'],
        ['key'=>'e_zuhr',       'label'=>'Zuhr Earliest',        'icon'=>'☀️'],
        ['key'=>'e_asr_hanafi', 'label'=>'Asr Earliest (Hanafi)','icon'=>'🌤️'],
        ['key'=>'e_asr_shafi',  'label'=>'Asr Earliest (Shafi)', 'icon'=>'🌤️'],
        ['key'=>'sunset',       'label'=>'Sunset / Iftaar',      'icon'=>'🌆'],
        ['key'=>'e_esha',       'label'=>'Esha Earliest',        'icon'=>'🌃'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#07170A">
    <title>MDS Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
            --red-bg:      rgba(192,57,43,0.15);
            --red-border:  rgba(192,57,43,0.35);
            --card-bg:     rgba(16,46,22,0.82);
            --card-border: rgba(201,168,76,0.22);
            --shadow:      0 8px 32px rgba(0,0,0,0.45);
            --input-bg:    rgba(7,23,10,0.7);
            --input-border:rgba(201,168,76,0.28);
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        html{scroll-behavior:smooth;}
        body{
            background-color:var(--green-dark);
            color:var(--cream);
            font-family:'Nunito',sans-serif;
            min-height:100vh;
            overflow-x:hidden;
        }
        body::before{
            content:'';
            position:fixed;
            inset:0;
            background-image:
                    radial-gradient(ellipse 90% 55% at 50% -5%,rgba(201,168,76,0.12) 0%,transparent 65%),
                    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Cg fill='none' stroke='rgba(201,168,76,0.045)' stroke-width='1'%3E%3Cpath d='M40 4 L76 40 L40 76 L4 40Z'/%3E%3Cpath d='M40 18 L62 40 L40 62 L18 40Z'/%3E%3Ccircle cx='40' cy='40' r='10'/%3E%3C/g%3E%3C/svg%3E");
            background-size:auto,80px 80px;
            pointer-events:none;
            z-index:0;
        }

        /* ── Layout ── */
        .layout{display:flex;min-height:100vh;position:relative;z-index:1;}

        /* ── Sidebar ── */
        .sidebar{
            width:240px;
            flex-shrink:0;
            background:rgba(7,23,10,0.92);
            border-right:1px solid var(--card-border);
            display:flex;
            flex-direction:column;
            padding:0 0 24px;
            backdrop-filter:blur(16px);
            position:sticky;
            top:0;
            height:100vh;
            overflow-y:auto;
        }
        .sidebar-logo{
            padding:28px 22px 22px;
            border-bottom:1px solid var(--card-border);
            text-align:center;
        }
        .sidebar-mosque{font-size:36px;display:block;animation:glow-pulse 3.5s ease-in-out infinite;}
        @keyframes glow-pulse{
            0%,100%{filter:drop-shadow(0 0 8px rgba(201,168,76,0.35));}
            50%{filter:drop-shadow(0 0 20px rgba(201,168,76,0.8));}
        }
        .sidebar-brand{
            font-family:'Cinzel Decorative',serif;
            font-size:11px;
            color:var(--gold);
            letter-spacing:1.5px;
            margin-top:8px;
            line-height:1.4;
        }
        .sidebar-sub{font-size:9px;color:var(--gold-dim);letter-spacing:3px;text-transform:uppercase;}
        .sidebar-user{
            padding:16px 20px;
            border-bottom:1px solid var(--card-border);
            font-size:11px;
            color:var(--cream-dim);
        }
        .sidebar-user strong{display:block;color:var(--gold-light);font-size:13px;margin-bottom:2px;}
        .badge-role{
            display:inline-block;
            font-size:8px;
            letter-spacing:1.5px;
            text-transform:uppercase;
            padding:2px 8px;
            border-radius:20px;
            background:rgba(201,168,76,0.15);
            border:1px solid rgba(201,168,76,0.3);
            color:var(--gold);
        }
        .nav{padding:16px 0;}
        .nav-label{font-size:8px;letter-spacing:3px;text-transform:uppercase;color:var(--gold-dim);padding:6px 22px 4px;}
        .nav a{
            display:flex;
            align-items:center;
            gap:10px;
            padding:10px 22px;
            color:var(--cream-dim);
            text-decoration:none;
            font-size:13px;
            font-weight:600;
            letter-spacing:0.3px;
            transition:background 0.2s,color 0.2s;
            border-left:2px solid transparent;
        }
        .nav a:hover{background:rgba(201,168,76,0.07);color:var(--cream);}
        .nav a.active{background:rgba(201,168,76,0.1);color:var(--gold-light);border-left-color:var(--gold);}
        .nav-icon{font-size:16px;width:20px;text-align:center;flex-shrink:0;}
        .sidebar-footer{margin-top:auto;padding:16px 22px;}
        .btn-logout{
            display:flex;align-items:center;gap:8px;
            width:100%;padding:9px 14px;
            background:var(--red-bg);border:1px solid var(--red-border);
            color:var(--red-soft);font-family:'Nunito',sans-serif;font-size:12px;font-weight:700;
            border-radius:10px;cursor:pointer;text-decoration:none;
            transition:background 0.2s;
        }
        .btn-logout:hover{background:rgba(192,57,43,0.25);}

        /* ── Main Content ── */
        .main{flex:1;padding:32px 32px 80px;overflow-x:hidden;}
        .page-header{margin-bottom:28px;}
        .page-title{
            font-family:'Cinzel Decorative',serif;
            font-size:clamp(16px,2.5vw,22px);
            color:var(--gold);
            letter-spacing:1.5px;
            margin-bottom:4px;
        }
        .page-desc{font-size:12px;color:var(--cream-dim);letter-spacing:1px;}
        .gold-rule{height:1px;background:linear-gradient(to right,transparent,var(--gold),transparent);margin:14px 0 24px;}

        /* ── Cards ── */
        .card{
            background:var(--card-bg);
            border:1px solid var(--card-border);
            border-radius:18px;
            overflow:hidden;
            backdrop-filter:blur(10px);
            box-shadow:var(--shadow);
            animation:fadein 0.6s ease both;
        }
        @keyframes fadein{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
        .card-top-rule{height:2px;background:linear-gradient(to right,transparent,var(--gold),transparent);}
        .card-body{padding:28px 30px;}

        /* ── Forms ── */
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
        .form-group{display:flex;flex-direction:column;gap:5px;}
        .form-group.full{grid-column:1/-1;}
        .form-label{
            font-size:10px;
            text-transform:uppercase;
            letter-spacing:2px;
            color:var(--gold-dim);
            font-weight:700;
        }
        .form-input, .form-select{
            background:var(--input-bg);
            border:1px solid var(--input-border);
            border-radius:10px;
            color:var(--cream);
            font-family:'Nunito',sans-serif;
            font-size:14px;
            padding:10px 14px;
            transition:border-color 0.2s,box-shadow 0.2s;
            width:100%;
            outline:none;
        }
        .form-input:focus,.form-select:focus{
            border-color:var(--gold);
            box-shadow:0 0 0 3px rgba(201,168,76,0.12);
        }
        .form-input.time-input{font-variant-numeric:tabular-nums;letter-spacing:2px;font-size:15px;font-weight:700;}
        .form-select option{background:var(--green-mid);}

        .group-label{
            font-size:9px;letter-spacing:3px;text-transform:uppercase;
            color:var(--gold-dim);
            grid-column:1/-1;
            padding-top:8px;
            border-top:1px solid rgba(201,168,76,0.12);
            margin-top:4px;
        }
        .group-label:first-child{border-top:none;margin-top:0;padding-top:0;}

        /* ── Buttons ── */
        .btn{
            display:inline-flex;align-items:center;gap:7px;
            padding:10px 22px;border-radius:30px;
            font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;
            letter-spacing:0.5px;border:none;cursor:pointer;
            transition:transform 0.15s,box-shadow 0.15s,opacity 0.15s;
            text-decoration:none;
        }
        .btn:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,0.35);}
        .btn-primary{background:linear-gradient(135deg,var(--gold-dim),var(--gold));color:var(--green-dark);}
        .btn-secondary{background:rgba(201,168,76,0.1);border:1px solid var(--card-border);color:var(--cream);}
        .btn-danger{background:var(--red-bg);border:1px solid var(--red-border);color:var(--red-soft);}
        .btn-sm{padding:6px 14px;font-size:11px;}

        /* ── Alert ── */
        .alert{
            border-radius:12px;padding:14px 18px;margin-bottom:22px;
            font-size:13px;font-weight:600;
            display:flex;align-items:flex-start;gap:10px;
            animation:fadein 0.4s ease both;
        }
        .alert-success{background:rgba(27,94,53,0.35);border:1px solid rgba(27,94,53,0.6);color:#7FD499;}
        .alert-error  {background:var(--red-bg);border:1px solid var(--red-border);color:var(--red-soft);}
        .alert ul{margin:6px 0 0 16px;}
        .alert li{margin-bottom:2px;}

        /* ── Table ── */
        .table-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:13px;}
        thead th{
            padding:10px 14px;
            text-align:left;
            font-size:9px;
            letter-spacing:2px;
            text-transform:uppercase;
            color:var(--gold-dim);
            border-bottom:1px solid var(--card-border);
            white-space:nowrap;
        }
        tbody tr{border-bottom:1px solid rgba(201,168,76,0.07);transition:background 0.15s;}
        tbody tr:hover{background:rgba(201,168,76,0.05);}
        tbody td{padding:12px 14px;vertical-align:middle;}
        .td-icon{font-size:18px;}
        .status-dot{
            display:inline-block;width:8px;height:8px;border-radius:50%;
            margin-right:5px;vertical-align:middle;
        }
        .dot-green{background:#4CAF50;}
        .dot-red  {background:#E07070;}

        /* ── Date Picker Tabs ── */
        .date-picker{
            display:flex;flex-wrap:wrap;gap:10px;align-items:center;
            background:rgba(7,23,10,0.6);
            border:1px solid var(--card-border);
            border-radius:14px;
            padding:18px 22px;
            margin-bottom:22px;
        }
        .date-picker label{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);font-weight:700;}
        .date-picker .form-select{max-width:180px;}

        /* ── Month tabs ── */
        .month-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px;}
        .month-tab{
            padding:5px 12px;border-radius:20px;
            font-size:11px;font-weight:700;letter-spacing:0.5px;
            cursor:pointer;text-decoration:none;
            border:1px solid rgba(201,168,76,0.2);
            color:var(--cream-dim);background:transparent;
            transition:all 0.15s;
        }
        .month-tab:hover,.month-tab.active{background:rgba(201,168,76,0.15);border-color:var(--gold);color:var(--gold);}

        /* ── Password strength meter ── */
        .pw-meter{margin-top:6px;}
        .pw-bar{height:4px;border-radius:4px;background:rgba(255,255,255,0.1);overflow:hidden;}
        .pw-fill{height:100%;width:0%;border-radius:4px;transition:width 0.3s,background 0.3s;}
        .pw-hint{font-size:10px;color:var(--cream-dim);margin-top:4px;}

        /* ── Toggle checkbox ── */
        .toggle-wrap{display:flex;align-items:center;gap:10px;}
        .toggle{position:relative;width:40px;height:22px;}
        .toggle input{opacity:0;width:0;height:0;}
        .toggle-slider{
            position:absolute;inset:0;border-radius:22px;
            background:rgba(255,255,255,0.1);
            cursor:pointer;transition:background 0.3s;
        }
        .toggle-slider::before{
            content:'';position:absolute;
            width:16px;height:16px;border-radius:50%;
            bottom:3px;left:3px;
            background:var(--cream-dim);transition:transform 0.3s,background 0.3s;
        }
        .toggle input:checked + .toggle-slider{background:var(--green);}
        .toggle input:checked + .toggle-slider::before{transform:translateX(18px);background:var(--gold);}
        .toggle-label{font-size:12px;color:var(--cream-dim);}

        /* ── Login page specific ── */
        .login-wrap{
            min-height:100vh;display:flex;align-items:center;justify-content:center;
            padding:20px;position:relative;z-index:1;
        }
        .login-box{width:100%;max-width:420px;}
        .login-hero{text-align:center;margin-bottom:28px;}
        .login-mosque{font-size:54px;animation:glow-pulse 3.5s ease-in-out infinite;}
        .login-title{
            font-family:'Cinzel Decorative',serif;
            font-size:clamp(14px,3.5vw,22px);color:var(--gold);
            letter-spacing:2px;margin:8px 0 2px;
        }
        .login-sub{font-size:9px;color:var(--cream-dim);letter-spacing:4px;text-transform:uppercase;}
        .login-rule{width:100px;height:1px;background:linear-gradient(to right,transparent,var(--gold),transparent);margin:14px auto 0;}

        /* ── View toggle buttons ── */
        .view-toggle-btn {
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--cream-dim);
            text-decoration: none;
            background: transparent;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .view-toggle-btn:hover { background: rgba(201,168,76,0.08); color: var(--cream); text-decoration: none; }
        .view-toggle-btn.active { background: rgba(201,168,76,0.18); color: var(--gold-light); }

        /* ── Grid table ── */
        .grid-table-wrap {
            overflow-x: auto;
            margin: 0 -4px;
            padding: 0 4px;
            /* max height with scroll for long months */
            max-height: 68vh;
            overflow-y: auto;
            border: 1px solid var(--card-border);
            border-radius: 12px;
        }
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 620px;
        }
        .grid-table thead {
            position: sticky;
            top: 0;
            z-index: 2;
            background: rgba(7,23,10,0.97);
            backdrop-filter: blur(10px);
        }
        .grid-table thead th {
            padding: 10px 10px;
            text-align: center;
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold-dim);
            border-bottom: 2px solid var(--card-border);
            white-space: nowrap;
        }
        .grid-table thead th:first-child { text-align: left; padding-left: 14px; }
        .grid-table tbody tr {
            border-bottom: 1px solid rgba(201,168,76,0.06);
            transition: background 0.12s;
        }
        .grid-table tbody tr:hover { background: rgba(201,168,76,0.05); }
        .grid-table tbody td { padding: 6px 8px; text-align: center; vertical-align: middle; }
        .grid-table tbody td:first-child { text-align: left; padding-left: 14px; }

        .col-day { min-width: 80px; }
        .col-action { width: 54px; }

        /* Day column */
        .day-num {
            font-size: 15px;
            font-weight: 800;
            color: var(--cream);
            font-variant-numeric: tabular-nums;
            margin-right: 5px;
        }
        .day-dow {
            font-size: 10px;
            color: var(--gold-dim);
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .today-pip {
            display: inline-block;
            font-size: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
            background: var(--gold);
            color: var(--green-dark);
            padding: 1px 6px;
            border-radius: 10px;
            font-weight: 800;
            margin-left: 5px;
            vertical-align: middle;
        }
        .grid-today td { background: rgba(201,168,76,0.07); }

        /* Grid time inputs — compact */
        .grid-time-input {
            background: var(--input-bg);
            border: 1px solid rgba(201,168,76,0.2);
            border-radius: 7px;
            color: var(--cream);
            font-family: 'Nunito', sans-serif;
            font-size: 13px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            letter-spacing: 1px;
            padding: 5px 7px;
            width: 90px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            text-align: center;
        }
        .grid-time-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 2px rgba(201,168,76,0.15);
        }

        /* Flash animation for copy-down */
        @keyframes grid-flash-anim {
            0%   { background: rgba(201,168,76,0.22); }
            100% { background: transparent; }
        }
        .grid-flash { animation: grid-flash-anim 0.6s ease both; }

        @media(max-width:780px){
            .layout{flex-direction:column;}
            .sidebar{width:100%;height:auto;position:relative;flex-direction:row;flex-wrap:wrap;}
            .main{padding:20px 16px 60px;}
            .form-grid,.form-grid-3{grid-template-columns:1fr;}
            .form-group.full{grid-column:1;}
            .day-two-col { grid-template-columns: 1fr !important; }
        }
        @media(max-width:520px){
            .sidebar{flex-direction:column;}
            .card-body{padding:20px 18px;}
        }
    </style>
    <?php if (($site['active_theme'] ?? 'green') !== 'green') echo nmcRenderThemeCSS($site); ?>
</head>
<body>

<?php if (!isLoggedIn() || $action === 'login'): ?>
    <!-- ══════════════════════════════════════════════
     LOGIN PAGE
     ══════════════════════════════════════════════ -->
    <div class="login-wrap">
        <div class="login-box">
            <div class="login-hero">
                <span class="login-mosque">🕌</span>
                <h1 class="login-title"><?= e($site_name) ?> Admin</h1>
                <p class="login-sub">Secure Access Portal</p>
                <div class="login-rule"></div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <span>⚠️</span>
                    <ul><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-top-rule"></div>
                <div class="card-body">
                    <form method="POST" action="admin.php?action=login" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                        <div class="form-grid" style="grid-template-columns:1fr;">
                            <div class="form-group">
                                <label class="form-label" for="username">Username</label>
                                <input class="form-input" type="text" id="username" name="username"
                                       value="<?= e($_POST['username'] ?? '') ?>"
                                       autocomplete="username" required autofocus>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="password">Password</label>
                                <input class="form-input" type="password" id="password" name="password"
                                       autocomplete="current-password" required>
                            </div>
                            <div style="padding-top:6px;">
                                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                                    🔐 Sign In
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <p style="text-align:center;margin-top:20px;font-size:11px;color:var(--gold-dim);">
                Accounts lock for 15 minutes after 5 failed attempts.
            </p>
            <div style="text-align:center;font-size:10px;color:var(--gold-dim);margin-top:30px;opacity:0.5;line-height:1.5;">
                Musjid Display System (MDS) &copy; 2026 Muhammed Cotwal<br>
                All Rights Reserved &middot; <a href="https://github.com/muhammedc/mds" target="_blank" style="color:inherit;text-decoration:none;">github.com/muhammedc/mds</a>
                <br>v<?= getSystemVersion() ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ══════════════════════════════════════════════
     AUTHENTICATED LAYOUT
     ══════════════════════════════════════════════ -->
    <div class="layout">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="sidebar-mosque">🕌</span>
                <div class="sidebar-brand"><?= e($site_name) ?></div>
                <div class="sidebar-sub">Admin Panel</div>
            </div>

            <div class="sidebar-user">
                <strong><?= e($_SESSION['admin_username']) ?></strong>
                <span class="badge-role"><?= e($_SESSION['admin_role']) ?></span>
            </div>

            <nav class="nav">
                <div class="nav-label">Navigation</div>
                <a href="admin.php?action=dashboard" class="<?= $action==='dashboard'?'active':'' ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="admin.php?action=times" class="<?= $action==='times'||$action==='save_times'?'active':'' ?>">
                    <span class="nav-icon">🕐</span> Salaah Times
                </a>
                <div class="nav-label" style="margin-top:8px;">Display Content</div>
                <a href="admin.php?action=community_messages" class="<?= in_array($action,['community_messages','edit_community_msg','new_community_msg'])?'active':'' ?>">
                    <span class="nav-icon">📢</span> Community Messages
                </a>
                <a href="admin.php?action=funeral_notices" class="<?= in_array($action,['funeral_notices','edit_funeral','new_funeral'])?'active':'' ?>">
                    <span class="nav-icon">⚰️</span> Funeral Notices
                </a>
                <a href="admin.php?action=ticker_messages" class="<?= in_array($action,['ticker_messages','edit_ticker','new_ticker'])?'active':'' ?>">
                    <span class="nav-icon">📰</span> Ticker Messages
                </a>
                <a href="admin.php?action=hijri_date" class="<?= in_array($action,['hijri_date','save_hijri_offset'])?'active':'' ?>">
                    <span class="nav-icon">🌙</span> Hijri Date
                </a>
                <a href="admin.php?action=jummah" class="<?= in_array($action,['jummah','save_jummah'])?'active':'' ?>">
                    <span class="nav-icon">🕌</span> Jummah Times
                </a>
                <a href="admin.php?action=ramadan" class="<?= in_array($action,['ramadan','ramadan_setup','ramadan_save_grid','ramadan_toggle','ramadan_reset'])?'active':'' ?>"><?php
                    /* Show active badge on nav if Ramadan override is live */
                    $ram_nav_check = @$db->query("SELECT is_active, end_date FROM ramadan_schedule WHERE id='1' LIMIT 1");
                    $ram_nav = $ram_nav_check ? $ram_nav_check->fetch(PDO::FETCH_ASSOC) : null;
                    $ram_nav_active = $ram_nav && $ram_nav['is_active'] && date('Y-m-d') <= $ram_nav['end_date'];
                    ?>
                    <span class="nav-icon">☪️</span> Ramadan Override<?php if ($ram_nav_active): ?> <span style="margin-left:auto;background:#2ecc71;color:#07170A;font-size:7px;font-weight:900;letter-spacing:1px;text-transform:uppercase;padding:2px 6px;border-radius:10px;">LIVE</span><?php endif; ?>
                </a>
                <a href="admin.php?action=themes" class="<?= in_array($action,['themes','save_theme'])?'active':'' ?>">
                    <span class="nav-icon">🎨</span> Themes
                </a>
                <?php if (isSuperAdmin()): ?>
                    <div class="nav-label" style="margin-top:8px;">Administration</div>
                    <a href="admin.php?action=users" class="<?= in_array($action,['users','edit_user','new_user'])?'active':'' ?>">
                        <span class="nav-icon">👥</span> Users
                    </a>
                    <a href="admin.php?action=restore" class="<?= $action==='restore'||$action==='do_restore'?'active':'' ?>"
                       style="color:var(--red-soft);">
                        <span class="nav-icon">🛟</span> Restore Backup
                    </a>
                    <a href="admin.php?action=settings" class="<?= in_array($action,['settings','save_settings'])?'active':'' ?>">
                        <span class="nav-icon">⚙️</span> Site Settings
                    </a>
                    <a href="#" onclick="askMusjidLayout(event, true)" style="color:var(--gold);">
                        <span class="nav-icon">🧪</span> Debug Sim Tool (Musjid)
                    </a>
                    <a href="index.php?sim=1&sim_date=<?= date('Y-m-d') ?>&sim_time=<?= date('H:i') ?>:00"
                       target="_blank" style="color:var(--gold);">
                        <span class="nav-icon">🧪</span> Debug Sim Tool (Website)
                    </a>
                <?php endif; ?>
                <div class="nav-label" style="margin-top:8px;">Site</div>
                <a href="index.php" target="_blank">
                    <span class="nav-icon">🌐</span> View Normal Site
                </a>
                <a href="#" onclick="askMusjidLayout(event, false)">
                    <span class="nav-icon">📺</span> View Musjid Site
                </a>
                <script>
                    function askMusjidLayout(e, isSim) {
                        e.preventDefault();
                        let choice = prompt("Which display layout do you want to open?\n\nEnter 1 for Musjid 1 (Classic)\nEnter 2 for Musjid 2 (New Layout)", "1");
                        if (choice === "1" || choice === "2") {
                            let url = "index.php?display=musjid" + choice;
                            if (isSim) {
                                url += "&sim=1&sim_date=<?= date('Y-m-d') ?>&sim_time=<?= date('H:i') ?>:00";
                            }
                            window.open(url, "_blank");
                        }
                    }
                </script>
                <a href="hadith.php" target="_blank">
                    <span class="nav-icon">📖</span> Daily Hadith
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="admin.php?action=logout" class="btn-logout">🚪 Sign Out</a>
                <div style="font-size:9px;color:var(--gold-dim);margin-top:16px;text-align:center;opacity:0.5;line-height:1.4;">
                    MDS &copy; 2026<br>Muhammed Cotwal<br>
                    <span style="opacity:0.7;">v<?= getSystemVersion() ?></span>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <main class="main">

            <?php if ($flash_success): ?>
                <div class="alert alert-success">✅ <?= e($flash_success) ?></div>
            <?php endif; ?>
            <?php if ($flash_error): ?>
                <div class="alert alert-error">⚠️ <?= e($flash_error) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <span>⚠️</span>
                    <ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <?php /* ── DASHBOARD ── */ if ($action === 'dashboard'): ?>

                <div class="page-header">
                    <div class="page-title">📊 Dashboard</div>
                    <div class="page-desc">Welcome back, <?= e($_SESSION['admin_username']) ?>. Here's a full system overview.</div>
                </div>
                <div class="gold-rule"></div>

            <?php
            /* ── Gather all dashboard data ── */
            $today_m  = (int)date('n');
            $today_d  = (int)date('j');
            $now_sql  = date('Y-m-d H:i:s');
            $soon_sql = date('Y-m-d H:i:s', strtotime('+48 hours'));

            /* Today's salaah times */
            $tres       = $db->query("SELECT * FROM perpetual_salaah_times WHERE month='$today_m' AND date='$today_d' LIMIT 1");
            $today_times = $tres ? $tres->fetch(PDO::FETCH_ASSOC) : null;

            /* Hijri date (reuse offset from $site) */
            function dash_gregorianToHijri($y,$m,$d){
                $jd=$l=$n=$j=0;
                $jd=gregoriantojd($m,$d,$y);
                $l=$jd-1948440+10632; $n=(int)(($l-1)/10631);
                $l=$l-10631*$n+354;
                $j=(int)((10985-$l)/5316)*(int)((50*$l)/17719)+(int)($l/5670)*(int)((43*$l)/15238);
                $l=$l-(int)((30-$j)/15)*(int)((17719*$j)/50)-(int)($j/16)*(int)((15238*$j)/43)+29;
                $hm=(int)((24*$l)/709); $hd=$l-(int)((709*$hm)/24); $hy=30*$n+$j-30;
                return[$hy,$hm,$hd];
            }
            $hijriNames=[1=>'Muharram',2=>'Safar',3=>"Rabi' al-Awwal",4=>"Rabi' al-Thani",
                    5=>"Jumada al-Awwal",6=>"Jumada al-Thani",7=>'Rajab',8=>"Sha'ban",
                    9=>'Ramadan',10=>'Shawwal',11=>"Dhu al-Qi'dah",12=>'Dhu al-Hijjah'];
            $h_off    = (int)($site['hijri_offset'] ?? 0);
            $h_ts     = time() + $h_off * 86400;
            [$hy,$hm,$hd] = dash_gregorianToHijri((int)date('Y',$h_ts),(int)date('n',$h_ts),(int)date('j',$h_ts));
            $hijri_str = "$hd {$hijriNames[$hm]} $hy AH";

            /* Madhab */
            $madhab_label = ucfirst($site['madhab'] ?? 'hanafi');
            $madhab_icon  = '🕌';

            /* Content counts — helper */
            function dash_counts($db, $table, $now_sql, $soon_sql) {
                $out = ['total'=>0,'active'=>0,'inactive'=>0,'expired'=>0,'upcoming'=>0,'expiring'=>0];
                $r = @$db->query("SELECT is_active, start_dt, end_dt FROM `$table`");
                if (!$r) return $out;
                while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
                    $out['total']++;
                    $started  = !$row['start_dt'] || $row['start_dt'] <= $now_sql;
                    $not_exp  = !$row['end_dt']   || $row['end_dt']   >= $now_sql;
                    $expired  = $row['end_dt']    && $row['end_dt']   < $now_sql;
                    $expiring = $row['end_dt']    && $row['end_dt']   >= $now_sql && $row['end_dt'] <= $soon_sql;
                    $future   = $row['start_dt']  && $row['start_dt'] > $now_sql;
                    if ($row['is_active'] && $started && $not_exp) $out['active']++;
                    elseif (!$row['is_active'])                    $out['inactive']++;
                    if ($expired)  $out['expired']++;
                    if ($expiring) $out['expiring']++;
                    if ($future)   $out['upcoming']++;
                }
                return $out;
            }
            $cm_c  = dash_counts($db, 'community_messages', $now_sql, $soon_sql);
            $fn_c  = dash_counts($db, 'funeral_notices',    $now_sql, $soon_sql);
            $tk_c  = dash_counts($db, 'ticker_messages',    $now_sql, $soon_sql);

            /* Admin users */
            $u_total = $u_active = $u_super = 0;
            $ur = @$db->query("SELECT role, is_active FROM admin_users");
            if ($ur) while ($row = $ur->fetch(PDO::FETCH_ASSOC)) {
                $u_total++;
                if ($row['is_active'])       $u_active++;
                if ($row['role']==='superadmin') $u_super++;
            }

            /* Salaah times coverage — count months with data */
            $cov_r = @$db->query("SELECT COUNT(*) AS cnt FROM perpetual_salaah_times");
            $cov_cnt = $cov_r ? (int)$cov_r->fetch(PDO::FETCH_ASSOC)['cnt'] : 0;

            /* Last times edit — perpetual_salaah_times has no updated_at column in this DB */
            $last_times_edit = null;

            /* Jummah times */
            try { $db->exec("ALTER TABLE jummah_settings ADD COLUMN talk_by TEXT DEFAULT ''"); } catch(Exception $e) {}
            $dash_jum = ['azaan'=>'—','khutbah'=>'—','jamaat'=>'—','talk_by'=>'','updated_at'=>null];
            $jqr = @$db->query("SELECT azaan_time, khutbah_time, jamaat_time, talk_by, updated_at FROM jummah_settings WHERE id='1' LIMIT 1");
            if ($jqr && $jqrow = $jqr->fetch(PDO::FETCH_ASSOC)) {
                $dash_jum = [
                        'azaan'      => substr($jqrow['azaan_time'],   0, 5),
                        'khutbah'    => substr($jqrow['khutbah_time'], 0, 5),
                        'jamaat'     => substr($jqrow['jamaat_time'],  0, 5),
                        'talk_by'    => $jqrow['talk_by'] ?? '',
                        'updated_at' => $jqrow['updated_at'],
                ];
            }
            $today_is_friday   = (date('N') == 5); // 5 = Friday
            $today_is_thursday = (date('N') == 4);
            ?>

                <!-- ══════════════════════════════════════════════════════
     ROW 1 — Today's snapshot (date / time / Hijri)
     ══════════════════════════════════════════════════════ -->
                <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:var(--gold-dim);
            font-weight:700;margin-bottom:10px;">📅 Today</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:28px;">
                    <?php
                    $today_cards = [
                            ['icon'=>'📅','label'=>'Gregorian Date', 'value'=>date('d M Y')],
                            ['icon'=>'🕐','label'=>'Current Time',   'value'=>'<span id="db-clock">'.date('H:i:s').'</span>'],
                            ['icon'=>'🌙','label'=>'Hijri Date',      'value'=>$hijri_str . ($h_off != 0 ? ' <span style="font-size:11px;color:var(--gold-dim);">('.($h_off>0?'+':'').$h_off.'d)</span>' : '')],
                            ['icon'=>$madhab_icon,'label'=>'Active Madhab','value'=>$madhab_label,
                                    'sub'=>'Asr boundary: '.($madhab_label==='Hanafi'?'e_asr_hanafi (later)':'e_asr_shafi (earlier)'),
                                    'link'=>isSuperAdmin()?'admin.php?action=settings':null],
                    ];
                    foreach ($today_cards as $i=>$c):
                        ?>
                        <div class="card" style="animation-delay:<?= $i*0.05 ?>s;<?= !empty($c['link']) ? 'cursor:pointer;' : '' ?>"
                                <?= !empty($c['link']) ? 'onclick="location.href=\''.$c['link'].'\'"' : '' ?>>
                            <div class="card-top-rule"></div>
                            <div class="card-body" style="padding:18px 22px;">
                                <div style="font-size:22px;margin-bottom:6px;"><?= $c['icon'] ?></div>
                                <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);"><?= e($c['label']) ?></div>
                                <div style="font-size:18px;font-weight:800;color:var(--gold-light);letter-spacing:1px;margin-top:4px;line-height:1.3;"><?= $c['value'] ?></div>
                                <?php if (!empty($c['sub'])): ?>
                                    <div style="font-size:10px;color:var(--cream-dim);margin-top:5px;"><?= e($c['sub']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ══════════════════════════════════════════════════════
     ROW 2 — Today's Jamaat Times
     ══════════════════════════════════════════════════════ -->
            <?php if ($today_times): ?>
                <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:var(--gold-dim);
            font-weight:700;margin-bottom:10px;">🕌 Today's Jamaat Times</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:28px;">
                    <?php
                    $jamaat_cards = [
                            ['icon'=>'🌅','label'=>'Fajr',    'value'=>substr($today_times['fajr'],0,-3)],
                            ['icon'=>'☀️','label'=>'Zuhr',    'value'=>substr($today_times['zuhr'],0,-3)],
                            ['icon'=>'🌤️','label'=>'Asr',     'value'=>substr($today_times['asr'],0,-3)],
                            ['icon'=>'🌆','label'=>'Maghrib', 'value'=>substr($today_times['sunset'],0,-3)],
                            ['icon'=>'🌃','label'=>'Esha',    'value'=>substr($today_times['esha'],0,-3)],
                    ];
                    foreach ($jamaat_cards as $i=>$c):
                        ?>
                        <div class="card" style="animation-delay:<?= $i*0.05 ?>s;">
                            <div class="card-top-rule" style="<?= !empty($c['dim']) ? 'opacity:0.4;' : '' ?>"></div>
                            <div class="card-body" style="padding:16px 18px;">
                                <div style="font-size:20px;margin-bottom:4px;"><?= $c['icon'] ?></div>
                                <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;
                                        color:<?= !empty($c['dim']) ? 'var(--cream-dim)' : 'var(--gold-dim)' ?>;"><?= e($c['label']) ?></div>
                                <div style="font-size:24px;font-weight:800;color:<?= !empty($c['dim']) ? 'var(--cream-dim)' : 'var(--gold-light)' ?>;
                                        letter-spacing:2px;margin-top:3px;font-variant-numeric:tabular-nums;"><?= e($c['value']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

                <!-- ══════════════════════════════════════════════════════
     ROW 3 — Content Overview (3 wide cards side by side)
     ══════════════════════════════════════════════════════ -->
                <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:var(--gold-dim);
            font-weight:700;margin-bottom:10px;">📋 Display Content</div>

            <?php
            /* Reusable: render a content stat card */
            function dash_content_card($icon, $label, $link, $counts, $delay) {
                $soon_note = $counts['expiring'] > 0
                        ? '<span style="color:#e0a800;font-size:10px;font-weight:700;">⚠️ '.$counts['expiring'].' expiring within 48h</span>'
                        : '';
                echo '<div class="card" style="animation-delay:'.$delay.'s;cursor:pointer;" onclick="location.href=\''.e($link).'\'">';
                echo '<div class="card-top-rule"></div>';
                echo '<div class="card-body" style="padding:20px 24px;">';
                echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">';
                echo '<span style="font-size:26px;">'.$icon.'</span>';
                echo '<div>';
                echo '<div style="font-family:\'Cinzel Decorative\',serif;font-size:12px;color:var(--gold);">'.$label.'</div>';
                echo '<div style="font-size:10px;color:var(--cream-dim);">Click to manage</div>';
                echo '</div></div>';

                /* Big active number */
                echo '<div style="font-size:36px;font-weight:900;color:var(--gold-light);letter-spacing:2px;margin-bottom:10px;font-variant-numeric:tabular-nums;">'
                        .$counts['active'].'</div>';
                echo '<div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);margin-bottom:14px;">Active</div>';

                /* Stat row */
                $pills = [
                        ['Total',    $counts['total'],    '#c8a84b'],
                        ['Active',   $counts['active'],   '#7FD499'],
                        ['Inactive', $counts['inactive'], 'var(--cream-dim)'],
                        ['Expired',  $counts['expired'],  'var(--red-soft)'],
                        ['Scheduled',$counts['upcoming'], '#a0c4ff'],
                ];
                echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">';
                foreach ($pills as [$pl, $pv, $pc]) {
                    if ($pl !== 'Total' && $pv === 0) continue; // hide zero sub-stats except Total
                    echo '<div style="background:rgba(0,0,0,0.3);border:1px solid rgba(200,168,75,0.2);
                          border-radius:20px;padding:3px 10px;font-size:10px;color:'.$pc.';">'
                            .'<strong>'.$pv.'</strong> '.$pl.'</div>';
                }
                echo '</div>';
                if ($soon_note) echo '<div style="margin-top:4px;">'.$soon_note.'</div>';
                echo '</div></div>';
            }
            ?>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-bottom:28px;">
                    <?php
                    dash_content_card('📢','Community Messages','admin.php?action=community_messages',$cm_c,0);
                    dash_content_card('⚰️','Funeral Notices',   'admin.php?action=funeral_notices',   $fn_c,0.05);
                    dash_content_card('📰','Ticker Messages',   'admin.php?action=ticker_messages',   $tk_c,0.10);
                    ?>
                </div>

                <!-- ══════════════════════════════════════════════════════
     ROW 4 — System Info
     ══════════════════════════════════════════════════════ -->
                <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:var(--gold-dim);
            font-weight:700;margin-bottom:10px;">⚙️ System</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:28px;">

                    <!-- Salaah Times Records -->
                    <div class="card" style="cursor:pointer;" onclick="location.href='admin.php?action=times'">
                        <div class="card-top-rule"></div>
                        <div class="card-body" style="padding:18px 22px;">
                            <div style="font-size:22px;margin-bottom:6px;">🕐</div>
                            <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);">Salaah Time Records</div>
                            <div style="font-size:28px;font-weight:900;color:var(--gold-light);letter-spacing:2px;margin-top:4px;font-variant-numeric:tabular-nums;"><?= $cov_cnt ?></div>
                            <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">of 365/366 days populated</div>
                            <?php if ($last_times_edit): ?>
                                <div style="font-size:10px;color:var(--gold-dim);margin-top:8px;">Last edit: <?= date('d M Y H:i', strtotime($last_times_edit)) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hijri Offset -->
                    <div class="card" <?= isSuperAdmin() ? 'style="cursor:pointer;" onclick="location.href=\'admin.php?action=hijri_date\'"' : '' ?>>
                        <div class="card-top-rule"></div>
                        <div class="card-body" style="padding:18px 22px;">
                            <div style="font-size:22px;margin-bottom:6px;">🌙</div>
                            <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);">Hijri Date Offset</div>
                            <div style="font-size:28px;font-weight:900;color:var(--gold-light);letter-spacing:2px;margin-top:4px;">
                                <?= $h_off >= 0 ? '+' : '' ?><?= $h_off ?>d
                            </div>
                            <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">
                                <?= $h_off === 0 ? 'No adjustment — calculated date' : ($h_off > 0 ? 'Shifted forward (moon sighted early)' : 'Shifted back (moon sighted late)') ?>
                            </div>
                            <div style="font-size:10px;color:var(--gold-dim);margin-top:8px;">Today: <?= $hijri_str ?></div>
                        </div>
                    </div>

                    <?php
                    // Force a fresh fetch right before rendering to guarantee 'talk_by' is caught
                    $dash_talk_by = '';
                    try { $db->exec("ALTER TABLE jummah_settings ADD COLUMN talk_by TEXT DEFAULT ''"); } catch(Exception $e) {}
                    $tq = @$db->query("SELECT talk_by FROM jummah_settings WHERE id='1' LIMIT 1");
                    if ($tq && $tr = $tq->fetch(PDO::FETCH_ASSOC)) { $dash_talk_by = $tr['talk_by']; }
                    ?>
                    <div class="card" style="cursor:pointer;<?= $today_is_friday ? 'border-color:var(--gold);box-shadow:0 0 20px rgba(201,168,76,0.15);' : '' ?>"
                         onclick="location.href='admin.php?action=jummah'">
                        <div class="card-top-rule"></div>
                        <div class="card-body" style="padding:18px 22px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:22px;">🕌</span>
                                <?php if ($today_is_friday): ?>
                                    <span style="background:var(--gold);color:#0a1f0d;border-radius:20px;padding:2px 10px;font-size:10px;font-weight:700;">Today</span>
                                <?php elseif ($today_is_thursday): ?>
                                    <span style="background:rgba(168,130,20,0.25);color:#d4aa30;border:1px solid rgba(168,130,20,0.5);border-radius:20px;padding:2px 10px;font-size:10px;font-weight:700;">Tomorrow</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);margin-bottom:8px;">Jummah Times</div>
                            <div style="display:flex;flex-direction:column;gap:5px;">
                                <div style="display:flex;justify-content:space-between;font-size:11px;">
                                    <span style="color:var(--cream-dim);">📢 Azaan</span>
                                    <strong style="color:var(--gold-light);font-variant-numeric:tabular-nums;"><?= e($dash_jum['azaan']) ?></strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:11px;">
                                    <span style="color:var(--cream-dim);">🎙️ Khutbah</span>
                                    <strong style="color:var(--gold-light);font-variant-numeric:tabular-nums;"><?= e($dash_jum['khutbah']) ?></strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:11px;">
                                    <span style="color:var(--cream-dim);">🕌 Jamaat</span>
                                    <strong style="color:var(--gold-light);font-variant-numeric:tabular-nums;"><?= e($dash_jum['jamaat']) ?></strong>
                                </div>

                                <?php if (!empty($dash_talk_by)): ?>
                                    <div style="display:flex;justify-content:space-between;font-size:11px;border-top:1px solid rgba(201,168,76,0.2);padding-top:5px;margin-top:2px;">
                                        <span style="color:var(--cream-dim);">🗣️ Talk By</span>
                                        <strong style="color:var(--gold-light);"><?= e($dash_talk_by) ?></strong>
                                    </div>
                                <?php endif; ?>

                            </div>
                            <?php if ($dash_jum['updated_at']): ?>
                                <div style="font-size:10px;color:var(--gold-dim);margin-top:10px;">Last updated: <?= date('d M Y', strtotime($dash_jum['updated_at'])) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (isSuperAdmin()): ?>
                        <!-- Admin Users -->
                        <div class="card" style="cursor:pointer;" onclick="location.href='admin.php?action=users'">
                            <div class="card-top-rule"></div>
                            <div class="card-body" style="padding:18px 22px;">
                                <div style="font-size:22px;margin-bottom:6px;">👥</div>
                                <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);">Admin Users</div>
                                <div style="font-size:28px;font-weight:900;color:var(--gold-light);letter-spacing:2px;margin-top:4px;"><?= $u_total ?></div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                                    <div style="background:rgba(0,0,0,0.3);border:1px solid rgba(200,168,75,0.2);
                            border-radius:20px;padding:3px 10px;font-size:10px;color:#7FD499;">
                                        <strong><?= $u_active ?></strong> Active</div>
                                    <div style="background:rgba(0,0,0,0.3);border:1px solid rgba(200,168,75,0.2);
                            border-radius:20px;padding:3px 10px;font-size:10px;color:var(--gold);">
                                        <strong><?= $u_super ?></strong> Superadmin</div>
                                </div>
                            </div>
                        </div>

                        <!-- Site Settings -->
                        <div class="card" style="cursor:pointer;" onclick="location.href='admin.php?action=settings'">
                            <div class="card-top-rule"></div>
                            <div class="card-body" style="padding:18px 22px;">
                                <div style="font-size:22px;margin-bottom:6px;">⚙️</div>
                                <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);">Site Settings</div>
                                <div style="font-size:13px;font-weight:700;color:var(--gold-light);margin-top:8px;word-break:break-word;"><?= e($site['site_name'] ?? '—') ?></div>
                                <div style="font-size:10px;color:var(--cream-dim);margin-top:6px;">
                                    Madhab: <strong style="color:var(--gold);"><?= $madhab_label ?></strong>
                                </div>
                                <div style="font-size:10px;color:var(--cream-dim);margin-top:3px;">
                                    Hijri offset: <strong style="color:var(--gold);"><?= ($h_off>=0?'+':'').$h_off ?>d</strong>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Themes (visible to all admins) -->
                    <div class="card" style="cursor:pointer;" onclick="location.href='admin.php?action=themes'">
                        <div class="card-top-rule"></div>
                        <div class="card-body" style="padding:18px 22px;">
                            <div style="font-size:22px;margin-bottom:6px;">🎨</div>
                            <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);">Themes</div>
                            <?php
                            $theme_labels = ['green'=>'Forest Green','blue'=>'Midnight Blue','burgundy'=>'Royal Burgundy','grey'=>'Slate Grey','white'=>'Ivory Light','custom'=>'Custom'];
                            $cur_theme = $site['active_theme'] ?? 'green';
                            ?>
                            <div style="font-size:13px;font-weight:700;color:var(--gold-light);margin-top:8px;"><?= e($theme_labels[$cur_theme] ?? ucfirst($cur_theme)) ?></div>
                            <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">Active colour theme</div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════
     Quick Actions
     ══════════════════════════════════════════════════════ -->
                <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:var(--gold-dim);
            font-weight:700;margin-bottom:10px;">⚡ Quick Actions</div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:6px;">
                    <a href="admin.php?action=times"             class="btn btn-primary">🕐 Edit Salaah Times</a>
                    <a href="admin.php?action=jummah"            class="btn btn-secondary">🕌 Jummah Times</a>
                    <a href="admin.php?action=ramadan"           class="btn btn-secondary">☪️ Ramadan Override</a>
                    <a href="admin.php?action=new_community_msg" class="btn btn-secondary">➕ New Community Message</a>
                    <a href="admin.php?action=new_funeral"       class="btn btn-secondary">⚰️ New Funeral Notice</a>
                    <a href="admin.php?action=new_ticker"        class="btn btn-secondary">📰 New Ticker</a>
                    <a href="admin.php?action=themes"            class="btn btn-secondary">🎨 Themes</a>
                    <?php if(isSuperAdmin()): ?>
                        <a href="admin.php?action=new_user"          class="btn btn-secondary">👤 Add Admin User</a>
                    <?php endif; ?>
                    <a href="index.php"  target="_blank" class="btn btn-secondary">🌐 View Site</a>
                    <a href="hadith.php" target="_blank" class="btn btn-secondary">📖 Daily Hadith</a>
                </div>

            <?php
            /* ── Ramadan status widget on dashboard ── */
            $db_ram = @$db->query("SELECT is_active, start_date, end_date FROM ramadan_schedule WHERE id='1' LIMIT 1");
            $db_ram_row = $db_ram ? $db_ram->fetch(PDO::FETCH_ASSOC) : null;
            if ($db_ram_row):
            $db_ram_active  = (int)$db_ram_row['is_active'] === 1;
            $db_ram_today   = date('Y-m-d');
            $db_ram_expired = $db_ram_today > $db_ram_row['end_date'];
            $db_ram_live    = $db_ram_active && !$db_ram_expired && $db_ram_today >= $db_ram_row['start_date'];
            if ($db_ram_live):
            ?>
                <div style="margin-top:14px;background:rgba(46,204,113,0.1);border:1px solid rgba(46,204,113,0.35);border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;">🌙</span>
                        <div>
                            <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#2ecc71;font-weight:700;">Ramadan Override is LIVE</div>
                            <div style="font-size:12px;color:var(--cream-dim);margin-top:2px;">
                                Fajr, Zuhr, Asr &amp; Esha are using Ramadan times until <?= date('j M Y', strtotime($db_ram_row['end_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <a href="admin.php?action=ramadan" class="btn" style="background:rgba(46,204,113,0.15);border:1px solid rgba(46,204,113,0.4);color:#2ecc71;padding:8px 18px;font-size:12px;">Manage →</a>
                </div>
            <?php elseif ($db_ram_active && !$db_ram_expired): ?>
                <div style="margin-top:14px;background:rgba(241,196,15,0.08);border:1px solid rgba(241,196,15,0.3);border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;">⏳</span>
                        <div>
                            <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#f1c40f;font-weight:700;">Ramadan Override Scheduled</div>
                            <div style="font-size:12px;color:var(--cream-dim);margin-top:2px;">
                                Active from <?= date('j M Y', strtotime($db_ram_row['start_date'])) ?> to <?= date('j M Y', strtotime($db_ram_row['end_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <a href="admin.php?action=ramadan" class="btn btn-secondary" style="padding:8px 18px;font-size:12px;">Manage →</a>
                </div>
            <?php endif; ?>
            <?php endif; ?>

                <script>
                    /* Live clock on dashboard */
                    (function(){
                        const el = document.getElementById('db-clock');
                        if (!el) return;
                        function tick(){
                            const n=new Date();
                            const pad=v=>String(v).padStart(2,'0');
                            el.textContent=pad(n.getHours())+':'+pad(n.getMinutes())+':'+pad(n.getSeconds());
                        }
                        setInterval(tick,1000); tick();
                    })();
                </script>


            <?php /* ── SALAAH TIMES EDITOR ── */ elseif ($action === 'times' || $action === 'save_times' || $action === 'save_grid'): ?>

                <div class="page-header">
                    <div class="page-title">🕐 Jamaat Times Editor</div>
                    <div class="page-desc">Edit the five daily Jamaat times. All other calculated times are read-only.</div>
                </div>
                <div class="gold-rule"></div>

            <?php $months = ['January','February','March','April','May','June','July','August','September','October','November','December']; ?>

                <!-- Controls bar: month/date selectors + view toggle -->
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:20px;">

                    <!-- Month selector -->
                    <form method="GET" action="admin.php" id="navForm" style="display:contents;">
                        <input type="hidden" name="action" value="times">
                        <input type="hidden" name="view"   id="navView" value="<?= e($edit_view) ?>">
                        <input type="hidden" name="date"   id="navDate" value="<?= $edit_date ?>">

                        <div class="date-picker" style="margin-bottom:0;flex:1;min-width:260px;">
                            <label>Month</label>
                            <select class="form-select" name="month" onchange="document.getElementById('navForm').submit()">
                                <?php for ($m=1;$m<=12;$m++): ?>
                                    <option value="<?=$m?>" <?=$m===$edit_month?'selected':''?>><?= $months[$m-1] ?></option>
                                <?php endfor; ?>
                            </select>

                            <?php if ($edit_view === 'day'): ?>
                                <label style="margin-left:10px;">Date</label>
                                <select class="form-select" name="date" onchange="document.getElementById('navForm').submit()" style="max-width:90px;">
                                    <?php for($d=1;$d<=31;$d++): ?>
                                        <option value="<?=$d?>" <?=$d===$edit_date?'selected':''?>><?=$d?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- View toggle -->
                    <div style="display:flex;gap:0;border:1px solid var(--card-border);border-radius:30px;overflow:hidden;flex-shrink:0;">
                        <a href="admin.php?action=times&view=day&month=<?=$edit_month?>&date=<?=$edit_date?>"
                           class="view-toggle-btn <?= $edit_view==='day'?'active':'' ?>">📋 Day</a>
                        <a href="admin.php?action=times&view=grid&month=<?=$edit_month?>"
                           class="view-toggle-btn <?= $edit_view==='grid'?'active':'' ?>">🗓️ Month Grid</a>
                    </div>
                </div>

            <?php if ($edit_view === 'day'): /* ═══ DAY VIEW ═══ */
            if (!$times_row): ?>
                <div class="alert alert-error">⚠️ No record found for <?= $months[$edit_month-1] ?> <?= $edit_date ?>.</div>
            <?php else: ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;" class="day-two-col">

                    <!-- LEFT: Editable Jamaat Times -->
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body">
                            <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:20px;">
                                ✏️ Editing Jamaat — <?= $months[$edit_month-1] ?> <?= $edit_date ?>
                            </div>

                            <form method="POST" action="admin.php?action=save_times">
                                <input type="hidden" name="csrf"  value="<?= $_SESSION['csrf'] ?>">
                                <input type="hidden" name="month" value="<?= $edit_month ?>">
                                <input type="hidden" name="date"  value="<?= $edit_date ?>">
                                <input type="hidden" name="view"  value="day">

                                <div style="display:flex;flex-direction:column;gap:14px;">
                                    <?php foreach ($jamaat_fields as $jf):
                                        $raw = $times_row[$jf['key']] ?? '00:00:00';
                                        $val = substr($raw, 0, 5);
                                        ?>
                                        <div class="form-group">
                                            <label class="form-label"><?= $jf['icon'] ?> <?= e($jf['label']) ?> Jamaat</label>
                                            <input type="time" class="form-input time-input"
                                                   name="<?= $jf['key'] ?>" value="<?= e($val) ?>" required>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div style="display:flex;gap:10px;margin-top:24px;flex-wrap:wrap;">
                                    <button type="submit" class="btn btn-primary">💾 Save Jamaat Times</button>
                                    <a href="admin.php?action=times&month=<?=$edit_month?>&date=<?=$edit_date?>&view=day"
                                       class="btn btn-secondary">↺ Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- RIGHT: Read-only reference times -->
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body">
                            <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold-dim);margin-bottom:20px;">
                                🔒 Reference Times (read-only)
                            </div>
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                <?php foreach ($readonly_fields as $rf):
                                    $raw = $times_row[$rf['key']] ?? '00:00:00';
                                    $val = substr($raw, 0, 5);
                                    ?>
                                    <div style="display:flex;justify-content:space-between;align-items:center;
                            padding:8px 12px;background:rgba(7,23,10,0.5);
                            border:1px solid rgba(201,168,76,0.1);border-radius:8px;">
                                        <span style="font-size:12px;color:var(--cream-dim);"><?= $rf['icon'] ?> <?= e($rf['label']) ?></span>
                                        <span style="font-size:15px;font-weight:700;color:var(--cream);
                                 font-variant-numeric:tabular-nums;letter-spacing:1px;"><?= e($val) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div><!-- /two-col -->

            <?php endif; /* end day view */ ?>

            <?php else: /* ═══ GRID VIEW ═══ */ ?>

                <?php
// How many days in this month (use current year for leap year accuracy)
                $days_in_month = cal_days_in_month(CAL_GREGORIAN, $edit_month, date('Y'));
                ?>

                <div class="card" style="overflow:visible;">
                    <div class="card-top-rule"></div>
                    <div class="card-body" style="padding:22px 18px;">

                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:18px;">
            <span style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);">
                🗓️ <?= $months[$edit_month-1] ?> — All <?= $days_in_month ?> Days
            </span>
                            <span style="font-size:11px;color:var(--gold-dim);">Edit any row then click Save Grid</span>
                        </div>

                        <form method="POST" action="admin.php?action=save_grid" id="gridForm">
                            <input type="hidden" name="csrf"  value="<?= $_SESSION['csrf'] ?>">
                            <input type="hidden" name="month" value="<?= $edit_month ?>">

                            <div class="grid-table-wrap">
                                <table class="grid-table">
                                    <thead>
                                    <tr>
                                        <th class="col-day">Day</th>
                                        <?php foreach ($jamaat_fields as $jf): ?>
                                            <th><?= $jf['icon'] ?> <?= e($jf['label']) ?></th>
                                        <?php endforeach; ?>
                                        <th class="col-action">Quick</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php for ($d = 1; $d <= $days_in_month; $d++):
                                        $row = $month_rows[$d] ?? null;
                                        $dow = $row ? date('D', mktime(0,0,0,$edit_month,$d,date('Y'))) : '–';
                                        $isToday = ($d === (int)date('j') && $edit_month === (int)date('n'));
                                        ?>
                                        <tr class="grid-row <?= $isToday ? 'grid-today' : '' ?>" data-day="<?=$d?>">
                                            <td class="col-day">
                                                <span class="day-num"><?= $d ?></span>
                                                <span class="day-dow"><?= $dow ?></span>
                                                <?php if ($isToday): ?><span class="today-pip">Today</span><?php endif; ?>
                                            </td>
                                            <?php foreach ($jamaat_fields as $jf):
                                                $raw = $row[$jf['key']] ?? '00:00:00';
                                                $val = substr($raw, 0, 5);
                                                ?>
                                                <td>
                                                    <?php if ($row): ?>
                                                        <input type="time" class="grid-time-input"
                                                               name="times[<?=$d?>][<?= $jf['key'] ?>]"
                                                               value="<?= e($val) ?>" required>
                                                    <?php else: ?>
                                                        <span style="color:var(--gold-dim);font-size:11px;">–</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="col-action">
                                                <?php if ($row): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm copy-down-btn"
                                                            title="Copy this row's times to all rows below"
                                                            data-day="<?=$d?>">↓</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div><!-- /grid-table-wrap -->

                            <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;align-items:center;">
                                <button type="submit" class="btn btn-primary">💾 Save Entire Grid</button>
                                <a href="admin.php?action=times&view=grid&month=<?=$edit_month?>" class="btn btn-secondary">↺ Reset</a>
                                <span style="font-size:11px;color:var(--gold-dim);margin-left:auto;">
                    <?= count($month_rows) ?> / <?= $days_in_month ?> days loaded from DB
                </span>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    /* ── Copy-down button: copies a row's times to all rows below it ── */
                    document.querySelectorAll('.copy-down-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const fromDay = parseInt(btn.dataset.day);
                            const fromRow = document.querySelector(`.grid-row[data-day="${fromDay}"]`);
                            const fromInputs = [...fromRow.querySelectorAll('input[type=time]')];
                            const fromVals = fromInputs.map(i => i.value);

                            document.querySelectorAll('.grid-row').forEach(row => {
                                const d = parseInt(row.dataset.day);
                                if (d <= fromDay) return;
                                const inputs = [...row.querySelectorAll('input[type=time]')];
                                inputs.forEach((inp, idx) => { if (fromVals[idx]) inp.value = fromVals[idx]; });
                                // Flash the row
                                row.classList.add('grid-flash');
                                setTimeout(() => row.classList.remove('grid-flash'), 600);
                            });
                        });
                    });
                </script>

            <?php endif; /* end grid view */ ?>


            <?php /* ── USERS LIST ── */ elseif ($action === 'users' && isSuperAdmin()): ?>

                <div class="page-header">
                    <div class="page-title">👥 Admin Users</div>
                    <div class="page-desc">Manage administrator accounts. Passwords must meet strict security requirements.</div>
                </div>
                <div class="gold-rule"></div>

                <div style="margin-bottom:18px;">
                    <a href="admin.php?action=new_user" class="btn btn-primary">➕ Add New User</a>
                </div>

                <div class="card">
                    <div class="card-top-rule"></div>
                    <div class="card-body" style="padding:0;">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($users_list as $u): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:var(--cream);"><?= e($u['username']) ?></strong>
                                            <?php if ($u['id'] == $_SESSION['admin_id']): ?>
                                                <span style="font-size:9px;background:rgba(201,168,76,0.15);border:1px solid rgba(201,168,76,0.3);color:var(--gold);padding:1px 7px;border-radius:20px;margin-left:6px;">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:var(--cream-dim);font-size:12px;"><?= e($u['email'] ?? '–') ?></td>
                                        <td>
                                            <span class="badge-role"><?= e($u['role']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($u['locked_until'] && strtotime($u['locked_until']) > time()): ?>
                                                <span class="status-dot" style="background:#E07070;"></span>
                                                <span style="color:var(--red-soft);font-size:11px;">Locked</span>
                                            <?php elseif (!$u['is_active']): ?>
                                                <span class="status-dot dot-red"></span>
                                                <span style="color:var(--cream-dim);font-size:11px;">Inactive</span>
                                            <?php else: ?>
                                                <span class="status-dot dot-green"></span>
                                                <span style="color:#7FD499;font-size:11px;">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:var(--cream-dim);font-size:12px;">
                                            <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                <a href="admin.php?action=edit_user&uid=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                                <?php if ($u['locked_until'] && strtotime($u['locked_until']) > time()): ?>
                                                    <a href="admin.php?action=unlock_user&uid=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" style="color:#7FD499;">🔓</a>
                                                <?php endif; ?>
                                                <?php if ($u['id'] != $_SESSION['admin_id']): ?>
                                                    <a href="admin.php?action=delete_user&uid=<?= $u['id'] ?>"
                                                       class="btn btn-danger btn-sm"
                                                       onclick="return confirm('Delete user <?= e($u['username']) ?>? This cannot be undone.')">🗑️</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


            <?php /* ── USER FORM (New / Edit) ── */
            elseif (($action === 'new_user' || $action === 'edit_user') && isSuperAdmin()):
            $is_new = ($action === 'new_user');
            $eu = $edit_user ?? [];
            ?>

                <div class="page-header">
                    <div class="page-title"><?= $is_new ? '➕ New Admin User' : '✏️ Edit User: ' . e($eu['username']??'') ?></div>
                    <div class="page-desc">
                        <?= $is_new ? 'Create a new administrator account.' : 'Update user details. Leave password blank to keep current.' ?>
                    </div>
                </div>
                <div class="gold-rule"></div>

                <div class="card">
                    <div class="card-top-rule"></div>
                    <div class="card-body">
                        <form method="POST" action="admin.php?action=save_user" autocomplete="off" id="userForm">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <input type="hidden" name="uid"  value="<?= $is_new ? 0 : (int)$eu['id'] ?>">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="uname">Username</label>
                                    <input class="form-input" type="text" id="uname" name="username"
                                           value="<?= e($eu['username']??($_POST['username']??'')) ?>"
                                           autocomplete="off" required pattern="[a-zA-Z0-9_]{3,30}"
                                           title="3–30 alphanumeric characters or underscore">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="email">Email Address</label>
                                    <input class="form-input" type="email" id="email" name="email"
                                           value="<?= e($eu['email']??($_POST['email']??'')) ?>"
                                           autocomplete="off">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="new_password">
                                        <?= $is_new ? 'Password' : 'New Password' ?> <?= $is_new ? '(required)' : '(leave blank to keep)' ?>
                                    </label>
                                    <input class="form-input" type="password" id="new_password" name="new_password"
                                           autocomplete="new-password" <?= $is_new ? 'required' : '' ?>>
                                    <div class="pw-meter">
                                        <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
                                        <div class="pw-hint" id="pwHint">Min 10 chars · Uppercase · Lowercase · Number · Special char</div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirm Password</label>
                                    <input class="form-input" type="password" id="confirm_password" name="confirm_password"
                                           autocomplete="new-password">
                                    <div class="pw-hint" id="pwMatch" style="margin-top:6px;font-size:11px;"></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="role">Role</label>
                                    <select class="form-select" id="role" name="role">
                                        <option value="admin" <?= ($eu['role']??'admin')==='admin'?'selected':'' ?>>Admin</option>
                                        <option value="superadmin" <?= ($eu['role']??'')==='superadmin'?'selected':'' ?>>Super Admin</option>
                                    </select>
                                </div>

                                <div class="form-group" style="justify-content:flex-end;padding-bottom:4px;">
                                    <label class="form-label">Account Status</label>
                                    <div class="toggle-wrap" style="margin-top:6px;">
                                        <label class="toggle">
                                            <input type="checkbox" name="is_active" value="1"
                                                    <?= ($eu['is_active']??1) ? 'checked' : '' ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Account Active</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Password policy box -->
                            <div style="background:rgba(7,23,10,0.5);border:1px solid var(--card-border);border-radius:12px;padding:16px 20px;margin:20px 0;font-size:12px;">
                                <div style="color:var(--gold-dim);letter-spacing:2px;text-transform:uppercase;font-size:9px;font-weight:700;margin-bottom:10px;">🔒 Password Policy</div>
                                <div id="pwReqs" style="display:grid;grid-template-columns:1fr 1fr;gap:5px 16px;color:var(--cream-dim);">
                                    <span id="req-len">⬜ At least 10 characters</span>
                                    <span id="req-upper">⬜ Uppercase letter</span>
                                    <span id="req-lower">⬜ Lowercase letter</span>
                                    <span id="req-num">⬜ Digit (0–9)</span>
                                    <span id="req-sym">⬜ Special character</span>
                                </div>
                            </div>

                            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:10px;">
                                <button type="submit" class="btn btn-primary">💾 <?= $is_new ? 'Create User' : 'Update User' ?></button>
                                <a href="admin.php?action=users" class="btn btn-secondary">← Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    const pwInput  = document.getElementById('new_password');
                    const pw2Input = document.getElementById('confirm_password');
                    const pwFill   = document.getElementById('pwFill');
                    const pwHint   = document.getElementById('pwHint');
                    const pwMatch  = document.getElementById('pwMatch');

                    const reqs = {
                        len:   { el: document.getElementById('req-len'),   re: /^.{10,}$/    },
                        upper: { el: document.getElementById('req-upper'), re: /[A-Z]/       },
                        lower: { el: document.getElementById('req-lower'), re: /[a-z]/       },
                        num:   { el: document.getElementById('req-num'),   re: /[0-9]/       },
                        sym:   { el: document.getElementById('req-sym'),   re: /[^A-Za-z0-9]/},
                    };

                    function updateStrength() {
                        const pw = pwInput.value;
                        let score = 0;
                        for (const k in reqs) {
                            const pass = reqs[k].re.test(pw);
                            reqs[k].el.textContent = (pass ? '✅' : '⬜') + ' ' + reqs[k].el.textContent.slice(2);
                            if (pass) score++;
                        }
                        const pct = (score / 5) * 100;
                        const colors = ['#E07070','#E09040','#E8C97A','#7FD499','#4CAF50'];
                        const labels = ['Very Weak','Weak','Fair','Strong','Very Strong'];
                        pwFill.style.width = pct + '%';
                        pwFill.style.background = colors[score-1] || '#E07070';
                        pwHint.textContent = pw.length ? labels[score-1] || '' : 'Min 10 chars · Uppercase · Lowercase · Number · Special char';
                        pwHint.style.color = score >= 4 ? '#7FD499' : 'var(--cream-dim)';
                    }

                    function updateMatch() {
                        const pw = pwInput.value, pw2 = pw2Input.value;
                        if (!pw2) { pwMatch.textContent = ''; return; }
                        if (pw === pw2) {
                            pwMatch.textContent = '✅ Passwords match';
                            pwMatch.style.color = '#7FD499';
                        } else {
                            pwMatch.textContent = '❌ Passwords do not match';
                            pwMatch.style.color = 'var(--red-soft)';
                        }
                    }

                    pwInput.addEventListener('input', () => { updateStrength(); updateMatch(); });
                    pw2Input.addEventListener('input', updateMatch);
                </script>



            <?php /* ── SITE SETTINGS ── */
            elseif (in_array($action, ['settings','save_settings']) && isSuperAdmin()):

            // Fetch updated_at for the site_name setting
            $su = $db->query("SELECT updated_at FROM site_settings WHERE setting_key='site_name' LIMIT 1");
            $setting_meta = $su ? $su->fetch(PDO::FETCH_ASSOC) : null;
            ?>

                <div class="page-header">
                    <div class="page-title">⚙️ Site Settings</div>
                    <div class="page-desc">Superadmin only. Changes here are reflected immediately across the site.</div>
                </div>
                <div class="gold-rule"></div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;" class="day-two-col">

                    <!-- Settings form -->
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body">
                            <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:20px;">
                                🏷️ Site Identity
                            </div>

                            <form method="POST" action="admin.php?action=save_settings" autocomplete="off">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

                                <div class="form-group" style="margin-bottom:20px;">
                                    <label class="form-label" for="site_name_input">Community / Organisation Name</label>
                                    <input class="form-input" type="text" id="site_name_input" name="site_name"
                                           value="<?= e($site['site_name']) ?>"
                                           maxlength="120" required
                                           oninput="updatePreviews(this.value)"
                                           placeholder="e.g. Newcastle Muslim Community">
                                    <div style="display:flex;justify-content:space-between;margin-top:5px;">
                                        <span style="font-size:10px;color:var(--gold-dim);">Used across the admin panel, Salaah Times page, and Daily Hadith page.</span>
                                        <span id="charCount" style="font-size:10px;color:var(--gold-dim);"><?= mb_strlen($site['site_name']) ?>/120</span>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom:20px;">
                                    <label class="form-label" for="site_url_input">Site URL (Optional)</label>
                                    <input class="form-input" type="url" id="site_url_input" name="site_url"
                                           value="<?= e($site['site_url'] ?? '') ?>"
                                           placeholder="e.g. https://www.masjid-name.org.za">
                                    <div style="font-size:10px;color:var(--gold-dim);margin-top:5px;">
                                        Used in the "Share Hadith" text to link back to your site.
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom:20px;padding-top:16px;border-top:1px solid rgba(201,168,76,0.1);">
                                    <div style="display:flex;justify-content:space-between;align-items:center;">
                                        <div>
                                            <label class="form-label" style="margin-bottom:4px;">Remove Copyright Notice</label>
                                            <div style="font-size:10px;color:var(--cream-dim);">
                                                Hide the "Musjid Display System" copyright footer on the public site and the ticker message on the display.
                                            </div>
                                        </div>
                                        <div class="toggle-wrap">
                                            <label class="toggle">
                                                <input type="checkbox" name="remove_copyright" value="1" <?= !empty($site['remove_copyright']) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($setting_meta): ?>
                                    <div style="font-size:10px;color:var(--gold-dim);margin-bottom:18px;">
                                        Last updated: <?= date('d M Y H:i', strtotime($setting_meta['updated_at'])) ?>
                                    </div>
                                <?php endif; ?>

                                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <button type="submit" class="btn btn-primary">💾 Save Settings</button>
                                    <button type="button" class="btn btn-secondary"
                                            onclick="document.getElementById('site_name_input').value='<?= e(addslashes($site['site_name'])) ?>';updatePreviews('<?= e(addslashes($site['site_name'])) ?>')">
                                        ↺ Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Live preview panel -->
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body">
                            <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold-dim);margin-bottom:18px;">
                                👁️ Live Preview
                            </div>

                            <!-- Salaah Times page preview -->
                            <div style="background:rgba(7,23,10,0.7);border:1px solid var(--card-border);
                        border-radius:12px;padding:16px 18px;margin-bottom:14px;">
                                <div style="font-size:9px;letter-spacing:2px;text-transform:uppercase;
                            color:var(--gold-dim);margin-bottom:10px;">🕌 Salaah Times page</div>
                                <div style="text-align:center;">
                                    <div style="font-size:22px;margin-bottom:4px;">🕌</div>
                                    <div style="font-family:'Cinzel Decorative',serif;font-size:12px;color:var(--gold);
                                letter-spacing:1px;" id="prev_salaah"><?= e($site['site_name']) ?></div>
                                    <div style="font-size:9px;color:var(--cream-dim);letter-spacing:3px;
                                text-transform:uppercase;margin-top:2px;">Salaah Times</div>
                                </div>
                            </div>

                            <!-- Hadith page preview -->
                            <div style="background:rgba(7,23,10,0.7);border:1px solid var(--card-border);
                        border-radius:12px;padding:16px 18px;margin-bottom:14px;">
                                <div style="font-size:9px;letter-spacing:2px;text-transform:uppercase;
                            color:var(--gold-dim);margin-bottom:10px;">📖 Daily Hadith page</div>
                                <div style="text-align:center;">
                                    <div style="font-size:22px;margin-bottom:4px;">📖</div>
                                    <div style="font-family:'Cinzel Decorative',serif;font-size:12px;color:var(--gold);
                                letter-spacing:1px;" id="prev_hadith"><?= e($site['site_name']) ?></div>
                                    <div style="font-size:9px;color:var(--cream-dim);letter-spacing:3px;
                                text-transform:uppercase;margin-top:2px;">Daily Hadith</div>
                                </div>
                            </div>

                            <!-- Admin panel preview -->
                            <div style="background:rgba(7,23,10,0.7);border:1px solid var(--card-border);
                        border-radius:12px;padding:16px 18px;">
                                <div style="font-size:9px;letter-spacing:2px;text-transform:uppercase;
                            color:var(--gold-dim);margin-bottom:10px;">🔐 Admin sidebar &amp; login</div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="font-size:22px;">🕌</span>
                                    <div>
                                        <div style="font-family:'Cinzel Decorative',serif;font-size:11px;
                                    color:var(--gold);letter-spacing:1px;" id="prev_admin"><?= e($site['site_name']) ?></div>
                                        <div style="font-size:9px;color:var(--cream-dim);letter-spacing:2px;
                                    text-transform:uppercase;">Admin Panel</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /two-col -->

                      <!-- Madhab Setting -->
                <div class="card" style="margin-top:16px;">
                    <div class="card-top-rule"></div>
                    <div class="card-body">
                        <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:6px;">
                            🕌 Madhab — Asr Timing
                        </div>
                        <div style="font-size:11px;color:var(--cream-dim);margin-bottom:20px;">
                            Determines which Asr earliest time is used as the boundary between Zuhr and Asr prayer windows on the Salaah Times display.
                            <br><strong style="color:var(--gold-dim);">Hanafi</strong> uses the later Asr time &nbsp;|&nbsp; <strong style="color:var(--gold-dim);">Shafi</strong> uses the earlier Asr time.
                        </div>

                        <form method="POST" action="admin.php?action=save_madhab" autocomplete="off">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

                            <div style="display:flex;gap:14px;margin-bottom:22px;flex-wrap:wrap;">
                                <!-- Hanafi -->
                                <label style="flex:1;min-width:160px;cursor:pointer;">
                                    <input type="radio" name="madhab" value="hanafi"
                                            <?= ($site['madhab'] ?? 'hanafi') === 'hanafi' ? 'checked' : '' ?>
                                           style="display:none;" onchange="updateMadhabPreview('hanafi')">
                                    <div class="madhab-opt" id="mopt-hanafi"
                                         style="border:2px solid <?= ($site['madhab'] ?? 'hanafi') === 'hanafi' ? 'var(--gold)' : 'var(--card-border)' ?>;
                                                 border-radius:10px;padding:14px 16px;text-align:center;transition:border .2s;"
                                         onclick="selectMadhab('hanafi')">
                                        <div style="font-size:22px;margin-bottom:6px;">🕌</div>
                                        <div style="font-family:'Cinzel Decorative',serif;font-size:12px;color:var(--gold);">Hanafi</div>
                                        <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">Later Asr earliest<br>(e_asr_hanafi)</div>
                                    </div>
                                </label>
                                <!-- Shafi -->
                                <label style="flex:1;min-width:160px;cursor:pointer;">
                                    <input type="radio" name="madhab" value="shafi"
                                            <?= ($site['madhab'] ?? 'hanafi') === 'shafi' ? 'checked' : '' ?>
                                           style="display:none;" onchange="updateMadhabPreview('shafi')">
                                    <div class="madhab-opt" id="mopt-shafi"
                                         style="border:2px solid <?= ($site['madhab'] ?? 'hanafi') === 'shafi' ? 'var(--gold)' : 'var(--card-border)' ?>;
                                                 border-radius:10px;padding:14px 16px;text-align:center;transition:border .2s;"
                                         onclick="selectMadhab('shafi')">
                                        <div style="font-size:22px;margin-bottom:6px;">🕌</div>
                                        <div style="font-family:'Cinzel Decorative',serif;font-size:12px;color:var(--gold);">Shafi</div>
                                        <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">Earlier Asr earliest<br>(e_asr_shafi)</div>
                                    </div>
                                </label>
                            </div>

                            <div style="font-size:11px;color:var(--cream-dim);margin-bottom:16px;">
                                Currently active: <strong style="color:var(--gold);" id="madhabCurrentLabel"><?= ucfirst($site['madhab'] ?? 'hanafi') ?></strong>
                            </div>

                            <button type="submit" class="btn btn-primary">💾 Save Madhab</button>
                        </form>
                    </div>
                </div>

                <script>
                    function selectMadhab(m) {
                        document.querySelectorAll('input[name="madhab"]').forEach(r => r.checked = (r.value === m));
                        document.querySelectorAll('.madhab-opt').forEach(d => d.style.borderColor = 'var(--card-border)');
                        document.getElementById('mopt-' + m).style.borderColor = 'var(--gold)';
                        document.getElementById('madhabCurrentLabel').textContent = m.charAt(0).toUpperCase() + m.slice(1);
                    }
                </script>
                <div style="background:rgba(7,23,10,0.5);border:1px solid var(--card-border);
            border-radius:12px;padding:18px 22px;margin-top:16px;font-size:12px;color:var(--cream-dim);">
                    <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:var(--gold-dim);
                font-weight:700;margin-bottom:10px;">📍 Where this name appears</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
                        <div>🕌 <strong style="color:var(--cream);">index.php</strong> — page hero heading &amp; meta description</div>
                        <div>📖 <strong style="color:var(--cream);">hadith.php</strong> — page hero heading, meta description &amp; WhatsApp/copy share text</div>
                        <div>🔐 <strong style="color:var(--cream);">admin.php</strong> — login page title &amp; sidebar brand</div>
                        <div>🗄️ <strong style="color:var(--cream);">site_settings</strong> DB table — single source of truth</div>
                    </div>
                </div>

                <script>
                    function updatePreviews(val) {
                        const safe = val || '…';
                        document.getElementById('prev_salaah').textContent = safe;
                        document.getElementById('prev_hadith').textContent = safe;
                        document.getElementById('prev_admin').textContent  = safe;
                        document.getElementById('charCount').textContent   = val.length + '/120';
                    }
                </script>

            <?php /* ── HIJRI DATE OFFSET ── */
            elseif (in_array($action, ['hijri_date','save_hijri_offset'])):
            $current_offset = (int)($site['hijri_offset'] ?? 0);

            function gregorianToHijriAdmin($year, $month, $day) {
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
            $hijriMonthNamesAdmin = [
                    1=>'Muharram', 2=>'Safar', 3=>"Rabi' al-Awwal", 4=>"Rabi' al-Thani",
                    5=>"Jumada al-Awwal", 6=>"Jumada al-Thani", 7=>'Rajab', 8=>"Sha'ban",
                    9=>'Ramadan', 10=>'Shawwal', 11=>"Dhu al-Qi'dah", 12=>'Dhu al-Hijjah'
            ];
            $today_ts = time();
            $preview_dates = [];
            for ($off = -2; $off <= 2; $off++) {
                $ts = $today_ts + ($off * 86400);
                [$hy, $hm, $hd] = gregorianToHijriAdmin((int)date('Y',$ts),(int)date('n',$ts),(int)date('j',$ts));
                $preview_dates[$off] = [
                        'greg'  => date('l jS \of F Y', $ts),
                        'hijri' => $hd . ' ' . $hijriMonthNamesAdmin[$hm] . ' ' . $hy . ' AH'
                ];
            }
            $offset_labels = [-2=>'-2 Days', -1=>'-1 Day', 0=>'No Adjustment (Default)', 1=>'+1 Day', 2=>'+2 Days'];
            ?>

                <div class="page-header">
                    <div class="page-title">🌙 Hijri Date</div>
                    <div class="page-desc">Adjust the Islamic calendar date to match your local moon sighting. The calculated date may differ by ±1–2 days depending on your region.</div>
                </div>
                <div class="gold-rule"></div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;" class="day-two-col">

                    <!-- Offset form -->
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body">
                            <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:20px;">
                                🌙 Hijri Date Adjustment
                            </div>
                            <form method="POST" action="admin.php?action=save_hijri_offset" autocomplete="off">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                <div class="form-group" style="margin-bottom:24px;">
                                    <label class="form-label" for="hijri_offset_sel">Date Offset</label>
                                    <select class="form-select" id="hijri_offset_sel" name="hijri_offset"
                                            onchange="updateHijriPreview(this.value)"
                                            style="font-size:14px;padding:10px 14px;">
                                        <?php foreach ($offset_labels as $val => $lbl): ?>
                                            <option value="<?= $val ?>" <?= $current_offset === $val ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($lbl) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div style="font-size:10px;color:var(--gold-dim);margin-top:6px;">
                                        Subtract days if your community observed the moon earlier; add days if a day later than the calculated date.
                                    </div>
                                </div>

                                <div style="background:rgba(7,23,10,0.8);border:1px solid var(--card-border);
                            border-radius:10px;padding:14px 16px;margin-bottom:20px;" id="hijriLivePrev">
                                    <div style="font-size:9px;letter-spacing:2px;text-transform:uppercase;
                                color:var(--gold-dim);margin-bottom:8px;">⚡ Live Preview — what the site will show</div>
                                    <div style="font-size:11px;color:var(--cream-dim);margin-bottom:4px;" id="prevGreg">
                                        <?= htmlspecialchars($preview_dates[$current_offset]['greg']) ?>
                                    </div>
                                    <div style="font-size:13px;font-weight:700;color:var(--gold);" id="prevHijri">
                                        <?= htmlspecialchars($preview_dates[$current_offset]['hijri']) ?>
                                    </div>
                                </div>

                                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <button type="submit" class="btn btn-primary">💾 Save Offset</button>
                                    <button type="button" class="btn btn-secondary"
                                            onclick="document.getElementById('hijri_offset_sel').value='0';updateHijriPreview(0);">
                                        ↺ Reset to Default
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- All offset options preview -->
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body">
                            <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold-dim);margin-bottom:18px;">
                                📅 All Offset Options — Today
                            </div>
                            <?php foreach ($preview_dates as $off => $pd): ?>
                                <div style="background:<?= $off === $current_offset ? 'rgba(201,168,76,0.12)' : 'rgba(7,23,10,0.6)' ?>;
                                        border:1px solid <?= $off === $current_offset ? 'var(--gold)' : 'var(--card-border)' ?>;
                                        border-radius:10px;padding:12px 14px;margin-bottom:10px;cursor:pointer;transition:all 0.2s;"
                                     onclick="document.getElementById('hijri_offset_sel').value='<?= $off ?>';updateHijriPreview(<?= $off ?>)"
                                     id="offCard_<?= $off + 10 ?>">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <span style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);">
                        <?= htmlspecialchars($offset_labels[$off]) ?>
                    </span>
                                        <?php if ($off === $current_offset): ?>
                                            <span style="font-size:9px;background:var(--gold);color:#0a1f0d;
                                 border-radius:4px;padding:2px 6px;font-weight:700;" id="activeBadge_<?= $off + 10 ?>">ACTIVE</span>
                                        <?php else: ?>
                                            <span style="font-size:9px;" id="activeBadge_<?= $off + 10 ?>"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:10px;color:var(--cream-dim);"><?= htmlspecialchars($pd['greg']) ?></div>
                                    <div style="font-size:12px;font-weight:700;color:var(--gold);"><?= htmlspecialchars($pd['hijri']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <div style="background:rgba(7,23,10,0.5);border:1px solid var(--card-border);
            border-radius:12px;padding:18px 22px;margin-top:16px;font-size:12px;color:var(--cream-dim);">
                    <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:var(--gold-dim);
                font-weight:700;margin-bottom:10px;">📍 Where the Hijri date appears</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
                        <div>📺 <strong style="color:var(--cream);">Musjid Display</strong> — top bar alongside the Gregorian date</div>
                        <div>🌐 <strong style="color:var(--cream);">Normal View</strong> — date bar at the top of the page</div>
                    </div>
                </div>

                <script>
                    const hijriData = <?= json_encode($preview_dates) ?>;
                    function updateHijriPreview(off) {
                        off = parseInt(off);
                        const d = hijriData[off];
                        if (!d) return;
                        document.getElementById('prevGreg').textContent  = d.greg;
                        document.getElementById('prevHijri').textContent = d.hijri;
                        for (let o = -2; o <= 2; o++) {
                            const card  = document.getElementById('offCard_' + (o + 10));
                            const badge = document.getElementById('activeBadge_' + (o + 10));
                            if (!card) continue;
                            if (o === off) {
                                card.style.background   = 'rgba(201,168,76,0.12)';
                                card.style.borderColor  = 'var(--gold)';
                                if (badge) { badge.textContent = 'SELECTED'; badge.style.cssText = 'font-size:9px;background:var(--gold);color:#0a1f0d;border-radius:4px;padding:2px 6px;font-weight:700;'; }
                            } else {
                                card.style.background  = 'rgba(7,23,10,0.6)';
                                card.style.borderColor = 'var(--card-border)';
                                if (badge) { badge.textContent = ''; badge.style.background = ''; }
                            }
                        }
                    }
                </script>

            <?php /* ── JUMMAH TIMES ── */
            elseif (in_array($action, ['jummah','save_jummah'])):
            $jr = @$db->query("SELECT * FROM jummah_settings WHERE id='1' LIMIT 1");
            $jrow = ($jr && $jrow_tmp = $jr->fetch(PDO::FETCH_ASSOC)) ? $jrow_tmp : ['azaan_time'=>'12:20:00','khutbah_time'=>'12:30:00','jamaat_time'=>'13:00:00','updated_at'=>null];
            $j_az = substr($jrow['azaan_time'],0,5);
            $j_kh = substr($jrow['khutbah_time'],0,5);
            $j_jm = substr($jrow['jamaat_time'],0,5);
            ?>

                <div class="page-header">
                    <div class="page-title">🕌 Jummah Times</div>
                    <div class="page-desc">Set the weekly Jummah Azaan, Khutbah and Jamaat times. Update whenever the schedule changes.</div>
                </div>
                <div class="gold-rule"></div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;" class="day-two-col">

                    <!-- Form -->
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body">
                            <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:20px;">
                                🕌 Weekly Jummah Schedule
                            </div>

                            <form method="POST" action="admin.php?action=save_jummah" autocomplete="off">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

                                <div style="display:flex;flex-direction:column;gap:18px;">
                                    <div class="form-group">
                                        <label class="form-label">📢 Azaan Time</label>
                                        <input type="time" class="form-input time-input" name="azaan_time"
                                               value="<?= e($j_az) ?>" required
                                               oninput="updateJumPreview()">
                                        <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">The call to prayer for Jummah</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">🎙️ Khutbah Time</label>
                                        <input type="time" class="form-input time-input" name="khutbah_time"
                                               value="<?= e($j_kh) ?>" required
                                               oninput="updateJumPreview()">
                                        <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">When the Imam begins the sermon</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">🕌 Jamaat Time</label>
                                        <input type="time" class="form-input time-input" name="jamaat_time"
                                               value="<?= e($j_jm) ?>" required
                                               oninput="updateJumPreview()">
                                        <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">When the prayer begins</div>
                                    </div>
                                    <div class="form-group" style="margin-top:8px;padding-top:16px;border-top:1px solid rgba(201,168,76,0.1);">
                                        <label class="form-label">🗣️ Talk By (Optional)</label>
                                        <input type="text" class="form-input" name="talk_by"
                                               value="<?= e($jrow['talk_by'] ?? '') ?>"
                                               oninput="updateJumPreview()" placeholder="e.g. Moulana Ahmed">
                                        <div style="font-size:10px;color:var(--cream-dim);margin-top:4px;">Name of the speaker for the pre-khutbah talk</div>
                                    </div>
                                </div>

                                <div id="jum-error" style="display:none;color:var(--red-soft);font-size:11px;margin-top:14px;"></div>

                                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:24px;">
                                    <button type="submit" class="btn btn-primary" onclick="return validateJum()">💾 Save Jummah Times</button>
                                </div>
                            </form>

                            <?php if ($jrow['updated_at']): ?>
                                <div style="font-size:10px;color:var(--gold-dim);margin-top:18px;">
                                    Last updated: <?= date('d M Y H:i', strtotime($jrow['updated_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Live preview -->
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body">
                            <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold-dim);margin-bottom:18px;">
                                👁️ Friday Card Preview
                            </div>

                            <!-- Preview card mimicking index.php prayer card -->
                            <div style="background:rgba(7,23,10,0.7);border:1px solid var(--gold);border-radius:14px;
                        padding:18px 20px;position:relative;box-shadow:0 0 30px rgba(201,168,76,0.12);">
                <span style="position:absolute;top:12px;right:14px;background:var(--gold);color:#0a1f0d;
                             border-radius:20px;padding:3px 12px;font-size:11px;font-weight:700;">Now</span>
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                                    <span style="font-size:28px;">🕌</span>
                                    <div>
                                        <div style="font-family:'Cinzel Decorative',serif;font-size:15px;color:var(--gold);">Jummah</div>
                                        <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);">Friday Prayer</div>
                                    </div>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:10px;letter-spacing:1.5px;
                            text-transform:uppercase;color:var(--gold-dim);margin-bottom:4px;">
                                    <span>Jamaat Time</span>
                                    <span>Earliest Time</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:10px;">
                                    <div style="text-align:left;">
                                        <div style="font-size:28px;font-weight:800;color:var(--gold-light);font-variant-numeric:tabular-nums;letter-spacing:2px;line-height:1;" id="prev-jamaat"><?= e($j_jm) ?></div>
                                        <div id="prev-talk" style="font-size:11px;color:var(--cream);margin-top:8px;display:<?= !empty($jrow['talk_by']) ? 'block' : 'none' ?>;">Talk by: <?= e($jrow['talk_by'] ?? '') ?></div>
                                    </div>
                                    <div style="text-align:right;">
                                        <!-- earliest comes from DB daily times -->
                                        <div style="display:flex;gap:6px;margin-top:6px;justify-content:flex-end;flex-wrap:wrap;">
                                            <div style="background:rgba(201,168,76,0.12);border:1px solid rgba(201,168,76,0.3);
                                        border-radius:8px;padding:4px 10px;text-align:center;">
                                                <div style="font-size:9px;letter-spacing:1px;text-transform:uppercase;color:var(--gold-dim);">Azaan</div>
                                                <div style="font-size:15px;font-weight:700;color:var(--gold-light);font-variant-numeric:tabular-nums;"
                                                     id="prev-azaan"><?= e($j_az) ?></div>
                                            </div>
                                            <div style="background:rgba(201,168,76,0.12);border:1px solid rgba(201,168,76,0.3);
                                        border-radius:8px;padding:4px 10px;text-align:center;">
                                                <div style="font-size:9px;letter-spacing:1px;text-transform:uppercase;color:var(--gold-dim);">Khutbah</div>
                                                <div style="font-size:15px;font-weight:700;color:var(--gold-light);font-variant-numeric:tabular-nums;"
                                                     id="prev-khutbah"><?= e($j_kh) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top:16px;font-size:11px;color:var(--cream-dim);line-height:1.7;">
                                <div style="color:var(--gold-dim);font-size:9px;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;">📋 Friday Behaviour</div>
                                <div>🟡 <strong style="color:var(--cream);">Thursday after Maghrib</strong> — Zuhr card switches to Jummah, pill shows <span style="background:rgba(168,130,20,0.25);border:1px solid rgba(168,130,20,0.5);border-radius:10px;padding:1px 7px;font-size:10px;color:#d4aa30;">Tomorrow</span></div>
                                <div style="margin-top:5px;">🟢 <strong style="color:var(--cream);">Friday midnight</strong> — auto-reloads, pill changes to <span style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:1px 7px;font-size:10px;color:var(--cream-dim);">Upcoming</span></div>
                                <div style="margin-top:5px;">✅ <strong style="color:var(--cream);">Friday Zuhr window</strong> — card is <span style="background:var(--gold);border-radius:10px;padding:1px 7px;font-size:10px;color:#0a1f0d;">Now</span></div>
                                <div style="margin-top:5px;">🔴 <strong style="color:var(--cream);">Friday after Asr</strong> — reverts to Zuhr label, shows <span style="background:rgba(192,57,43,0.18);border:1px solid rgba(192,57,43,0.35);border-radius:10px;padding:1px 7px;font-size:10px;color:#E07070;">Qadha</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function updateJumPreview() {
                        const az = document.querySelector('[name="azaan_time"]').value;
                        const kh = document.querySelector('[name="khutbah_time"]').value;
                        const jm = document.querySelector('[name="jamaat_time"]').value;
                        const tb = document.querySelector('[name="talk_by"]').value;
                        if (az) document.getElementById('prev-azaan').textContent   = az;
                        if (kh) document.getElementById('prev-khutbah').textContent = kh;
                        if (jm) document.getElementById('prev-jamaat').textContent  = jm;

                        const prevTalk = document.getElementById('prev-talk');
                        if (prevTalk) {
                            prevTalk.textContent = tb ? 'Talk by: ' + tb : '';
                            prevTalk.style.display = tb ? 'block' : 'none';
                        }
                    }
                    function validateJum() {
                        const az = document.querySelector('[name="azaan_time"]').value;
                        const kh = document.querySelector('[name="khutbah_time"]').value;
                        const jm = document.querySelector('[name="jamaat_time"]').value;
                        const err = document.getElementById('jum-error');
                        if (az >= kh || kh >= jm) {
                            err.textContent = '⚠️ Times must be in order: Azaan < Khutbah < Jamaat.';
                            err.style.display = 'block';
                            return false;
                        }
                        err.style.display = 'none';
                        return true;
                    }
                </script>

            <?php /* ── RESTORE FROM BACKUP ── */
            elseif (($action === 'restore' || $action === 'do_restore') && isSuperAdmin()):

            // Fetch row counts for the comparison panel
            $live_count   = 0; $backup_count = 0;
            $rc = $db->query("SELECT COUNT(*) AS c FROM perpetual_salaah_times");
            if ($rc) $live_count = (int)$rc->fetch(PDO::FETCH_ASSOC)['c'];
            $rb = $db->query("SELECT COUNT(*) AS c FROM perpetual_salaah_times_orig_2016");
            if ($rb) $backup_count = (int)$rb->fetch(PDO::FETCH_ASSOC)['c'];

            // Sample a few rows from each table so admin can eyeball them
            function sampleRows($db, $table) {
                $rows = [];
                $r = $db->query("SELECT month, date, fajr, zuhr, asr, maghrib, esha
                             FROM `$table`
                             ORDER BY CAST(month AS INTEGER), CAST(date AS INTEGER)
                             LIMIT 5");
                if ($r) while ($row = $r->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
                return $rows;
            }
            $live_sample   = sampleRows($db, 'perpetual_salaah_times');
            $backup_sample = sampleRows($db, 'perpetual_salaah_times_orig_2016');
            ?>

                <div class="page-header">
                    <div class="page-title" style="color:var(--red-soft);">🛟 Restore From Backup</div>
                    <div class="page-desc">Superadmin only. Overwrites ALL live Salaah times with the original 2016 backup.</div>
                </div>
                <div class="gold-rule"></div>

                <!-- Danger banner -->
                <div style="background:rgba(192,57,43,0.12);border:2px solid rgba(192,57,43,0.5);
            border-radius:16px;padding:20px 24px;margin-bottom:24px;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <span style="font-size:32px;">⚠️</span>
                        <div>
                            <div style="font-family:'Cinzel Decorative',serif;font-size:14px;color:var(--red-soft);letter-spacing:1px;">
                                Destructive Operation
                            </div>
                            <div style="font-size:12px;color:var(--cream-dim);margin-top:3px;">
                                This will permanently overwrite every row in <code style="background:rgba(255,255,255,0.08);padding:1px 6px;border-radius:4px;">perpetual_salaah_times</code>
                                with data from <code style="background:rgba(255,255,255,0.08);padding:1px 6px;border-radius:4px;">perpetual_salaah_times_orig_2016</code>.
                                This cannot be undone.
                            </div>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px;color:var(--cream-dim);">
                        <div>✅ Runs inside a transaction — safe if it fails midway</div>
                        <div>❌ All manual Jamaat time edits will be lost</div>
                        <div>✅ Backup table is never modified</div>
                        <div>❌ There is no undo — consider exporting first</div>
                    </div>
                </div>

                <!-- Row count comparison -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px;">
                    <div class="card">
                        <div class="card-top-rule"></div>
                        <div class="card-body" style="padding:18px 22px;">
                            <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);margin-bottom:6px;">
                                🟢 Live Table
                            </div>
                            <div style="font-size:10px;color:var(--cream-dim);margin-bottom:4px;font-family:monospace;">perpetual_salaah_times</div>
                            <div style="font-size:32px;font-weight:800;color:var(--cream);font-variant-numeric:tabular-nums;"><?= $live_count ?></div>
                            <div style="font-size:11px;color:var(--gold-dim);">rows (will be replaced)</div>

                            <?php if (!empty($live_sample)): ?>
                                <div style="margin-top:14px;font-size:11px;">
                                    <div style="color:var(--gold-dim);letter-spacing:1px;text-transform:uppercase;font-size:9px;margin-bottom:6px;">First 5 rows — Jamaat times</div>
                                    <table style="width:100%;border-collapse:collapse;font-size:11px;font-variant-numeric:tabular-nums;">
                                        <thead><tr>
                                            <?php foreach(['Mo','Da','Fajr','Zuhr','Asr','Maghr','Esha'] as $h): ?>
                                                <th style="color:var(--gold-dim);padding:2px 5px;text-align:left;font-size:9px;"><?=$h?></th>
                                            <?php endforeach; ?>
                                        </tr></thead>
                                        <tbody>
                                        <?php foreach ($live_sample as $sr): ?>
                                            <tr>
                                                <td style="padding:2px 5px;color:var(--cream-dim);"><?= $sr['month'] ?></td>
                                                <td style="padding:2px 5px;color:var(--cream-dim);"><?= $sr['date'] ?></td>
                                                <td style="padding:2px 5px;color:var(--cream);"><?= substr($sr['fajr'],0,5) ?></td>
                                                <td style="padding:2px 5px;color:var(--cream);"><?= substr($sr['zuhr'],0,5) ?></td>
                                                <td style="padding:2px 5px;color:var(--cream);"><?= substr($sr['asr'],0,5) ?></td>
                                                <td style="padding:2px 5px;color:var(--cream);"><?= substr($sr['maghrib'],0,5) ?></td>
                                                <td style="padding:2px 5px;color:var(--cream);"><?= substr($sr['esha'],0,5) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card" style="border-color:rgba(201,168,76,0.4);">
                        <div class="card-top-rule" style="background:linear-gradient(to right,transparent,var(--gold),transparent);"></div>
                        <div class="card-body" style="padding:18px 22px;">
                            <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);margin-bottom:6px;">
                                🗄️ Backup Table
                            </div>
                            <div style="font-size:10px;color:var(--cream-dim);margin-bottom:4px;font-family:monospace;">perpetual_salaah_times_orig_2016</div>
                            <div style="font-size:32px;font-weight:800;color:var(--gold-light);font-variant-numeric:tabular-nums;"><?= $backup_count ?></div>
                            <div style="font-size:11px;color:var(--gold-dim);">rows (will be copied in)</div>

                            <?php if (!empty($backup_sample)): ?>
                                <div style="margin-top:14px;font-size:11px;">
                                    <div style="color:var(--gold-dim);letter-spacing:1px;text-transform:uppercase;font-size:9px;margin-bottom:6px;">First 5 rows — Jamaat times</div>
                                    <table style="width:100%;border-collapse:collapse;font-size:11px;font-variant-numeric:tabular-nums;">
                                        <thead><tr>
                                            <?php foreach(['Mo','Da','Fajr','Zuhr','Asr','Maghr','Esha'] as $h): ?>
                                                <th style="color:var(--gold-dim);padding:2px 5px;text-align:left;font-size:9px;"><?=$h?></th>
                                            <?php endforeach; ?>
                                        </tr></thead>
                                        <tbody>
                                        <?php foreach ($backup_sample as $sr): ?>
                                            <tr>
                                                <td style="padding:2px 5px;color:var(--cream-dim);"><?= $sr['month'] ?></td>
                                                <td style="padding:2px 5px;color:var(--cream-dim);"><?= $sr['date'] ?></td>
                                                <td style="padding:2px 5px;color:var(--gold-light);"><?= substr($sr['fajr'],0,5) ?></td>
                                                <td style="padding:2px 5px;color:var(--gold-light);"><?= substr($sr['zuhr'],0,5) ?></td>
                                                <td style="padding:2px 5px;color:var(--gold-light);"><?= substr($sr['asr'],0,5) ?></td>
                                                <td style="padding:2px 5px;color:var(--gold-light);"><?= substr($sr['maghrib'],0,5) ?></td>
                                                <td style="padding:2px 5px;color:var(--gold-light);"><?= substr($sr['esha'],0,5) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Step-by-step confirmation form -->
                <div class="card">
                    <div class="card-top-rule" style="background:linear-gradient(to right,transparent,var(--red-soft),transparent);"></div>
                    <div class="card-body">

                        <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--red-soft);margin-bottom:20px;">
                            🔐 Two-Step Confirmation Required
                        </div>

                        <form method="POST" action="admin.php?action=do_restore" id="restoreForm" autocomplete="off">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

                            <!-- Step 1: checkbox -->
                            <div style="background:rgba(7,23,10,0.6);border:1px solid rgba(192,57,43,0.3);
                        border-radius:12px;padding:18px 20px;margin-bottom:16px;">
                                <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;
                            color:var(--red-soft);margin-bottom:12px;font-weight:700;">
                                    Step 1 — Acknowledge
                                </div>
                                <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
                                    <input type="checkbox" id="ack1" style="margin-top:2px;accent-color:var(--gold);width:16px;height:16px;flex-shrink:0;">
                                    <span style="font-size:13px;color:var(--cream-dim);line-height:1.6;">
                        I understand this will <strong style="color:var(--red-soft);">permanently delete all current Jamaat times</strong>
                        and replace them with the original 2016 reference data.
                    </span>
                                </label>
                            </div>

                            <!-- Step 2: checkbox -->
                            <div style="background:rgba(7,23,10,0.6);border:1px solid rgba(192,57,43,0.3);
                        border-radius:12px;padding:18px 20px;margin-bottom:16px;">
                                <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;
                            color:var(--red-soft);margin-bottom:12px;font-weight:700;">
                                    Step 2 — Confirm No Backup Needed
                                </div>
                                <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
                                    <input type="checkbox" id="ack2" style="margin-top:2px;accent-color:var(--gold);width:16px;height:16px;flex-shrink:0;">
                                    <span style="font-size:13px;color:var(--cream-dim);line-height:1.6;">
                        I have noted or exported any times I wish to keep, and I do
                        <strong style="color:var(--red-soft);">not</strong> need to preserve the current data.
                    </span>
                                </label>
                            </div>

                            <!-- Step 3: type the phrase -->
                            <div style="background:rgba(7,23,10,0.6);border:1px solid rgba(192,57,43,0.3);
                        border-radius:12px;padding:18px 20px;margin-bottom:20px;">
                                <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;
                            color:var(--red-soft);margin-bottom:12px;font-weight:700;">
                                    Step 3 — Type the Confirmation Phrase
                                </div>
                                <div style="font-size:12px;color:var(--cream-dim);margin-bottom:10px;">
                                    To proceed, type exactly:
                                    <code style="background:rgba(255,255,255,0.08);color:var(--gold-light);
                                 padding:3px 10px;border-radius:6px;font-size:13px;
                                 letter-spacing:1px;display:inline-block;margin-top:4px;">RESTORE FROM BACKUP</code>
                                </div>
                                <input type="text" name="confirm_phrase" id="confirmPhrase"
                                       class="form-input" placeholder="Type the phrase above exactly…"
                                       autocomplete="off" spellcheck="false"
                                       style="font-size:15px;font-weight:700;letter-spacing:1.5px;">
                                <div id="phraseCheck" style="font-size:11px;margin-top:6px;min-height:18px;"></div>
                            </div>

                            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                <button type="submit" id="restoreBtn"
                                        class="btn" disabled
                                        style="background:var(--red-bg);border:2px solid var(--red-border);
                               color:var(--red-soft);opacity:0.45;cursor:not-allowed;
                               font-size:14px;padding:12px 28px;">
                                    🛟 Execute Restore
                                </button>
                                <a href="admin.php?action=dashboard" class="btn btn-secondary">← Cancel</a>
                                <span style="font-size:11px;color:var(--gold-dim);margin-left:auto;">
                    Logged as: <?= e($_SESSION['admin_username']) ?>
                </span>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    (function () {
                        const ack1   = document.getElementById('ack1');
                        const ack2   = document.getElementById('ack2');
                        const phrase = document.getElementById('confirmPhrase');
                        const check  = document.getElementById('phraseCheck');
                        const btn    = document.getElementById('restoreBtn');
                        const REQUIRED = 'RESTORE FROM BACKUP';

                        function validate() {
                            const phraseOk = phrase.value === REQUIRED;
                            const allOk    = ack1.checked && ack2.checked && phraseOk;

                            // Phrase feedback
                            if (phrase.value === '') {
                                check.textContent = '';
                            } else if (phraseOk) {
                                check.textContent = '✅ Phrase matches';
                                check.style.color = '#7FD499';
                            } else {
                                check.textContent = '❌ Does not match — type exactly: ' + REQUIRED;
                                check.style.color = 'var(--red-soft)';
                            }

                            // Enable / disable button
                            if (allOk) {
                                btn.disabled = false;
                                btn.style.opacity = '1';
                                btn.style.cursor  = 'pointer';
                                btn.style.border  = '2px solid var(--red-soft)';
                            } else {
                                btn.disabled = true;
                                btn.style.opacity = '0.4';
                                btn.style.cursor  = 'not-allowed';
                                btn.style.border  = '2px solid var(--red-border)';
                            }
                        }

                        // Final native confirm as a last-ditch safety net
                        document.getElementById('restoreForm').addEventListener('submit', function (e) {
                            if (!confirm('⚠️ FINAL WARNING\n\nYou are about to overwrite ALL live Salaah times with the 2016 backup.\n\nThis cannot be undone. Proceed?')) {
                                e.preventDefault();
                            }
                        });

                        ack1.addEventListener('change', validate);
                        ack2.addEventListener('change', validate);
                        phrase.addEventListener('input', validate);
                    })();
                </script>

            <?php elseif (!isSuperAdmin() && ($action === 'restore' || $action === 'do_restore')): ?>
                <div class="alert alert-error">⚠️ Access denied. Superadmin only.</div>
                <a href="admin.php?action=dashboard" class="btn btn-secondary">← Dashboard</a>

            <?php elseif (in_array($action, ['community_messages','new_community_msg','edit_community_msg'])): /* COMMUNITY MESSAGES */ ?>

                <div class="page-header">
                    <div class="page-title">📢 Community Messages</div>
                    <div class="page-desc">Manage the community notice display. Supports WYSIWYG HTML or image uploads with scheduling.</div>
                </div>
                <div class="gold-rule"></div>

            <?php if ($action === 'new_community_msg' || $action === 'edit_community_msg'): ?>
            <?php $cm = $edit_community_msg; $is_new = ($action === 'new_community_msg'); ?>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-top-rule"></div>
                    <div class="card-body">
                        <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:18px;">
                            <?= $is_new ? '➕ New Community Message' : '✏️ Edit Community Message' ?>
                        </div>
                        <form method="POST" action="admin.php?action=save_community_msg" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <input type="hidden" name="msg_id" value="<?= $is_new ? 0 : (int)$cm['id'] ?>">
                            <div class="form-grid">
                                <div class="form-group full">
                                    <label class="form-label">Title / Heading</label>
                                    <input class="form-input" type="text" name="title" value="<?= e($cm['title'] ?? '') ?>" placeholder="Community Notice">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Content Type</label>
                                    <select class="form-select" name="content_type" id="ctypeSelect" onchange="toggleContentType()">
                                        <option value="html" <?= ($cm['content_type']??'html')==='html'?'selected':'' ?>>WYSIWYG / HTML</option>
                                        <option value="image" <?= ($cm['content_type']??'')==='image'?'selected':'' ?>>Image Upload (JPG/PNG)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Display Duration (seconds)</label>
                                    <input class="form-input" type="number" name="display_secs" min="5" max="300" value="<?= (int)($cm['display_secs'] ?? 30) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Schedule Start (optional)</label>
                                    <input class="form-input" type="datetime-local" name="start_dt" value="<?= e(str_replace(' ','T',substr($cm['start_dt']??'',0,16))) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Schedule End / Expiry (optional)</label>
                                    <input class="form-input" type="datetime-local" name="end_dt" value="<?= e(str_replace(' ','T',substr($cm['end_dt']??'',0,16))) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Sort Order</label>
                                    <input class="form-input" type="number" name="sort_order" min="0" value="<?= (int)($cm['sort_order'] ?? 0) ?>">
                                </div>
                                <div class="form-group" style="align-self:center;">
                                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                        <input type="checkbox" name="is_active" value="1" <?= ($cm['is_active']??1)?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--gold);">
                                        <span class="form-label" style="margin:0;">Active</span>
                                    </label>
                                </div>
                            </div>

                            <!-- WYSIWYG editor section -->
                            <div id="htmlSection" style="margin-top:16px;">
                                <div class="form-group full">
                                    <label class="form-label">Content (HTML / Rich Text)</label>
                                    <!-- Summernote rich editor — full fonts, images, colours, tables -->
                                    <div id="summernote-editor"><?= $cm['content_html'] ?? '' ?></div>
                                    <textarea id="wysiwyg_editor" name="content_html" style="display:none;"><?= htmlspecialchars($cm['content_html'] ?? '', ENT_QUOTES) ?></textarea>
                                </div>
                                <div class="form-group full" style="margin-top:12px;">
                                    <div style="font-size:11px;color:var(--gold-dim);margin-bottom:8px;">📋 Live Preview</div>
                                    <div id="htmlPreview" style="background:rgba(16,46,22,0.8);border:1px solid var(--card-border);border-radius:12px;padding:18px;min-height:80px;color:var(--cream);font-family:'Nunito',sans-serif;font-size:14px;"></div>
                                </div>
                            </div>

                            <!-- Image upload section -->
                            <div id="imageSection" style="margin-top:16px;display:none;">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Upload Image</label>
                                        <input class="form-input" type="file" name="image_file" accept="image/*" onchange="previewImg(this)">
                                        <?php if (!empty($cm['media_id'])): ?>
                                            <div style="margin-top:8px;font-size:11px;color:var(--gold-dim);">Current image stored in database (media #<?= (int)$cm['media_id'] ?>)</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Image Fit</label>
                                        <select class="form-select" name="image_fit">
                                            <option value="contain" <?= ($cm['image_fit']??'contain')==='contain'?'selected':'' ?>>Contain (Centered, letterboxed)</option>
                                            <option value="cover"   <?= ($cm['image_fit']??'')==='cover'?'selected':'' ?>>Cover (Zooms to fill, crops edges)</option>
                                            <option value="fill"    <?= ($cm['image_fit']??'')==='fill'?'selected':'' ?>>Stretch (Fills all sides exactly)</option>
                                        </select>
                                    </div>
                                </div>
                                <?php if (!empty($cm['media_id'])): ?>
                                    <div style="margin-top:10px;">
                                        <img src="index.php?action=img&id=<?= (int)$cm['media_id'] ?>" id="imgPreview" style="max-height:160px;max-width:100%;border-radius:8px;border:1px solid var(--card-border);display:block;margin:0 auto;">
                                    </div>
                                <?php else: ?>
                                    <img id="imgPreview" style="max-height:160px;max-width:100%;border-radius:8px;border:1px solid var(--card-border);display:none;margin:10px auto;">
                                <?php endif; ?>
                            </div>

                            <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                                <button type="submit" class="btn btn-primary">💾 Save Message</button>
                                <a href="admin.php?action=community_messages" class="btn btn-secondary">← Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Summernote rich editor — open source, no API key, full featured -->
            <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
                <style>
                    /* Summernote dark-theme overrides */
                    .note-editor.note-frame { border: 1px solid var(--input-border) !important; border-radius: 10px !important; overflow: hidden; }
                    .note-toolbar { background: rgba(7,23,10,0.92) !important; border-bottom: 1px solid var(--input-border) !important; }

                    /* Target main buttons, but EXCLUDE color swatches (which use data-value) so they keep their inline colors */
                    .note-toolbar .note-btn:not([data-value]) { background: transparent !important; border: 1px solid transparent !important; color: var(--cream-dim) !important; }
                    .note-toolbar .note-btn:not([data-value]):hover, .note-toolbar .note-btn:not([data-value]).active { background: rgba(201,168,76,0.15) !important; border-color: var(--gold-dim) !important; color: var(--gold) !important; }

                    /* The color swatches inside the dropdowns */
                    .note-color-palette .note-btn { border: 1px solid rgba(255,255,255,0.2) !important; margin: 1px !important; width: 20px !important; height: 20px !important; }

                    .note-editable { background: rgba(7,23,10,0.7) !important; color: var(--cream) !important; min-height: 180px !important; font-family: 'Nunito', sans-serif !important; font-size: 14px !important; }
                    .note-statusbar { background: rgba(7,23,10,0.6) !important; border-top: 1px solid var(--input-border) !important; }
                    .note-dropdown-menu, .note-popover { background: #0d2410 !important; border-color: var(--input-border) !important; }
                    .note-dropdown-item { color: var(--cream-dim) !important; }
                    .note-dropdown-item:hover { background: rgba(201,168,76,0.12) !important; color: var(--gold) !important; }
                    .note-color-palette td, .note-color-palette th { border-color: transparent !important; }

                    /* Modals */
                    .note-modal .note-modal-content { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; color: var(--cream) !important; box-shadow: var(--shadow); }
                    .note-modal .note-modal-header { border-bottom: 1px solid var(--card-border) !important; }
                    .note-modal .note-modal-footer { border-top: 1px solid var(--card-border) !important; }
                    .note-modal-title { color: var(--gold) !important; }
                    .note-form-label { color: var(--gold-dim) !important; }
                    .note-input { background: var(--input-bg) !important; border: 1px solid var(--input-border) !important; color: var(--cream) !important; }
                    .note-modal-backdrop { z-index: 1040 !important; }

                    /* --- 1. Fix Color Palette Labels & Buttons --- */
                    .note-palette-title { color: var(--gold-light) !important; font-size: 12px !important; margin: 8px 0 4px !important; font-weight: 800 !important; text-align: center; }
                    .note-color-reset { background: var(--input-bg) !important; color: var(--cream) !important; border: 1px solid var(--input-border) !important; padding: 4px; border-radius: 4px; width: 90%; margin: 4px auto; display: block; cursor: pointer; transition: 0.2s; }
                    .note-color-reset:hover { border-color: var(--gold) !important; color: var(--gold) !important; }

                    /* --- 2. Make Image Resize Drag Handles (Nodes) visible and usable --- */
                    .note-control-selection { border: 2px dashed var(--gold) !important; background: transparent !important; }
                    .note-control-selection .note-control-handle,
                    .note-control-selection .note-control-holder {
                        background-color: var(--gold) !important;
                        border: 2px solid var(--green-dark) !important;
                        width: 14px !important;
                        height: 14px !important;
                        border-radius: 50% !important;
                        z-index: 1050 !important;
                    }

                    /* Ensure the nodes sit perfectly on the corners */
                    .note-control-selection .note-control-nw { top: -7px !important; left: -7px !important; cursor: nw-resize !important; }
                    .note-control-selection .note-control-ne { top: -7px !important; right: -7px !important; cursor: ne-resize !important; }
                    .note-control-selection .note-control-sw { bottom: -7px !important; left: -7px !important; cursor: sw-resize !important; }
                    .note-control-selection .note-control-se { bottom: -7px !important; right: -7px !important; cursor: se-resize !important; }

                    /* --- 3. NATIVE Browser Image Resize (Foolproof fallback) --- */
                    /* --- NATIVE Browser Image Resize & Drag Prevention --- */
                    .note-editable img {
                        -webkit-user-drag: none !important; /* This stops the image from moving! */
                        resize: both !important;
                        overflow: hidden !important;
                        max-width: 100% !important;
                    }
                    .note-color .note-dropdown-menu .note-palette {
                        display: inline-block !important;
                        width: 48% !important;
                        vertical-align: top !important;
                        margin: 0 !important;
                    }
                </style>
                <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
                <script>
                    $(document).ready(function(){
                        var ta = document.getElementById('wysiwyg_editor');
                        $('#summernote-editor').summernote({
                            placeholder: 'Type your community message here...',
                            tabsize: 2,
                            height: 220,
                            dialogsInBody: true, // <-- This fixes the freezing!
                            popover: {
                                image: [], // <-- This completely disables the image popup menu!
                                link: [],
                                air: []
                            },
                            toolbar: [
                                ['style',   ['style']],
                                ['font',    ['bold','italic','underline','strikethrough','clear']],
                                ['fontname',['fontname']],
                                ['fontsize',['fontsize']],
                                ['color',   ['color']],
                                ['para',    ['ul','ol','paragraph']],
                                ['table',   ['table']],
                                ['insert',  ['link','picture','hr']],
                                ['view',    ['fullscreen','codeview']]
                            ],
                            fontNames: ['Arial','Comic Sans MS','Courier New','Georgia','Nunito','Tahoma','Times New Roman','Trebuchet MS','Verdana'],
                            fontNamesIgnoreCheck: ['Nunito'],
                            callbacks: {
                                onChange: function(contents){
                                    ta.value = contents;
                                    document.getElementById('htmlPreview').innerHTML = contents;
                                },
                                onInit: function(){
                                    /* Seed editor from textarea */
                                    if (ta.value.trim()) {
                                        $('#summernote-editor').summernote('code', ta.value);
                                        document.getElementById('htmlPreview').innerHTML = ta.value;
                                    }
                                }
                            }
                        });
                        /* Ensure latest content on submit */
                        ta.closest('form').addEventListener('submit', function(){
                            ta.value = $('#summernote-editor').summernote('code');
                        });
                    });
                    function toggleContentType(){
                        var v = document.getElementById('ctypeSelect').value;
                        document.getElementById('htmlSection').style.display   = v==='html'  ? 'block':'none';
                        document.getElementById('imageSection').style.display  = v==='image' ? 'block':'none';
                    }
                    function previewImg(inp){
                        var f = inp.files[0], prev = document.getElementById('imgPreview');
                        if(f){ var r = new FileReader(); r.onload=function(e){prev.src=e.target.result;prev.style.display='block';}; r.readAsDataURL(f); }
                    }
                    toggleContentType();
                </script>

            <?php else: /* list view */ ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <div style="font-size:12px;color:var(--cream-dim);"><?= count($community_msgs) ?> message(s)</div>
                    <a href="admin.php?action=new_community_msg" class="btn btn-primary">➕ Add Message</a>
                </div>

                <?php if (empty($community_msgs)): ?>
                <div class="card"><div class="card-body" style="text-align:center;color:var(--cream-dim);padding:40px;">No community messages yet. Click "Add Message" to get started.</div></div>
            <?php else: ?>
                <?php foreach ($community_msgs as $cm): ?>
                <div class="card" style="margin-bottom:12px;">
                    <div class="card-top-rule"></div>
                    <div class="card-body" style="padding:16px 20px;">
                        <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:200px;">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                                    <span style="font-size:16px;font-weight:800;color:var(--gold-light);"><?= e($cm['title'] ?: '(No title)') ?></span>
                                    <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;
                                            background:<?= $cm['content_type']==='image' ? 'rgba(201,168,76,0.2)' : 'rgba(27,94,53,0.4)' ?>;
                                            color:<?= $cm['content_type']==='image' ? 'var(--gold-light)' : '#7FD499' ?>;">
                        <?= $cm['content_type'] ?>
                    </span>
                                    <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;
                                            background:<?= $cm['is_active'] ? 'rgba(0,180,80,0.18)' : 'rgba(192,57,43,0.18)' ?>;
                                            color:<?= $cm['is_active'] ? '#7FD499' : 'var(--red-soft)' ?>;">
                        <?= $cm['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                                    <?php if ($cm['end_dt'] && strtotime($cm['end_dt']) < time()): ?>
                                        <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:rgba(180,50,50,0.3);color:#ff9090;">
                        ⛔ Expired
                    </span>
                                    <?php elseif ($cm['start_dt'] && strtotime($cm['start_dt']) > time()): ?>
                                        <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:rgba(100,100,20,0.3);color:#d4c060;">
                        ⏳ Scheduled
                    </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11px;color:var(--cream-dim);">
                                    ⏱️ <?= (int)$cm['display_secs'] ?>s &nbsp;|&nbsp;
                                    🔢 Order: <?= (int)$cm['sort_order'] ?>
                                    <?php if ($cm['start_dt']): ?>&nbsp;|&nbsp; 📅 From: <?= e(substr($cm['start_dt'],0,16)) ?><?php endif; ?>
                                    <?php if ($cm['end_dt']): ?>&nbsp;|&nbsp; 📅 Until: <?= e(substr($cm['end_dt'],0,16)) ?><?php endif; ?>
                                </div>
                                <?php if ($cm['content_type']==='html' && $cm['content_html']): ?>
                                    <div style="font-size:11px;color:var(--cream-dim);margin-top:4px;max-height:32px;overflow:hidden;opacity:0.6;"><?= strip_tags($cm['content_html']) ?></div>
                                <?php elseif ($cm['content_type']==='image' && $cm['media_id']): ?>
                                    <img src="index.php?action=img&id=<?= (int)$cm['media_id'] ?>" style="height:40px;margin-top:6px;border-radius:5px;border:1px solid var(--card-border);">
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;gap:8px;flex-shrink:0;align-items:center;">
                                <a href="admin.php?action=edit_community_msg&id=<?= $cm['id'] ?>" class="btn btn-secondary" style="padding:7px 14px;font-size:12px;">✏️ Edit</a>
                                <a href="admin.php?action=toggle_community_msg&id=<?= $cm['id'] ?>" class="btn btn-secondary" style="padding:7px 14px;font-size:12px;"><?= $cm['is_active'] ? '⏸️ Disable' : '▶️ Enable' ?></a>
                                <a href="admin.php?action=delete_community_msg&id=<?= $cm['id'] ?>" class="btn" style="padding:7px 14px;font-size:12px;background:var(--red-bg);border:1px solid var(--red-border);color:var(--red-soft);"
                                   onclick="return confirm('Delete this message?')">🗑️ Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php endif; ?>

            <?php elseif (in_array($action, ['funeral_notices','new_funeral','edit_funeral'])): /* FUNERAL NOTICES */ ?>

                <div class="page-header">
                    <div class="page-title">⚰️ Funeral Notices</div>
                    <div class="page-desc">Create and manage funeral announcements with all relevant details.</div>
                </div>
                <div class="gold-rule"></div>

            <?php if ($action === 'new_funeral' || $action === 'edit_funeral'):
            $fn = $edit_funeral; $fn_new = ($action === 'new_funeral');
            ?>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-top-rule"></div>
                    <div class="card-body">
                        <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:18px;">
                            <?= $fn_new ? '➕ New Funeral Notice' : '✏️ Edit Funeral Notice' ?>
                        </div>
                        <form method="POST" action="admin.php?action=save_funeral">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <input type="hidden" name="funeral_id" value="<?= $fn_new ? 0 : (int)$fn['id'] ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">a. Date of Funeral — English</label>
                                    <input class="form-input" type="text" name="funeral_date_en" value="<?= e($fn['funeral_date_en'] ?? '') ?>" placeholder="e.g. 30 September 2025">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">b. Date of Funeral — Hijri</label>
                                    <input class="form-input" type="text" name="funeral_date_hijri" value="<?= e($fn['funeral_date_hijri'] ?? '') ?>" placeholder="e.g. 07 Rabi ul Aakhir 1447">
                                </div>
                                <div class="form-group full">
                                    <label class="form-label">c. Deceased Name</label>
                                    <input class="form-input" type="text" name="deceased_name" value="<?= e($fn['deceased_name'] ?? '') ?>" placeholder="Full name of the deceased">
                                </div>
                                <div class="form-group full">
                                    <label class="form-label">d. Family Details (Father/Mother/Spouse of… | Grandfather/Grandmother of…)</label>
                                    <textarea class="form-input" name="family_details" rows="2" placeholder="e.g. Father of Yusuf, Rashida and Razia Bhorat. Grandfather of Zaid, Abdullah, Mohammed, Uzair Naroth"><?= e($fn['family_details'] ?? '') ?></textarea>
                                </div>
                                <div class="form-group full">
                                    <label class="form-label">e. Will Leave From (address)</label>
                                    <textarea class="form-input" name="leave_from" rows="2" placeholder="e.g. 94 Harding Street, Flat 4 Villa Navley"><?= e($fn['leave_from'] ?? '') ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">f. Time of Departure (Pick Up)</label>
                                    <input class="form-input" type="text" name="departure_time" value="<?= e($fn['departure_time'] ?? '') ?>" placeholder="e.g. 11:00">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">g. Proceeding To (Cemetery)</label>
                                    <input class="form-input" type="text" name="proceeding_to" value="<?= e($fn['proceeding_to'] ?? '') ?>" placeholder="e.g. NN Cemetery #2">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">h. Janazah Salaah Location</label>
                                    <input class="form-input" type="text" name="janazah_location" value="<?= e($fn['janazah_location'] ?? '') ?>" placeholder="e.g. Darul Uloom Musjid">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">i. Janazah Salaah Time</label>
                                    <input class="form-input" type="text" name="janazah_time" value="<?= e($fn['janazah_time'] ?? '') ?>" placeholder="e.g. 11:15">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Display Duration (seconds)</label>
                                    <input class="form-input" type="number" name="display_secs" min="5" max="300" value="<?= (int)($fn['display_secs'] ?? 30) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Schedule Start (optional)</label>
                                    <input class="form-input" type="datetime-local" name="start_dt" value="<?= e(str_replace(' ','T',substr($fn['start_dt']??'',0,16))) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Schedule End / Expiry (optional)</label>
                                    <input class="form-input" type="datetime-local" name="end_dt" value="<?= e(str_replace(' ','T',substr($fn['end_dt']??'',0,16))) ?>">
                                </div>
                                <div class="form-group" style="align-self:center;">
                                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                        <input type="checkbox" name="is_active" value="1" <?= ($fn['is_active']??1)?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--gold);">
                                        <span class="form-label" style="margin:0;">Active</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Live Preview -->
                            <div style="margin-top:20px;">
                                <div style="font-size:11px;color:var(--gold-dim);margin-bottom:8px;letter-spacing:2px;text-transform:uppercase;">📋 Live Funeral Notice Preview</div>
                                <div id="funeralPreview" style="
                    background:linear-gradient(160deg,rgba(20,8,8,0.95) 0%,rgba(10,4,4,0.92) 100%);
                    border:1px solid rgba(192,57,43,0.35);border-radius:14px;padding:20px 24px;
                    font-family:'Nunito',sans-serif;color:rgba(245,237,214,0.85);line-height:1.8;font-size:14px;">
                                </div>
                            </div>

                            <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                                <button type="submit" class="btn btn-primary">💾 Save Notice</button>
                                <a href="admin.php?action=funeral_notices" class="btn btn-secondary">← Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                    function buildFuneralPreview(){
                        var dn  = document.querySelector('[name=deceased_name]').value.trim();
                        var fam = document.querySelector('[name=family_details]').value.trim();
                        var lf  = document.querySelector('[name=leave_from]').value.trim();
                        var dep = document.querySelector('[name=departure_time]').value.trim();
                        var pro = document.querySelector('[name=proceeding_to]').value.trim();
                        var jloc= document.querySelector('[name=janazah_location]').value.trim();
                        var jt  = document.querySelector('[name=janazah_time]').value.trim();
                        var den = document.querySelector('[name=funeral_date_en]').value.trim();
                        var dhj = document.querySelector('[name=funeral_date_hijri]').value.trim();
                        var html = '<div style="text-align:center;margin-bottom:12px;">';
                        html += '<div style="font-size:10px;letter-spacing:4px;text-transform:uppercase;color:rgba(192,57,43,0.7);margin-bottom:4px;">⚰️ Funeral Notice — إِنَّا لِلَّهِ وَإِنَّا إِلَيْهِ رَاجِعُونَ</div>';
                        if(dn) html += '<div style="font-size:22px;font-weight:800;color:#C8896A;letter-spacing:1px;margin:6px 0;">' + dn + '</div>';
                        if(fam) html += '<div style="font-size:12px;color:rgba(200,180,138,0.7);margin-bottom:10px;">' + fam + '</div>';
                        html += '</div>';
                        html += '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
                        if(lf||dep){
                            html += '<tr><td style="padding:6px 10px;border:1px solid rgba(192,57,43,0.2);color:rgba(201,168,76,0.7);font-size:10px;letter-spacing:1px;text-transform:uppercase;width:38%;">🏠 Will Leave From / Pick Up</td>';
                            html += '<td style="padding:6px 10px;border:1px solid rgba(192,57,43,0.2);">' + (lf?lf:'–') + (dep?' &nbsp;<strong>'+dep+'</strong>':'') + '</td></tr>';
                        }
                        if(jloc||jt){
                            html += '<tr><td style="padding:6px 10px;border:1px solid rgba(192,57,43,0.2);color:rgba(201,168,76,0.7);font-size:10px;letter-spacing:1px;text-transform:uppercase;">🕌 Janazah Salaah</td>';
                            html += '<td style="padding:6px 10px;border:1px solid rgba(192,57,43,0.2);">' + (jloc?jloc:'–') + (jt?' &nbsp;<strong>'+jt+'</strong>':'') + '</td></tr>';
                        }
                        if(pro){
                            html += '<tr><td style="padding:6px 10px;border:1px solid rgba(192,57,43,0.2);color:rgba(201,168,76,0.7);font-size:10px;letter-spacing:1px;text-transform:uppercase;">⚰️ Proceeding To</td>';
                            html += '<td style="padding:6px 10px;border:1px solid rgba(192,57,43,0.2);">' + pro + '</td></tr>';
                        }
                        if(den||dhj){
                            html += '<tr><td style="padding:6px 10px;border:1px solid rgba(192,57,43,0.2);color:rgba(201,168,76,0.7);font-size:10px;letter-spacing:1px;text-transform:uppercase;">📅 Date</td>';
                            html += '<td style="padding:6px 10px;border:1px solid rgba(192,57,43,0.2);">' + (den?den:'') + (dhj?' &nbsp;·&nbsp; '+dhj:'') + '</td></tr>';
                        }
                        html += '</table>';
                        html += '<div style="font-size:10px;text-align:center;margin-top:10px;opacity:0.5;font-style:italic;">May Allāh Ta\'ala grant all Marhoomeen forgiveness and Jannatul Firdaus. Āmeen.</div>';
                        document.getElementById('funeralPreview').innerHTML = html;
                    }
                    document.querySelectorAll('input,textarea').forEach(function(el){ el.addEventListener('input', buildFuneralPreview); });
                    buildFuneralPreview();
                </script>

            <?php else: /* list view */ ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <div style="font-size:12px;color:var(--cream-dim);"><?= count($funeral_list) ?> notice(s)</div>
                    <a href="admin.php?action=new_funeral" class="btn btn-primary">➕ Add Funeral Notice</a>
                </div>

                <?php if (empty($funeral_list)): ?>
                <div class="card"><div class="card-body" style="text-align:center;color:var(--cream-dim);padding:40px;">No funeral notices. Click "Add Funeral Notice" to create one.</div></div>
            <?php else: ?>
                <?php foreach ($funeral_list as $fn): ?>
                <div class="card" style="margin-bottom:12px;">
                    <div class="card-top-rule" style="background:linear-gradient(to right,transparent,rgba(192,57,43,0.5),transparent);"></div>
                    <div class="card-body" style="padding:16px 20px;">
                        <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:200px;">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                                    <span style="font-size:16px;font-weight:800;color:#C8896A;">⚰️ <?= e($fn['deceased_name'] ?: '(Unnamed)') ?></span>
                                    <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;
                                            background:<?= $fn['is_active'] ? 'rgba(0,180,80,0.18)' : 'rgba(192,57,43,0.18)' ?>;
                                            color:<?= $fn['is_active'] ? '#7FD499' : 'var(--red-soft)' ?>;">
                        <?= $fn['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                                    <?php if ($fn['end_dt'] && strtotime($fn['end_dt']) < time()): ?>
                                        <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:rgba(180,50,50,0.3);color:#ff9090;">
                        ⛔ Expired
                    </span>
                                    <?php elseif ($fn['start_dt'] && strtotime($fn['start_dt']) > time()): ?>
                                        <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:rgba(100,100,20,0.3);color:#d4c060;">
                        ⏳ Scheduled
                    </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11px;color:var(--cream-dim);">
                                    📅 <?= e($fn['funeral_date_en']) ?>
                                    <?php if ($fn['funeral_date_hijri']): ?>&nbsp;·&nbsp; <?= e($fn['funeral_date_hijri']) ?><?php endif; ?>
                                    &nbsp;|&nbsp; 🕌 <?= e($fn['janazah_location'] ?: '–') ?>
                                    &nbsp;@&nbsp; <?= e($fn['janazah_time'] ?: '–') ?>
                                    &nbsp;|&nbsp; ⏱️ <?= (int)$fn['display_secs'] ?>s
                                    <?php if ($fn['end_dt']): ?>&nbsp;|&nbsp; Expires: <?= e(substr($fn['end_dt'],0,16)) ?><?php endif; ?>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;flex-shrink:0;align-items:center;">
                                <a href="admin.php?action=edit_funeral&id=<?= $fn['id'] ?>" class="btn btn-secondary" style="padding:7px 14px;font-size:12px;">✏️ Edit</a>
                                <a href="admin.php?action=toggle_funeral&id=<?= $fn['id'] ?>" class="btn btn-secondary" style="padding:7px 14px;font-size:12px;"><?= $fn['is_active'] ? '⏸️ Disable' : '▶️ Enable' ?></a>
                                <a href="admin.php?action=delete_funeral&id=<?= $fn['id'] ?>" class="btn" style="padding:7px 14px;font-size:12px;background:var(--red-bg);border:1px solid var(--red-border);color:var(--red-soft);"
                                   onclick="return confirm('Delete this funeral notice?')">🗑️ Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php endif; ?>

            <?php elseif (in_array($action, ['ticker_messages','new_ticker','edit_ticker'])): /* TICKER MESSAGES */ ?>

                <div class="page-header">
                    <div class="page-title">📰 Ticker Messages</div>
                    <div class="page-desc">Manage the scrolling ticker strip at the bottom of the display screen.</div>
                </div>
                <div class="gold-rule"></div>

                <?php if ($action === 'new_ticker' || $action === 'edit_ticker'):
                $tk = $edit_ticker; $tk_new = ($action === 'new_ticker');
                ?>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-top-rule"></div>
                    <div class="card-body">
                        <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:18px;">
                            <?= $tk_new ? '➕ New Ticker Message' : '✏️ Edit Ticker Message' ?>
                        </div>
                        <form method="POST" action="admin.php?action=save_ticker">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <input type="hidden" name="ticker_id" value="<?= $tk_new ? 0 : (int)$tk['id'] ?>">
                            <div class="form-grid">
                                <div class="form-group full">
                                    <label class="form-label">Ticker Message Text</label>
                                    <textarea class="form-input" name="message_text" rows="3" placeholder="Enter your ticker message…"><?= e($tk['message_text'] ?? '') ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Display Duration (seconds, for cycling)</label>
                                    <input class="form-input" type="number" name="display_secs" min="5" max="300" value="<?= (int)($tk['display_secs'] ?? 30) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Sort Order</label>
                                    <input class="form-input" type="number" name="sort_order" min="0" value="<?= (int)($tk['sort_order'] ?? 0) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Schedule Start (optional)</label>
                                    <input class="form-input" type="datetime-local" name="start_dt" value="<?= e(str_replace(' ','T',substr($tk['start_dt']??'',0,16))) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Schedule End / Expiry (optional)</label>
                                    <input class="form-input" type="datetime-local" name="end_dt" value="<?= e(str_replace(' ','T',substr($tk['end_dt']??'',0,16))) ?>">
                                </div>
                                <div class="form-group" style="align-self:center;">
                                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                        <input type="checkbox" name="is_active" value="1" <?= ($tk['is_active']??1)?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--gold);">
                                        <span class="form-label" style="margin:0;">Active</span>
                                    </label>
                                </div>
                            </div>
                            <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                                <button type="submit" class="btn btn-primary">💾 Save Ticker</button>
                                <a href="admin.php?action=ticker_messages" class="btn btn-secondary">← Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: /* list view */ ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <div style="font-size:12px;color:var(--cream-dim);"><?= count($ticker_list) ?> ticker(s)</div>
                    <a href="admin.php?action=new_ticker" class="btn btn-primary">➕ Add Ticker</a>
                </div>
                <?php if (empty($ticker_list)): ?>
                <div class="card"><div class="card-body" style="text-align:center;color:var(--cream-dim);padding:40px;">No ticker messages. Add one to replace the static default.</div></div>
            <?php else: ?>
                <?php foreach ($ticker_list as $tk): ?>
                <div class="card" style="margin-bottom:12px;">
                    <div class="card-top-rule"></div>
                    <div class="card-body" style="padding:16px 20px;">
                        <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:200px;">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                                    <span style="font-size:13px;font-weight:700;color:var(--cream);"><?= e(mb_strimwidth($tk['message_text'],0,120,'…')) ?></span>
                                    <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;
                                            background:<?= $tk['is_active'] ? 'rgba(0,180,80,0.18)' : 'rgba(192,57,43,0.18)' ?>;
                                            color:<?= $tk['is_active'] ? '#7FD499' : 'var(--red-soft)' ?>;">
                        <?= $tk['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                                    <?php if ($tk['end_dt'] && strtotime($tk['end_dt']) < time()): ?>
                                        <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:rgba(180,50,50,0.3);color:#ff9090;">
                        ⛔ Expired
                    </span>
                                    <?php elseif ($tk['start_dt'] && strtotime($tk['start_dt']) > time()): ?>
                                        <span style="font-size:9px;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:rgba(100,100,20,0.3);color:#d4c060;">
                        ⏳ Scheduled
                    </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11px;color:var(--cream-dim);">
                                    ⏱️ <?= (int)$tk['display_secs'] ?>s &nbsp;|&nbsp; 🔢 Order: <?= (int)$tk['sort_order'] ?>
                                    <?php if ($tk['start_dt']): ?>&nbsp;|&nbsp; 📅 From: <?= e(substr($tk['start_dt'],0,16)) ?><?php endif; ?>
                                    <?php if ($tk['end_dt']): ?>&nbsp;|&nbsp; Until: <?= e(substr($tk['end_dt'],0,16)) ?><?php endif; ?>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;flex-shrink:0;align-items:center;">
                                <a href="admin.php?action=edit_ticker&id=<?= $tk['id'] ?>" class="btn btn-secondary" style="padding:7px 14px;font-size:12px;">✏️ Edit</a>
                                <a href="admin.php?action=toggle_ticker&id=<?= $tk['id'] ?>" class="btn btn-secondary" style="padding:7px 14px;font-size:12px;"><?= $tk['is_active'] ? '⏸️ Disable' : '▶️ Enable' ?></a>
                                <a href="admin.php?action=delete_ticker&id=<?= $tk['id'] ?>" class="btn" style="padding:7px 14px;font-size:12px;background:var(--red-bg);border:1px solid var(--red-border);color:var(--red-soft);"
                                   onclick="return confirm('Delete this ticker?')">🗑️ Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php endif; ?>

            <?php elseif (in_array($action, ['themes','save_theme'])): /* ── THEMES ── */

            $active_theme   = $site['active_theme'] ?? 'green';
            $custom_json    = json_decode($site['custom_theme_json'] ?? '{}', true) ?: [];

            // Defaults for custom pickers
            $custom_defaults = [
                    'gold'       => '#C9A84C',
                    'gold_light' => '#E8C97A',
                    'gold_dim'   => '#8A6E32',
                    'bg_dark'    => '#07170A',
                    'bg_mid'     => '#102E16',
                    'bg_accent'  => '#1B5E35',
                    'cream'      => '#F5EDD6',
                    'cream_dim'  => '#C8B98A',
            ];
            $custom_vals = array_merge($custom_defaults, $custom_json);

            $preset_themes = [
                    'green'    => ['name'=>'Forest Green',  'emoji'=>'🌿', 'desc'=>'Original MDS theme — deep greens with gold accents.',        'swatch_bg'=>'#07170A', 'swatch_acc'=>'#C9A84C', 'swatch_card'=>'#102E16'],
                    'blue'     => ['name'=>'Midnight Blue',  'emoji'=>'🌊', 'desc'=>'Deep navy backgrounds with sky-blue accents.',               'swatch_bg'=>'#050D18', 'swatch_acc'=>'#7EB8E8', 'swatch_card'=>'#0A1929'],
                    'burgundy' => ['name'=>'Royal Burgundy', 'emoji'=>'🍷', 'desc'=>'Rich dark reds with lovely warm gold highlights.',           'swatch_bg'=>'#120407', 'swatch_acc'=>'#D4A44C', 'swatch_card'=>'#240810'],
                    'grey'     => ['name'=>'Slate Grey',     'emoji'=>'🪨', 'desc'=>'Pure charcoal/grey tones with silver-grey accents.',         'swatch_bg'=>'#0D0E0F', 'swatch_acc'=>'#A0A8B0', 'swatch_card'=>'#181A1C'],
                    'white'    => ['name'=>'Ivory Light',    'emoji'=>'🕊️', 'desc'=>'Clean light theme — ivory backgrounds, warm amber accents.', 'swatch_bg'=>'#F7F5F0', 'swatch_acc'=>'#B07D2A', 'swatch_card'=>'#EDE9E0'],
                    'custom'   => ['name'=>'Custom',         'emoji'=>'🎨', 'desc'=>'Build your own theme with full colour control.',             'swatch_bg'=>$custom_vals['bg_dark'], 'swatch_acc'=>$custom_vals['gold'], 'swatch_card'=>$custom_vals['bg_mid']],
            ];

            $custom_fields = [
                    'gold'       => ['label'=>'Accent / Highlight Colour',    'hint'=>'Main accent — headings, icons, borders'],
                    'gold_light' => ['label'=>'Accent Light',                  'hint'=>'Lighter accent variant — active states'],
                    'gold_dim'   => ['label'=>'Accent Dim',                    'hint'=>'Muted accent — secondary labels'],
                    'bg_dark'    => ['label'=>'Page Background (Darkest)',     'hint'=>'Main body background colour'],
                    'bg_mid'     => ['label'=>'Card / Panel Background',       'hint'=>'Cards and sidebar colour'],
                    'bg_accent'  => ['label'=>'Highlight Panel Background',    'hint'=>'Active card and toggle backgrounds'],
                    'cream'      => ['label'=>'Primary Text Colour',           'hint'=>'Main readable text colour'],
                    'cream_dim'  => ['label'=>'Secondary Text Colour',         'hint'=>'Labels, hints, secondary info'],
            ];
            ?>
                <div class="page-header">
                    <div class="page-title">🎨 Themes</div>
                    <div class="page-desc">Choose a colour theme for the public site and admin panel. Changes apply immediately to all screens.</div>
                </div>
                <div class="gold-rule"></div>

                <form method="POST" action="admin.php?action=save_theme" id="theme-form">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="theme" id="theme-input" value="<?= e($active_theme) ?>">

                    <!-- Preset Theme Cards -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:28px;">
                        <?php foreach ($preset_themes as $tid => $td): ?>
                            <?php $is_active = ($active_theme === $tid); ?>
                            <div class="theme-card <?= $is_active ? 'theme-active' : '' ?>"
                                 data-theme="<?= e($tid) ?>"
                                 onclick="selectTheme('<?= e($tid) ?>')"
                                 style="cursor:pointer;">
                                <div class="card" style="margin:0;border:2px solid <?= $is_active ? 'var(--gold)' : 'var(--card-border)' ?>;transition:border-color 0.2s,transform 0.15s;position:relative;overflow:hidden;">
                                    <!-- Swatch bar -->
                                    <div style="height:8px;display:flex;">
                                        <div style="flex:1;background:<?= e($td['swatch_bg']) ?>;"></div>
                                        <div style="flex:1;background:<?= e($td['swatch_card']) ?>;"></div>
                                        <div style="flex:1;background:<?= e($td['swatch_acc']) ?>;"></div>
                                    </div>
                                    <div class="card-body" style="padding:18px 20px;">
                                        <!-- Active badge -->
                                        <?php if ($is_active): ?>
                                            <div style="position:absolute;top:16px;right:16px;background:var(--gold);color:var(--green-dark);font-size:9px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;padding:3px 10px;border-radius:20px;">Active</div>
                                        <?php endif; ?>
                                        <div style="font-size:26px;margin-bottom:8px;"><?= $td['emoji'] ?></div>
                                        <div style="font-size:15px;font-weight:800;color:var(--cream);margin-bottom:4px;"><?= e($td['name']) ?></div>
                                        <div style="font-size:11px;color:var(--cream-dim);line-height:1.5;"><?= e($td['desc']) ?></div>
                                        <!-- Mini swatch circles -->
                                        <div style="display:flex;gap:6px;margin-top:14px;align-items:center;">
                                            <div style="width:18px;height:18px;border-radius:50%;background:<?= e($td['swatch_bg']) ?>;border:1px solid rgba(255,255,255,0.15);" title="Background"></div>
                                            <div style="width:18px;height:18px;border-radius:50%;background:<?= e($td['swatch_card']) ?>;border:1px solid rgba(255,255,255,0.15);" title="Card"></div>
                                            <div style="width:18px;height:18px;border-radius:50%;background:<?= e($td['swatch_acc']) ?>;border:1px solid rgba(255,255,255,0.15);" title="Accent"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Custom Theme Editor (shown only when custom selected) -->
                    <div id="custom-editor" style="display:<?= $active_theme === 'custom' ? 'block' : 'none' ?>;">
                        <div class="card" style="margin-bottom:24px;">
                            <div class="card-top-rule"></div>
                            <div class="card-body">
                                <div style="font-size:13px;font-weight:800;color:var(--cream);margin-bottom:4px;">🎨 Custom Colour Editor</div>
                                <div style="font-size:11px;color:var(--cream-dim);margin-bottom:20px;">Adjust each colour below. The theme updates live as you pick colours.</div>
                                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                                    <?php foreach ($custom_fields as $fkey => $finfo): ?>
                                        <div style="display:flex;align-items:center;gap:12px;background:rgba(0,0,0,0.2);padding:12px 14px;border-radius:10px;border:1px solid var(--card-border);">
                                            <div style="position:relative;flex-shrink:0;">
                                                <input type="color"
                                                       id="cp_<?= e($fkey) ?>"
                                                       name="custom_<?= e($fkey) ?>"
                                                       value="<?= e($custom_vals[$fkey]) ?>"
                                                       oninput="livePreview()"
                                                       style="width:44px;height:44px;border:none;border-radius:8px;cursor:pointer;padding:2px;background:transparent;">
                                            </div>
                                            <div>
                                                <div style="font-size:12px;font-weight:700;color:var(--cream);"><?= e($finfo['label']) ?></div>
                                                <div style="font-size:10px;color:var(--cream-dim);margin-top:2px;"><?= e($finfo['hint']) ?></div>
                                                <div style="font-size:10px;color:var(--gold-dim);margin-top:2px;font-variant-numeric:tabular-nums;" id="hex_<?= e($fkey) ?>"><?= e($custom_vals[$fkey]) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="margin-top:16px;padding:12px 14px;background:rgba(201,168,76,0.06);border-radius:10px;border:1px solid var(--card-border);font-size:11px;color:var(--cream-dim);">
                                    💡 <strong style="color:var(--cream);">Tip:</strong> Click any colour swatch to open the colour picker. Changes preview live on this page — save to apply site-wide.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary" style="padding:12px 32px;font-size:14px;">💾 Save Theme</button>
                        <span style="font-size:11px;color:var(--cream-dim);">Active theme: <strong id="active-theme-label" style="color:var(--gold);"><?= e($preset_themes[$active_theme]['name'] ?? 'Custom') ?></strong></span>
                    </div>
                </form>

                <style>
                    .theme-card .card:hover { transform: translateY(-3px); border-color: var(--gold) !important; }
                    .theme-card.theme-active .card { box-shadow: 0 0 24px rgba(201,168,76,0.25); }
                    input[type="color"] { appearance: none; -webkit-appearance: none; }
                    input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
                    input[type="color"]::-webkit-color-swatch { border: none; border-radius: 6px; }
                </style>

                <script>
                    const themeNames = <?= json_encode(array_map(fn($t)=>$t['name'], $preset_themes)) ?>;

                    function selectTheme(tid) {
                        // Update hidden input
                        document.getElementById('theme-input').value = tid;
                        // Update active label
                        document.getElementById('active-theme-label').textContent = themeNames[tid] || tid;
                        // Update card borders
                        document.querySelectorAll('.theme-card').forEach(c => {
                            const isActive = c.dataset.theme === tid;
                            c.classList.toggle('theme-active', isActive);
                            const card = c.querySelector('.card');
                            card.style.borderColor = isActive ? 'var(--gold)' : 'var(--card-border)';
                            card.style.boxShadow   = isActive ? '0 0 24px rgba(201,168,76,0.25)' : '';
                            // Update / remove badge
                            let badge = card.querySelector('.active-badge');
                            if (isActive && !badge) {
                                badge = document.createElement('div');
                                badge.className = 'active-badge';
                                badge.style.cssText = 'position:absolute;top:16px;right:16px;background:var(--gold);color:var(--green-dark);font-size:9px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;padding:3px 10px;border-radius:20px;';
                                badge.textContent = 'Active';
                                card.querySelector('.card-body').prepend(badge);
                            } else if (!isActive && badge) { badge.remove(); }
                        });
                        // Show/hide custom editor
                        document.getElementById('custom-editor').style.display = tid === 'custom' ? 'block' : 'none';
                        // Live preview if custom
                        if (tid === 'custom') livePreview();
                        else applyPreset(tid);
                    }

                    const presetVars = {
                        green:    {'--gold':'#C9A84C','--gold-light':'#E8C97A','--gold-dim':'#8A6E32','--green-dark':'#07170A','--green-mid':'#102E16','--green':'#1B5E35','--cream':'#F5EDD6','--cream-dim':'#C8B98A'},
                        blue:     {'--gold':'#7EB8E8','--gold-light':'#A8D4F5','--gold-dim':'#3A6A96','--green-dark':'#050D18','--green-mid':'#0A1929','--green':'#0F2A42','--cream':'#E8F4FF','--cream-dim':'#8FB8D8'},
                        burgundy: {'--gold':'#D4A44C','--gold-light':'#E8C97A','--gold-dim':'#8A6232','--green-dark':'#120407','--green-mid':'#240810','--green':'#4A1020','--cream':'#F5EDE8','--cream-dim':'#C8A898'},
                        grey:     {'--gold':'#A0A8B0','--gold-light':'#C8D0D8','--gold-dim':'#606870','--green-dark':'#0D0E0F','--green-mid':'#181A1C','--green':'#252729','--cream':'#E8EAEC','--cream-dim':'#909498'},
                        white:    {'--gold':'#B07D2A','--gold-light':'#C9A84C','--gold-dim':'#8A6432','--green-dark':'#F7F5F0','--green-mid':'#EDE9E0','--green':'#DDD6C8','--cream':'#2A2420','--cream-dim':'#5A524A'},
                    };

                    function hexToRgb(hex) {
                        hex = hex.replace('#','');
                        if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
                        return [parseInt(hex.slice(0,2),16), parseInt(hex.slice(2,4),16), parseInt(hex.slice(4,6),16)];
                    }

                    function applyPreset(tid) {
                        if (!presetVars[tid]) return;
                        const root = document.documentElement;
                        const v = presetVars[tid];
                        Object.entries(v).forEach(([k,val]) => root.style.setProperty(k, val));
                        applyDerivedVars(v['--gold'], v['--green-dark'], v['--green-mid'], v['--green']);
                    }

                    function applyDerivedVars(gold, bgDark, bgMid, bgAccent) {
                        const root = document.documentElement;
                        const [ar,ag,ab] = hexToRgb(gold);
                        const [dr,dg,db] = hexToRgb(bgDark);
                        const [mr,mg,mb] = hexToRgb(bgMid);
                        const [hr,hg,hb] = hexToRgb(bgAccent);
                        const isLight = (dr+dg+db)/3 > 128;

                        root.style.setProperty('--card-bg',     `rgba(${mr},${mg},${mb},0.82)`);
                        root.style.setProperty('--card-border', isLight ? `rgba(${ar},${ag},${ab},0.30)` : `rgba(${ar},${ag},${ab},0.22)`);
                        root.style.setProperty('--card-active', `rgba(${ar},${ag},${ab},0.13)`);
                        root.style.setProperty('--input-bg',    isLight ? 'rgba(255,255,255,0.7)' : `rgba(${dr},${dg},${db},0.7)`);

                        root.style.setProperty('--bg-deep',     `rgba(${dr},${dg},${db},0.92)`);
                        root.style.setProperty('--bg-card-hi',  `rgba(${mr},${mg},${mb},0.92)`);
                        root.style.setProperty('--bg-card-lo',  isLight ? `rgba(${hr},${hg},${hb},0.60)` : `rgba(${hr},${hg},${hb},0.42)`);
                        root.style.setProperty('--bg-card-mid', `rgba(${mr},${mg},${mb},0.80)`);
                        root.style.setProperty('--bg-marker',   isLight ? `rgba(${mr},${mg},${mb},0.85)` : `rgba(${dr},${dg},${db},0.78)`);
                        root.style.setProperty('--bg-ticker',   `rgba(${dr},${dg},${db},0.92)`);
                        root.style.setProperty('--bg-sim',      `rgba(${dr},${dg},${db},0.97)`);
                        root.style.setProperty('--bg-fab',      `rgba(${dr},${dg},${db},0.97)`);

                        root.style.setProperty('--accent-glow-sm', `rgba(${ar},${ag},${ab},0.09)`);
                        root.style.setProperty('--accent-glow-bg', `rgba(${ar},${ag},${ab},0.38)`);
                        root.style.setProperty('--accent-glow-hi', `rgba(${ar},${ag},${ab},0.9)`);
                        root.style.setProperty('--accent-faint',   `rgba(${ar},${ag},${ab},0.08)`);
                        root.style.setProperty('--accent-subtle',  `rgba(${ar},${ag},${ab},0.13)`);
                        root.style.setProperty('--accent-low',     `rgba(${ar},${ag},${ab},0.18)`);
                        root.style.setProperty('--accent-mid',     `rgba(${ar},${ag},${ab},0.22)`);
                        root.style.setProperty('--accent-mod',     `rgba(${ar},${ag},${ab},0.25)`);
                        root.style.setProperty('--accent-str',     `rgba(${ar},${ag},${ab},0.30)`);
                        root.style.setProperty('--accent-brt',     `rgba(${ar},${ag},${ab},0.50)`);
                        root.style.setProperty('--accent-glow30',  `rgba(${ar},${ag},${ab},0.30)`);
                        root.style.setProperty('--accent-act',     `rgba(${ar},${ag},${ab},0.10)`);
                        root.style.setProperty('--accent-act2',    `rgba(${ar},${ag},${ab},0.05)`);
                        root.style.setProperty('--accent-shadow',  `rgba(${ar},${ag},${ab},0.20)`);
                        root.style.setProperty('--accent-shadow2', `rgba(${ar},${ag},${ab},0.2)`);
                    }

                    function livePreview() {
                        const root = document.documentElement;
                        const map = {
                            'gold':'--gold','gold_light':'--gold-light','gold_dim':'--gold-dim',
                            'bg_dark':'--green-dark','bg_mid':'--green-mid','bg_accent':'--green',
                            'cream':'--cream','cream_dim':'--cream-dim'
                        };
                        const vals = {};
                        Object.entries(map).forEach(([fk, cssVar]) => {
                            const el = document.getElementById('cp_' + fk);
                            if (el) {
                                root.style.setProperty(cssVar, el.value);
                                vals[cssVar] = el.value;
                                const hexEl = document.getElementById('hex_' + fk);
                                if (hexEl) hexEl.textContent = el.value;
                            }
                        });
                        // Derive all rgba vars from the picked colours
                        const gold    = vals['--gold']       || '#C9A84C';
                        const bgDark  = vals['--green-dark'] || '#07170A';
                        const bgMid   = vals['--green-mid']  || '#102E16';
                        const bgAccent= vals['--green']      || '#1B5E35';
                        applyDerivedVars(gold, bgDark, bgMid, bgAccent);
                    }

                    // Initialise — apply live preview if custom is already active
                    <?php if ($active_theme === 'custom'): ?>
                    document.addEventListener('DOMContentLoaded', () => { livePreview(); });
                    <?php elseif ($active_theme !== 'green'): ?>
                    // No-op: PHP already injected the theme override CSS
                    <?php endif; ?>
                </script>

                </script>

            <?php /* ── RAMADAN OVERRIDE ── */ elseif (in_array($action, ['ramadan','ramadan_setup','ramadan_save_grid','ramadan_toggle','ramadan_reset'])): ?>

            <?php
            /* ── Status computation ── */
            $ram_has_schedule = !empty($ramadan_schedule);
            $ram_is_active    = $ram_has_schedule && (int)$ramadan_schedule['is_active'] === 1;
            $ram_today        = date('Y-m-d');
            $ram_start        = $ram_has_schedule ? $ramadan_schedule['start_date'] : null;
            $ram_end          = $ram_has_schedule ? $ramadan_schedule['end_date']   : null;
            $ram_expired      = $ram_has_schedule && $ram_today > $ram_end;
            $ram_in_window    = $ram_has_schedule && $ram_today >= $ram_start && $ram_today <= $ram_end;
            $ram_days_count   = $ram_has_schedule ? ((int)(strtotime($ram_end) - strtotime($ram_start)) / 86400) + 1 : 0;

            /* Status label and colour */
            if (!$ram_has_schedule) {
                $ram_status_label = 'Not Set Up';
                $ram_status_color = 'var(--cream-dim)';
                $ram_status_bg    = 'rgba(200,185,138,0.1)';
                $ram_status_border= 'rgba(200,185,138,0.25)';
                $ram_status_icon  = '⚙️';
            } elseif ($ram_expired) {
                $ram_status_label = 'Expired';
                $ram_status_color = 'var(--red-soft)';
                $ram_status_bg    = 'rgba(192,57,43,0.12)';
                $ram_status_border= 'rgba(192,57,43,0.3)';
                $ram_status_icon  = '⛔';
            } elseif ($ram_is_active && $ram_in_window) {
                $ram_status_label = 'Active — Ramadan Mode LIVE';
                $ram_status_color = '#2ecc71';
                $ram_status_bg    = 'rgba(46,204,113,0.1)';
                $ram_status_border= 'rgba(46,204,113,0.3)';
                $ram_status_icon  = '🌙';
            } elseif ($ram_is_active && !$ram_in_window) {
                $ram_status_label = 'Active (scheduled — not yet in window)';
                $ram_status_color = '#f1c40f';
                $ram_status_bg    = 'rgba(241,196,15,0.1)';
                $ram_status_border= 'rgba(241,196,15,0.3)';
                $ram_status_icon  = '⏳';
            } else {
                $ram_status_label = 'Inactive';
                $ram_status_color = 'var(--cream-dim)';
                $ram_status_bg    = 'rgba(200,185,138,0.08)';
                $ram_status_border= 'rgba(200,185,138,0.2)';
                $ram_status_icon  = '⏸️';
            }
            ?>

                <div class="page-header">
                    <div class="page-title">☪️ Ramadan Override</div>
                    <div class="page-desc">Set special Jamaat times for Fajr, Zuhr, Asr and Esha during Ramadan. Maghrib always follows the main timetable.</div>
                </div>
                <div class="gold-rule"></div>

            <?php if ($ram_has_schedule): ?>
                <!-- ═══════════════════════════════════
     STATUS BANNER + CONTROLS
     ═══════════════════════════════════ -->
                <div style="background:<?= $ram_status_bg ?>;border:1px solid <?= $ram_status_border ?>;border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
                    <div style="display:flex;align-items:center;gap:14px;">
                        <div style="font-size:32px;line-height:1;"><?= $ram_status_icon ?></div>
                        <div>
                            <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:<?= $ram_status_color ?>;font-weight:700;margin-bottom:3px;">Ramadan Override Status</div>
                            <div style="font-size:17px;font-weight:800;color:<?= $ram_status_color ?>;"><?= e($ram_status_label) ?></div>
                            <div style="font-size:11px;color:var(--cream-dim);margin-top:3px;">
                                <?= date('j M Y', strtotime($ram_start)) ?> → <?= date('j M Y', strtotime($ram_end)) ?>
                                &nbsp;·&nbsp; <?= $ram_days_count ?> days
                                <?php if ($ram_in_window): ?>
                                    &nbsp;·&nbsp; <strong style="color:<?= $ram_status_color ?>;">Today: <?= date('j M Y') ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <?php if (!$ram_expired): ?>
                            <form method="POST" action="admin.php?action=ramadan_toggle" style="margin:0;">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                <?php if ($ram_is_active): ?>
                                    <button type="submit" class="btn" style="background:rgba(192,57,43,0.2);border:1px solid rgba(192,57,43,0.5);color:var(--red-soft);font-weight:800;padding:10px 22px;">
                                        ⏸️ Deactivate
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary" style="background:rgba(46,204,113,0.2);border:1px solid rgba(46,204,113,0.5);color:#2ecc71;font-weight:800;padding:10px 22px;">
                                        🌙 Activate Ramadan Mode
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="admin.php?action=ramadan_reset" style="margin:0;"
                              onsubmit="return confirm('Reset the entire Ramadan schedule? This will delete all times and cannot be undone.');">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <button type="submit" class="btn" style="background:var(--red-bg);border:1px solid var(--red-border);color:var(--red-soft);padding:10px 18px;">
                                🗑️ Reset Schedule
                            </button>
                        </form>
                    </div>
                </div>

            <?php if ($ram_is_active && $ram_in_window): ?>
                <div style="background:rgba(46,204,113,0.07);border:1px solid rgba(46,204,113,0.25);border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:12px;color:#2ecc71;line-height:1.6;">
                    🌙 <strong>Ramadan Mode is LIVE.</strong> The public website and musjid displays are now using Ramadan Jamaat times for Fajr, Zuhr, Asr and Esha. Maghrib continues to use the main timetable. The override will automatically deactivate after <strong><?= date('j M Y', strtotime($ram_end)) ?></strong>.
                </div>
            <?php endif; ?>

                <!-- ═══════════════════════════════════
     EDIT GRID
     ═══════════════════════════════════ -->
                <div class="card">
                    <div class="card-top-rule"></div>
                    <div class="card-body">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                            <div>
                                <div style="font-family:'Cinzel Decorative',serif;font-size:13px;color:var(--gold);margin-bottom:4px;">📋 Ramadan Jamaat Times</div>
                                <div style="font-size:11px;color:var(--cream-dim);">Edit Fajr, Zuhr, Asr and Esha times for each day of Ramadan. Maghrib is excluded — it uses the main timetable. Grey reference columns show original times.</div>
                            </div>
                            <button type="submit" form="ramadan-grid-form" class="btn btn-primary" style="padding:10px 24px;">
                                💾 Save All Times
                            </button>
                        </div>

                        <form id="ramadan-grid-form" method="POST" action="admin.php?action=ramadan_save_grid" autocomplete="off">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

                            <!-- Responsive table wrapper -->
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:12px;font-variant-numeric:tabular-nums;">
                                    <thead>
                                    <tr style="border-bottom:1px solid var(--card-border);">
                                        <th style="padding:8px 10px;text-align:left;color:var(--gold-dim);font-size:9px;letter-spacing:2px;text-transform:uppercase;white-space:nowrap;">Date</th>
                                        <th style="padding:8px 10px;text-align:left;color:var(--gold-dim);font-size:9px;letter-spacing:2px;text-transform:uppercase;" colspan="2">🌅 Fajr</th>
                                        <th style="padding:8px 10px;text-align:left;color:var(--gold-dim);font-size:9px;letter-spacing:2px;text-transform:uppercase;" colspan="2">☀️ Zuhr</th>
                                        <th style="padding:8px 10px;text-align:left;color:var(--gold-dim);font-size:9px;letter-spacing:2px;text-transform:uppercase;" colspan="2">🌤️ Asr</th>
                                        <th style="padding:8px 10px;text-align:left;color:var(--gold-dim);font-size:9px;letter-spacing:2px;text-transform:uppercase;" colspan="2">🌃 Esha</th>
                                    </tr>
                                    <tr style="border-bottom:2px solid var(--card-border);">
                                        <th style="padding:4px 10px;"></th>
                                        <?php foreach (['fajr','zuhr','asr','esha'] as $f): ?>
                                            <th style="padding:4px 8px;color:var(--gold);font-size:9px;font-weight:800;">Ramadan</th>
                                            <th style="padding:4px 8px;color:var(--cream-dim);font-size:9px;font-weight:600;">Original</th>
                                        <?php endforeach; ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $today_ymd = date('Y-m-d');
                                    foreach ($ramadan_rows as $idx => $rr):
                                        $is_today  = ($rr['prayer_date'] === $today_ymd);
                                        $is_past   = ($rr['prayer_date'] < $today_ymd);
                                        $row_style = $is_today  ? 'background:rgba(46,204,113,0.08);border-left:3px solid #2ecc71;'
                                                : ($is_past ? 'opacity:0.6;' : '');
                                        $row_n     = $idx + 1;
                                        $date_disp = date('D j M', strtotime($rr['prayer_date']));
                                        ?>
                                        <tr style="border-bottom:1px solid rgba(201,168,76,0.07);<?= $row_style ?>">
                                            <td style="padding:6px 10px;white-space:nowrap;">
                                                <span style="font-size:9px;color:var(--gold-dim);display:block;">Day <?= $row_n ?></span>
                                                <span style="font-weight:700;color:<?= $is_today ? '#2ecc71' : 'var(--cream)' ?>;"><?= $date_disp ?></span>
                                                <?php if ($is_today): ?><span style="display:block;font-size:8px;color:#2ecc71;font-weight:800;letter-spacing:1px;">TODAY</span><?php endif; ?>
                                            </td>
                                            <?php foreach (['fajr','zuhr','asr','esha'] as $f):
                                                $current_val = substr($rr[$f], 0, 5);
                                                $orig_val    = $rr['orig_'.$f] ? substr($rr['orig_'.$f], 0, 5) : '—';
                                                $changed     = $current_val !== $orig_val && $orig_val !== '—';
                                                ?>
                                                <td style="padding:4px 6px;">
                                                    <input type="time"
                                                           name="times[<?= e($rr['prayer_date']) ?>][<?= $f ?>]"
                                                           value="<?= e($current_val) ?>"
                                                           required
                                                           style="background:var(--input-bg);border:1px solid <?= $changed ? 'rgba(201,168,76,0.6)' : 'var(--input-border)' ?>;color:<?= $changed ? 'var(--gold-light)' : 'var(--cream)' ?>;border-radius:8px;padding:5px 8px;font-family:'Nunito',sans-serif;font-size:12px;font-variant-numeric:tabular-nums;width:100%;min-width:90px;"
                                                           oninput="markChanged(this, '<?= e($orig_val) ?>')">
                                                </td>
                                                <td style="padding:4px 8px;color:var(--cream-dim);font-size:11px;white-space:nowrap;"><?= e($orig_val) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top:18px;display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;">
                                <button type="submit" class="btn btn-primary" style="padding:11px 32px;font-size:14px;">
                                    💾 Save All Times
                                </button>
                            </div>
                        </form>

                        <div style="margin-top:16px;padding:12px 16px;background:rgba(201,168,76,0.05);border-radius:10px;border:1px solid var(--card-border);font-size:11px;color:var(--cream-dim);line-height:1.7;">
                            💡 <strong style="color:var(--cream);">Tips:</strong>
                            Gold-highlighted inputs indicate times that differ from the original timetable.
                            Maghrib is excluded and will always use the standard timetable value.
                            Greyed rows are past dates (still editable).
                            <strong style="color:#2ecc71;">Green row</strong> = today.
                        </div>
                    </div>
                </div>

                <script>
                    function markChanged(input, origVal) {
                        const changed = input.value !== origVal && origVal !== '—';
                        input.style.borderColor  = changed ? 'rgba(201,168,76,0.6)' : 'var(--input-border)';
                        input.style.color        = changed ? 'var(--gold-light)' : 'var(--cream)';
                    }
                </script>

            <?php else: ?>
                <!-- ═══════════════════════════════════
     SETUP FORM (no schedule yet)
     ═══════════════════════════════════ -->
                <div class="card" style="max-width:600px;">
                    <div class="card-top-rule"></div>
                    <div class="card-body">
                        <div style="font-family:'Cinzel Decorative',serif;font-size:14px;color:var(--gold);margin-bottom:6px;">☪️ Create Ramadan Schedule</div>
                        <div style="font-size:12px;color:var(--cream-dim);margin-bottom:24px;line-height:1.6;">
                            Select the start and end dates of Ramadan. The range must be <strong style="color:var(--cream);">29 to 31 consecutive days</strong>.<br>
                            Jamaat times for Fajr, Zuhr, Asr and Esha will be pre-filled from the main timetable — you can then edit them individually.
                        </div>

                        <form method="POST" action="admin.php?action=ramadan_setup" autocomplete="off" id="ramadan-setup-form">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

                            <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
                                <div class="form-group">
                                    <label class="form-label">🌙 Ramadan Start Date</label>
                                    <input type="date" name="start_date" id="ram_start" class="form-input"
                                           value="<?= e($_POST['start_date'] ?? '') ?>"
                                           required onchange="updateDayCount()">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">🌄 Ramadan End Date</label>
                                    <input type="date" name="end_date" id="ram_end" class="form-input"
                                           value="<?= e($_POST['end_date'] ?? '') ?>"
                                           required onchange="updateDayCount()">
                                </div>
                            </div>

                            <!-- Live day count feedback -->
                            <div id="day-count-feedback" style="margin-bottom:22px;padding:12px 16px;border-radius:10px;
                 background:rgba(201,168,76,0.07);border:1px solid var(--card-border);
                 font-size:12px;color:var(--cream-dim);display:none;">
                                <span id="day-count-icon">📅</span>
                                <span id="day-count-text"></span>
                            </div>

                            <button type="submit" id="ram-setup-btn" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:14px;" disabled>
                                🌙 Generate Ramadan Timetable
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card" style="max-width:600px;margin-top:16px;">
                    <div class="card-body" style="padding:18px 22px;">
                        <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gold-dim);margin-bottom:10px;">ℹ️ How This Works</div>
                        <ul style="font-size:12px;color:var(--cream-dim);line-height:2;list-style:none;padding:0;">
                            <li>🔄 Times are pre-filled from the main timetable for the selected dates</li>
                            <li>✏️ You can edit any time individually in the grid</li>
                            <li>🌙 Activate the override when Ramadan begins</li>
                            <li>⏸️ The override auto-deactivates after the end date</li>
                            <li>🗑️ You can reset and regenerate the schedule at any time</li>
                            <li>🕌 Maghrib always uses the main timetable — never overridden</li>
                            <li>📺 Musjid screens and website both update within 60 seconds of changes</li>
                        </ul>
                    </div>
                </div>

                <script>
                    function updateDayCount() {
                        const startEl = document.getElementById('ram_start');
                        const endEl   = document.getElementById('ram_end');
                        const fb      = document.getElementById('day-count-feedback');
                        const btn     = document.getElementById('ram-setup-btn');
                        const icon    = document.getElementById('day-count-icon');
                        const txt     = document.getElementById('day-count-text');

                        if (!startEl.value || !endEl.value) { fb.style.display = 'none'; btn.disabled = true; return; }

                        const startTs = new Date(startEl.value).getTime();
                        const endTs   = new Date(endEl.value).getTime();

                        if (endTs <= startTs) {
                            fb.style.display = 'block';
                            fb.style.borderColor = 'rgba(192,57,43,0.4)';
                            fb.style.background  = 'rgba(192,57,43,0.08)';
                            icon.textContent = '⚠️';
                            txt.style.color  = 'var(--red-soft)';
                            txt.textContent  = 'End date must be after start date.';
                            btn.disabled = true;
                            return;
                        }

                        const days = Math.round((endTs - startTs) / 86400000) + 1;
                        fb.style.display = 'block';

                        if (days < 29) {
                            fb.style.borderColor = 'rgba(192,57,43,0.4)';
                            fb.style.background  = 'rgba(192,57,43,0.08)';
                            icon.textContent = '❌';
                            txt.style.color  = 'var(--red-soft)';
                            txt.textContent  = days + ' days selected — minimum is 29 days.';
                            btn.disabled = true;
                        } else if (days > 31) {
                            fb.style.borderColor = 'rgba(192,57,43,0.4)';
                            fb.style.background  = 'rgba(192,57,43,0.08)';
                            icon.textContent = '❌';
                            txt.style.color  = 'var(--red-soft)';
                            txt.textContent  = days + ' days selected — maximum is 31 days.';
                            btn.disabled = true;
                        } else {
                            fb.style.borderColor = 'rgba(46,204,113,0.4)';
                            fb.style.background  = 'rgba(46,204,113,0.08)';
                            icon.textContent = '✅';
                            txt.style.color  = '#2ecc71';
                            txt.textContent  = days + ' days selected — valid Ramadan range!';
                            btn.disabled = false;
                        }
                    }
                    // Run on load if values are pre-filled (e.g. after validation error redirect)
                    document.addEventListener('DOMContentLoaded', updateDayCount);
                </script>
            <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-error">⚠️ Page not found or access denied.</div>
                <a href="admin.php?action=dashboard" class="btn btn-secondary">← Dashboard</a>

            <?php endif; ?>

        </main>
    </div><!-- /.layout -->
<?php endif; ?>

</body>
</html>