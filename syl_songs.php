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
// ─── Add Song POST handler ────────────────────────────────────────────────────
// Requires active session. POSTs here with action=add_song.
// Converts "m:ss" duration string → seconds before INSERT.
// rating is 1-10 (TINYINT CHECK BETWEEN 1 AND 10).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_song' && $isLoggedIn) {
    $song   = trim($_POST['song_name'] ?? '');
    $artist = trim($_POST['artist']    ?? '');
    $album  = trim($_POST['album']     ?? '') ?: null;
    $durStr = trim($_POST['duration']  ?? '0:00');
    $rating = max(1, min(10, (int)($_POST['rating'] ?? 1)));
    $notes  = trim($_POST['notes']     ?? '') ?: null;

    $parts   = explode(':', $durStr);
    $durSecs = count($parts) === 2 ? (int)$parts[0]*60+(int)$parts[1] : (int)$parts[0];

    if ($song && $artist) {
        // Check if THIS user already reviewed this song
        $dupCheck = $pdo->prepare("
            SELECT r.id FROM Reviews r
            JOIN UserReviews ur ON r.id = ur.review_id
            WHERE ur.user_tag = ? AND r.song_name = ? AND r.artist_name = ?
            LIMIT 1
        ");
        $dupCheck->execute([$userTag, $song, $artist]);
        $existingReview = $dupCheck->fetch();

        if ($existingReview) {
            // This user already reviewed this song — send them to the existing review
            $_SESSION['flash_notice'] = "You already reviewed \"$song\" — here is your existing review.";
            header('Location: syl_song.php?id=' . $existingReview['id']);
            exit;
        }

        // Check if OTHER users have reviewed this song already
        $otherCheck = $pdo->prepare("
            SELECT r.id, COUNT(*) AS cnt FROM Reviews r
            JOIN UserReviews ur ON r.id = ur.review_id
            WHERE r.song_name = ? AND r.artist_name = ?
            LIMIT 1
        ");
        $otherCheck->execute([$song, $artist]);
        $otherReview = $otherCheck->fetch();
        $alreadyExists = $otherReview && $otherReview['cnt'] > 0;

        // Insert the new review regardless (different users can review the same song)
        $stmt = $pdo->prepare("INSERT INTO Reviews (song_name,artist_name,album_name,duration,rating,review) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$song,$artist,$album,$durSecs,$rating,$notes]);
        $reviewId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO UserReviews (user_tag,review_id) VALUES (?,?)");
        $stmt->execute([$userTag,$reviewId]);

        if ($alreadyExists) {
            $_SESSION['flash_notice'] = "&#9432; Others have also reviewed \"$song\" — check out what they think on the song page!";
        } else {
            $_SESSION['flash_notice'] = "&#10003; \"$song\" added successfully!";
        }
        header('Location: syl_song.php?id=' . $reviewId);
        exit;
    }
    header('Location: syl_songs.php');
    exit;
}

// ─── Songs catalog — all Reviews rows, injected as JSON for JS search/sort ───
// JS handles live search across song_name, artist_name, album_name, review.
// Filters: A-Z | Album | Artist — all client-side, no extra server requests.
$songsCatalog = [];
try {
    $stmt = $pdo->query("
        SELECT r.id, r.song_name, r.artist_name,
               COALESCE(r.album_name,'Unknown Album') AS album_name,
               r.duration, r.rating, r.review
        FROM Reviews r ORDER BY r.song_name ASC
    ");
    $songsCatalog = $stmt->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL — Songs</title>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
/* ─── COLOR TOKENS (matches entire SYL design system) ─── */
:root {
  --yellow:#F5E642; --pink:#F06292; --teal:#4DD0C4; --lavender:#B39DDB;
  --green:#69D17E;  --orange:#FF8A50; --blue:#5B9BF5; --purple:#9C6FE4;
  --sand:#E8E0D0;   --dark:#1A1A2E;  --darker:#0F0F1E; --card:#22223B;
  --text:#F0EDE6;   --muted:#8B8BAA;
  --sidebar-w:220px; --sidebar-w-col:64px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--darker);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

/* ─── SIDEBAR ─── */
#sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--dark);border-right:1px solid #ffffff0a;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:200;transition:width .3s cubic-bezier(.4,0,.2,1);overflow:hidden;}
#sidebar.col{width:var(--sidebar-w-col);}
.sb-top{display:flex;align-items:center;padding:20px 16px 16px;border-bottom:1px solid #ffffff08;min-height:64px;flex-shrink:0;}
.sb-logo{font-family:'Dela Gothic One',sans-serif;font-size:1.4rem;background:linear-gradient(90deg,var(--yellow),var(--pink));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;white-space:nowrap;opacity:1;transition:opacity .2s;cursor:pointer;}
#sidebar.col .sb-logo{opacity:0;pointer-events:none;}
/* Toggle always visible — outside sidebar */
#sb-toggle-btn{position:fixed;top:14px;z-index:300;background:var(--dark);border:1px solid #ffffff12;color:var(--muted);cursor:pointer;font-size:1.05rem;width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;transition:color .2s,background .2s,left .3s cubic-bezier(.4,0,.2,1),box-shadow .2s;}
#sb-toggle-btn:hover{color:var(--yellow);background:var(--card);box-shadow:0 0 0 3px #F5E64220;}
body:not(.sb-col) #sb-toggle-btn{left:calc(var(--sidebar-w) - 46px);}
body.sb-col #sb-toggle-btn{left:14px;}
.sb-nav{flex:1;padding:16px 8px;display:flex;flex-direction:column;gap:4px;overflow:hidden;}
.sb-link{display:flex;text-decoration:none;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;border:none;background:none;color:var(--muted);cursor:pointer;font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:500;white-space:nowrap;transition:background .18s,color .18s;text-align:left;width:100%;position:relative;text-decoration:none;}
.sb-link:hover{background:#ffffff08;color:var(--text);}
.sb-link.active{background:#F5E64218;color:var(--yellow);}
.sb-icon{font-size:1.15rem;flex-shrink:0;width:24px;text-align:center;}
.sb-label{opacity:1;transition:opacity .2s;overflow:hidden;}
#sidebar.col .sb-label{opacity:0;}
.sb-bottom{padding:8px 8px 20px;border-top:1px solid #ffffff08;}
#sidebar.col .sb-link::after{content:attr(data-label);position:absolute;left:calc(var(--sidebar-w-col) + 8px);top:50%;transform:translateY(-50%);background:var(--card);color:var(--text);font-size:.78rem;padding:5px 10px;border-radius:8px;white-space:nowrap;pointer-events:none;opacity:0;border:1px solid #ffffff15;transition:opacity .15s;z-index:300;}
#sidebar.col .sb-link:hover::after{opacity:1;}

/* ─── MAIN ─── */
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
.nbtn.danger{background:transparent;color:var(--muted);border:2px solid #ffffff15;}
.nbtn.danger:hover{border-color:var(--pink);color:var(--pink);}
.user-chip{display:flex;align-items:center;gap:8px;background:var(--card);border-radius:100px;padding:6px 14px 6px 8px;font-size:.82rem;}
.user-av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--teal));display:flex;align-items:center;justify-content:center;font-size:.8rem;}
#nav-out{display:flex;gap:10px;}
#nav-in{display:none;gap:10px;align-items:center;}

/* ─── SONGS PAGE LAYOUT ─── */
.songs-wrap{padding:40px;max-width:1060px;margin:0 auto;}

/* Page header */
.songs-header{margin-bottom:32px;}
.songs-title{font-family:'Dela Gothic One',sans-serif;font-size:2.2rem;margin-bottom:6px;}
.songs-sub{font-size:.88rem;color:var(--muted);line-height:1.5;}

/* ─── SEARCH BAR ─── */
.songs-search-row{display:flex;gap:12px;align-items:center;margin-bottom:24px;flex-wrap:wrap;}
.songs-search-wrap{flex:1;min-width:220px;position:relative;}
.songs-search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.95rem;pointer-events:none;}
.songs-search{
  width:100%;padding:13px 16px 13px 42px;
  background:var(--card);border:2px solid #ffffff10;
  border-radius:14px;color:var(--text);
  font-family:'DM Sans',sans-serif;font-size:.95rem;
  outline:none;transition:border-color .2s,box-shadow .2s;
}
.songs-search:focus{border-color:var(--yellow);box-shadow:0 0 0 3px #F5E64215;}
.songs-search::placeholder{color:var(--muted);}

/* ─── FILTER + SORT ROW ─── */
.songs-controls{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;}
.songs-filter-group{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.filter-label{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:2px;color:var(--muted);text-transform:uppercase;margin-right:4px;}
.spill{
  padding:7px 18px;border-radius:100px;
  border:2px solid #ffffff15;background:none;
  color:var(--muted);font-family:'DM Sans',sans-serif;
  font-size:.82rem;font-weight:600;cursor:pointer;
  transition:all .15s;
}
.spill:hover{border-color:var(--yellow);color:var(--yellow);}
.spill.active{background:var(--yellow);border-color:var(--yellow);color:var(--dark);}

/* Add Song button (logged-in) / Sign up prompt (guests) */
.songs-add-btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:11px 20px;background:var(--yellow);color:var(--dark);
  border:none;border-radius:12px;font-family:'Dela Gothic One',sans-serif;
  font-size:.88rem;cursor:pointer;transition:all .2s;white-space:nowrap;
}
.songs-add-btn:hover{background:#ffe000;transform:translateY(-1px);box-shadow:0 6px 20px #F5E64230;}
.songs-guest-note{
  display:inline-flex;align-items:center;gap:8px;
  padding:11px 18px;border:2px solid #ffffff15;border-radius:12px;
  color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.82rem;
  font-weight:600;cursor:pointer;transition:all .15s;background:none;white-space:nowrap;
}
.songs-guest-note:hover{border-color:var(--teal);color:var(--teal);}

/* Result count */
.songs-count{font-family:'Space Mono',monospace;font-size:.7rem;color:var(--muted);letter-spacing:1px;margin-bottom:16px;}
.songs-count strong{color:var(--yellow);}

/* ─── SECTION DIVIDERS (group headers) ─── */
.songs-divider{
  display:flex;align-items:center;gap:14px;
  grid-column:1 / -1;
  margin:12px 0 4px;
}
.songs-divider-label{
  font-family:'Dela Gothic One',sans-serif;font-size:1rem;
  color:var(--yellow);white-space:nowrap;
}
.songs-divider-line{flex:1;height:1px;background:#ffffff08;}

/* ─── SONGS GRID ─── */
.songs-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}

/* ─── INDIVIDUAL SONG CARD ─── */
.song-card{
  background:var(--card);border-radius:14px;padding:18px 20px;
  border:1px solid #ffffff08;
  transition:border-color .2s,transform .2s,box-shadow .2s;
  cursor:default;
  display:flex;flex-direction:column;gap:0;
}
.song-card:hover{border-color:#ffffff1a;transform:translateY(-2px);box-shadow:0 8px 28px #00000040;}

/* Card top row: emoji thumb + info + rating */
.sc-top{display:flex;gap:14px;align-items:flex-start;}
.sc-thumb{
  width:52px;height:52px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.6rem;flex-shrink:0;
  border:1px solid #ffffff10;
}
.sc-info{flex:1;min-width:0;}
.sc-title{
  font-family:'Dela Gothic One',sans-serif;font-size:1rem;
  margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.sc-artist{font-size:.82rem;color:var(--muted);margin-bottom:3px;}
.sc-album{
  font-size:.72rem;color:var(--muted);
  font-family:'Space Mono',monospace;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
/* Album pill tag */
.sc-album-tag{
  display:inline-block;margin-top:5px;
  padding:2px 8px;border-radius:6px;
  font-family:'Space Mono',monospace;font-size:.62rem;font-weight:700;
  background:#ffffff08;color:var(--muted);
  border:1px solid #ffffff10;
}

/* Rating block on right */
.sc-rating-block{flex-shrink:0;text-align:right;}
.sc-rating{
  font-family:'Dela Gothic One',sans-serif;font-size:1.5rem;
  line-height:1;
}
.sc-rating-sub{font-family:'Space Mono',monospace;font-size:.6rem;color:var(--muted);margin-top:2px;}
.sc-dur{font-family:'Space Mono',monospace;font-size:.68rem;color:var(--muted);margin-top:5px;}

/* Review text — truncated to 2 lines */
.sc-review{
  margin-top:14px;padding-top:12px;
  border-top:1px solid #ffffff08;
  font-size:.82rem;color:var(--text);
  line-height:1.55;font-style:italic;opacity:.85;
  display:-webkit-box;
  -webkit-line-clamp:2;
  -webkit-box-orient:vertical;
  overflow:hidden;
}

/* Rating color based on score */
.rating-low   { color: var(--pink);    }  /* 1-4  */
.rating-mid   { color: var(--orange);  }  /* 5-6  */
.rating-good  { color: var(--yellow);  }  /* 7-8  */
.rating-great { color: var(--green);   }  /* 9-10 */

/* ─── EMPTY STATE ─── */
.songs-empty{
  grid-column:1 / -1;
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:80px 20px;text-align:center;
  color:var(--muted);
}
.songs-empty-icon{font-size:3.5rem;margin-bottom:18px;}
.songs-empty-title{font-family:'Dela Gothic One',sans-serif;font-size:1.5rem;color:var(--text);margin-bottom:8px;}
.songs-empty-sub{font-size:.88rem;line-height:1.6;max-width:320px;}

/* ─── ADD SONG MODAL ─── */
.modal-overlay{display:none;position:fixed;inset:0;background:#00000085;backdrop-filter:blur(6px);z-index:400;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.add-modal{background:var(--card);border-radius:24px;padding:40px;width:480px;max-width:95vw;border:1px solid #ffffff15;position:relative;box-shadow:0 32px 80px #000000a0;max-height:90vh;overflow-y:auto;}
.add-modal-title{font-family:'Dela Gothic One',sans-serif;font-size:1.4rem;margin-bottom:24px;}
.modal-close{position:absolute;top:18px;right:20px;background:none;border:none;color:var(--muted);font-size:1.4rem;cursor:pointer;line-height:1;transition:color .2s;}
.modal-close:hover{color:var(--text);}
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:7px;}
.form-input{width:100%;padding:13px 16px;background:var(--darker);border:2px solid #ffffff10;border-radius:12px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;transition:border-color .2s;}
.form-input:focus{border-color:var(--yellow);}
.form-input::placeholder{color:var(--muted);}
.form-submit{width:100%;padding:14px;background:var(--yellow);color:var(--dark);border:none;border-radius:12px;font-family:'Dela Gothic One',sans-serif;font-size:1rem;cursor:pointer;margin-top:8px;transition:all .2s;}
.form-submit:hover{background:#ffe000;transform:translateY(-1px);}
/* 10-star rating row */
.star-row{display:flex;gap:3px;margin-top:6px;flex-wrap:wrap;}
.star-btn{font-size:1.3rem;background:none;border:none;cursor:pointer;color:#ffffff20;transition:color .12s;}
.star-btn.lit{color:var(--yellow);}
/* Rating hint */
.rating-hint{margin-top:6px;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--muted);min-height:16px;}

/* ─── FOOTER ─── */
footer{text-align:center;padding:32px 40px;color:var(--muted);font-size:.75rem;font-family:'Space Mono',monospace;border-top:1px solid #ffffff08;}

/* ─── RESPONSIVE ─── */
@media(max-width:900px){.songs-grid{grid-template-columns:1fr;}}
@media(max-width:768px){.songs-wrap{padding:20px;}.songs-search-row{flex-direction:column;align-items:stretch;}.songs-controls{flex-direction:column;align-items:flex-start;}}
@media(max-width:600px){#main{margin-left:var(--sidebar-w-col);}#sidebar{width:var(--sidebar-w-col);}.sb-label{opacity:0;}.sb-logo{opacity:0;pointer-events:none;}body:not(.sb-col) #sb-toggle-btn{left:14px;}}
</style>
</head>
<body>

<!-- Fixed sidebar toggle — always visible -->
<button id="sb-toggle-btn" onclick="toggleSidebar()" title="Toggle sidebar">&#9776;</button>

<!-- ─── SIDEBAR ─── -->
<aside id="sidebar">
  <div class="sb-top"><a class="sb-logo" href="syl_home.php" style="text-decoration:none;">SYL</a></div>
  <nav class="sb-nav">
    <a class="sb-link "      href="syl_home.php"      data-label="home">      <span class="sb-icon">&#127968;</span><span class="sb-label">Home</span></a>
    <a class="sb-link active"     href="syl_songs.php"     data-label="songs">     <span class="sb-icon">&#127925;</span><span class="sb-label">Songs</span></a>
    
    <a class="sb-link" href="syl_friends.php"   data-label="friends">   <span class="sb-icon">&#128101;</span><span class="sb-label">Friends</span></a>
    <a class="sb-link " href="syl_community.php" data-label="community"><span class="sb-icon">&#127758;</span><span class="sb-label">Community</span></a>
  </nav>
  <div class="sb-bottom">
    <a class="sb-link "  href="syl_settings.php"  data-label="settings">  <span class="sb-icon">&#9881;&#65039;</span><span class="sb-label">Settings</span></a>
  </div>
</aside>

<!-- ─── MAIN ─── -->
<div id="main">

  <!-- Top nav — PHP renders correct buttons based on session -->
  <div class="top-nav">
    <a href="syl_home.php" style="text-decoration:none;font-family:'Dela Gothic One',sans-serif;font-size:1.3rem;background:linear-gradient(90deg,var(--yellow),var(--pink),var(--teal));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-right:auto;padding-left:50px;">SYL</a>
    <?php if ($isLoggedIn): ?>
      <a class="nav-avatar" href="syl_profile.php" title="@<?= h($userTag) ?>" style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--teal));border:2px solid var(--yellow);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:box-shadow .2s;flex-shrink:0;">&#127911;</a>
      <a class="nbtn out" href="syl_auth.php?action=logout" style="text-decoration:none;">Log Out</a>
    <?php else: ?>
      <a class="nbtn ghost"   href="syl_auth.php?tab=login"    style="text-decoration:none;">Log In</a>
      <a class="nbtn primary" href="syl_auth.php?tab=register" style="text-decoration:none;">Sign Up</a>
    <?php endif; ?>
  </div>

  <!-- Flash notice -->
  <?php if (!empty($_SESSION['flash_notice'])): ?>
  <div style="background:var(--card);border-left:4px solid var(--teal);color:var(--text);font-size:.85rem;padding:12px 40px;margin:0;">
    <?= htmlspecialchars($_SESSION['flash_notice'], ENT_QUOTES, 'UTF-8') ?>
    <?php unset($_SESSION['flash_notice']); ?>
  </div>
  <?php endif; ?>

  <!-- ══════════════════════════════════
       SONGS PAGE
  ══════════════════════════════════ -->
  <div class="songs-wrap">

    <!-- Header -->
    <div class="songs-header">
      <h1 class="songs-title">&#127925; Songs</h1>
      <p class="songs-sub">Browse every song SYL members have reviewed. Search by title, artist, or album &mdash; then add your own.</p>
    </div>

    <!-- Search bar -->
    <div class="songs-search-row">
      <div class="songs-search-wrap">
        <span class="songs-search-icon">&#128269;</span>
        <input class="songs-search" id="songsSearch" type="text"
               placeholder="Search songs, artists, albums&hellip;"
               oninput="filterAndRender()">
      </div>
    </div>

    <!-- Sort filters + Add Song button -->
    <div class="songs-controls">
      <div class="songs-filter-group">
        <span class="filter-label">Sort by:</span>
        <button class="spill active" id="pill-az"     onclick="setSort('az')">A &ndash; Z</button>
        <button class="spill"        id="pill-album"  onclick="setSort('album')">Album</button>
        <button class="spill"        id="pill-artist" onclick="setSort('artist')">Artist</button>
      </div>

      <!-- Add Song — changes based on login state -->
      <div id="add-song-area">
        <!-- Populated by JS based on isLoggedIn -->
      </div>
    </div>

    <!-- Result count line -->
    <div class="songs-count" id="songsCount"></div>

    <!-- Song cards grid — filled by JS -->
    <div class="songs-grid" id="songsGrid"></div>

  </div>
  <footer>SYL &mdash; Save Your Listens &middot; &copy; 2026</footer>
</div>


<!-- ADD SONG MODAL — POSTs to syl_songs.php with action=add_song -->
<div class="modal-overlay" id="addModal" onclick="closeIfOverlay(event,'addModal')">
  <div class="add-modal">
    <button class="modal-close" onclick="closeModal('addModal')">&#10005;</button>
    <div class="add-modal-title">&#43; Add a Song</div>
    <form method="post" action="syl_songs.php" id="addSongForm">
      <input type="hidden" name="action" value="add_song">
      <input type="hidden" name="rating" id="rating-value" value="1">
      <div class="form-group">
        <label class="form-label">Song Name <span style="color:var(--pink)">*</span></label>
        <input class="form-input" name="song_name" type="text" placeholder="e.g. Blinding Lights" required>
      </div>
      <div class="form-group">
        <label class="form-label">Artist <span style="color:var(--pink)">*</span></label>
        <input class="form-input" name="artist" type="text" placeholder="e.g. The Weeknd" required>
      </div>
      <div class="form-group">
        <label class="form-label">Album</label>
        <input class="form-input" name="album" type="text" placeholder="e.g. After Hours">
      </div>
      <div class="form-group">
        <label class="form-label">Duration <span style="color:var(--muted);font-size:.7rem;text-transform:none;letter-spacing:0">(min:sec)</span></label>
        <input class="form-input" name="duration" type="text" placeholder="3:22">
      </div>
      <div class="form-group">
        <label class="form-label">Rating (1&ndash;10)</label>
        <div class="star-row" id="starRow"></div>
        <div class="rating-hint" id="ratingHint">Click a star to rate</div>
      </div>
      <div class="form-group">
        <label class="form-label">Your Review</label>
        <input class="form-input" name="notes" type="text" placeholder="What did you think?">
      </div>
      <button type="button" class="form-submit" onclick="validateAndSubmit()">Save Song &rarr;</button>
    </form>
  </div>
</div>


<script>
// ─── AUTH STATE (from PHP session) ───────────────────────────────────────────
const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

// ─── SONGS CATALOG (PHP-injected from DB) ────────────────────────────────────
const SONGS_CATALOG = <?= json_encode($songsCatalog, JSON_HEX_TAG|JSON_HEX_QUOT) ?>;

// ─── HELPERS ─────────────────────────────────────────────────────────────────
const EMOJIS = ['🎵','🎶','🎸','🎹','🎺','🎻','🥁','🎷','🎼','🎤','🎧','🪗'];
const getEmoji = id => EMOJIS[id % EMOJIS.length];

function ratingClass(r) {
  if (r <= 4) return 'rating-low';
  if (r <= 6) return 'rating-mid';
  if (r <= 8) return 'rating-good';
  return 'rating-great';
}

function formatDur(s) {
  if (!s) return '—';
  return Math.floor(s/60) + ':' + String(s%60).padStart(2,'0');
}

// ─── SORT & FILTER ────────────────────────────────────────────────────────────
let activeSort  = 'az';
let searchQuery = '';

function setSort(mode) {
  activeSort = mode;
  ['az','album','artist'].forEach(m =>
    document.getElementById('pill-' + m).classList.toggle('active', m === mode)
  );
  filterAndRender();
}

function filterAndRender() {
  searchQuery = document.getElementById('songsSearch').value.toLowerCase().trim();
  renderSongs();
}

// ─── RENDER SONGS ─────────────────────────────────────────────────────────────
function renderSongs() {
  const grid  = document.getElementById('songsGrid');
  const count = document.getElementById('songsCount');

  let results = SONGS_CATALOG.filter(s => {
    if (!searchQuery) return true;
    return [s.song_name, s.artist_name, s.album_name, s.review || '']
      .join(' ').toLowerCase().includes(searchQuery);
  });

  if (activeSort === 'az') {
    results.sort((a,b) => a.song_name.localeCompare(b.song_name));
  } else if (activeSort === 'album') {
    results.sort((a,b) => (a.album_name||'').localeCompare(b.album_name||'') || a.song_name.localeCompare(b.song_name));
  } else {
    results.sort((a,b) => a.artist_name.localeCompare(b.artist_name) || a.song_name.localeCompare(b.song_name));
  }

  count.innerHTML = results.length === 0
    ? 'No results found'
    : 'Showing <strong>' + results.length + '</strong> of <strong>' + SONGS_CATALOG.length + '</strong> songs';

  if (results.length === 0) {
    grid.innerHTML = '<div class="songs-empty" style="grid-column:1/-1"><div class="songs-empty-icon">&#127925;</div><div class="songs-empty-title">No songs found</div><div class="songs-empty-sub">Try a different search, or add this song!</div></div>';
    return;
  }

  let html = '';
  let lastGroup = null;

  results.forEach((s, idx) => {
    const groupKey = activeSort === 'az'
      ? s.song_name.charAt(0).toUpperCase()
      : activeSort === 'album' ? (s.album_name || 'Unknown Album')
      : s.artist_name;

    if (groupKey !== lastGroup) {
      html += '<div class="songs-divider" style="grid-column:1/-1"><div class="songs-divider-label">' + groupKey + '</div><div class="songs-divider-line"></div></div>';
      lastGroup = groupKey;
    }

    const emoji = getEmoji(s.id || idx);
    const rc = ratingClass(s.rating).replace('rating-','');
    const thumbColors = {low:'#F0629215',mid:'#FF8A5015',good:'#F5E64215',great:'#69D17E15'};
    const reviewHtml = s.review ? '<div class="sc-review">&ldquo;' + s.review + '&rdquo;</div>' : '';

    html += '<a class="song-card" href="syl_song.php?id=' + s.id + '" style="text-decoration:none;display:flex;flex-direction:column;gap:0;">' +
      '<div class="sc-top">' +
        '<div class="sc-thumb" style="background:' + (thumbColors[rc]||thumbColors.good) + ';">' + emoji + '</div>' +
        '<div class="sc-info">' +
          '<div class="sc-title">' + s.song_name + '</div>' +
          '<div class="sc-artist">' + s.artist_name + '</div>' +
          '<div class="sc-album">&#127894; ' + s.album_name + '</div>' +
        '</div>' +
        '<div class="sc-rating-block">' +
          '<div class="sc-rating ' + ratingClass(s.rating) + '">&#9733;&thinsp;' + s.rating + '</div>' +
          '<div class="sc-rating-sub">/ 10</div>' +
          '<div class="sc-dur">' + formatDur(s.duration) + '</div>' +
        '</div>' +
      '</div>' +
      reviewHtml +
    '</a>';
  });

  grid.innerHTML = html;
}

// ─── ADD SONG BUTTON ──────────────────────────────────────────────────────────
function renderAddButton() {
  const area = document.getElementById('add-song-area');
  if (!area) return;
  area.innerHTML = '';
  if (isLoggedIn) {
    const btn = document.createElement('button');
    btn.className = 'songs-add-btn';
    btn.innerHTML = '&#43; Add Song';
    btn.onclick = function() { openModal('addModal'); };
    area.appendChild(btn);
  } else {
    const a = document.createElement('a');
    a.className = 'songs-guest-note';
    a.href = 'syl_auth.php?tab=register';
    a.style.textDecoration = 'none';
    a.innerHTML = '&#128274; Sign up to add songs';
    area.appendChild(a);
  }
}

// ─── MODAL ────────────────────────────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); buildStars(); }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}
function closeIfOverlay(e, id) {
  if (e.target === document.getElementById(id)) closeModal(id);
}

// ─── STAR RATING ──────────────────────────────────────────────────────────────
let currentRating = 0;

function buildStars() {
  const row = document.getElementById('starRow');
  if (!row || row.children.length === 10) return;
  row.innerHTML = '';
  for (let i = 1; i <= 10; i++) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'star-btn';
    btn.textContent = '★';
    btn.onclick = (function(n){ return function(){ currentRating = n; updateStars(); }; })(i);
    row.appendChild(btn);
  }
}

function updateStars() {
  document.querySelectorAll('#starRow .star-btn').forEach((s,i) =>
    s.classList.toggle('lit', i < currentRating)
  );
  const hints = ['','1 — Painful','2 — Very Bad','3 — Bad','4 — Below Average',
                  '5 — Average','6 — Decent','7 — Good','8 — Great',
                  '9 — Excellent','10 — Perfect'];
  const hint = document.getElementById('ratingHint');
  if (hint) hint.textContent = currentRating > 0 ? hints[currentRating] : 'Click a star to rate';
}

function validateAndSubmit() {
  if (!currentRating) { alert('\u26a0 Please give the song a rating.'); return; }

  const songName   = document.querySelector('#addSongForm [name="song_name"]').value.trim().toLowerCase();
  const artistName = document.querySelector('#addSongForm [name="artist"]').value.trim().toLowerCase();

  if (songName && artistName) {
    const exists = SONGS_CATALOG.find(s =>
      s.song_name.toLowerCase() === songName &&
      s.artist_name.toLowerCase() === artistName
    );
    if (exists) {
      const goView = confirm(
        '\u26a0 This song already exists in the catalog!\n\n' +
        'Click OK to view the existing reviews, or Cancel to add your own review anyway.'
      );
      if (goView) { window.location.href = 'syl_song.php?id=' + exists.id; return; }
    }
  }

  document.getElementById('rating-value').value = currentRating;
  document.getElementById('addSongForm').submit();
}

// ─── SIDEBAR ──────────────────────────────────────────────────────────────────
let sbOpen = true;
function toggleSidebar() {
  sbOpen = !sbOpen;
  document.getElementById('sidebar').classList.toggle('col', !sbOpen);
  document.body.classList.toggle('sb-col', !sbOpen);
}

// ─── INIT (wait for DOM) ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  renderAddButton();
  buildStars();
  renderSongs();
});
</script>
</body>
</html>
