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
// ─── Live stats ───────────────────────────────────────────────────────────────
$totalReviews = 0; $totalMembers = 0; $todayReviews = 0;
try {
    $totalReviews = $pdo->query("SELECT COUNT(*) FROM Reviews")->fetchColumn();
    $totalMembers = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn();
    $todayReviews = $pdo->query("SELECT COUNT(*) FROM Reviews WHERE DATE(review_date)=CURDATE()")->fetchColumn();
} catch (Exception $e) {}

// ─── Feed — recent or top rated ───────────────────────────────────────────────
// Data injected as JSON for JS toggle (no page reload needed for feed switch)
$feedQuery = "
    SELECT r.song_name, r.artist_name, r.album_name, r.rating, r.review, r.review_date,
           u.tag, u.first_name
    FROM Reviews r
    JOIN UserReviews ur ON r.id = ur.review_id
    JOIN Users u ON ur.user_tag = u.tag
";
$recentFeed = [];
$topFeed    = [];
try {
    $recentFeed = $pdo->query($feedQuery." ORDER BY r.review_date DESC LIMIT 30")->fetchAll();
    $topFeed    = $pdo->query($feedQuery." ORDER BY r.rating DESC, r.review_date DESC LIMIT 30")->fetchAll();
} catch (Exception $e) {}

