<?php
// ============================================================
//  syl_auth.php — SYL Login & Register
//  Handles both login and register as one page with tab switching.
//  No sidebar — full-page auth layout.
//
//  POST action=login   → identifier (tag or email) + password
//  POST action=register → reg_username, reg_email, reg_password, reg_confirm
//
//  On success → redirects to syl_home.php with session set.
//  On failure → sets flash error and redirects back to correct tab.
// ============================================================
session_start();
require_once 'db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ─── Logout ───────────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: syl_home.php');
    exit;
}

// ─── Logout ───────────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: syl_home.php');
    exit;
}

// Already logged in — go home
if (isset($_SESSION['user_tag']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: syl_home.php');
    exit;
}

// ─── Login ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'login') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT tag, pw_hash FROM Users WHERE tag=? OR email=?");
    $stmt->execute([$identifier, $identifier]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['pw_hash'])) {
        $_SESSION['user_tag'] = $row['tag'];
        header('Location: syl_home.php');
        exit;
    }
    $_SESSION['flash_error'] = '⚠ Invalid username or password.';
    header('Location: syl_auth.php?tab=login');
    exit;
}

// ─── Register ─────────────────────────────────────────────────────────────────
// Creates a new row in Users. tag is the PK — must be unique.
// Password is hashed with password_hash($pw, PASSWORD_BCRYPT) before storage.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'register') {
    $tag     = trim($_POST['reg_username'] ?? '');
    $email   = trim($_POST['reg_email']    ?? '');
    $pw      = $_POST['reg_password'] ?? '';
    $confirm = $_POST['reg_confirm']  ?? '';

    if (!$tag || !$email || !$pw) {
        $_SESSION['flash_error'] = '⚠ All fields are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{2,32}$/', $tag)) {
        $_SESSION['flash_error'] = '⚠ Username must be 2–32 characters (letters, numbers, underscores only).';
    } elseif (strlen($pw) < 8) {
        $_SESSION['flash_error'] = '⚠ Password must be at least 8 characters.';
    } elseif ($pw !== $confirm) {
        $_SESSION['flash_error'] = '⚠ Passwords do not match.';
    } else {
        $check = $pdo->prepare("SELECT tag FROM Users WHERE tag=? OR email=?");
        $check->execute([$tag, $email]);
        if ($check->fetch()) {
            $_SESSION['flash_error'] = '⚠ Username or email already taken.';
        } else {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            // first_name defaults to tag since register only collects username
            $pdo->prepare("INSERT INTO Users (tag,first_name,last_name,email,pw_hash) VALUES (?,?,?,?,?)")
                ->execute([$tag, $tag, '', $email, $hash]);
            $_SESSION['user_tag'] = $tag;
            header('Location: syl_home.php');
            exit;
        }
    }
    header('Location: syl_auth.php?tab=register');
    exit;
}

// ─── Display ──────────────────────────────────────────────────────────────────
$activeTab  = ($_GET['tab'] ?? 'login') === 'register' ? 'register' : 'login';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL &mdash; <?= $activeTab === 'register' ? 'Sign Up' : 'Log In' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--yellow:#F5E642;--pink:#F06292;--teal:#4DD0C4;--lavender:#B39DDB;--green:#69D17E;--orange:#FF8A50;--dark:#1A1A2E;--darker:#0F0F1E;--card:#22223B;--text:#F0EDE6;--muted:#8B8BAA;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--darker);color:var(--text);min-height:100vh;}

/* ─── Auth split layout ─── */
.auth-wrap{display:grid;grid-template-columns:1fr 1fr;min-height:100vh;}

