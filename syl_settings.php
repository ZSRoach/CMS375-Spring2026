<?php
session_start();
require_once 'db.php';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatDuration($s) { if (!$s) return '&mdash;'; return floor($s/60).':'.str_pad($s%60,2,'0',STR_PAD_LEFT); }
$isLoggedIn = isset($_SESSION['user_tag']);
$userTag    = $isLoggedIn ? $_SESSION['user_tag'] : '';
if (isset($_GET['action']) && $_GET['action'] === 'logout') { session_destroy(); header('Location: syl_home.php'); exit; }
?>
<?php
// ─── Auth guard — settings requires login ────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: syl_auth.php?tab=login');
    exit;
}

// ─── Flash messages ───────────────────────────────────────────────────────────
$flashError   = $_SESSION['flash_error']   ?? '';
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ─── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update profile — saves Users.first_name, last_name, email, bio
    // Users.tag (PK) is never editable
    if ($action === 'update_profile') {
        $fn    = trim($_POST['first_name'] ?? '');
        $ln    = trim($_POST['last_name']  ?? '');
        $email = trim($_POST['email']      ?? '');
        $bio   = trim($_POST['bio']        ?? '') ?: null;
        if (!$fn || !$email) {
            $_SESSION['flash_error'] = '⚠ First name and email are required.';
        } elseif (strlen($bio??'') > 200) {
            $_SESSION['flash_error'] = '⚠ Bio must be 200 characters or fewer.';
        } else {
            $check = $pdo->prepare("SELECT tag FROM Users WHERE email=? AND tag!=?");
            $check->execute([$email,$userTag]);
            if ($check->fetch()) {
                $_SESSION['flash_error'] = '⚠ That email belongs to another account.';
            } else {
                $pdo->prepare("UPDATE Users SET first_name=?,last_name=?,email=?,bio=? WHERE tag=?")
                    ->execute([$fn,$ln,$email,$bio,$userTag]);
                $_SESSION['flash_success'] = '✓ Profile updated.';
            }
        }
        header('Location: syl_settings.php'); exit;
    }

    // Update password — verifies current hash, stores new bcrypt hash
    if ($action === 'update_password') {
        $current = $_POST['current_password'] ?? '';
        $newPw   = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $row = $pdo->prepare("SELECT pw_hash FROM Users WHERE tag=?")->execute([$userTag]) ? null : null;
        $stmt = $pdo->prepare("SELECT pw_hash FROM Users WHERE tag=?");
        $stmt->execute([$userTag]);
        $row = $stmt->fetch();
        if (!$current || !password_verify($current, $row['pw_hash'])) {
            $_SESSION['flash_error'] = '⚠ Current password is incorrect.';
        } elseif (strlen($newPw) < 8) {
            $_SESSION['flash_error'] = '⚠ New password must be at least 8 characters.';
        } elseif ($newPw !== $confirm) {
            $_SESSION['flash_error'] = '⚠ Passwords do not match.';
        } else {
            $pdo->prepare("UPDATE Users SET pw_hash=? WHERE tag=?")
                ->execute([password_hash($newPw,PASSWORD_BCRYPT),$userTag]);
            $_SESSION['flash_success'] = '✓ Password updated.';
        }
        header('Location: syl_settings.php'); exit;
    }

    // Delete account — CASCADE in schema auto-deletes UserReviews and UserFavorites
    if ($action === 'delete_account') {
        $pdo->prepare("DELETE FROM Users WHERE tag=?")->execute([$userTag]);
        session_destroy();
        header('Location: syl_home.php'); exit;
    }
}

// ─── Load current user data ───────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT tag,first_name,last_name,email,bio FROM Users WHERE tag=?");
$stmt->execute([$userTag]);
$user = $stmt->fetch();

// ─── Load favorites from UserFavorites table ──────────────────────────────────
$favorites = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.song_name, r.artist_name, uf.rank
        FROM UserFavorites uf
        JOIN Reviews r ON r.id = uf.review_id
        WHERE uf.user_tag=? ORDER BY uf.rank ASC
    ");
    $stmt->execute([$userTag]);
    $favorites = $stmt->fetchAll();
} catch (Exception $e) {}

