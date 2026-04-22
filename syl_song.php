<?php
// ============================================================
//  syl_song.php — SYL Song Detail Page
//  Shows a single song's info, average rating, all user reviews,
//  and allows logged-in users to reply to reviews.
//
//  URL: syl_song.php?id=REVIEW_ID
//
//  Note: In our schema, each Review row IS a specific user's take
//  on a song. Songs with the same name/artist may have multiple
//  Review rows from different users — this page groups them.
//
// ============================================================
session_start();
require_once 'db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatDuration($s) {
    if (!$s) return '&mdash;';
    return floor($s/60).':'.str_pad($s%60,2,'0',STR_PAD_LEFT);
}

$isLoggedIn = isset($_SESSION['user_tag']);
$userTag    = $isLoggedIn ? $_SESSION['user_tag'] : '';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy(); header('Location: syl_home.php'); exit;
}

// ─── Add review POST handler ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review' && $isLoggedIn) {
    $reviewId2 = (int)($_GET['id'] ?? 0);
    $rating    = max(1, min(10, (int)($_POST['rating'] ?? 1)));
    $reviewTxt = trim($_POST['review'] ?? '') ?: null;

    // Load song info first
    $sinfo = $pdo->prepare("SELECT r.song_name, r.artist_name, r.album_name, r.duration FROM Reviews r WHERE r.id=?");
    $sinfo->execute([$reviewId2]);
    $sinfo = $sinfo->fetch();

    if ($sinfo) {
        // Check duplicate
        $dup = $pdo->prepare("SELECT r.id FROM Reviews r JOIN UserReviews ur ON r.id=ur.review_id WHERE ur.user_tag=? AND r.song_name=? AND r.artist_name=? LIMIT 1");
        $dup->execute([$userTag, $sinfo['song_name'], $sinfo['artist_name']]);
        $existing = $dup->fetch();
        if ($existing) {
            $_SESSION['flash_notice'] = '\u26a0 You already reviewed this song. Edit it on your profile.';
            header("Location: syl_song.php?id=$reviewId2"); exit;
        }
        $stmt = $pdo->prepare("INSERT INTO Reviews (song_name,artist_name,album_name,duration,rating,review) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$sinfo['song_name'], $sinfo['artist_name'], $sinfo['album_name'], $sinfo['duration'], $rating, $reviewTxt]);
        $newId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO UserReviews (user_tag,review_id) VALUES (?,?)")->execute([$userTag, $newId]);
        header("Location: syl_song.php?id=$newId"); exit;
    }
    header("Location: syl_songs.php"); exit;
}

$flashNotice = $_SESSION['flash_notice'] ?? '';
unset($_SESSION['flash_notice']);

// ─── Get the review ID from URL ───────────────────────────────────────────────
$reviewId = (int)($_GET['id'] ?? 0);
if (!$reviewId) { header('Location: syl_songs.php'); exit; }

// ─── Load the specific review ─────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT r.*, u.tag AS reviewer_tag, u.first_name
    FROM Reviews r JOIN UserReviews ur ON r.id=ur.review_id JOIN Users u ON ur.user_tag=u.tag
    WHERE r.id=?");
$stmt->execute([$reviewId]);
$review = $stmt->fetch();

if (!$review) { header('Location: syl_songs.php'); exit; }

// ─── Load ALL reviews for the same song (same name + artist) ─────────────────
// This shows everyone's take on the same song
$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.review, r.review_date, r.duration,
           u.tag AS reviewer_tag, u.first_name
    FROM Reviews r
    JOIN UserReviews ur ON r.id = ur.review_id
    JOIN Users u ON ur.user_tag = u.tag
    WHERE r.song_name = ? AND r.artist_name = ?
    ORDER BY r.review_date DESC
");
$stmt->execute([$review['song_name'], $review['artist_name']]);
$allReviews = $stmt->fetchAll();