/* Left branding panel */
.auth-left{background:var(--dark);padding:60px 52px;display:flex;flex-direction:column;justify-content:center;border-right:1px solid #ffffff08;position:relative;overflow:hidden;}
.auth-left::before{content:'';position:absolute;top:-80px;right:-80px;width:320px;height:320px;border-radius:50%;background:radial-gradient(circle,#F5E64215,transparent 70%);}
.auth-brand{font-family:'Dela Gothic One',sans-serif;font-size:2.6rem;background:linear-gradient(90deg,var(--yellow),var(--pink),var(--teal));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:32px;text-decoration:none;display:inline-block;}
.auth-tagline{font-family:'Dela Gothic One',sans-serif;font-size:2.4rem;line-height:1.15;margin-bottom:20px;}
.auth-left-sub{font-size:.95rem;color:var(--muted);line-height:1.7;margin-bottom:40px;max-width:380px;}
.auth-song-strip{padding:11px 16px;border-radius:10px;font-family:'Space Mono',monospace;font-size:.72rem;font-weight:700;margin-bottom:8px;animation:slideIn .5s ease both;}
.auth-song-strip:nth-child(5){animation-delay:.1s;}.auth-song-strip:nth-child(6){animation-delay:.2s;}.auth-song-strip:nth-child(7){animation-delay:.3s;}.auth-song-strip:nth-child(8){animation-delay:.4s;}
@keyframes slideIn{from{opacity:0;transform:translateX(-16px);}to{opacity:1;transform:translateX(0);}}
.as-yellow{background:var(--yellow);color:var(--dark);}.as-pink{background:var(--pink);color:#fff;}
.as-teal{background:var(--teal);color:var(--dark);}.as-lavender{background:var(--lavender);color:var(--dark);}

/* Right form panel */
.auth-right{display:flex;align-items:center;justify-content:center;padding:48px 40px;background:var(--darker);}
.auth-card{width:100%;max-width:420px;}
.auth-welcome{font-family:'Dela Gothic One',sans-serif;font-size:1.7rem;margin-bottom:6px;}
.auth-welcome-sub{font-size:.9rem;color:var(--muted);margin-bottom:28px;}

/* Tab switcher — real <a> links, no JS needed */
.tab-row{display:flex;background:var(--dark);border-radius:12px;padding:4px;margin-bottom:28px;}
.tab{flex:1;padding:9px;border-radius:9px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:.88rem;color:var(--muted);transition:all .2s;text-decoration:none;text-align:center;display:block;}
.tab.active{background:var(--yellow);color:var(--dark);}

/* Form elements */
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:7px;}
.form-input{width:100%;padding:13px 16px;background:var(--darker);border:2px solid #ffffff10;border-radius:12px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;transition:border-color .2s;}
.form-input:focus{border-color:var(--yellow);}
.form-input::placeholder{color:var(--muted);}
.form-submit{width:100%;padding:14px;background:var(--yellow);color:var(--dark);border:none;border-radius:12px;font-family:'Dela Gothic One',sans-serif;font-size:1rem;cursor:pointer;margin-top:8px;transition:all .2s;}
.form-submit:hover{background:#ffe000;transform:translateY(-1px);}
.pw-wrap{position:relative;}
.pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;opacity:.5;transition:opacity .15s;}
.pw-toggle:hover{opacity:1;}
.pw-strength-bar{height:4px;background:#ffffff10;border-radius:4px;margin-top:8px;overflow:hidden;}
#pw-strength-fill{height:100%;width:0%;border-radius:4px;transition:width .3s,background .3s;}
.pw-strength-label{font-size:.72rem;margin-top:4px;color:var(--muted);font-family:'Space Mono',monospace;}

/* Flash error */
.auth-error{color:var(--pink);font-size:.82rem;font-family:'Space Mono',monospace;padding:10px 14px;background:#F0629215;border:1px solid #F0629244;border-radius:10px;margin-bottom:16px;}

/* Auth switch link */
.auth-switch{text-align:center;margin-top:20px;font-size:.85rem;color:var(--muted);}
.auth-switch a{color:var(--yellow);font-weight:700;text-decoration:underline;}
.auth-switch a:hover{color:#ffe000;}

@media(max-width:768px){.auth-wrap{grid-template-columns:1fr;}.auth-left{display:none;}}
</style>
</head>
<body>

<div class="auth-wrap">

  <!-- Left branding panel — decorative, hidden on mobile -->
  <div class="auth-left">
    <a class="auth-brand" href="syl_home.php">SYL</a>
    <h2 class="auth-tagline">Your music.<br>Your voice.<br>Your list.</h2>
    <p class="auth-left-sub">Log what you listen to, rate it out of 10, leave your thoughts, and share your taste with the world.</p>
    <div class="auth-song-strip as-yellow">Espresso &middot; Sabrina Carpenter &middot; &#9733; 9</div>
    <div class="auth-song-strip as-pink">luther &middot; Kendrick ft. SZA &middot; &#9733; 10</div>
    <div class="auth-song-strip as-teal">APT. &middot; Rose &amp; Bruno Mars &middot; &#9733; 9</div>
    <div class="auth-song-strip as-lavender">Blinding Lights &middot; The Weeknd &middot; &#9733; 10</div>
  </div>

  <!-- Right form panel -->
  <div class="auth-right">
    <div class="auth-card">

      <!-- Tab switcher — real links so PHP knows which tab to render -->
      <div class="tab-row">
        <a class="tab <?= $activeTab === 'login'    ? 'active' : '' ?>" href="syl_auth.php?tab=login">Log In</a>
        <a class="tab <?= $activeTab === 'register' ? 'active' : '' ?>" href="syl_auth.php?tab=register">Sign Up</a>
      </div>

      <!-- Flash error from server (shown after failed POST + redirect) -->
      <?php if ($flashError): ?>
        <div class="auth-error"><?= h($flashError) ?></div>
      <?php endif; ?>

      <?php if ($activeTab === 'login'): ?>
      <!-- ── LOGIN FORM ──
           POSTs action=login to syl_auth.php.
           PHP looks up user by tag OR email, then runs password_verify()
           against the bcrypt hash stored in Users.pw_hash. -->
      <form method="post" action="syl_auth.php">
        <input type="hidden" name="action" value="login">
        <div class="auth-welcome">Welcome back &#128075;</div>
        <div class="auth-welcome-sub">Sign in to your SYL account</div>

        <div class="form-group">
          <label class="form-label">Username or Email</label>
          <input class="form-input" name="identifier" type="text"
                 placeholder="yourname or you@email.com"
                 value="<?= h($_POST['identifier'] ?? '') ?>" autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="pw-wrap">
            <input class="form-input" id="login-pw" name="password"
                   type="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
            <button type="button" class="pw-toggle" onclick="togglePw('login-pw',this)">&#128065;</button>
          </div>
        </div>

        <button type="submit" class="form-submit">Log In &rarr;</button>
        <div class="auth-switch">Don&apos;t have an account? <a href="syl_auth.php?tab=register">Sign Up</a></div>
      </form>

      <?php else: ?>
      <!-- ── REGISTER FORM ──
           POSTs action=register to syl_auth.php.
           PHP validates, then calls password_hash($pw, PASSWORD_BCRYPT).
           Inserts into Users table: tag (PK), first_name=tag, last_name='',
           email, pw_hash. -->
      <form method="post" action="syl_auth.php">
        <input type="hidden" name="action" value="register">
        <div class="auth-welcome">Create your account &#10024;</div>
        <div class="auth-welcome-sub">It&apos;s free &mdash; start logging your listens</div>

        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-input" name="reg_username" type="text"
                 placeholder="yourname (letters, numbers, _)"
                 value="<?= h($_POST['reg_username'] ?? '') ?>" autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-input" name="reg_email" type="email"
                 placeholder="you@email.com"
                 value="<?= h($_POST['reg_email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="pw-wrap">
            <input class="form-input" id="reg-pw" name="reg_password"
                   type="password" placeholder="Min. 8 characters">
            <button type="button" class="pw-toggle" onclick="togglePw('reg-pw',this)">&#128065;</button>
          </div>
          <!-- Visual strength meter — actual enforcement happens in PHP above -->
          <div class="pw-strength-bar"><div id="pw-strength-fill"></div></div>
          <div class="pw-strength-label" id="pw-strength-label"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="pw-wrap">
            <input class="form-input" id="reg-confirm" name="reg_confirm"
                   type="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
            <button type="button" class="pw-toggle" onclick="togglePw('reg-confirm',this)">&#128065;</button>
          </div>
        </div>

        <button type="submit" class="form-submit">Create Account &rarr;</button>
        <div class="auth-switch">Already have an account? <a href="syl_auth.php?tab=login">Log In</a></div>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
// Password show/hide toggle
function togglePw(id, btn) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
  btn.style.opacity = el.type === 'text' ? '1' : '0.5';
}

// Password strength meter — visual only, PHP enforces minimum on the server
const regPw = document.getElementById('reg-pw');
if (regPw) {
  regPw.addEventListener('input', function() {
    const pw = this.value;
    const fill  = document.getElementById('pw-strength-fill');
    const label = document.getElementById('pw-strength-label');
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
      {w:'100%',color:'#69D17E',    text:'Strong \u2713'},
    ];
    const lvl = pw.length === 0 ? 0 : Math.max(1, score);
    if (fill)  { fill.style.width = levels[lvl].w; fill.style.background = levels[lvl].color; }
    if (label) label.textContent = levels[lvl].text;
  });
}
</script>
</body>
</html>