// ─── Widgets ──────────────────────────────────────────────────────────────────
$topSongs = [];
try {
    $topSongs = $pdo->query("
        SELECT song_name, artist_name, ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS cnt
        FROM Reviews GROUP BY song_name, artist_name
        ORDER BY avg_rating DESC, cnt DESC LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {}

$topReviewers = [];
try {
    $topReviewers = $pdo->query("
        SELECT u.tag, u.first_name, COUNT(ur.review_id) AS cnt
        FROM Users u JOIN UserReviews ur ON u.tag=ur.user_tag
        GROUP BY u.tag, u.first_name ORDER BY cnt DESC LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {}

// Build JS-ready arrays
$avatarEmojis = ['&#127911;','&#127928;','&#128133;','&#127908;','&#127864;','&#127769;','&#128367;','&#127754;','&#127752;','&#128153;'];
$avatarColors = ['#69D17E','#F06292','#B39DDB','#F5E642','#4DD0C4','#5B9BF5','#FF8A50','#9C6FE4','#4DD0C4','#F06292'];

function buildFeedJs($rows, $avatarEmojis, $avatarColors) {
    return array_map(function($r) use ($avatarEmojis, $avatarColors) {
        static $idx = 0; $i = $idx++ % count($avatarEmojis);
        $diff = time() - strtotime($r['review_date']);
        $time = $diff < 3600 ? floor($diff/60).'m ago' : ($diff < 86400 ? floor($diff/3600).'h ago' : ($diff < 604800 ? floor($diff/86400).'d ago' : date('M j', strtotime($r['review_date']))));
        return ['tag'=>$r['tag'],'emoji'=>$avatarEmojis[$i],'color'=>$avatarColors[$i],
                'song'=>$r['song_name'],'artist'=>$r['artist_name'],'album'=>$r['album_name']??'',
                'rating'=>(int)$r['rating'],'review'=>$r['review']??'','time'=>$time];
    }, $rows);
}

$feedDataJs = json_encode([
    'recent' => buildFeedJs($recentFeed, $avatarEmojis, $avatarColors),
    'top'    => buildFeedJs($topFeed,    $avatarEmojis, $avatarColors),
], JSON_HEX_TAG|JSON_HEX_QUOT);

$topSongsJs = json_encode(array_map(fn($s)=>[
    'name'=>$s['song_name'],'artist'=>$s['artist_name'],'avg'=>$s['avg_rating']
], $topSongs), JSON_HEX_TAG|JSON_HEX_QUOT);

$topReviewersJs = json_encode(array_map(function($r) use ($avatarEmojis,$avatarColors) {
    static $idx=0; $i=$idx++%count($avatarEmojis);
    return ['tag'=>$r['tag'],'name'=>$r['first_name']?:$r['tag'],'count'=>(int)$r['cnt'],'emoji'=>$avatarEmojis[$i],'color'=>$avatarColors[$i]];
}, $topReviewers), JSON_HEX_TAG|JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL — Community</title>
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

/* ─── COMMUNITY LAYOUT ─── */
.page-wrap{padding:40px;max-width:1060px;margin:0 auto;}
.page-header{margin-bottom:36px;}
.page-title{font-family:'Dela Gothic One',sans-serif;font-size:2.2rem;margin-bottom:6px;}
.page-sub{font-size:.9rem;color:var(--muted);line-height:1.5;}

/* ─── STATS BAR ─── */
/* Counts pulled from DB: total reviews, total users, reviews today
   In PHP:
     SELECT COUNT(*) FROM Reviews                    → total_reviews
     SELECT COUNT(*) FROM Users                      → total_members
     SELECT COUNT(*) FROM Reviews WHERE DATE(review_date) = CURDATE() → today */
.stats-bar{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:40px;}
.stat-card{background:var(--card);border-radius:14px;padding:22px 24px;border:1px solid #ffffff08;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.stat-card.sy::before{background:var(--yellow);}
.stat-card.st::before{background:var(--teal);}
.stat-card.sp::before{background:var(--pink);}
.stat-num{font-family:'Dela Gothic One',sans-serif;font-size:2.2rem;margin-bottom:4px;}
.stat-card.sy .stat-num{color:var(--yellow);}
.stat-card.st .stat-num{color:var(--teal);}
.stat-card.sp .stat-num{color:var(--pink);}
.stat-lbl{font-size:.82rem;color:var(--muted);}

/* ─── FILTER PILLS ─── */
.filter-row{display:flex;align-items:center;gap:8px;margin-bottom:24px;flex-wrap:wrap;}
.filter-lbl{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:2px;color:var(--muted);text-transform:uppercase;margin-right:4px;}
.fpill{padding:7px 18px;border-radius:100px;border:2px solid #ffffff15;background:none;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .15s;}
.fpill:hover{border-color:var(--yellow);color:var(--yellow);}
.fpill.active{background:var(--yellow);border-color:var(--yellow);color:var(--dark);}

/* ─── MAIN CONTENT GRID ─── */
.community-layout{display:grid;grid-template-columns:1fr 280px;gap:28px;align-items:start;}

/* ─── REVIEW FEED ─── */
/* In PHP: SELECT r.*, u.tag, u.first_name
           FROM Reviews r
           JOIN UserReviews ur ON r.id = ur.review_id
           JOIN Users u ON ur.user_tag = u.tag
           ORDER BY r.review_date DESC / r.rating DESC
           LIMIT 30 */
.feed{display:flex;flex-direction:column;gap:10px;}
.feed-card{background:var(--card);border-radius:14px;padding:18px 20px;border:1px solid #ffffff08;transition:border-color .2s,transform .15s;}
.feed-card:hover{border-color:#ffffff15;transform:translateY(-1px);}
.fc-header{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
.fc-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.fc-user-info{flex:1;min-width:0;}
.fc-username{font-family:'Space Mono',monospace;font-size:.72rem;color:var(--teal);font-weight:700;}
.fc-time{font-family:'Space Mono',monospace;font-size:.62rem;color:var(--muted);}
.fc-rating{font-family:'Dela Gothic One',sans-serif;font-size:1.1rem;color:var(--yellow);flex-shrink:0;}
.fc-song{font-family:'Dela Gothic One',sans-serif;font-size:1rem;margin-bottom:3px;}
.fc-artist{font-size:.8rem;color:var(--muted);margin-bottom:3px;}
.fc-album{font-family:'Space Mono',monospace;font-size:.68rem;color:var(--muted);}
.fc-review{margin-top:10px;font-size:.84rem;color:var(--text);line-height:1.55;font-style:italic;border-top:1px solid #ffffff08;padding-top:10px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}

/* ─── SIDEBAR WIDGETS ─── */
.sidebar-widgets{display:flex;flex-direction:column;gap:16px;position:sticky;top:80px;}
.widget{background:var(--card);border-radius:14px;padding:18px 20px;border:1px solid #ffffff08;}
.widget-title{font-family:'Dela Gothic One',sans-serif;font-size:.95rem;margin-bottom:14px;color:var(--text);}
/* Top rated songs widget
   In PHP: SELECT song_name, artist_name, ROUND(AVG(rating),1) AS avg, COUNT(*) AS cnt
           FROM Reviews GROUP BY song_name, artist_name
           ORDER BY avg DESC, cnt DESC LIMIT 5 */
.top-song{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #ffffff08;}
.top-song:last-child{border-bottom:none;}
.ts-rank{font-family:'Space Mono',monospace;font-size:.65rem;color:var(--muted);min-width:18px;}
.ts-info{flex:1;min-width:0;}
.ts-name{font-size:.85rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ts-artist{font-size:.72rem;color:var(--muted);}
.ts-rating{font-family:'Space Mono',monospace;font-size:.72rem;color:var(--yellow);flex-shrink:0;}
/* Top reviewers widget
   In PHP: SELECT u.tag, u.first_name, COUNT(ur.review_id) AS cnt
           FROM Users u JOIN UserReviews ur ON u.tag=ur.user_tag
           GROUP BY u.tag ORDER BY cnt DESC LIMIT 5 */
.top-reviewer{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #ffffff08;}
.top-reviewer:last-child{border-bottom:none;}
.tr-avatar{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.tr-info{flex:1;min-width:0;}
.tr-name{font-size:.82rem;font-weight:600;}
.tr-tag{font-family:'Space Mono',monospace;font-size:.65rem;color:var(--muted);}
.tr-count{font-family:'Space Mono',monospace;font-size:.68rem;color:var(--teal);flex-shrink:0;}

.sec-label{font-family:'Space Mono',monospace;font-size:.68rem;letter-spacing:3px;color:var(--muted);text-transform:uppercase;margin-bottom:14px;}

footer{text-align:center;padding:32px 40px;color:var(--muted);font-size:.75rem;font-family:'Space Mono',monospace;border-top:1px solid #ffffff08;}

@media(max-width:900px){.community-layout{grid-template-columns:1fr;}.sidebar-widgets{position:static;}.stats-bar{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){#main{margin-left:var(--sidebar-w-col);}#sidebar{width:var(--sidebar-w-col);}.sb-label{opacity:0;}.sb-logo{opacity:0;pointer-events:none;}body:not(.sb-col) #sb-toggle-btn{left:14px;}.page-wrap{padding:20px;}.stats-bar{grid-template-columns:1fr;}}
</style>
</head>
<body>

<button id="sb-toggle-btn" onclick="toggleSidebar()">&#9776;</button>

<aside id="sidebar">
  <div class="sb-top"><a class="sb-logo" href="syl_home.php" style="text-decoration:none;">SYL</a></div>
  <nav class="sb-nav">
    <a class="sb-link "      href="syl_home.php"      data-label="home">      <span class="sb-icon">&#127968;</span><span class="sb-label">Home</span></a>
    <a class="sb-link "     href="syl_songs.php"     data-label="songs">     <span class="sb-icon">&#127925;</span><span class="sb-label">Songs</span></a>
    <a class="sb-link" href="syl_friends.php"   data-label="friends">   <span class="sb-icon">&#128101;</span><span class="sb-label">Friends</span></a>

    <a class="sb-link active" href="syl_community.php" data-label="community"><span class="sb-icon">&#127758;</span><span class="sb-label">Community</span></a>
  </nav>
  <div class="sb-bottom">
    <a class="sb-link "  href="syl_settings.php"  data-label="settings">  <span class="sb-icon">&#9881;&#65039;</span><span class="sb-label">Settings</span></a>
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

    <div class="page-header">
      <h1 class="page-title">&#127758; Community</h1>
      <p class="page-sub">See what every SYL member is listening to. Browse recent reviews, top-rated songs, and the most active listeners.</p>
    </div>

    <!-- Live stats from DB -->
    <div class="stats-bar">
      <div class="stat-card sy"><div class="stat-num" id="stat-reviews">—</div><div class="stat-lbl">Total reviews</div></div>
      <div class="stat-card st"><div class="stat-num" id="stat-members">—</div><div class="stat-lbl">Members</div></div>
      <div class="stat-card sp"><div class="stat-num" id="stat-today">—</div><div class="stat-lbl">Reviews today</div></div>
    </div>

    <!-- Filter pills -->
    <div class="filter-row">
      <span class="filter-lbl">Show:</span>
      <button class="fpill active" id="fp-recent" onclick="setFeed('recent')">Most Recent</button>
      <button class="fpill"        id="fp-top"    onclick="setFeed('top')">Top Rated</button>
    </div>

    <div class="community-layout">

      <!-- Main feed -->
      <div>
        <div class="sec-label" id="feed-label">Recent Reviews</div>
        <div class="feed" id="communityFeed"></div>
      </div>

      <!-- Sidebar widgets -->
      <div class="sidebar-widgets">
        <div class="widget">
          <div class="widget-title">&#128293; Top Rated Songs</div>
          <div id="topSongs"></div>
        </div>
        <div class="widget">
          <div class="widget-title">&#127881; Most Active</div>
          <div id="topReviewers"></div>
        </div>
      </div>

    </div>
  </div>
  <footer>SYL &mdash; Save Your Listens &middot; &copy; 2026</footer>
</div>

<script>
/* ── Sample data — in PHP all of this comes from DB queries ──
   Reviews JOIN UserReviews JOIN Users, ORDER BY review_date DESC / rating DESC */
const FEED_DATA = <?= $feedDataJs ?>;

const TOP_SONGS = <?= $topSongsJs ?>;

const TOP_REVIEWERS = <?= $topReviewersJs ?>;

let activeFeed = 'recent';

function setFeed(mode) {
  activeFeed = mode;
  document.getElementById('fp-recent').classList.toggle('active', mode === 'recent');
  document.getElementById('fp-top').classList.toggle('active', mode === 'top');
  document.getElementById('feed-label').textContent = mode === 'recent' ? 'Recent Reviews' : 'Top Rated Reviews';
  renderFeed();
}

function renderFeed() {
  document.getElementById('communityFeed').innerHTML = FEED_DATA[activeFeed].map(r => `
    <div class="feed-card">
      <div class="fc-header">
        <div class="fc-avatar" style="background:${r.color}22;border:2px solid ${r.color}44;">${r.emoji}</div>
        <div class="fc-user-info">
          <div class="fc-username">@${r.tag}</div>
          <div class="fc-time">${r.time}</div>
        </div>
        <div class="fc-rating">&#9733; ${r.rating}</div>
      </div>
      <div class="fc-song">${r.song}</div>
      <div class="fc-artist">${r.artist}</div>
      <div class="fc-album">&#127894; ${r.album}</div>
      ${r.review ? `<div class="fc-review">&ldquo;${r.review}&rdquo;</div>` : ''}
    </div>`).join('');
}

function renderWidgets() {
  // Stats — in PHP these are real COUNT queries
  document.getElementById('stat-reviews').textContent = FEED_DATA.recent.length + '+';
  document.getElementById('stat-members').textContent = '<?= (int)$totalMembers ?>';
  document.getElementById('stat-today').textContent   = '<?= (int)$todayReviews ?>';

  document.getElementById('topSongs').innerHTML = TOP_SONGS.map((s, i) => `
    <div class="top-song">
      <span class="ts-rank">#${i + 1}</span>
      <div class="ts-info">
        <div class="ts-name">${s.name}</div>
        <div class="ts-artist">${s.artist}</div>
      </div>
      <span class="ts-rating">&#9733; ${s.avg}</span>
    </div>`).join('');

  document.getElementById('topReviewers').innerHTML = TOP_REVIEWERS.map(r => `
    <div class="top-reviewer">
      <div class="tr-avatar" style="background:${r.color}22;border:1.5px solid ${r.color}44;">${r.emoji}</div>
      <div class="tr-info">
        <div class="tr-name">${r.name}</div>
        <div class="tr-tag">@${r.tag}</div>
      </div>
      <span class="tr-count">${r.count} reviews</span>
    </div>`).join('');
}

let sbOpen = true;
function toggleSidebar() {
  sbOpen = !sbOpen;
  document.getElementById('sidebar').classList.toggle('col', !sbOpen);
  document.body.classList.toggle('sb-col', !sbOpen);
}

renderFeed();
renderWidgets();
</script>
</body>
</html>