$favColors = ['#69D17E','#5B9BF5','#9C6FE4','#FF8A50'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL — Settings</title>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--yellow:#F5E642;--pink:#F06292;--teal:#4DD0C4;--lavender:#B39DDB;--green:#69D17E;--orange:#FF8A50;--blue:#5B9BF5;--purple:#9C6FE4;--sand:#E8E0D0;--dark:#1A1A2E;--darker:#0F0F1E;--card:#22223B;--text:#F0EDE6;--muted:#8B8BAA;--sidebar-w:220px;--sidebar-w-col:64px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--darker);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

/* ─── SIDEBAR (shared) ─── */
#sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--dark);border-right:1px solid #ffffff0a;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:200;transition:width .3s cubic-bezier(.4,0,.2,1);overflow:hidden;}
#sidebar.col{width:var(--sidebar-w-col);}
.sb-top{display:flex;align-items:center;padding:20px 16px 16px;border-bottom:1px solid #ffffff08;min-height:64px;flex-shrink:0;}
.sb-logo{font-family:'Dela Gothic One',sans-serif;font-size:1.4rem;background:linear-gradient(90deg,var(--yellow),var(--pink));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;white-space:nowrap;opacity:1;transition:opacity .2s;cursor:pointer;}
#sidebar.col .sb-logo{opacity:0;pointer-events:none;}
#sb-toggle-btn{position:fixed;top:14px;z-index:300;background:var(--dark);border:1px solid #ffffff12;color:var(--muted);cursor:pointer;font-size:1.05rem;width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;transition:color .2s,background .2s,left .3s cubic-bezier(.4,0,.2,1),box-shadow .2s;}
#sb-toggle-btn:hover{color:var(--yellow);background:var(--card);box-shadow:0 0 0 3px #F5E64220;}
body:not(.sb-col) #sb-toggle-btn{left:calc(var(--sidebar-w) - 46px);}
body.sb-col #sb-toggle-btn{left:14px;}
.sb-nav{flex:1;padding:16px 8px;display:flex;flex-direction:column;gap:4px;overflow:hidden;}
.sb-link{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;text-decoration:none;background:none;color:var(--muted);cursor:pointer;font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:500;white-space:nowrap;transition:background .18s,color .18s;text-align:left;width:100%;position:relative;}
.sb-link:hover{background:#ffffff08;color:var(--text);}
.sb-link.active{background:#F5E64218;color:var(--yellow);}
.sb-icon{font-size:1.15rem;flex-shrink:0;width:24px;text-align:center;}
.sb-label{opacity:1;transition:opacity .2s;overflow:hidden;}
#sidebar.col .sb-label{opacity:0;}
.sb-bottom{padding:8px 8px 20px;border-top:1px solid #ffffff08;}
#sidebar.col .sb-link::after{content:attr(data-label);position:absolute;left:calc(var(--sidebar-w-col) + 8px);top:50%;transform:translateY(-50%);background:var(--card);color:var(--text);font-size:.78rem;padding:5px 10px;border-radius:8px;white-space:nowrap;pointer-events:none;opacity:0;border:1px solid #ffffff15;transition:opacity .15s;z-index:300;}
#sidebar.col .sb-link:hover::after{opacity:1;}
#main{margin-left:var(--sidebar-w);flex:1;transition:margin-left .3s cubic-bezier(.4,0,.2,1);min-width:0;}
body.sb-col #main{margin-left:var(--sidebar-w-col);}

/* ─── TOP NAV ─── */
.top-nav{display:flex;align-items:center;justify-content:flex-end;padding:14px 40px;background:var(--dark);border-bottom:1px solid #ffffff0a;position:sticky;top:0;z-index:100;gap:10px;}
.nav-logo{font-family:'Dela Gothic One',sans-serif;font-size:1.3rem;background:linear-gradient(90deg,var(--yellow),var(--pink),var(--teal));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-right:auto;padding-left:50px;}
.nbtn{padding:8px 20px;border-radius:100px;border:none;font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:600;cursor:pointer;transition:all .2s;}
.nbtn.ghost{background:transparent;color:var(--text);border:2px solid #ffffff20;}
.nbtn.ghost:hover{border-color:var(--yellow);color:var(--yellow);}
.nbtn.primary{background:var(--yellow);color:var(--dark);}
.nbtn.primary:hover{background:#ffe000;transform:translateY(-1px);}
.nbtn.danger{background:transparent;color:var(--pink);border:2px solid var(--pink)33;}
.nbtn.danger:hover{background:var(--pink)18;border-color:var(--pink);}

/* ─── SETTINGS LAYOUT ─── */
.page-wrap{padding:40px;max-width:800px;margin:0 auto;}
.page-header{margin-bottom:36px;}
.page-title{font-family:'Dela Gothic One',sans-serif;font-size:2.2rem;margin-bottom:6px;}
.page-sub{font-size:.9rem;color:var(--muted);line-height:1.5;}

/* ─── SETTINGS SECTIONS ─── */
.settings-section{background:var(--card);border-radius:16px;padding:28px;border:1px solid #ffffff08;margin-bottom:20px;}
.settings-section-header{display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #ffffff08;}
.settings-section-icon{font-size:1.2rem;}
.settings-section-title{font-family:'Dela Gothic One',sans-serif;font-size:1rem;}
.settings-section-sub{font-size:.78rem;color:var(--muted);margin-top:2px;}

/* ─── FORM FIELDS ─── */
/* Settings forms POST to SYL.php with action=update_profile / update_password / delete_account
   All editable fields map directly to Users table columns:
     tag (read-only PK), first_name, last_name, email, bio, picture, pw_hash */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
.form-row.single{grid-template-columns:1fr;}
.form-group{display:flex;flex-direction:column;gap:7px;}
.form-label{font-size:.78rem;font-weight:600;color:var(--muted);letter-spacing:1px;text-transform:uppercase;}
.form-input{padding:12px 15px;background:var(--darker);border:2px solid #ffffff10;border-radius:12px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.92rem;outline:none;transition:border-color .2s;}
.form-input:focus{border-color:var(--yellow);}
.form-input::placeholder{color:var(--muted);}
.form-input:disabled{opacity:.45;cursor:not-allowed;}
.form-input.readonly{opacity:.6;cursor:default;}
.form-hint{font-size:.72rem;color:var(--muted);margin-top:2px;}
textarea.form-input{resize:vertical;min-height:80px;line-height:1.5;}

/* Save button */
.save-btn{padding:11px 28px;background:var(--yellow);color:var(--dark);border:none;border-radius:12px;font-family:'Dela Gothic One',sans-serif;font-size:.9rem;cursor:pointer;transition:all .2s;margin-top:8px;}
.save-btn:hover{background:#ffe000;transform:translateY(-1px);}

/* Password fields */
.pw-wrap{position:relative;}
.pw-toggle{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:.95rem;opacity:.5;transition:opacity .15s;}
.pw-toggle:hover{opacity:1;}
.pw-strength-bar{height:4px;background:#ffffff10;border-radius:4px;margin-top:8px;overflow:hidden;}
#pw-fill{height:100%;width:0%;border-radius:4px;transition:width .3s,background .3s;}
.pw-strength-label{font-size:.7rem;margin-top:4px;color:var(--muted);font-family:'Space Mono',monospace;}

/* Flash message */
.flash{display:none;padding:10px 16px;border-radius:10px;font-size:.82rem;font-family:'Space Mono',monospace;margin-bottom:16px;}
.flash.success{background:#69D17E18;border:1px solid #69D17E44;color:var(--green);}
.flash.error  {background:#F0629218;border:1px solid #F0629244;color:var(--pink);}

/* ─── AVATAR SECTION ─── */
.avatar-row{display:flex;align-items:center;gap:20px;margin-bottom:20px;}
.avatar-preview{width:72px;height:72px;border-radius:16px;background:linear-gradient(135deg,var(--green),var(--teal));display:flex;align-items:center;justify-content:center;font-size:2rem;border:3px solid var(--yellow);flex-shrink:0;}
.avatar-actions{display:flex;flex-direction:column;gap:8px;}
.avatar-btn{padding:8px 18px;background:none;border:2px solid #ffffff15;border-radius:10px;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .15s;}
.avatar-btn:hover{border-color:var(--yellow);color:var(--yellow);}
.avatar-note{font-size:.72rem;color:var(--muted);}

/* ─── DANGER ZONE ─── */
.danger-zone{background:var(--card);border-radius:16px;padding:28px;border:1px solid var(--pink)22;margin-bottom:20px;}
.danger-header{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.danger-icon{font-size:1.2rem;}
.danger-title{font-family:'Dela Gothic One',sans-serif;font-size:1rem;color:var(--pink);}
.danger-item{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid #ffffff08;gap:20px;}
.danger-item:last-child{border-bottom:none;padding-bottom:0;}
.danger-item-info{flex:1;}
.danger-item-label{font-weight:600;font-size:.9rem;margin-bottom:3px;}
.danger-item-sub{font-size:.78rem;color:var(--muted);}
.danger-btn{padding:8px 18px;background:none;border:2px solid var(--pink)55;color:var(--pink);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap;flex-shrink:0;}
.danger-btn:hover{background:var(--pink)15;}

/* ─── CONFIRM DIALOG ─── */
.modal-overlay{display:none;position:fixed;inset:0;background:#00000085;backdrop-filter:blur(6px);z-index:400;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.confirm-modal{background:var(--card);border-radius:20px;padding:36px;width:400px;max-width:95vw;border:1px solid #ffffff15;box-shadow:0 32px 80px #000000a0;text-align:center;}
.cm-icon{font-size:2.5rem;margin-bottom:14px;}
.cm-title{font-family:'Dela Gothic One',sans-serif;font-size:1.3rem;margin-bottom:8px;}
.cm-sub{font-size:.85rem;color:var(--muted);line-height:1.6;margin-bottom:24px;}
.cm-actions{display:flex;gap:10px;justify-content:center;}
.cm-cancel{padding:10px 24px;background:none;border:2px solid #ffffff20;border-radius:12px;color:var(--muted);font-family:'DM Sans',sans-serif;font-weight:600;cursor:pointer;transition:all .2s;}
.cm-cancel:hover{border-color:var(--text);color:var(--text);}
.cm-confirm{padding:10px 24px;background:var(--pink);border:none;border-radius:12px;color:#fff;font-family:'Dela Gothic One',sans-serif;cursor:pointer;transition:all .2s;}
.cm-confirm:hover{background:#e05580;}

footer{text-align:center;padding:32px 40px;color:var(--muted);font-size:.75rem;font-family:'Space Mono',monospace;border-top:1px solid #ffffff08;}

@media(max-width:600px){.form-row{grid-template-columns:1fr;}#main{margin-left:var(--sidebar-w-col);}#sidebar{width:var(--sidebar-w-col);}.sb-label{opacity:0;}.sb-logo{opacity:0;pointer-events:none;}body:not(.sb-col) #sb-toggle-btn{left:14px;}.page-wrap{padding:20px;}.danger-item{flex-direction:column;align-items:flex-start;}}
</style>
</head>
<body>

<button id="sb-toggle-btn" onclick="toggleSidebar()">&#9776;</button>

<aside id="sidebar">
  <div class="sb-top"><a class="sb-logo" href="syl_home.php" style="text-decoration:none;">SYL</a></div>
  <nav class="sb-nav">
    <a class="sb-link "      href="syl_home.php"      data-label="home">      <span class="sb-icon">&#127968;</span><span class="sb-label">Home</span></a>
    <a class="sb-link "     href="syl_songs.php"     data-label="songs">     <span class="sb-icon">&#127925;</span><span class="sb-label">Songs</span></a>
    
    <a class="sb-link " href="syl_community.php" data-label="community"><span class="sb-icon">&#127758;</span><span class="sb-label">Community</span></a>
  </nav>
  <div class="sb-bottom">
    <a class="sb-link active"  href="syl_settings.php"  data-label="settings">  <span class="sb-icon">&#9881;&#65039;</span><span class="sb-label">Settings</span></a>
  </div>
</aside>

<div id="main">
  <div class="top-nav">
    <span class="nav-logo">SYL</span>
    <?php if ($isLoggedIn): ?>
      <a class="nav-avatar" href="syl_profile.php" title="@<?= h($userTag) ?>" style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--teal));border:2px solid var(--yellow);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:box-shadow .2s;flex-shrink:0;">&#127911;</a>
      <a class="nbtn out" href="syl_auth.php?action=logout" style="text-decoration:none;">Log Out</a>
    <?php else: ?>
      <a class="nbtn ghost"   href="syl_auth.php?tab=login"    style="text-decoration:none;">Log In</a>
      <a class="nbtn primary" href="syl_auth.php?tab=register" style="text-decoration:none;">Sign Up</a>
    <?php endif; ?>
  </div>

  <div class="page-wrap">
    <?php if($flashError): ?>
    <div style="background:#F0629218;border:1px solid #F0629244;color:var(--pink);font-family:'Space Mono',monospace;font-size:.82rem;padding:12px 18px;border-radius:12px;margin-bottom:20px;"><?= h($flashError) ?></div>
    <?php endif; ?>
    <?php if($flashSuccess): ?>
    <div style="background:#69D17E18;border:1px solid #69D17E44;color:#69D17E;font-family:'Space Mono',monospace;font-size:.82rem;padding:12px 18px;border-radius:12px;margin-bottom:20px;"><?= h($flashSuccess) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <h1 class="page-title">&#9881;&#65039; Settings</h1>
      <p class="page-sub">Manage your profile, change your password, and control your account.</p>
    </div>

    <!-- ─── PROFILE SECTION ───
         In PHP: <form method="post" action="SYL.php">
                   <input name="action" value="update_profile">
                 Saves to: Users.first_name, Users.last_name, Users.bio
                 tag is read-only (it's the primary key — can't change) -->
    <div class="settings-section">
      <div class="settings-section-header">
        <span class="settings-section-icon">&#128100;</span>
        <div>
          <div class="settings-section-title">Profile</div>
          <div class="settings-section-sub">Your public information on SYL</div>
        </div>
      </div>

      <div class="flash" id="profile-flash"></div>

      <!-- Avatar — Users.picture is MEDIUMBLOB; upload/display TBD -->
      <div class="avatar-row">
        <div class="avatar-preview">&#127911;</div>
        <div class="avatar-actions">
          <button class="avatar-btn" onclick="alert('Avatar upload coming in Phase 2')">&#128247; Upload Photo</button>
          <div class="avatar-note">JPEG or PNG &middot; max 2MB &middot; stored in Users.picture</div>
        </div>
      </div>

      <!-- Users.tag — Primary Key, not editable -->
      <form method="post" action="syl_settings.php"><input type="hidden" name="action" value="update_profile">
        <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username (tag)</label>
          <input class="form-input readonly" name="tag" value="@joshm" disabled>
          <span class="form-hint">Your @tag is permanent &mdash; it's your unique identifier in the DB</span>
        </div>
        <div class="form-group">
          <label class="form-label">Email &mdash; Users.email</label>
          <!-- In PHP: value="<?= h($currentUser['email']) ?>" -->
          <input class="form-input" name="email" type="email" value="josh@example.com" id="s-email">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name &mdash; Users.first_name</label>
          <!-- In PHP: value="<?= h($currentUser['first_name']) ?>" -->
          <input class="form-input" name="first_name" type="text" value="<?= h($user['first_name']??'')?>" id="s-fname" required>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name &mdash; Users.last_name</label>
          <!-- In PHP: value="<?= h($currentUser['last_name']) ?>" -->
          <input class="form-input" name="last_name" type="text" value="<?= h($user['last_name']??'')?>" id="s-lname">
        </div>
      </div>

      <div class="form-row single">
        <div class="form-group">
          <label class="form-label">Bio &mdash; Users.bio (max 200 chars)</label>
          <!-- In PHP: <?= h($currentUser['bio'] ?? '') ?> -->
          <textarea class="form-input" name="bio" maxlength="200" id="s-bio"
                    placeholder="Tell people about your music taste&hellip;"><?= h($user['bio']??'')?></textarea>
          <span class="form-hint" id="bio-counter">167 characters remaining</span>
        </div>
      </div>

      <button type="submit" class="form-submit">Save Profile</button>
    </div>


    <!-- ─── PASSWORD SECTION ───
         In PHP: action=update_password
         PHP uses password_verify(current_pw, $user['pw_hash']) then
         password_hash(new_pw, PASSWORD_BCRYPT) and UPDATE Users SET pw_hash=? -->
    <div class="settings-section">
      <div class="settings-section-header">
        <span class="settings-section-icon">&#128274;</span>
        <div>
          <div class="settings-section-title">Password</div>
          <div class="settings-section-sub">Stored as bcrypt hash in Users.pw_hash</div>
        </div>
      </div>

      <div class="flash" id="pw-flash"></div>

      <div class="form-row single">
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <div class="pw-wrap">
            <input class="form-input" id="pw-current" name="current_password" type="password" placeholder="Your current password">
            <button type="button" class="pw-toggle" onclick="togglePw('pw-current',this)">&#128065;</button>
          </div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="pw-wrap">
            <input class="form-input" id="pw-new" name="new_password" type="password" placeholder="Min. 8 characters"
                   oninput="updateStrength(this.value)">
            <button type="button" class="pw-toggle" onclick="togglePw('pw-new',this)">&#128065;</button>
          </div>
          <div class="pw-strength-bar"><div id="pw-fill"></div></div>
          <div class="pw-strength-label" id="pw-strength-label"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div class="pw-wrap">
            <input class="form-input" id="pw-confirm" name="confirm_password" type="password" placeholder="Repeat new password">
            <button type="button" class="pw-toggle" onclick="togglePw('pw-confirm',this)">&#128065;</button>
          </div>
        </div>
      </div>

      <button type="submit" class="form-submit">Update Password</button>
    </div>


    <!-- ─── FAVORITES MANAGEMENT ───
         In PHP: action=update_favorites
         Writes review IDs to UserFavorites (user_tag, review_id, rank 1-4)
         Max 4 entries per user enforced by DB PK (user_tag, rank) -->
    <div class="settings-section">
      <div class="settings-section-header">
        <span class="settings-section-icon">&#11088;</span>
        <div>
          <div class="settings-section-title">Favorite Picks</div>
          <div class="settings-section-sub">Pin up to 4 reviews to your profile &mdash; stored in UserFavorites table</div>
        </div>
      </div>
      <p style="font-size:.85rem;color:var(--muted);line-height:1.6;margin-bottom:16px;">
        Go to your <strong style="color:var(--text)">profile page</strong> and click any song review to set it as a favorite pick.
        Your 4 picks show in the 2&times;2 grid at the top of your profile, ordered by <code style="background:var(--darker);padding:1px 6px;border-radius:4px;font-size:.78rem;color:var(--teal)">UserFavorites.rank</code>.
      </p>
      <!-- Favorite slots placeholder — in PHP populated from getUserFavorites() -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <?php for ($slot=1;$slot<=4;$slot++):
            $fav=null; foreach($favorites as $f){ if((int)$f['rank']===$slot){$fav=$f;break;} }
            $fc=$favColors[$slot-1];
          ?>
          <div style="background:<?= $fav?$fc.'18':'var(--darker)'?>;border:2px solid <?= $fav?$fc.'44':'#ffffff0a'?>;border-radius:12px;padding:14px 16px;min-height:70px;display:flex;flex-direction:column;justify-content:flex-end;">
            <div style="font-family:'Space Mono',monospace;font-size:.6rem;color:var(--muted);letter-spacing:1px;margin-bottom:6px;">PICK <?= $slot ?></div>
            <?php if($fav): ?>
              <div style="font-family:'Dela Gothic One',sans-serif;font-size:.82rem;"><?= h($fav['song_name'])?></div>
              <div style="font-size:.7rem;color:var(--muted);margin-top:2px;"><?= h($fav['artist_name'])?></div>
            <?php else: ?>
              <div style="font-size:.78rem;color:var(--muted);">Empty &mdash; add from profile</div>
            <?php endif; ?>
          </div>
          <?php endfor; ?>
        </div>
    </div>


    <!-- ─── DANGER ZONE ───
         In PHP: action=delete_account → DELETE FROM Users WHERE tag=?
         CASCADE deletes propagate through UserReviews and UserFavorites automatically -->
    <div class="danger-zone">
      <div class="danger-header">
        <span class="danger-icon">&#9888;&#65039;</span>
        <div class="danger-title">Danger Zone</div>
      </div>

      <div class="danger-item">
        <div class="danger-item-info">
          <div class="danger-item-label">Log out of all devices</div>
          <div class="danger-item-sub">Destroys your current PHP session via session_destroy()</div>
        </div>
        <button class="danger-btn" onclick="alert('→ SYL.php?action=logout')">Log Out</button>
      </div>

      <div class="danger-item">
        <div class="danger-item-info">
          <div class="danger-item-label">Delete account</div>
          <div class="danger-item-sub">Permanently deletes your user row. All reviews and favorites cascade-delete automatically per the FK constraints in the schema.</div>
        </div>
        <button class="danger-btn" onclick="openConfirm()">Delete Account</button>
      </div>
    </div>

  </div>
  <footer>SYL &mdash; Save Your Listens &middot; &copy; 2026</footer>
</div>


<!-- Delete confirm dialog -->
<div class="modal-overlay" id="confirmModal" onclick="closeIfOverlay(event)">
  <div class="confirm-modal">
    <div class="cm-icon">&#128680;</div>
    <div class="cm-title">Delete your account?</div>
    <div class="cm-sub">
      This will permanently delete your user row from the <code>Users</code> table.
      All your reviews and favorites will cascade-delete automatically.
      <strong>This cannot be undone.</strong>
    </div>
    <div class="cm-actions">
      <button class="cm-cancel" onclick="closeConfirm()">Cancel</button>
      <form method="post" action="syl_settings.php" id="deleteForm" style="display:none;"><input type="hidden" name="action" value="delete_account"></form>
      <button class="cm-confirm" onclick="this.closest('form').submit()" form="deleteForm">Yes, delete it</button>
    </div>
  </div>
</div>


<script>
/* ─── BIO CHARACTER COUNTER ─── */
const bioEl = document.getElementById('s-bio');
const bioCounter = document.getElementById('bio-counter');
bioEl.addEventListener('input', () => {
  const remaining = 200 - bioEl.value.length;
  bioCounter.textContent = `${remaining} character${remaining !== 1 ? 's' : ''} remaining`;
  bioCounter.style.color = remaining < 20 ? 'var(--pink)' : 'var(--muted)';
});

/* ─── PASSWORD SHOW/HIDE ─── */
function togglePw(id, btn) {
  const el = document.getElementById(id);
  const hidden = el.type === 'password';
  el.type = hidden ? 'text' : 'password';
  btn.style.opacity = hidden ? '1' : '0.5';
}

/* ─── PASSWORD STRENGTH METER ─── */
function updateStrength(pw) {
  let score = 0;
  if (pw.length >= 8)          score++;
  if (/[A-Z]/.test(pw))        score++;
  if (/[0-9]/.test(pw))        score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const levels = [
    {w:'0%',  color:'transparent',text:''},
    {w:'25%', color:'#F06292',    text:'Weak'},
    {w:'50%', color:'#FF8A50',    text:'Fair'},
    {w:'75%', color:'#F5E642',    text:'Good'},
    {w:'100%',color:'#69D17E',    text:'Strong ✓'},
  ];
  const lvl = pw.length === 0 ? 0 : Math.max(1, score);
  document.getElementById('pw-fill').style.width      = levels[lvl].w;
  document.getElementById('pw-fill').style.background = levels[lvl].color;
  document.getElementById('pw-strength-label').textContent = levels[lvl].text;
}

/* ─── SAVE HANDLERS ─── */
/* In PHP these buttons submit forms to SYL.php with the action field.
   Here they just show a success flash for demonstration. */

function saveProfile() {
  const fname = document.getElementById('s-fname').value.trim();
  const lname = document.getElementById('s-lname').value.trim();
  const email = document.getElementById('s-email').value.trim();
  if (!fname) { showFlash('profile-flash', '⚠ First name is required.', 'error'); return; }
  if (!email.includes('@')) { showFlash('profile-flash', '⚠ Enter a valid email address.', 'error'); return; }
  /* In PHP: POST action=update_profile → UPDATE Users SET first_name=?, last_name=?, email=?, bio=? WHERE tag=? */
  showFlash('profile-flash', '✓ Profile saved successfully.', 'success');
}

function savePassword() {
  const current = document.getElementById('pw-current').value;
  const newPw   = document.getElementById('pw-new').value;
  const confirm = document.getElementById('pw-confirm').value;
  if (!current)           { showFlash('pw-flash', '⚠ Enter your current password.', 'error'); return; }
  if (newPw.length < 8)   { showFlash('pw-flash', '⚠ New password must be at least 8 characters.', 'error'); return; }
  if (newPw !== confirm)  { showFlash('pw-flash', '⚠ Passwords do not match.', 'error'); return; }
  /* In PHP: POST action=update_password
     → password_verify(current, $user['pw_hash'])
     → password_hash(newPw, PASSWORD_BCRYPT)
     → UPDATE Users SET pw_hash=? WHERE tag=? */
  showFlash('pw-flash', '✓ Password updated successfully.', 'success');
  ['pw-current','pw-new','pw-confirm'].forEach(id => document.getElementById(id).value = '');
  updateStrength('');
}

function showFlash(id, msg, type) {
  const el = document.getElementById(id);
  el.textContent = msg;
  el.className = `flash ${type}`;
  el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 4000);
}

/* ─── FAVORITES SLOTS ─── */
/* In PHP: getUserFavorites($pdo, $tag) returns up to 4 rows from UserFavorites
   Each slot shows the song name or an empty placeholder */
const FAV_SLOTS = [
  {rank:1, song:'luther',       artist:'Kendrick Lamar'},
  {rank:2, song:'Espresso',     artist:'Sabrina Carpenter'},
  {rank:3, song:null,           artist:null},
  {rank:4, song:null,           artist:null},
];
const SLOT_COLORS = ['#69D17E','#5B9BF5','#9C6FE4','#FF8A50'];

// Favorites rendered server-side by PHP above

/* ─── DELETE CONFIRM ─── */
function openConfirm()  { document.getElementById('confirmModal').classList.add('open'); }
function closeConfirm() { document.getElementById('confirmModal').classList.remove('open'); }
function closeIfOverlay(e) { if (e.target === document.getElementById('confirmModal')) closeConfirm(); }

/* ─── SIDEBAR ─── */
let sbOpen = true;
function toggleSidebar() {
  sbOpen = !sbOpen;
  document.getElementById('sidebar').classList.toggle('col', !sbOpen);
  document.body.classList.toggle('sb-col', !sbOpen);
}
</script>
</body>
</html>
