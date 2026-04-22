<?php
session_start();
require_once 'db.php';

// ─── GET logout ───────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: SYL.php');
    exit;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function formatDuration($seconds) {
    $m = floor($seconds / 60);
    $s = str_pad($seconds % 60, 2, '0', STR_PAD_LEFT);
    return "$m:$s";
}

// ─── DB Functions (from usageexamples.php) ────────────────────────────────────
function createUser($pdo, $tag, $first_name, $last_name, $email, $password, $bio = null, $picture = null) {
    $pw_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO Users (tag, first_name, last_name, email, pw_hash, bio, picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tag, $first_name, $last_name, $email, $pw_hash, $bio, $picture]);
    return $tag;
}

function getUserInfo($pdo, $tag) {
    $stmt = $pdo->prepare("SELECT tag, first_name, last_name, bio, picture FROM Users WHERE tag = ?");
    $stmt->execute([$tag]);
    return $stmt->fetch();
}

function getUserReviews($pdo, $tag) {
    $stmt = $pdo->prepare("SELECT r.id, r.song_name, r.artist_name, r.album_name, r.duration, r.rating, r.review, r.review_date FROM Reviews r JOIN UserReviews ur ON r.id = ur.review_id WHERE ur.user_tag = ? ORDER BY r.review_date DESC");
    $stmt->execute([$tag]);
    return $stmt->fetchAll();
}

function getUserReviewCountThisMonth($pdo, $tag) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM Reviews r JOIN UserReviews ur ON r.id = ur.review_id WHERE ur.user_tag = ? AND MONTH(r.review_date) = MONTH(CURDATE()) AND YEAR(r.review_date) = YEAR(CURDATE())");
    $stmt->execute([$tag]);
    return $stmt->fetch()['total'];
}

function getUserReviewsByRating($pdo, $tag) {
    $stmt = $pdo->prepare("SELECT r.id, r.song_name, r.artist_name, r.album_name, r.duration, r.rating, r.review, r.review_date FROM Reviews r JOIN UserReviews ur ON r.id = ur.review_id WHERE ur.user_tag = ? ORDER BY r.rating DESC");
    $stmt->execute([$tag]);
    return $stmt->fetchAll();
}

function getUserReviewsThisWeek($pdo, $tag) {
    $stmt = $pdo->prepare("SELECT r.id, r.song_name, r.artist_name, r.album_name, r.duration, r.rating, r.review, r.review_date FROM Reviews r JOIN UserReviews ur ON r.id = ur.review_id WHERE ur.user_tag = ? AND r.review_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY r.review_date DESC");
    $stmt->execute([$tag]);
    return $stmt->fetchAll();
}

function getUserReviewCount($pdo, $tag) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM UserReviews WHERE user_tag = ?");
    $stmt->execute([$tag]);
    return $stmt->fetch()['total'];
}

function getUserAverageRating($pdo, $tag) {
    $stmt = $pdo->prepare("SELECT ROUND(AVG(r.rating), 1) AS average FROM Reviews r JOIN UserReviews ur ON r.id = ur.review_id WHERE ur.user_tag = ?");
    $stmt->execute([$tag]);
    return $stmt->fetch()['average'];
}

function getUserFavorites($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.song_name, r.artist_name, uf.rank
        FROM UserFavorites uf
        JOIN Reviews r ON r.id = uf.review_id
        WHERE uf.user_tag = ?
        ORDER BY uf.rank ASC
    ");
    $stmt->execute([$tag]);
    return $stmt->fetchAll();
}

function createReview($pdo, $tag, $song, $artist, $album, $duration, $rating, $review) {
    $stmt = $pdo->prepare("INSERT INTO Reviews (song_name, artist_name, album_name, duration, rating, review) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$song, $artist, $album, $duration, $rating, $review]);
    $reviewId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO UserReviews (user_tag, review_id) VALUES (?, ?)");
    $stmt->execute([$tag, $reviewId]);
    return $reviewId;
}

// ─── Flash messages ───────────────────────────────────────────────────────────
$loginError = $_SESSION['flash_login_error'] ?? '';
$regError   = $_SESSION['flash_reg_error']   ?? '';
unset($_SESSION['flash_login_error'], $_SESSION['flash_reg_error']);

// ─── POST Handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT tag, pw_hash FROM Users WHERE tag = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['pw_hash'])) {
            $_SESSION['user_tag'] = $row['tag'];
            header('Location: SYL.php?page=profile');
            exit;
        }
        $_SESSION['flash_login_error'] = '⚠ Invalid username or password.';
        header('Location: SYL.php?page=auth&tab=login');
        exit;
    }

    if ($action === 'register') {
        $tag     = trim($_POST['reg_username'] ?? '');
        $email   = trim($_POST['reg_email']    ?? '');
        $pw      = $_POST['reg_password'] ?? '';
        $confirm = $_POST['reg_confirm']  ?? '';

        if (!$tag || !$email || !$pw) {
            $_SESSION['flash_reg_error'] = '⚠ All fields are required.';
        } elseif (strlen($pw) < 8) {
            $_SESSION['flash_reg_error'] = '⚠ Password must be at least 8 characters.';
        } elseif ($pw !== $confirm) {
            $_SESSION['flash_reg_error'] = '⚠ Passwords do not match.';
        } else {
            $check = $pdo->prepare("SELECT tag FROM Users WHERE tag = ? OR email = ?");
            $check->execute([$tag, $email]);
            if ($check->fetch()) {
                $_SESSION['flash_reg_error'] = '⚠ Username or email already taken.';
            } else {
                createUser($pdo, $tag, $tag, '', $email, $pw);
                $_SESSION['user_tag'] = $tag;
                header('Location: SYL.php?page=profile');
                exit;
            }
        }
        header('Location: SYL.php?page=auth&tab=register');
        exit;
    }

    if ($action === 'add_song' && isset($_SESSION['user_tag'])) {
        $tag     = $_SESSION['user_tag'];
        $song    = trim($_POST['song_name'] ?? '');
        $artist  = trim($_POST['artist']    ?? '');
        $album   = trim($_POST['album']     ?? '') ?: null;
        $durStr  = trim($_POST['duration']  ?? '0:00');
        $rating  = max(1, min(10, intval($_POST['rating'] ?? 1)));
        $notes   = trim($_POST['notes']     ?? '') ?: null;

        $parts   = explode(':', $durStr);
        $durSecs = count($parts) === 2 ? intval($parts[0]) * 60 + intval($parts[1]) : intval($parts[0]);

        if ($song && $artist) {
            createReview($pdo, $tag, $song, $artist, $album, $durSecs, $rating, $notes);
        }
        header('Location: SYL.php?page=profile');
        exit;
    }
}