// ─── Average rating across all reviews of this song ──────────────────────────
$stmt = $pdo->prepare("
    SELECT ROUND(AVG(r.rating),1) AS avg, COUNT(*) AS cnt
    FROM Reviews r
    JOIN UserReviews ur ON r.id = ur.review_id
    WHERE r.song_name = ? AND r.artist_name = ?
");
$stmt->execute([$review['song_name'], $review['artist_name']]);
$ratingInfo = $stmt->fetch();
$avgRating    = $ratingInfo['avg'];
$reviewCount  = $ratingInfo['cnt'];


$avatarEmojis = ['&#127911;','&#127928;','&#128133;','&#127908;','&#127864;','&#127769;','&#128367;','&#127754;'];
$avatarColors = ['#69D17E','#F06292','#B39DDB','#F5E642','#4DD0C4','#5B9BF5','#FF8A50','#9C6FE4'];

function reviewerEmoji($tag, $emojis) {
    return $emojis[abs(crc32($tag)) % count($emojis)];
}
function reviewerColor($tag, $colors) {
    return $colors[abs(crc32($tag)) % count($colors)];
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL &mdash; <?= h($review['song_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--yellow:#F5E642;--pink:#F06292;--teal:#4DD0C4;--lavender:#B39DDB;--green:#69D17E;--orange:#FF8A50;--blue:#5B9BF5;--purple:#9C6FE4;--sand:#E8E0D0;--dark:#1A1A2E;--darker:#0F0F1E;--card:#22223B;--text:#F0EDE6;--muted:#8B8BAA;--sidebar-w:220px;--sidebar-w-col:64px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--darker);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

/* Sidebar (same as all pages) */
#sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--dark);border-right:1px solid #ffffff0a;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:200;transition:width .3s cubic-bezier(.4,0,.2,1);overflow:hidden;}
#sidebar.col{width:var(--sidebar-w-col);}
.sb-top{display:flex;align-items:center;padding:20px 16px 16px;border-bottom:1px solid #ffffff08;min-height:64px;flex-shrink:0;}
.sb-logo{font-family:'Dela Gothic One',sans-serif;font-size:1.4rem;background:linear-gradient(90deg,var(--yellow),var(--pink));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;white-space:nowrap;opacity:1;transition:opacity .2s;text-decoration:none;}
#sidebar.col .sb-logo{opacity:0;pointer-events:none;}
#sb-toggle-btn{position:fixed;top:14px;z-index:300;background:var(--dark);border:1px solid #ffffff12;color:var(--muted);cursor:pointer;font-size:1.05rem;width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;transition:color .2s,background .2s,left .3s cubic-bezier(.4,0,.2,1),box-shadow .2s;}
#sb-toggle-btn:hover{color:var(--yellow);background:var(--card);box-shadow:0 0 0 3px #F5E64220;}
body:not(.sb-col) #sb-toggle-btn{left:calc(var(--sidebar-w) - 46px);}
body.sb-col #sb-toggle-btn{left:14px;}
.sb-nav{flex:1;padding:16px 8px;display:flex;flex-direction:column;gap:4px;overflow:hidden;}
.sb-link{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;text-decoration:none;background:none;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:500;white-space:nowrap;transition:background .18s,color .18s;}
.sb-link:hover{background:#ffffff08;color:var(--text);}
.sb-icon{font-size:1.15rem;flex-shrink:0;width:24px;text-align:center;}
.sb-label{opacity:1;transition:opacity .2s;overflow:hidden;}
#sidebar.col .sb-label{opacity:0;}
.sb-bottom{padding:8px 8px 20px;border-top:1px solid #ffffff08;}
#sidebar.col .sb-link::after{content:attr(data-label);position:absolute;left:calc(var(--sidebar-w-col) + 8px);top:50%;transform:translateY(-50%);background:var(--card);color:var(--text);font-size:.78rem;padding:5px 10px;border-radius:8px;white-space:nowrap;pointer-events:none;opacity:0;border:1px solid #ffffff15;transition:opacity .15s;z-index:300;}
#sidebar.col .sb-link:hover::after{opacity:1;}
#main{margin-left:var(--sidebar-w);flex:1;transition:margin-left .3s cubic-bezier(.4,0,.2,1);min-width:0;}
body.sb-col #main{margin-left:var(--sidebar-w-col);}

/* Nav */
.top-nav{display:flex;align-items:center;padding:14px 40px;background:var(--dark);border-bottom:1px solid #ffffff0a;position:sticky;top:0;z-index:100;gap:10px;}
.nav-logo{font-family:'Dela Gothic One',sans-serif;font-size:1.3rem;background:linear-gradient(90deg,var(--yellow),var(--pink),var(--teal));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-right:auto;padding-left:50px;text-decoration:none;}
.nbtn{padding:8px 20px;border-radius:100px;border:none;font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-block;}
.nbtn.ghost{background:transparent;color:var(--text);border:2px solid #ffffff20;}
.nbtn.ghost:hover{border-color:var(--yellow);color:var(--yellow);}
.nbtn.primary{background:var(--yellow);color:var(--dark);}
.nbtn.out{background:transparent;color:var(--muted);border:2px solid #ffffff15;}
.nbtn.out:hover{border-color:var(--pink);color:var(--pink);}
.nav-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--teal));border:2px solid var(--yellow);display:flex;align-items:center;justify-content:center;font-size:1rem;cursor:pointer;text-decoration:none;transition:box-shadow .2s;flex-shrink:0;}
.nav-avatar:hover{box-shadow:0 0 0 3px #F5E64230;}
.nav-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover;}

/* ─── SONG DETAIL PAGE ─── */
.song-wrap{padding:40px;max-width:900px;margin:0 auto;}

/* Back button */
.back-btn{display:inline-flex;align-items:center;gap:8px;color:var(--muted);font-size:.85rem;text-decoration:none;margin-bottom:32px;transition:color .2s;}
.back-btn:hover{color:var(--yellow);}

/* Song hero */
.song-hero{display:flex;gap:32px;align-items:flex-start;margin-bottom:48px;}
.song-art{width:160px;height:160px;border-radius:16px;background:linear-gradient(135deg,var(--card),#2a2040);display:flex;align-items:center;justify-content:center;font-size:4rem;border:1px solid #ffffff10;box-shadow:0 16px 48px #00000070;flex-shrink:0;}
.song-info{}
.song-album-label{font-family:'Space Mono',monospace;font-size:.7rem;letter-spacing:2px;color:var(--teal);text-transform:uppercase;margin-bottom:8px;}
.song-title{font-family:'Dela Gothic One',sans-serif;font-size:2.2rem;line-height:1.1;margin-bottom:6px;}
.song-artist{font-size:1rem;color:var(--muted);margin-bottom:20px;}
.song-stats{display:flex;gap:16px;flex-wrap:wrap;}
.song-stat{background:var(--card);border-radius:12px;padding:12px 18px;text-align:center;}
.song-stat-num{font-family:'Dela Gothic One',sans-serif;font-size:1.6rem;color:var(--yellow);}
.song-stat-num.no-rating{font-size:1rem;color:var(--muted);}
.song-stat-lbl{font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-family:'Space Mono',monospace;}

/* Star display */
.star-display{display:flex;gap:3px;margin-top:6px;}
.star-display span{font-size:1.1rem;color:var(--yellow);}
.star-display span.empty{color:#ffffff15;}

/* Reviews section */
.reviews-heading{font-family:'Dela Gothic One',sans-serif;font-size:1.3rem;margin-bottom:20px;}

/* Review card */
.review-card{background:var(--card);border-radius:16px;padding:20px 22px;margin-bottom:16px;border:1px solid #ffffff08;transition:border-color .2s;}
.review-card:hover{border-color:#ffffff15;}
.rc-header{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
.rc-avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.rc-user{flex:1;}
.rc-name{font-family:'Space Mono',monospace;font-size:.78rem;font-weight:700;}
.rc-time{font-size:.7rem;color:var(--muted);font-family:'Space Mono',monospace;}
.rc-rating{font-family:'Dela Gothic One',sans-serif;font-size:1.2rem;color:var(--yellow);}
.rc-review{font-size:.9rem;color:var(--text);line-height:1.6;font-style:italic;margin-bottom:14px;}
.rc-dur{font-family:'Space Mono',monospace;font-size:.7rem;color:var(--muted);margin-bottom:12px;}

/* No reviews state */
.no-reviews{text-align:center;padding:60px 20px;color:var(--muted);}
.no-reviews-icon{font-size:3rem;margin-bottom:14px;}
.no-reviews-title{font-family:'Dela Gothic One',sans-serif;font-size:1.3rem;color:var(--text);margin-bottom:8px;}

footer{text-align:center;padding:32px 40px;color:var(--muted);font-size:.75rem;font-family:'Space Mono',monospace;border-top:1px solid #ffffff08;}

@media(max-width:768px){.song-hero{flex-direction:column;}.song-art{width:120px;height:120px;font-size:3rem;}}
@media(max-width:600px){#main{margin-left:var(--sidebar-w-col)!important;}#sidebar{width:var(--sidebar-w-col)!important;}.sb-label{opacity:0!important;}.song-wrap{padding:20px;}}
</style>
</head>
<body>

<button id="sb-toggle-btn" onclick="toggleSidebar()">&#9776;</button>

<aside id="sidebar">
  <div class="sb-top"><a class="sb-logo" href="syl_home.php">SYL</a></div>
  <nav class="sb-nav">
    <a class="sb-link" href="syl_home.php"      data-label="home">      <span class="sb-icon">&#127968;</span><span class="sb-label">Home</span></a>
    <a class="sb-link" href="syl_songs.php"     data-label="songs">     <span class="sb-icon">&#127925;</span><span class="sb-label">Songs</span></a>
    <a class="sb-link" href="syl_friends.php"   data-label="friends">   <span class="sb-icon">&#128101;</span><span class="sb-label">Friends</span></a>
    
    <a class="sb-link" href="syl_community.php" data-label="community"><span class="sb-icon">&#127758;</span><span class="sb-label">Community</span></a>
  </nav>
  <div class="sb-bottom">
    <a class="sb-link" href="syl_settings.php" data-label="settings"><span class="sb-icon">&#9881;&#65039;</span><span class="sb-label">Settings</span></a>
  </div>
</aside>

<div id="main">

  <div class="top-nav">
    <a class="nav-logo" href="syl_home.php">SYL</a>
    <?php if ($isLoggedIn): ?>
      <a class="nav-avatar" href="syl_profile.php" title="@<?= h($userTag) ?>">&#127911;</a>
      <a class="nbtn out" href="syl_auth.php?action=logout" style="text-decoration:none;">Log Out</a>
    <?php else: ?>
      <a class="nbtn ghost"   href="syl_auth.php?tab=login"    style="text-decoration:none;">Log In</a>
      <a class="nbtn primary" href="syl_auth.php?tab=register" style="text-decoration:none;">Sign Up</a>
    <?php endif; ?>
  </div>

  <?php if (!empty($flashNotice)): ?>
  <div style="background:var(--card);border-left:4px solid var(--teal);color:var(--text);font-size:.85rem;padding:12px 40px;"><?= h($flashNotice) ?></div>
  <?php endif; ?>
  <div class="song-wrap">

    <a class="back-btn" href="javascript:history.back()">&#8592; Back</a>

    <!-- Song hero -->
    <div class="song-hero">
      <div class="song-art">&#127925;</div>
      <div class="song-info">
        <?php if ($review['album_name']): ?>
        <div class="song-album-label">&#127894; <?= h($review['album_name']) ?></div>
        <?php endif; ?>
        <div class="song-title"><?= h($review['song_name']) ?></div>
        <div class="song-artist"><?= h($review['artist_name']) ?></div>
        <div class="song-stats">
          <div class="song-stat">
            <?php if ($avgRating): ?>
              <div class="song-stat-num">&#9733;&thinsp;<?= h($avgRating) ?></div>
              <!-- Star bar representing avg out of 10 -->
              <div class="star-display" style="margin-top:4px;">
                <?php
                $filled = round($avgRating / 2); // convert 10-scale to 5 stars
                for ($s = 1; $s <= 5; $s++): ?>
                  <span><?= $s <= $filled ? '&#9733;' : '&#9734;' ?></span>
                <?php endfor; ?>
              </div>
            <?php else: ?>
              <div class="song-stat-num no-rating">No rating yet</div>
            <?php endif; ?>
            <div class="song-stat-lbl">Avg Rating / 10</div>
          </div>
          <div class="song-stat">
            <div class="song-stat-num"><?= $reviewCount ?></div>
            <div class="song-stat-lbl"><?= $reviewCount === 1 ? 'Review' : 'Reviews' ?></div>
          </div>
          <?php if ($review['duration']): ?>
          <div class="song-stat">
            <div class="song-stat-num" style="font-size:1.1rem;"><?= formatDuration($review['duration']) ?></div>
            <div class="song-stat-lbl">Duration</div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Add review section -->
    <?php
    $userAlreadyReviewed = false;
    if ($isLoggedIn) {
        foreach ($allReviews as $rv) {
            if ($rv['reviewer_tag'] === $userTag) { $userAlreadyReviewed = true; break; }
        }
    }
    ?>
    <?php if ($isLoggedIn && !$userAlreadyReviewed): ?>
    <div style="background:var(--card);border-radius:16px;padding:20px 22px;margin-bottom:24px;border:1px solid #ffffff08;">
      <div style="font-family:'Dela Gothic One',sans-serif;font-size:1rem;margin-bottom:14px;color:var(--yellow);">&#9998; Write Your Review</div>
      <form method="post" action="syl_song.php?id=<?= $reviewId ?>">
        <input type="hidden" name="action" value="add_review">
        <input type="hidden" name="rating" id="new-rating-val" value="1">
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
          <div style="flex:1;min-width:180px;">
            <label style="font-family:'Space Mono',monospace;font-size:.62rem;color:var(--muted);letter-spacing:1px;text-transform:uppercase;display:block;margin-bottom:5px;">Your Review (optional)</label>
            <input type="text" name="review" placeholder="What did you think?"
              style="width:100%;padding:10px 14px;background:var(--darker);border:2px solid #ffffff10;border-radius:10px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.88rem;outline:none;"
              onfocus="this.style.borderColor='var(--yellow)'" onblur="this.style.borderColor='#ffffff10'">
          </div>
          <div>
            <label style="font-family:'Space Mono',monospace;font-size:.62rem;color:var(--muted);letter-spacing:1px;text-transform:uppercase;display:block;margin-bottom:5px;">Rating (1–10)</label>
            <div id="new-star-row" style="display:flex;gap:3px;margin-bottom:4px;"></div>
            <div id="new-rating-hint" style="font-family:'Space Mono',monospace;font-size:.62rem;color:var(--muted);">Click a star</div>
          </div>
          <button type="button" onclick="submitNewReview()"
            style="padding:10px 20px;background:var(--yellow);color:var(--dark);border:none;border-radius:10px;font-family:'Dela Gothic One',sans-serif;font-size:.85rem;cursor:pointer;white-space:nowrap;margin-bottom:0;">
            Post Review
          </button>
        </div>
      </form>
    </div>
    <?php elseif ($isLoggedIn && $userAlreadyReviewed): ?>
    <div style="background:var(--card);border-radius:12px;padding:12px 18px;margin-bottom:20px;border:1px solid #F5E64233;font-size:.85rem;color:var(--muted);">
      &#9733; You reviewed this song &mdash; <a href="syl_profile.php" style="color:var(--yellow);font-weight:700;text-decoration:none;">edit it on your profile</a>
    </div>
    <?php elseif (!$isLoggedIn): ?>
    <div style="background:var(--card);border-radius:12px;padding:12px 18px;margin-bottom:20px;font-size:.85rem;color:var(--muted);">
      <a href="syl_auth.php?tab=login" style="color:var(--yellow);font-weight:700;text-decoration:none;">Log in</a> or
      <a href="syl_auth.php?tab=register" style="color:var(--yellow);font-weight:700;text-decoration:none;">sign up</a> to review this song.
    </div>
    <?php endif; ?>

    <!-- Reviews and replies -->
    <div class="reviews-heading">&#128172; What people are saying</div>

    <?php if (empty($allReviews)): ?>
      <div class="no-reviews">
        <div class="no-reviews-icon">&#127925;</div>
        <div class="no-reviews-title">No reviews yet</div>
        <p><?= $isLoggedIn ? '<a href="syl_songs.php" style="color:var(--yellow);font-weight:700;text-decoration:none;">Add your review →</a>' : '<a href="syl_auth.php?tab=register" style="color:var(--yellow);font-weight:700;text-decoration:none;">Sign up to be the first to review this song →</a>' ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($allReviews as $idx => $rev):
        $emoji = reviewerEmoji($rev['reviewer_tag'], $avatarEmojis);
        $color = reviewerColor($rev['reviewer_tag'], $avatarColors);
        
      ?>
      <div class="review-card" id="review-<?= $rev['id'] ?>">

        <!-- Review header -->
        <div class="rc-header">
          <a href="syl_profile.php?user=<?= h($rev['reviewer_tag']) ?>"
             style="text-decoration:none;">
            <div class="rc-avatar" style="background:<?= $color ?>22;border:2px solid <?= $color ?>44;"><?= $emoji ?></div>
          </a>
          <div class="rc-user">
            <div class="rc-name">
              <a href="syl_profile.php?user=<?= h($rev['reviewer_tag']) ?>"
                 style="color:var(--teal);text-decoration:none;">@<?= h($rev['reviewer_tag']) ?></a>
            </div>
            <div class="rc-time"><?= timeAgo($rev['review_date']) ?></div>
          </div>
          <div class="rc-rating">&#9733; <?= h($rev['rating']) ?><span style="font-size:.7rem;color:var(--muted);font-family:'Space Mono',monospace;">/10</span></div>
        </div>

        <?php if ($rev['duration']): ?>
        <div class="rc-dur">&#128336; <?= formatDuration($rev['duration']) ?></div>
        <?php endif; ?>

        <?php if ($rev['review']): ?>
        <div class="rc-review">&ldquo;<?= h($rev['review']) ?>&rdquo;</div>
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <footer>SYL &mdash; Save Your Listens &middot; &copy; 2026</footer>
</div>

<script>
let newRating = 0;
(function buildNewStars() {
  const row = document.getElementById('new-star-row');
  if (!row) return;
  for (let i = 1; i <= 10; i++) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = '\u2605';
    btn.style.cssText = 'font-size:1.1rem;background:none;border:none;cursor:pointer;color:#ffffff20;padding:0 1px;transition:color .1s;';
    btn.onclick = (function(n) { return function() {
      newRating = n;
      document.getElementById('new-rating-val').value = n;
      document.querySelectorAll('#new-star-row button').forEach((s,i) => {
        s.style.color = i < n ? 'var(--yellow)' : '#ffffff20';
      });
      const hints = ['','1','2','3','4','5 — Average','6','7 — Good','8 — Great','9 — Excellent','10 — Perfect'];
      const hint = document.getElementById('new-rating-hint');
      if (hint) hint.textContent = '\u2605 ' + hints[n];
    };})(i);
    row.appendChild(btn);
  }
})();

function submitNewReview() {
  if (!newRating) { alert('\u26a0 Please select a rating.'); return; }
  document.getElementById('new-rating-val').value = newRating;
  document.getElementById('new-rating-val').closest('form').submit();
}

let sbOpen = true;
function toggleSidebar() {
  sbOpen = !sbOpen;
  document.getElementById('sidebar').classList.toggle('col', !sbOpen);
  document.body.classList.toggle('sb-col', !sbOpen);
}
</script>
</body>
</html>

