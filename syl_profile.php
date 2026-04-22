<?php
// ============================================================
//  syl_profile.php — SYL User Profile
//  Shows a user's profile: avatar, bio, stats, 4 favorite picks,
//  and all their reviews with ratings.
//
//  URL: syl_profile.php             → logged-in user's own profile
//       syl_profile.php?user=tag    → any user's public profile
//
//  Requires login for own profile, public for others.
// ============================================================
session_start();
require_once 'db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatDuration($s) {
    if (!$s) return '&mdash;';
    return floor($s/60).':'.str_pad($s%60,2,'0',STR_PAD_LEFT);
}

$isLoggedIn = isset($_SESSION['user_tag']);
$sessionTag = $isLoggedIn ? $_SESSION['user_tag'] : '';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: syl_home.php');
    exit;
}

// ─── Whose profile to show ────────────────────────────────────────────────────
$viewTag     = trim($_GET['user'] ?? $sessionTag);
$isOwnProfile = $isLoggedIn && $viewTag === $sessionTag;

// If no user specified and not logged in → go to auth
if (!$viewTag) {
    header('Location: syl_auth.php?tab=login');
    exit;
}

// ─── Load profile user ────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT tag, first_name, last_name, bio, picture FROM Users WHERE tag = ?");
$stmt->execute([$viewTag]);
$profileUser = $stmt->fetch();

if (!$profileUser) {
    // User not found
    http_response_code(404);
    die('<!DOCTYPE html><html><body style="background:#0F0F1E;color:#F0EDE6;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:16px;"><h2>User not found</h2><a href="syl_home.php" style="color:#F5E642;">← Back to Home</a></body></html>');
}

// ─── Stats ────────────────────────────────────────────────────────────────────
$totalSongs = $pdo->prepare("SELECT COUNT(*) FROM UserReviews WHERE user_tag = ?");
$totalSongs->execute([$viewTag]);
$totalSongs = (int)$totalSongs->fetchColumn();

$avgRating = $pdo->prepare("SELECT ROUND(AVG(r.rating),1) FROM Reviews r JOIN UserReviews ur ON r.id=ur.review_id WHERE ur.user_tag=?");
$avgRating->execute([$viewTag]);
$avgRating = $avgRating->fetchColumn() ?? '—';

$thisMonth = $pdo->prepare("SELECT COUNT(*) FROM Reviews r JOIN UserReviews ur ON r.id=ur.review_id WHERE ur.user_tag=? AND MONTH(r.review_date)=MONTH(CURDATE()) AND YEAR(r.review_date)=YEAR(CURDATE())");
$thisMonth->execute([$viewTag]);
$thisMonth = (int)$thisMonth->fetchColumn();