// ─── Load page data ───────────────────────────────────────────────────────────
$isLoggedIn    = isset($_SESSION['user_tag']);
$currentUser   = null;
$userReviews   = [];
$userFavorites = [];
$totalSongs    = 0;
$avgRating     = '—';
$thisMonth     = 0;
$activeFilter  = $_GET['filter'] ?? 'all';

if ($isLoggedIn) {
    $tag = $_SESSION['user_tag'];
    $currentUser   = getUserInfo($pdo, $tag);
    $totalSongs    = getUserReviewCount($pdo, $tag);
    $avgRating     = getUserAverageRating($pdo, $tag) ?? '—';
    $thisMonth     = getUserReviewCountThisMonth($pdo, $tag);
    $userFavorites = getUserFavorites($pdo, $tag);

    if ($activeFilter === 'rating') {
        $userReviews = getUserReviewsByRating($pdo, $tag);
    } elseif ($activeFilter === 'week') {
        $userReviews = getUserReviewsThisWeek($pdo, $tag);
    } else {
        $userReviews = getUserReviews($pdo, $tag);
    }
}

// Community feed — most recent reviews across all users
$communityReviews = [];
try {
    $stmt = $pdo->query("SELECT r.song_name, r.artist_name, r.duration, r.rating, r.review FROM Reviews r ORDER BY r.review_date DESC LIMIT 5");
    $communityReviews = $stmt->fetchAll();
} catch (Exception $e) {}

// Initial page for JS routing
$initialPage = 'home';
$initialTab  = 'login';
if (isset($_GET['page']) && in_array($_GET['page'], ['home', 'profile', 'auth'])) {
    $initialPage = $_GET['page'];
}
if (isset($_GET['tab']) && $_GET['tab'] === 'register') {
    $initialTab = 'register';
}
if ($initialPage === 'profile' && !$isLoggedIn) {
    $initialPage = 'auth';
}

$rowColors = ['sr-yellow', 'sr-sand', 'sr-pink', 'sr-teal', 'sr-lavender'];
$favColors = ['ft-1', 'ft-2', 'ft-3', 'ft-4'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL — Save Your Listens</title>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root {
    --yellow:  #F5E642;
    --pink:    #F06292;
    --teal:    #4DD0C4;
    --lavender:#B39DDB;
    --green:   #69D17E;
    --orange:  #FF8A50;
    --blue:    #5B9BF5;
    --purple:  #9C6FE4;
    --sand:    #E8E0D0;
    --dark:    #1A1A2E;
    --darker:  #0F0F1E;
    --card:    #22223B;
    --text:    #F0EDE6;
    --muted:   #8B8BAA;
  }

  * { margin:0; padding:0; box-sizing:border-box; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--darker);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
  }

  nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 48px;
    background: var(--dark);
    border-bottom: 2px solid #ffffff10;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .nav-logo {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 1.6rem;
    letter-spacing: -1px;
    background: linear-gradient(90deg, var(--yellow), var(--pink), var(--teal));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  .nav-links { display: flex; gap: 12px; align-items: center; }
  .nav-btn {
    padding: 9px 22px;
    border-radius: 100px;
    border: none;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
  }
  .nav-btn.ghost {
    background: transparent;
    color: var(--text);
    border: 2px solid #ffffff25;
  }
  .nav-btn.ghost:hover { border-color: var(--yellow); color: var(--yellow); }
  .nav-btn.primary { background: var(--yellow); color: var(--dark); }
  .nav-btn.primary:hover { background: #ffe100; transform: translateY(-1px); }

  .page { display: none; }
  .page.active { display: block; }

  .hero {
    padding: 80px 48px 60px;
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
  }
  .hero-eyebrow {
    font-family: 'Space Mono', monospace;
    font-size: 0.75rem;
    letter-spacing: 3px;
    color: var(--teal);
    text-transform: uppercase;
    margin-bottom: 16px;
  }
  .hero-title {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 3.4rem;
    line-height: 1.05;
    margin-bottom: 20px;
  }
  .hero-title span { color: var(--yellow); }
  .hero-sub {
    font-size: 1.05rem;
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 32px;
  }
  .hero-cta { display: flex; gap: 14px; flex-wrap: wrap; }
  .cta-btn {
    padding: 14px 30px;
    border-radius: 100px;
    border: none;
    font-family: 'DM Sans', sans-serif;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
  }
  .cta-btn.big { background: var(--yellow); color: var(--dark); }
  .cta-btn.big:hover { transform: translateY(-2px); box-shadow: 0 10px 30px #F5E64240; }
  .cta-btn.outline { background: transparent; color: var(--text); border: 2px solid #ffffff30; }
  .cta-btn.outline:hover { border-color: var(--teal); color: var(--teal); }

  .hero-preview {
    background: var(--card);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid #ffffff10;
    box-shadow: 0 30px 80px #00000060;
  }
  .preview-header {
    padding: 14px 20px;
    background: #2D2D4E;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .preview-dots { display: flex; gap: 6px; }
  .preview-dots span { width: 10px; height: 10px; border-radius: 50%; }
  .dot-r { background: var(--pink); }
  .dot-y { background: var(--yellow); }
  .dot-g { background: var(--green); }
  .preview-title-bar {
    font-family: 'Space Mono', monospace;
    font-size: 0.7rem;
    color: var(--muted);
    margin-left: 6px;
  }
  .preview-body { padding: 16px; }
  .preview-user-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
  }
  .preview-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, var(--green), var(--teal));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
  }
  .preview-username { font-weight: 700; font-size: 0.95rem; }
  .preview-handle { font-size: 0.75rem; color: var(--muted); }
  .preview-song {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 7px;
    font-size: 0.82rem;
    font-weight: 600;
  }
  .preview-song .snum {
    font-family: 'Space Mono', monospace;
    font-size: 0.65rem;
    color: var(--dark);
    opacity: 0.6;
    min-width: 16px;
  }
  .preview-song .sname { flex: 1; }
  .preview-song .sartist { opacity: 0.65; font-weight: 400; }
  .preview-song .srating { font-family: 'Space Mono', monospace; font-size: 0.7rem; }
  .ps-1 { background: var(--yellow); color: var(--dark); }
  .ps-2 { background: var(--pink);   color: #fff; }
  .ps-3 { background: var(--teal);   color: var(--dark); }
  .ps-4 { background: var(--lavender); color: var(--dark); }
  .ps-5 { background: var(--sand);   color: var(--dark); }

  .features {
    max-width: 1100px;
    margin: 0 auto 80px;
    padding: 0 48px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }
  .feat-card {
    background: var(--card);
    border-radius: 16px;
    padding: 28px;
    border: 1px solid #ffffff08;
    transition: transform 0.2s;
  }
  .feat-card:hover { transform: translateY(-4px); }
  .feat-icon { font-size: 2rem; margin-bottom: 14px; display: block; }
  .feat-title {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 1rem;
    margin-bottom: 8px;
  }
  .feat-desc { font-size: 0.88rem; color: var(--muted); line-height: 1.6; }

  .section-block {
    max-width: 1100px;
    margin: 0 auto 80px;
    padding: 0 48px;
  }
  .section-label {
    font-family: 'Space Mono', monospace;
    font-size: 0.7rem;
    letter-spacing: 3px;
    color: var(--muted);
    text-transform: uppercase;
    margin-bottom: 8px;
  }
  .section-heading {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 1.8rem;
    margin-bottom: 24px;
  }

  .song-row {
    display: grid;
    grid-template-columns: 32px 1fr 140px 90px 80px 1fr;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    transition: filter 0.15s;
    cursor: pointer;
  }
  .song-row:hover { filter: brightness(1.08); }
  .song-row .row-num {
    font-family: 'Space Mono', monospace;
    font-size: 0.7rem;
    opacity: 0.55;
    text-align: center;
  }
  .song-row .row-name { font-weight: 700; }
  .song-row .row-artist { opacity: 0.7; font-weight: 400; }
  .song-row .row-dur { font-family: 'Space Mono', monospace; font-size: 0.78rem; opacity: 0.7; }
  .song-row .row-rating { font-family: 'Space Mono', monospace; font-size: 0.78rem; }
  .song-row .row-note {
    font-size: 0.8rem;
    font-weight: 400;
    opacity: 0.75;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .sr-yellow  { background: var(--yellow);   color: var(--dark); }
  .sr-sand    { background: var(--sand);     color: var(--dark); }
  .sr-pink    { background: var(--pink);     color: #fff; }
  .sr-teal    { background: var(--teal);     color: var(--dark); }
  .sr-lavender{ background: var(--lavender); color: var(--dark); }

  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: #00000080;
    backdrop-filter: blur(6px);
    z-index: 200;
    align-items: center;
    justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--card);
    border-radius: 24px;
    padding: 48px 40px;
    width: 420px;
    max-width: 95vw;
    border: 1px solid #ffffff15;
    box-shadow: 0 40px 100px #000000a0;
    position: relative;
  }
  .modal-close {
    position: absolute;
    top: 18px; right: 20px;
    background: none; border: none;
    color: var(--muted); font-size: 1.4rem;
    cursor: pointer; line-height: 1;
  }
  .modal-close:hover { color: var(--text); }
  .modal-logo {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 1.8rem;
    background: linear-gradient(90deg, var(--yellow), var(--pink));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 6px;
  }
  .modal-sub { font-size: 0.88rem; color: var(--muted); margin-bottom: 32px; }

  .tab-row {
    display: flex;
    background: var(--darker);
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 28px;
  }
  .tab {
    flex: 1; padding: 9px;
    border: none; background: none;
    border-radius: 9px;
    font-family: 'DM Sans', sans-serif;
    font-weight: 600; font-size: 0.88rem;
    color: var(--muted); cursor: pointer;
    transition: all 0.2s;
  }
  .tab.active { background: var(--yellow); color: var(--dark); }

  .form-group { margin-bottom: 18px; }
  .form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--muted);
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 7px;
  }
  .form-input {
    width: 100%;
    padding: 13px 16px;
    background: var(--darker);
    border: 2px solid #ffffff10;
    border-radius: 12px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.95rem;
    outline: none;
    transition: border-color 0.2s;
  }
  .form-input:focus { border-color: var(--yellow); }
  .form-submit {
    width: 100%;
    padding: 14px;
    background: var(--yellow);
    color: var(--dark);
    border: none;
    border-radius: 12px;
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 8px;
    transition: all 0.2s;
  }
  .form-submit:hover { background: #ffe000; transform: translateY(-1px); }

  .profile-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 40px 48px;
  }
  .profile-top {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 32px;
    align-items: start;
    margin-bottom: 40px;
  }
  .profile-avatar-block { position: relative; }
  .profile-avatar {
    width: 120px; height: 120px;
    border-radius: 20px;
    background: linear-gradient(135deg, var(--green) 0%, var(--teal) 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    border: 3px solid var(--yellow);
    box-shadow: 0 0 0 6px #F5E64220;
  }
  .profile-name {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 2rem;
    margin-bottom: 4px;
  }
  .profile-handle {
    font-family: 'Space Mono', monospace;
    font-size: 0.8rem;
    color: var(--teal);
    margin-bottom: 14px;
  }
  .profile-bio {
    font-size: 0.9rem;
    color: var(--muted);
    line-height: 1.6;
    max-width: 420px;
    background: var(--card);
    padding: 14px 18px;
    border-radius: 12px;
    border-left: 3px solid var(--yellow);
  }
  .profile-stats { display: flex; gap: 24px; margin-top: 16px; }
  .stat { text-align: center; }
  .stat-num {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 1.4rem;
    color: var(--yellow);
  }
  .stat-label { font-size: 0.72rem; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; }

  .fav-label {
    font-family: 'Space Mono', monospace;
    font-size: 0.65rem;
    letter-spacing: 3px;
    color: var(--muted);
    text-transform: uppercase;
    text-align: right;
    margin-bottom: 10px;
  }
  .fav-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    width: 220px;
  }
  .fav-tile {
    border-radius: 12px;
    padding: 14px 12px;
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 0.78rem;
    line-height: 1.3;
    cursor: pointer;
    transition: transform 0.18s;
    min-height: 70px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
  }
  .fav-tile:hover { transform: scale(1.04); }
  .fav-tile .ft-name { font-size: 0.76rem; }
  .fav-tile .ft-artist { font-size: 0.62rem; opacity: 0.7; font-family: 'DM Sans', sans-serif; font-weight: 400; }
  .ft-1 { background: var(--green);  color: var(--dark); }
  .ft-2 { background: var(--blue);   color: #fff; }
  .ft-3 { background: var(--purple); color: #fff; }
  .ft-4 { background: var(--orange); color: var(--dark); }

  .add-song-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    background: var(--yellow);
    color: var(--dark);
    border: none;
    border-radius: 12px;
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 16px;
  }
  .add-song-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px #F5E64240; }

  .profile-divider {
    background: var(--card);
    border-radius: 4px;
    height: 2px;
    margin-bottom: 32px;
    position: relative;
    overflow: hidden;
  }
  .profile-divider::after {
    content: '';
    position: absolute;
    left: 0; top: 0; height: 100%;
    width: 40%;
    background: linear-gradient(90deg, var(--yellow), var(--pink), var(--teal));
    border-radius: 4px;
  }

  .recent-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }
  .recent-heading {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 1.4rem;
  }
  .filter-pills { display: flex; gap: 8px; }
  .filter-pills a { text-decoration: none; }
  .pill {
    padding: 6px 16px;
    border-radius: 100px;
    border: 2px solid #ffffff15;
    background: none;
    color: var(--muted);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
  }
  .pill.active, .pill:hover { border-color: var(--yellow); color: var(--yellow); }

  .song-table-head {
    display: grid;
    grid-template-columns: 32px 1fr 140px 90px 80px 1fr;
    gap: 16px;
    padding: 0 20px 10px;
    font-family: 'Space Mono', monospace;
    font-size: 0.65rem;
    letter-spacing: 2px;
    color: var(--muted);
    text-transform: uppercase;
  }

  .add-modal {
    background: var(--card);
    border-radius: 24px;
    padding: 40px;
    width: 460px;
    max-width: 95vw;
    border: 1px solid #ffffff15;
    position: relative;
  }
  .add-modal-title {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 1.4rem;
    margin-bottom: 24px;
  }
  .color-picker-row { display: flex; gap: 8px; margin-top: 6px; }
  .color-swatch {
    width: 28px; height: 28px;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: transform 0.15s;
  }
  .color-swatch:hover, .color-swatch.selected { transform: scale(1.2); border-color: #fff; }
  .star-row { display: flex; gap: 6px; margin-top: 6px; }
  .star-btn {
    font-size: 1.4rem; background: none; border: none;
    cursor: pointer; color: #ffffff25;
    transition: color 0.15s;
  }
  .star-btn.lit { color: var(--yellow); }

  footer {
    text-align: center;
    padding: 40px;
    color: var(--muted);
    font-size: 0.8rem;
    font-family: 'Space Mono', monospace;
    border-top: 1px solid #ffffff08;
  }

  .auth-wrap {
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: calc(100vh - 69px);
  }
  .auth-left {
    background: var(--dark);
    padding: 60px 52px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border-right: 1px solid #ffffff08;
    position: relative;
    overflow: hidden;
  }
  .auth-left::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 320px; height: 320px;
    border-radius: 50%;
    background: radial-gradient(circle, #F5E64215, transparent 70%);
  }
  .auth-brand {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 2.6rem;
    background: linear-gradient(90deg, var(--yellow), var(--pink), var(--teal));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 32px;
  }
  .auth-tagline {
    font-family: 'Dela Gothic One', sans-serif;
    font-size: 2.4rem;
    line-height: 1.15;
    margin-bottom: 20px;
  }
  .auth-left-sub {
    font-size: 0.95rem;
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 40px;
    max-width: 380px;
  }
  .auth-song-strip {
    padding: 11px 16px;
    border-radius: 10px;
    font-family: 'Space Mono', monospace;
    font-size: 0.72rem;
    font-weight: 700;
    margin-bottom: 8px;
    letter-spacing: 0.5px;
    animation: slideIn 0.5s ease both;
  }
  .auth-song-strip:nth-child(5) { animation-delay: 0.1s; }
  .auth-song-strip:nth-child(6) { animation-delay: 0.2s; }
  .auth-song-strip:nth-child(7) { animation-delay: 0.3s; }
  .auth-song-strip:nth-child(8) { animation-delay: 0.4s; }
  @keyframes slideIn {
    from { opacity: 0; transform: translateX(-16px); }
    to   { opacity: 1; transform: translateX(0); }
  }
  .as-yellow  { background: var(--yellow);   color: var(--dark); }
  .as-pink    { background: var(--pink);     color: #fff; }
  .as-teal    { background: var(--teal);     color: var(--dark); }
  .as-lavender{ background: var(--lavender); color: var(--dark); }

  .auth-right {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 40px;
    background: var(--darker);
  }
  .auth-card { width: 100%; max-width: 420px; }
  .auth-welcome { font-family: 'Dela Gothic One', sans-serif; font-size: 1.7rem; margin-bottom: 6px; }
  .auth-welcome-sub { font-size: 0.9rem; color: var(--muted); margin-bottom: 28px; }
  .pw-wrap { position: relative; }
  .pw-toggle {
    position: absolute;
    right: 14px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    cursor: pointer; font-size: 1rem;
    opacity: 0.5;
    transition: opacity 0.15s;
  }
  .pw-toggle:hover { opacity: 1; }
  .pw-strength-bar {
    height: 4px;
    background: #ffffff10;
    border-radius: 4px;
    margin-top: 8px;
    overflow: hidden;
  }
  #pw-strength-fill { height: 100%; width: 0%; border-radius: 4px; transition: width 0.3s, background 0.3s; }
  .pw-strength-label { font-size: 0.72rem; margin-top: 4px; color: var(--muted); font-family: 'Space Mono', monospace; }
  .auth-error {
    color: var(--pink);
    font-size: 0.82rem;
    font-family: 'Space Mono', monospace;
    margin-bottom: 12px;
    min-height: 18px;
  }
  .auth-switch { text-align: center; margin-top: 20px; font-size: 0.85rem; color: var(--muted); }
  .auth-switch span { color: var(--yellow); cursor: pointer; font-weight: 700; text-decoration: underline; }
  .auth-switch span:hover { color: #ffe000; }

  .empty-state { color: var(--muted); padding: 20px 0; font-size: 0.9rem; }

  @media (max-width: 768px) {
    .auth-wrap { grid-template-columns: 1fr; }
    .auth-left { display: none; }
    .auth-right { padding: 32px 20px; align-items: flex-start; padding-top: 40px; }
    .hero { grid-template-columns: 1fr; padding: 40px 20px; gap: 40px; }
    .hero-title { font-size: 2.2rem; }
    .features { grid-template-columns: 1fr; padding: 0 20px; }
    .section-block { padding: 0 20px; }
    .profile-wrap { padding: 24px 20px; }
    .profile-top { grid-template-columns: 1fr; }
    .fav-grid { width: 100%; }
    .song-row { grid-template-columns: 28px 1fr 80px 60px; }
    .song-row .row-note, .song-row .row-rating { display: none; }
    .song-table-head { display: none; }
  }
</style>
</head>
<body>

<!-- ── NAV ── -->
<nav>
  <span class="nav-logo">SYL</span>
  <?php if ($isLoggedIn): ?>
  <div class="nav-links">
    <button class="nav-btn ghost" onclick="showPage('home')">Home</button>
    <button class="nav-btn ghost" onclick="showPage('profile')">My Page</button>
    <button class="nav-btn primary" onclick="window.location.href='SYL.php?action=logout'">Log Out</button>
  </div>
  <?php else: ?>
  <div class="nav-links">
    <button class="nav-btn ghost" onclick="showPage('home')">Home</button>
    <button class="nav-btn ghost" onclick="goToAuth('login')">Log In</button>
    <button class="nav-btn primary" onclick="goToAuth('register')">Sign Up</button>
  </div>
  <?php endif; ?>
</nav>

<!-- ══════════════════════════════════
     HOME PAGE
══════════════════════════════════ -->
<div id="page-home" class="page">

  <!-- Hero -->
  <section class="hero">
    <div>
      <p class="hero-eyebrow">🎵 Save Your Listens</p>
      <h1 class="hero-title">Your music,<br><span>your voice.</span></h1>
      <p class="hero-sub">Log what you listen to, rate it, leave your thoughts, and share your musical taste with the world — or just keep it for yourself.</p>
      <div class="hero-cta">
        <button class="cta-btn big" onclick="goToAuth('register')">Get Started — It's Free</button>
        <button class="cta-btn outline" onclick="goToAuth('login')">Log In</button>
      </div>
    </div>

    <!-- Decorative preview card -->
    <div class="hero-preview">
      <div class="preview-header">
        <div class="preview-dots">
          <span class="dot-r"></span><span class="dot-y"></span><span class="dot-g"></span>
        </div>
        <span class="preview-title-bar">yourname.syl.app</span>
      </div>
      <div class="preview-body">
        <div class="preview-user-row">
          <div class="preview-avatar">🎧</div>
          <div>
            <div class="preview-username">your listens</div>
            <div class="preview-handle">recent listens · tracked</div>
          </div>
        </div>
        <div class="preview-song ps-1"><span class="snum">01</span><span class="sname">Blinding Lights</span><span class="sartist">The Weeknd</span><span class="srating">★ 5</span></div>
        <div class="preview-song ps-2"><span class="snum">02</span><span class="sname">As It Was</span><span class="sartist">Harry Styles</span><span class="srating">★ 4</span></div>
        <div class="preview-song ps-3"><span class="snum">03</span><span class="sname">Levitating</span><span class="sartist">Dua Lipa</span><span class="srating">★ 4</span></div>
        <div class="preview-song ps-4"><span class="snum">04</span><span class="sname">Peaches</span><span class="sartist">Justin Bieber</span><span class="srating">★ 3</span></div>
        <div class="preview-song ps-5"><span class="snum">05</span><span class="sname">Stay</span><span class="sartist">The Kid LAROI</span><span class="srating">★ 4</span></div>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section class="features">
    <div class="feat-card">
      <span class="feat-icon">🎵</span>
      <div class="feat-title">Log Your Listens</div>
      <p class="feat-desc">Add any song you've heard. Write notes, give it a rating, and track your musical journey over time.</p>
    </div>
    <div class="feat-card">
      <span class="feat-icon">⭐</span>
      <div class="feat-title">Favorite Picks</div>
      <p class="feat-desc">Pin up to 4 songs as your top picks. They sit front-and-center on your profile for everyone to see.</p>
    </div>
    <div class="feat-card">
      <span class="feat-icon">🔗</span>
      <div class="feat-title">Embeddable Widget</div>
      <p class="feat-desc">Drop your top 5 list into any blog, Instagram bio, or personal site with a simple embed snippet.</p>
    </div>
  </section>

  <!-- Community recent listens — pulled from DB -->
  <section class="section-block">
    <p class="section-label">Community</p>
    <h2 class="section-heading">What people are listening to</h2>

    <div class="song-table-head">
      <span>#</span><span>Song</span><span>Artist</span><span>Duration</span><span>Rating</span><span>Note</span>
    </div>

    <?php if (empty($communityReviews)): ?>
    <p class="empty-state">No songs logged yet — be the first!</p>
    <?php else: ?>
    <?php foreach ($communityReviews as $i => $r): ?>
    <div class="song-row <?= $rowColors[$i % count($rowColors)] ?>">
      <span class="row-num"><?= $i + 1 ?></span>
      <span class="row-name"><?= h($r['song_name']) ?></span>
      <span class="row-artist"><?= h($r['artist_name']) ?></span>
      <span class="row-dur"><?= formatDuration($r['duration']) ?></span>
      <span class="row-rating">★ <?= h($r['rating']) ?></span>
      <span class="row-note"><?= h($r['review'] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <footer>SYL — Save Your Listens · Built for music lovers · © 2026</footer>
</div>


<!-- ══════════════════════════════════
     PROFILE PAGE
══════════════════════════════════ -->
<div id="page-profile" class="page">
  <?php if ($isLoggedIn && $currentUser): ?>
  <div class="profile-wrap">

    <div class="profile-top">

      <!-- Avatar -->
      <div class="profile-avatar-block">
        <div class="profile-avatar">🎧</div>
      </div>

      <!-- Info -->
      <div>
        <div class="profile-name"><?= h($currentUser['first_name'] . ($currentUser['last_name'] ? ' ' . $currentUser['last_name'] : '')) ?></div>
        <div class="profile-handle">@<?= h($currentUser['tag']) ?> · <?= h($currentUser['tag']) ?>.syl.app</div>
        <div class="profile-bio"><?= h($currentUser['bio'] ?? 'No bio set yet.') ?></div>
        <div class="profile-stats">
          <div class="stat">
            <div class="stat-num"><?= h($totalSongs) ?></div>
            <div class="stat-label">Songs Logged</div>
          </div>
          <div class="stat">
            <div class="stat-num"><?= h($avgRating) ?></div>
            <div class="stat-label">Avg Rating</div>
          </div>
          <div class="stat">
            <div class="stat-num"><?= h($thisMonth) ?></div>
            <div class="stat-label">This Month</div>
          </div>
        </div>
        <button class="add-song-btn" onclick="openModal('addModal')">
          <span>+</span> Add New Song
        </button>
      </div>

      <!-- Favorite Picks -->
      <div class="fav-section">
        <div class="fav-label">⭐ Favorite Picks</div>
        <div class="fav-grid">
          <?php if (empty($userFavorites)): ?>
          <div class="fav-tile ft-1" style="opacity:0.4;grid-column:span 2">
            <span class="ft-name">No favorites set</span>
          </div>
          <?php else: ?>
          <?php foreach ($userFavorites as $fi => $fav): ?>
          <div class="fav-tile <?= $favColors[$fi % 4] ?>">
            <span class="ft-name"><?= h($fav['song_name']) ?></span>
            <span class="ft-artist"><?= h($fav['artist_name']) ?></span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <div class="profile-divider"></div>

    <!-- Recent listens -->
    <div class="recent-header">
      <h2 class="recent-heading">Recent Listens</h2>
      <div class="filter-pills">
        <a href="SYL.php?page=profile&filter=all"><button class="pill <?= $activeFilter === 'all' ? 'active' : '' ?>">All</button></a>
        <a href="SYL.php?page=profile&filter=rating"><button class="pill <?= $activeFilter === 'rating' ? 'active' : '' ?>">Top Rated</button></a>
        <a href="SYL.php?page=profile&filter=week"><button class="pill <?= $activeFilter === 'week' ? 'active' : '' ?>">This Week</button></a>
      </div>
    </div>

    <div class="song-table-head">
      <span>#</span><span>Song</span><span>Artist</span><span>Duration</span><span>Rating</span><span>Note</span>
    </div>

    <?php if (empty($userReviews)): ?>
    <p class="empty-state">No songs logged yet. Hit "+ Add New Song" to get started!</p>
    <?php else: ?>
    <?php foreach ($userReviews as $i => $r): ?>
    <div class="song-row <?= $rowColors[$i % count($rowColors)] ?>">
      <span class="row-num"><?= $i + 1 ?></span>
      <span class="row-name"><?= h($r['song_name']) ?></span>
      <span class="row-artist"><?= h($r['artist_name']) ?></span>
      <span class="row-dur"><?= formatDuration($r['duration']) ?></span>
      <span class="row-rating">★ <?= h($r['rating']) ?></span>
      <span class="row-note"><?= h($r['review'] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

  </div>
  <?php endif; ?>
  <footer>SYL — Save Your Listens · Built for music lovers · © 2026</footer>
</div>


<!-- ══════════════════════════════════
     AUTH PAGE
══════════════════════════════════ -->
<div id="page-auth" class="page">
  <div class="auth-wrap">

    <!-- Left branding panel -->
    <div class="auth-left">
      <div class="auth-brand" onclick="showPage('home')" style="cursor:pointer;">SYL</div>
      <h2 class="auth-tagline">Your music.<br>Your voice.<br>Your list.</h2>
      <p class="auth-left-sub">Log what you listen to, rate it, leave your thoughts, and share your taste with the world.</p>
      <div class="auth-song-strip as-yellow">Espresso · Sabrina Carpenter · ★ 5</div>
      <div class="auth-song-strip as-pink">luther · Kendrick ft. SZA · ★ 5</div>
      <div class="auth-song-strip as-teal">APT. · Rose &amp; Bruno Mars · ★ 5</div>
      <div class="auth-song-strip as-lavender">Blinding Lights · The Weeknd · ★ 5</div>
    </div>

    <!-- Right form panel -->
    <div class="auth-right">
      <div class="auth-card">

        <div class="tab-row">
          <button class="tab" id="auth-tab-login" onclick="switchAuthTab('login')">Log In</button>
          <button class="tab" id="auth-tab-register" onclick="switchAuthTab('register')">Sign Up</button>
        </div>

        <!-- ── LOGIN FORM ── -->
        <form id="auth-login-panel" method="post" action="SYL.php">
          <input type="hidden" name="action" value="login">
          <div class="auth-welcome">Welcome back 👋</div>
          <div class="auth-welcome-sub">Sign in to your SYL account</div>

          <div class="form-group">
            <label class="form-label">Username or Email</label>
            <input class="form-input" id="login-identifier" name="identifier" type="text" placeholder="yourname or yourname@email.com">
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="pw-wrap">
              <input class="form-input" id="login-password" name="password" type="password" placeholder="••••••••">
              <button type="button" class="pw-toggle" onclick="togglePw('login-password', this)">👁</button>
            </div>
          </div>
          <div class="auth-error" id="login-error"></div>
          <button type="button" class="form-submit" onclick="handleLogin()">Log In →</button>
          <div class="auth-switch">Don't have an account? <span onclick="switchAuthTab('register')">Sign Up</span></div>
        </form>

        <!-- ── REGISTER FORM ── -->
        <form id="auth-register-panel" method="post" action="SYL.php" style="display:none;">
          <input type="hidden" name="action" value="register">
          <div class="auth-welcome">Create your account ✨</div>
          <div class="auth-welcome-sub">It's free — start logging your listens</div>

          <div class="form-group">
            <label class="form-label">Username</label>
            <input class="form-input" id="reg-username" name="reg_username" type="text" placeholder="yourname">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" id="reg-email" name="reg_email" type="email" placeholder="yourname@email.com">
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="pw-wrap">
              <input class="form-input" id="reg-password" name="reg_password" type="password" placeholder="Min. 8 characters">
              <button type="button" class="pw-toggle" onclick="togglePw('reg-password', this)">👁</button>
            </div>
            <div class="pw-strength-bar"><div id="pw-strength-fill"></div></div>
            <div class="pw-strength-label" id="pw-strength-label"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div class="pw-wrap">
              <input class="form-input" id="reg-confirm" name="reg_confirm" type="password" placeholder="••••••••">
              <button type="button" class="pw-toggle" onclick="togglePw('reg-confirm', this)">👁</button>
            </div>
          </div>
          <div class="auth-error" id="reg-error"></div>
          <button type="button" class="form-submit" onclick="handleRegister()">Create Account →</button>
          <div class="auth-switch">Already have an account? <span onclick="switchAuthTab('login')">Log In</span></div>
        </form>

      </div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════
     ADD SONG MODAL
══════════════════════════════════ -->
<div class="modal-overlay" id="addModal" onclick="closeIfBg(event,'addModal')">
  <form class="add-modal" method="post" action="SYL.php">
    <input type="hidden" name="action" value="add_song">
    <input type="hidden" name="rating" id="rating-value" value="1">
    <button type="button" class="modal-close" onclick="closeModal('addModal')">✕</button>
    <div class="add-modal-title">+ Add a Song</div>

    <div class="form-group">
      <label class="form-label">Song Name</label>
      <input class="form-input" name="song_name" type="text" placeholder="e.g. Blinding Lights" required>
    </div>
    <div class="form-group">
      <label class="form-label">Artist</label>
      <input class="form-input" name="artist" type="text" placeholder="e.g. The Weeknd" required>
    </div>
    <div class="form-group">
      <label class="form-label">Album</label>
      <input class="form-input" name="album" type="text" placeholder="e.g. After Hours">
    </div>
    <div class="form-group">
      <label class="form-label">Duration (min:sec)</label>
      <input class="form-input" name="duration" type="text" placeholder="3:22">
    </div>
    <div class="form-group">
      <label class="form-label">Rating (1–10)</label>
      <div class="star-row" id="starRow">
        <button type="button" class="star-btn" onclick="setRating(1)">★</button>
        <button type="button" class="star-btn" onclick="setRating(2)">★</button>
        <button type="button" class="star-btn" onclick="setRating(3)">★</button>
        <button type="button" class="star-btn" onclick="setRating(4)">★</button>
        <button type="button" class="star-btn" onclick="setRating(5)">★</button>
        <button type="button" class="star-btn" onclick="setRating(6)">★</button>
        <button type="button" class="star-btn" onclick="setRating(7)">★</button>
        <button type="button" class="star-btn" onclick="setRating(8)">★</button>
        <button type="button" class="star-btn" onclick="setRating(9)">★</button>
        <button type="button" class="star-btn" onclick="setRating(10)">★</button>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Your Notes</label>
      <input class="form-input" name="notes" type="text" placeholder="What did you think?">
    </div>
    <button type="submit" class="form-submit">Save Song →</button>
  </form>
</div>


<script>
  // PHP-injected auth state and initial routing
  const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

  // ── Page routing ──────────────────────────────────────────────────────────
  function showPage(id) {
    if (id === 'profile' && !isLoggedIn) { goToAuth('login'); return; }
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('page-' + id).classList.add('active');
    window.scrollTo(0, 0);
  }

  function goToAuth(tab) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('page-auth').classList.add('active');
    switchAuthTab(tab);
    window.scrollTo(0, 0);
  }

  function switchAuthTab(tab) {
    const isLogin = tab === 'login';
    document.getElementById('auth-login-panel').style.display    = isLogin ? 'block' : 'none';
    document.getElementById('auth-register-panel').style.display = isLogin ? 'none'  : 'block';
    document.getElementById('auth-tab-login').classList.toggle('active', isLogin);
    document.getElementById('auth-tab-register').classList.toggle('active', !isLogin);
    document.getElementById('login-error').textContent = '';
    document.getElementById('reg-error').textContent   = '';
  }

  // ── Auth handlers — validate then submit form to PHP ─────────────────────
  function handleLogin() {
    const id  = document.getElementById('login-identifier').value.trim();
    const pw  = document.getElementById('login-password').value;
    const err = document.getElementById('login-error');
    if (!id) { err.textContent = '⚠ Please enter your username or email.'; return; }
    if (!pw) { err.textContent = '⚠ Please enter your password.'; return; }
    err.textContent = '';
    document.getElementById('auth-login-panel').submit();
  }

  function handleRegister() {
    const username = document.getElementById('reg-username').value.trim();
    const email    = document.getElementById('reg-email').value.trim();
    const pw       = document.getElementById('reg-password').value;
    const confirm  = document.getElementById('reg-confirm').value;
    const err      = document.getElementById('reg-error');
    if (!username)                          { err.textContent = '⚠ Username is required.'; return; }
    if (!email || !email.includes('@'))     { err.textContent = '⚠ Enter a valid email address.'; return; }
    if (pw.length < 8)                      { err.textContent = '⚠ Password must be at least 8 characters.'; return; }
    if (pw !== confirm)                     { err.textContent = '⚠ Passwords do not match.'; return; }
    err.textContent = '';
    document.getElementById('auth-register-panel').submit();
  }

  // ── Password visibility toggle ────────────────────────────────────────────
  function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.style.opacity = isHidden ? '1' : '0.5';
  }

  // ── Password strength meter ───────────────────────────────────────────────
  document.getElementById('reg-password').addEventListener('input', function() {
    const pw = this.value;
    const fill  = document.getElementById('pw-strength-fill');
    const label = document.getElementById('pw-strength-label');
    let score = 0;
    if (pw.length >= 8)           score++;
    if (/[A-Z]/.test(pw))         score++;
    if (/[0-9]/.test(pw))         score++;
    if (/[^A-Za-z0-9]/.test(pw))  score++;
    const levels = [
      { w: '0%',   color: 'transparent', text: '' },
      { w: '25%',  color: '#F06292',     text: 'Weak' },
      { w: '50%',  color: '#FF8A50',     text: 'Fair' },
      { w: '75%',  color: '#F5E642',     text: 'Good' },
      { w: '100%', color: '#69D17E',     text: 'Strong ✓' },
    ];
    const lvl = pw.length === 0 ? 0 : Math.max(1, score);
    fill.style.width      = levels[lvl].w;
    fill.style.background = levels[lvl].color;
    label.textContent     = levels[lvl].text;
  });

  // ── Modal ─────────────────────────────────────────────────────────────────
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  function closeIfBg(e, id) {
    if (e.target === document.getElementById(id)) closeModal(id);
  }

  // ── Star rating — updates hidden input for form POST ─────────────────────
  function setRating(n) {
    const stars = document.querySelectorAll('#starRow .star-btn');
    stars.forEach((s, i) => s.classList.toggle('lit', i < n));
    document.getElementById('rating-value').value = n;
  }

  // ── Initial page display (set by PHP) ────────────────────────────────────
  showPage(<?= json_encode($initialPage) ?>);
  switchAuthTab(<?= json_encode($initialTab) ?>);

  // Display any server-side errors from flash messages
  <?php if ($loginError): ?>
  document.getElementById('login-error').textContent = <?= json_encode($loginError) ?>;
  <?php endif; ?>
  <?php if ($regError): ?>
  document.getElementById('reg-error').textContent = <?= json_encode($regError) ?>;
  <?php endif; ?>
</script>
</body>
</html>