// ─── Favorites (UserFavorites table, rank 1-4) ────────────────────────────────
$favStmt = $pdo->prepare("
    SELECT r.id, r.song_name, r.artist_name, uf.rank
    FROM UserFavorites uf
    JOIN Reviews r ON r.id = uf.review_id
    WHERE uf.user_tag = ?
    ORDER BY uf.rank ASC
");
$favStmt->execute([$viewTag]);
$favorites = $favStmt->fetchAll();

// ─── Reviews with filter ──────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all'; // all | rating | week
$baseQ  = "SELECT r.id, r.song_name, r.artist_name, r.album_name, r.duration, r.rating, r.review, r.review_date
           FROM Reviews r JOIN UserReviews ur ON r.id=ur.review_id WHERE ur.user_tag=?";

if ($filter === 'rating') {
    $baseQ .= " ORDER BY r.rating DESC";
} elseif ($filter === 'week') {
    $baseQ .= " AND r.review_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY r.review_date DESC";
} else {
    $baseQ .= " ORDER BY r.review_date DESC";
}

$revStmt = $pdo->prepare($baseQ);
$revStmt->execute([$viewTag]);
$reviews = $revStmt->fetchAll();

$rowColors = ['sr-yellow','sr-sand','sr-pink','sr-teal','sr-lavender'];
$favColors = ['#69D17E','#5B9BF5','#9C6FE4','#FF8A50'];

$displayName = trim(($profileUser['first_name']??'').' '.($profileUser['last_name']??'')) ?: $profileUser['tag'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL &mdash; @<?= h($viewTag) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--yellow:#F5E642;--pink:#F06292;--teal:#4DD0C4;--lavender:#B39DDB;--green:#69D17E;--orange:#FF8A50;--blue:#5B9BF5;--purple:#9C6FE4;--sand:#E8E0D0;--dark:#1A1A2E;--darker:#0F0F1E;--card:#22223B;--text:#F0EDE6;--muted:#8B8BAA;--sidebar-w:220px;--sidebar-w-col:64px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--darker);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

/* ─── SIDEBAR ─── */
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
.sb-link{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;text-decoration:none;background:none;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:500;white-space:nowrap;transition:background .18s,color .18s;text-align:left;width:100%;position:relative;}
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
.top-nav{display:flex;align-items:center;padding:14px 40px;background:var(--dark);border-bottom:1px solid #ffffff0a;position:sticky;top:0;z-index:100;gap:10px;}
.nav-logo{font-family:'Dela Gothic One',sans-serif;font-size:1.3rem;background:linear-gradient(90deg,var(--yellow),var(--pink),var(--teal));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-right:auto;padding-left:50px;text-decoration:none;}
.nbtn{padding:8px 20px;border-radius:100px;border:none;font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-block;}
.nbtn.ghost{background:transparent;color:var(--text);border:2px solid #ffffff20;}
.nbtn.ghost:hover{border-color:var(--yellow);color:var(--yellow);}
.nbtn.primary{background:var(--yellow);color:var(--dark);}
.nbtn.primary:hover{background:#ffe000;}
.nbtn.out{background:transparent;color:var(--muted);border:2px solid #ffffff15;}
.nbtn.out:hover{border-color:var(--pink);color:var(--pink);}

/* Avatar circle in nav */
.nav-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--teal));border:2px solid var(--yellow);display:flex;align-items:center;justify-content:center;font-size:1rem;cursor:pointer;text-decoration:none;transition:box-shadow .2s;flex-shrink:0;}
.nav-avatar:hover{box-shadow:0 0 0 3px #F5E64230;}
.nav-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover;}

/* ─── PROFILE PAGE ─── */
.profile-wrap{max-width:1060px;margin:0 auto;padding:40px;}

/* Profile top section */
.profile-top{display:grid;grid-template-columns:auto 1fr auto;gap:32px;align-items:start;margin-bottom:40px;}

/* Avatar */
.profile-avatar-block{position:relative;}
.profile-avatar{width:120px;height:120px;border-radius:20px;background:linear-gradient(135deg,var(--green),var(--teal));display:flex;align-items:center;justify-content:center;font-size:3rem;border:3px solid var(--yellow);box-shadow:0 0 0 6px #F5E64220;overflow:hidden;}
.profile-avatar img{width:100%;height:100%;object-fit:cover;}

/* Profile info */
.profile-name{font-family:'Dela Gothic One',sans-serif;font-size:2rem;margin-bottom:4px;}
.profile-handle{font-family:'Space Mono',monospace;font-size:.8rem;color:var(--teal);margin-bottom:14px;}
.profile-bio{font-size:.9rem;color:var(--muted);line-height:1.6;max-width:420px;background:var(--card);padding:14px 18px;border-radius:12px;border-left:3px solid var(--yellow);}
.profile-stats{display:flex;gap:24px;margin-top:16px;}
.stat{text-align:center;}
.stat-num{font-family:'Dela Gothic One',sans-serif;font-size:1.4rem;color:var(--yellow);}
.stat-label{font-size:.72rem;color:var(--muted);letter-spacing:1px;text-transform:uppercase;}

/* Edit profile button (own profile only) */
.edit-profile-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:none;border:2px solid #ffffff20;border-radius:12px;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;margin-top:16px;text-decoration:none;}
.edit-profile-btn:hover{border-color:var(--yellow);color:var(--yellow);}
.add-song-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;background:var(--yellow);color:var(--dark);border:none;border-radius:12px;font-family:'Dela Gothic One',sans-serif;font-size:.9rem;cursor:pointer;transition:all .2s;margin-top:16px;margin-left:8px;text-decoration:none;}
.add-song-btn:hover{background:#ffe000;transform:translateY(-2px);}

/* Favorite picks */
.fav-label{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:3px;color:var(--muted);text-transform:uppercase;text-align:right;margin-bottom:10px;}
.fav-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;width:220px;}
.fav-tile{border-radius:12px;padding:14px 12px;font-family:'Dela Gothic One',sans-serif;font-size:.78rem;line-height:1.3;cursor:pointer;transition:transform .18s;min-height:70px;display:flex;flex-direction:column;justify-content:flex-end;text-decoration:none;}
.fav-tile:hover{transform:scale(1.04);}
.fav-tile .ft-name{font-size:.76rem;}
.fav-tile .ft-artist{font-size:.62rem;opacity:.7;font-family:'DM Sans',sans-serif;font-weight:400;}

/* Divider */
.profile-divider{background:var(--card);border-radius:4px;height:2px;margin-bottom:32px;position:relative;overflow:hidden;}
.profile-divider::after{content:'';position:absolute;left:0;top:0;height:100%;width:40%;background:linear-gradient(90deg,var(--yellow),var(--pink),var(--teal));border-radius:4px;}

/* Recent listens */
.recent-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.recent-heading{font-family:'Dela Gothic One',sans-serif;font-size:1.4rem;}
.filter-pills{display:flex;gap:8px;}
.pill{padding:6px 16px;border-radius:100px;border:2px solid #ffffff15;background:none;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;display:inline-block;}
.pill.active,.pill:hover{border-color:var(--yellow);color:var(--yellow);}

/* Song rows */
.song-row{display:grid;grid-template-columns:32px 1fr 140px 90px 80px 1fr;align-items:center;gap:16px;padding:14px 20px;border-radius:12px;margin-bottom:8px;font-size:.9rem;font-weight:600;transition:filter .15s;cursor:pointer;text-decoration:none;}
.song-row:hover{filter:brightness(1.08);}
.song-row .row-num{font-family:'Space Mono',monospace;font-size:.7rem;opacity:.55;text-align:center;}
.song-row .row-name{font-weight:700;}
.song-row .row-artist{opacity:.7;font-weight:400;}
.song-row .row-dur{font-family:'Space Mono',monospace;font-size:.78rem;opacity:.7;}
.song-row .row-rating{font-family:'Space Mono',monospace;font-size:.78rem;}
.song-row .row-note{font-size:.8rem;font-weight:400;opacity:.75;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sr-yellow{background:var(--yellow);color:var(--dark);}
.sr-sand{background:var(--sand);color:var(--dark);}
.sr-pink{background:var(--pink);color:#fff;}
.sr-teal{background:var(--teal);color:var(--dark);}
.sr-lavender{background:var(--lavender);color:var(--dark);}
.song-table-head{display:grid;grid-template-columns:32px 1fr 140px 90px 80px 1fr;gap:16px;padding:0 20px 10px;font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:2px;color:var(--muted);text-transform:uppercase;}
.empty-state{color:var(--muted);padding:20px 0;font-size:.9rem;}

footer{text-align:center;padding:32px 40px;color:var(--muted);font-size:.75rem;font-family:'Space Mono',monospace;border-top:1px solid #ffffff08;}

@media(max-width:900px){.profile-top{grid-template-columns:1fr;}.fav-grid{width:100%;}}
@media(max-width:600px){#main{margin-left:var(--sidebar-w-col)!important;}#sidebar{width:var(--sidebar-w-col)!important;}.sb-label{opacity:0!important;}.profile-wrap{padding:20px;}.song-row{grid-template-columns:28px 1fr 80px 60px;}.song-row .row-note,.song-row .row-rating{display:none;}.song-table-head{display:none;}}
</style>
</head>
<body>

<button id="sb-toggle-btn" onclick="toggleSidebar()" title="Toggle sidebar">&#9776;</button>

<aside id="sidebar">
  <div class="sb-top"><a class="sb-logo" href="syl_home.php">SYL</a></div>
  <nav class="sb-nav">
    <a class="sb-link" href="syl_home.php"      data-label="home">      <span class="sb-icon">&#127968;</span><span class="sb-label">Home</span></a>
    <a class="sb-link" href="syl_songs.php"     data-label="songs">     <span class="sb-icon">&#127925;</span><span class="sb-label">Songs</span></a>
    
    <a class="sb-link" href="syl_community.php" data-label="community"><span class="sb-icon">&#127758;</span><span class="sb-label">Community</span></a>
  </nav>
  <div class="sb-bottom">
    <a class="sb-link" href="syl_settings.php" data-label="settings"><span class="sb-icon">&#9881;&#65039;</span><span class="sb-label">Settings</span></a>
  </div>
</aside>

<div id="main">

  <!-- Top nav with avatar circle -->
  <div class="top-nav">
    <a class="nav-logo" href="syl_home.php">SYL</a>
    <?php if ($isLoggedIn): ?>
      <!-- Clickable avatar circle → goes to own profile -->
      <a class="nav-avatar" href="syl_profile.php" title="@<?= h($sessionTag) ?>">
        <?php if (!empty($profileUser['picture']) && $isOwnProfile): ?>
          <img src="data:image/jpeg;base64,<?= base64_encode($profileUser['picture']) ?>" alt="avatar">
        <?php else: ?>
          &#127911;
        <?php endif; ?>
      </a>
      <a class="nbtn out" href="syl_auth.php?action=logout" style="text-decoration:none;">Log Out</a>
    <?php else: ?>
      <a class="nbtn ghost"   href="syl_auth.php?tab=login"    style="text-decoration:none;">Log In</a>
      <a class="nbtn primary" href="syl_auth.php?tab=register" style="text-decoration:none;">Sign Up</a>
    <?php endif; ?>
  </div>

  <div class="profile-wrap">
    <div class="profile-top">

      <!-- Avatar -->
      <div class="profile-avatar-block">
        <div class="profile-avatar">
          <?php if (!empty($profileUser['picture'])): ?>
            <img src="data:image/jpeg;base64,<?= base64_encode($profileUser['picture']) ?>" alt="avatar">
          <?php else: ?>
            &#127911;
          <?php endif; ?>
        </div>
      </div>

      <!-- Info -->
      <div>
        <div class="profile-name"><?= h($displayName) ?></div>
        <div class="profile-handle">@<?= h($profileUser['tag']) ?></div>
        <div class="profile-bio"><?= h($profileUser['bio'] ?? 'No bio set yet.') ?></div>

        <div class="profile-stats">
          <div class="stat"><div class="stat-num"><?= $totalSongs ?></div><div class="stat-label">Songs Logged</div></div>
          <div class="stat"><div class="stat-num"><?= $avgRating ?></div><div class="stat-label">Avg Rating</div></div>
          <div class="stat"><div class="stat-num"><?= $thisMonth ?></div><div class="stat-label">This Month</div></div>
        </div>

        <?php if ($isOwnProfile): ?>
          <a class="edit-profile-btn" href="syl_settings.php">&#9998; Edit Profile</a>
          <a class="add-song-btn"     href="syl_songs.php">&#43; Add Song</a>
        <?php endif; ?>
      </div>

      <!-- Favorite picks from UserFavorites table -->
      <div>
        <div class="fav-label">&#11088; Favorite Picks</div>
        <div class="fav-grid">
          <?php if (empty($favorites)): ?>
            <div class="fav-tile" style="background:var(--card);opacity:.5;grid-column:span 2;color:var(--muted);">
              <span class="ft-name">No favorites set</span>
            </div>
          <?php else: ?>
            <?php foreach ($favorites as $fi => $fav): ?>
            <a class="fav-tile" href="syl_song.php?id=<?= $fav['id'] ?>"
               style="background:<?= $favColors[$fi % 4] ?>22;border:1px solid <?= $favColors[$fi % 4] ?>44;color:var(--text);">
              <span class="ft-name"><?= h($fav['song_name']) ?></span>
              <span class="ft-artist"><?= h($fav['artist_name']) ?></span>
            </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <div class="profile-divider"></div>

    <!-- Filter pills — real links, PHP re-runs the query -->
    <div class="recent-header">
      <h2 class="recent-heading">Recent Listens</h2>
      <div class="filter-pills">
        <a class="pill <?= $filter==='all'    ? 'active':'' ?>" href="syl_profile.php?user=<?= h($viewTag) ?>&filter=all">All</a>
        <a class="pill <?= $filter==='rating' ? 'active':'' ?>" href="syl_profile.php?user=<?= h($viewTag) ?>&filter=rating">Top Rated</a>
        <a class="pill <?= $filter==='week'   ? 'active':'' ?>" href="syl_profile.php?user=<?= h($viewTag) ?>&filter=week">This Week</a>
      </div>
    </div>

    <div class="song-table-head">
      <span>#</span><span>Song</span><span>Artist</span><span>Duration</span><span>Rating</span><span>Review</span>
    </div>

    <?php if (empty($reviews)): ?>
      <p class="empty-state">No songs logged yet<?= $isOwnProfile ? '. Hit "+ Add Song" to get started!' : '.' ?></p>
    <?php else: ?>
      <?php foreach ($reviews as $i => $r): ?>
      <!-- Each row links to the song detail page -->
      <a class="song-row <?= $rowColors[$i % count($rowColors)] ?>" href="syl_song.php?id=<?= $r['id'] ?>">
        <span class="row-num"><?= $i + 1 ?></span>
        <span class="row-name"><?= h($r['song_name']) ?></span>
        <span class="row-artist"><?= h($r['artist_name']) ?></span>
        <span class="row-dur"><?= formatDuration($r['duration']) ?></span>
        <span class="row-rating">&#9733; <?= h($r['rating']) ?></span>
        <span class="row-note"><?= h($r['review'] ?? '') ?></span>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <footer>SYL &mdash; Save Your Listens &middot; &copy; 2026</footer>
</div>

<script>
let sbOpen = true;
function toggleSidebar() {
  sbOpen = !sbOpen;
  document.getElementById('sidebar').classList.toggle('col', !sbOpen);
  document.body.classList.toggle('sb-col', !sbOpen);
}
</script>
</body>
</html>
