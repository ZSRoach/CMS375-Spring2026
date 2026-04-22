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
// ─── All members with review counts ──────────────────────────────────────────
// LEFT JOIN so users with 0 reviews still appear.
// Ordered by review_count DESC so most active users show first.
$members = [];
try {
    $stmt = $pdo->query("
        SELECT u.tag, u.first_name, u.last_name, u.bio,
               COUNT(ur.review_id)     AS review_count,
               ROUND(AVG(r.rating),1)  AS avg_rating
        FROM Users u
        LEFT JOIN UserReviews ur ON u.tag = ur.user_tag
        LEFT JOIN Reviews r      ON ur.review_id = r.id
        GROUP BY u.tag, u.first_name, u.last_name, u.bio
        ORDER BY review_count DESC
    ");
    $members = $stmt->fetchAll();
} catch (Exception $e) {}

// ─── Recent activity — 20 most recent reviews with reviewer info ──────────────
$activity = [];
try {
    $stmt = $pdo->query("
        SELECT r.song_name, r.artist_name, r.rating, r.review, r.review_date,
               u.tag, u.first_name
        FROM Reviews r
        JOIN UserReviews ur ON r.id = ur.review_id
        JOIN Users u        ON ur.user_tag = u.tag
        ORDER BY r.review_date DESC LIMIT 20
    ");
    $activity = $stmt->fetchAll();
} catch (Exception $e) {}

// Convert DB rows to JS-compatible arrays for the render functions
$membersJs  = json_encode(array_map(function($m) {
    $avatarEmojis = ['&#127911;','&#127928;','&#128133;','&#127908;','&#127864;','&#127769;','&#128367;','&#127754;'];
    $avatarColors = ['#69D17E','#F06292','#B39DDB','#F5E642','#4DD0C4','#5B9BF5','#FF8A50','#9C6FE4'];
    static $idx = 0;
    $i = $idx++ % 8;
    return [
        'tag'     => $m['tag'],
        'name'    => trim(($m['first_name']??'') . ' ' . ($m['last_name']??'')) ?: $m['tag'],
        'bio'     => $m['bio'] ?: 'No bio yet.',
        'reviews' => (int)$m['review_count'],
        'avg'     => $m['avg_rating'] ? (float)$m['avg_rating'] : null,
        'emoji'   => $avatarEmojis[$i],
        'color'   => $avatarColors[$i],
    ];
}, $members), JSON_HEX_TAG|JSON_HEX_QUOT);

$activityJs = json_encode(array_map(function($a) {
    $avatarEmojis = ['&#127911;','&#127928;','&#128133;','&#127908;','&#127864;','&#127769;','&#128367;','&#127754;'];
    $avatarColors = ['#69D17E','#F06292','#B39DDB','#F5E642','#4DD0C4','#5B9BF5','#FF8A50','#9C6FE4'];
    static $idx = 0;
    $i = $idx++ % 8;
    $diff = time() - strtotime($a['review_date']);
    if ($diff < 3600)   $time = floor($diff/60).'m ago';
    elseif ($diff < 86400)  $time = floor($diff/3600).'h ago';
    elseif ($diff < 604800) $time = floor($diff/86400).'d ago';
    else $time = date('M j', strtotime($a['review_date']));
    return [
        'tag'     => $a['tag'],
        'emoji'   => $avatarEmojis[$i],
        'color'   => $avatarColors[$i],
        'song'    => $a['song_name'],
        'artist'  => $a['artist_name'],
        'rating'  => (int)$a['rating'],
        'review'  => $a['review'] ?: '',
        'time'    => $time,
    ];
}, $activity), JSON_HEX_TAG|JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL — Friends</title>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
/* ─── TOKENS ─── */
:root{--yellow:#F5E642;--pink:#F06292;--teal:#4DD0C4;--lavender:#B39DDB;--green:#69D17E;--orange:#FF8A50;--blue:#5B9BF5;--purple:#9C6FE4;--sand:#E8E0D0;--dark:#1A1A2E;--darker:#0F0F1E;--card:#22223B;--text:#F0EDE6;--muted:#8B8BAA;--sidebar-w:220px;--sidebar-w-col:64px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--darker);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

/* ─── SIDEBAR ─── */
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

/* ─── PAGE WRAP ─── */
.page-wrap{padding:40px;max-width:1060px;margin:0 auto;}

/* ─── PAGE HEADER ─── */
.page-header{margin-bottom:40px;}
.page-title{font-family:'Dela Gothic One',sans-serif;font-size:2.2rem;margin-bottom:6px;}
.page-sub{font-size:.9rem;color:var(--muted);line-height:1.5;}

/* ─── SEARCH BAR ─── */
.search-wrap{position:relative;margin-bottom:32px;}
.search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.95rem;pointer-events:none;}
.search-input{width:100%;padding:13px 16px 13px 42px;background:var(--card);border:2px solid #ffffff10;border-radius:14px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;transition:border-color .2s;}
.search-input:focus{border-color:var(--yellow);}
.search-input::placeholder{color:var(--muted);}

/* ─── COMING SOON BANNER ─── */
.coming-soon-banner{
  background:var(--card);border-radius:16px;padding:20px 24px;
  border-left:4px solid var(--teal);
  display:flex;align-items:center;gap:14px;
  margin-bottom:36px;
}
.csb-icon{font-size:1.4rem;}
.csb-text{font-size:.88rem;color:var(--muted);line-height:1.5;}
.csb-text strong{color:var(--text);}
.csb-badge{margin-left:auto;padding:5px 14px;border-radius:100px;border:1px solid var(--teal);color:var(--teal);font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:1px;white-space:nowrap;}

/* ─── SECTION LABEL ─── */
.sec-label{font-family:'Space Mono',monospace;font-size:.68rem;letter-spacing:3px;color:var(--muted);text-transform:uppercase;margin-bottom:16px;}

/* ─── USER CARDS GRID ─── */
/* Friends are pulled from Users table — username, first_name, last_name, bio, review count
   In PHP: SELECT u.tag, u.first_name, u.last_name, u.bio, COUNT(ur.review_id) AS review_count
           FROM Users u LEFT JOIN UserReviews ur ON u.tag = ur.user_tag
           GROUP BY u.tag ORDER BY review_count DESC */
.users-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:40px;}
.user-card{background:var(--card);border-radius:14px;padding:18px 20px;border:1px solid #ffffff08;display:flex;align-items:center;gap:14px;transition:border-color .2s,transform .2s;}
.user-card:hover{border-color:#ffffff1a;transform:translateY(-2px);}
.uc-avatar{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;border:2px solid #ffffff10;}
.uc-info{flex:1;min-width:0;}
.uc-name{font-family:'Dela Gothic One',sans-serif;font-size:.95rem;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.uc-handle{font-family:'Space Mono',monospace;font-size:.68rem;color:var(--teal);margin-bottom:4px;}
.uc-bio{font-size:.78rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.uc-stats{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;}
.uc-count{font-family:'Dela Gothic One',sans-serif;font-size:1.1rem;color:var(--yellow);}
.uc-count-lbl{font-family:'Space Mono',monospace;font-size:.6rem;color:var(--muted);}
.uc-add-btn{padding:5px 14px;border-radius:100px;border:2px solid #ffffff15;background:none;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s;margin-top:4px;}
.uc-add-btn:hover{border-color:var(--green);color:var(--green);}
.uc-add-btn.following{border-color:var(--green);color:var(--green);background:#69D17E18;}

/* ─── ACTIVITY FEED ─── */
/* In PHP: SELECT r.song_name, r.artist_name, r.rating, r.review, r.review_date,
                  u.tag, u.first_name
           FROM Reviews r
           JOIN UserReviews ur ON r.id = ur.review_id
           JOIN Users u ON ur.user_tag = u.tag
           ORDER BY r.review_date DESC LIMIT 20 */
.activity-feed{display:flex;flex-direction:column;gap:10px;}
.activity-item{background:var(--card);border-radius:14px;padding:16px 20px;border:1px solid #ffffff08;display:flex;gap:14px;align-items:flex-start;transition:border-color .2s;}
.activity-item:hover{border-color:#ffffff12;}
.ai-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.ai-body{flex:1;min-width:0;}
.ai-header{display:flex;align-items:baseline;gap:6px;margin-bottom:4px;flex-wrap:wrap;}
.ai-user{font-family:'Space Mono',monospace;font-size:.72rem;color:var(--teal);font-weight:700;}
.ai-action{font-size:.78rem;color:var(--muted);}
.ai-song{font-weight:700;color:var(--text);font-size:.88rem;}
.ai-artist{font-size:.78rem;color:var(--muted);}
.ai-review{font-size:.8rem;color:var(--text);font-style:italic;margin-top:6px;line-height:1.5;opacity:.85;}
.ai-meta{display:flex;align-items:center;gap:10px;margin-top:8px;}
.ai-rating{font-family:'Space Mono',monospace;font-size:.72rem;color:var(--yellow);}
.ai-time{font-family:'Space Mono',monospace;font-size:.65rem;color:var(--muted);}

/* ─── EMPTY STATE ─── */
.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
.empty-icon{font-size:3rem;margin-bottom:16px;}
.empty-title{font-family:'Dela Gothic One',sans-serif;font-size:1.4rem;color:var(--text);margin-bottom:8px;}
.empty-sub{font-size:.88rem;line-height:1.6;max-width:300px;margin:0 auto;}

footer{text-align:center;padding:32px 40px;color:var(--muted);font-size:.75rem;font-family:'Space Mono',monospace;border-top:1px solid #ffffff08;}

@media(max-width:768px){.users-grid{grid-template-columns:1fr;}.page-wrap{padding:20px;}}
@media(max-width:600px){#main{margin-left:var(--sidebar-w-col);}#sidebar{width:var(--sidebar-w-col);}.sb-label{opacity:0;}.sb-logo{opacity:0;pointer-events:none;}body:not(.sb-col) #sb-toggle-btn{left:14px;}}
</style>
</head>
<body>

<button id="sb-toggle-btn" onclick="toggleSidebar()" title="Toggle sidebar">&#9776;</button>

<aside id="sidebar">
  <div class="sb-top"><a class="sb-logo" href="syl_home.php" style="text-decoration:none;">SYL</a></div>
  <nav class="sb-nav">
    <a class="sb-link "      href="syl_home.php"      data-label="home">      <span class="sb-icon">&#127968;</span><span class="sb-label">Home</span></a>
    <a class="sb-link "     href="syl_songs.php"     data-label="songs">     <span class="sb-icon">&#127925;</span><span class="sb-label">Songs</span></a>
    <a class="sb-link active"   href="syl_friends.php"   data-label="friends">   <span class="sb-icon">&#128101;</span><span class="sb-label">Friends</span></a>
    <a class="sb-link " href="syl_community.php" data-label="community"><span class="sb-icon">&#127758;</span><span class="sb-label">Community</span></a>
  </nav>
  <div class="sb-bottom">
    <a class="sb-link "  href="syl_settings.php"  data-label="settings">  <span class="sb-icon">&#9881;&#65039;</span><span class="sb-label">Settings</span></a>
  </div>
</aside>

<div id="main">
  <div class="top-nav">
    <span class="nav-logo">SYL</span>
    <?php if ($isLoggedIn): ?>
      <div class="user-chip"><div class="user-av">&#127911;</div><span>@<?= h($userTag) ?></span></div>
      <a class="nbtn out" href="syl_auth.php?action=logout" style="text-decoration:none;">Log Out</a>
    <?php else: ?>
      <a class="nbtn ghost"   href="syl_auth.php?tab=login"    style="text-decoration:none;">Log In</a>
      <a class="nbtn primary" href="syl_auth.php?tab=register" style="text-decoration:none;">Sign Up</a>
    <?php endif; ?>
  </div>

  <div class="page-wrap">

    <div class="page-header">
      <h1 class="page-title">&#128101; Friends</h1>
      <p class="page-sub">See what other SYL members are listening to and find people with similar taste.</p>
    </div>

    <!-- Coming soon notice — no follows table in DB yet -->
    <!-- In PHP: once a UserFollows table is added, this banner is removed
         and the follow buttons will POST to SYL.php with action=follow/unfollow -->
    <div class="coming-soon-banner">
      <span class="csb-icon">&#128640;</span>
      <div class="csb-text">
        <strong>Follow system coming soon.</strong>
        Friend requests and following are being built for the next phase.
        For now you can browse all SYL members and their recent activity below.
      </div>
      <span class="csb-badge">PHASE 2</span>
    </div>

    <!-- Search for users -->
    <!-- In PHP: search queries Users table WHERE tag LIKE ? OR first_name LIKE ? -->
    <div class="search-wrap">
      <span class="search-icon">&#128269;</span>
      <input class="search-input" id="userSearch" type="text"
             placeholder="Search members by username or name&hellip;"
             oninput="filterUsers()">
    </div>

    <!-- All SYL members
         In PHP: SELECT u.tag, u.first_name, u.last_name, u.bio,
                        COUNT(ur.review_id) AS review_count,
                        ROUND(AVG(r.rating),1) AS avg_rating
                 FROM Users u
                 LEFT JOIN UserReviews ur ON u.tag = ur.user_tag
                 LEFT JOIN Reviews r ON ur.review_id = r.id
                 GROUP BY u.tag ORDER BY review_count DESC -->
    <div class="sec-label">All Members</div>
    <div class="users-grid" id="usersGrid"></div>

    <!-- Global activity feed — most recent reviews from all users
         In PHP: SELECT r.song_name, r.artist_name, r.rating, r.review, r.review_date,
                        u.tag, u.first_name
                 FROM Reviews r
                 JOIN UserReviews ur ON r.id = ur.review_id
                 JOIN Users u ON ur.user_tag = u.tag
                 ORDER BY r.review_date DESC LIMIT 20 -->
    <div class="sec-label" style="margin-top:40px;">Recent Activity</div>
    <div class="activity-feed" id="activityFeed"></div>

  </div>
  <footer>SYL &mdash; Save Your Listens &middot; &copy; 2026</footer>
</div>

<script>
/* ── Sample data — in PHP these come from DB queries above ── */
const MEMBERS = <?= $membersJs ?>;

const ACTIVITY = <?= $activityJs ?>;

let following = new Set();

function filterUsers() {
  const q = document.getElementById('userSearch').value.toLowerCase().trim();
  const filtered = MEMBERS.filter(m =>
    !q || m.tag.toLowerCase().includes(q) || m.name.toLowerCase().includes(q)
  );
  renderUsers(filtered);
}

function renderUsers(members) {
  const grid = document.getElementById('usersGrid');
  if (!members.length) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">&#128101;</div><div class="empty-title">No members found</div><div class="empty-sub">Try a different search term.</div></div>`;
    return;
  }
  grid.innerHTML = members.map(m => `
    <div class="user-card">
      <div class="uc-avatar" style="background:${m.color}22;border-color:${m.color}44;">${m.emoji}</div>
      <div class="uc-info">
        <div class="uc-name">${m.name}</div>
        <div class="uc-handle">@${m.tag}</div>
        <div class="uc-bio">${m.bio}</div>
      </div>
      <div class="uc-stats">
        <div class="uc-count">${m.reviews}</div>
        <div class="uc-count-lbl">reviews</div>
        <div class="uc-count" style="color:var(--teal);font-size:.9rem;">&#9733;&thinsp;${m.avg}</div>
        <button class="uc-add-btn ${following.has(m.tag) ? 'following' : ''}"
                onclick="alert('Follow feature coming soon \u2014 requires a UserFollows table')">
          ${following.has(m.tag) ? '&#10003; Following' : '+ Follow'}
        </button>
      </div>
    </div>`).join('');
}

function toggleFollow(tag, btn) {
  /* In PHP: POST to SYL.php with action=follow/unfollow and target_tag
     Requires a UserFollows table: (follower_tag, followed_tag, created_at) */
  if (following.has(tag)) {
    following.delete(tag);
    btn.classList.remove('following');
    btn.innerHTML = '+ Follow';
  } else {
    following.add(tag);
    btn.classList.add('following');
    btn.innerHTML = '&#10003; Following';
  }
}

function renderActivity() {
  document.getElementById('activityFeed').innerHTML = ACTIVITY.map(a => `
    <div class="activity-item">
      <div class="ai-avatar" style="background:${a.color}22;border:2px solid ${a.color}44;">${a.emoji}</div>
      <div class="ai-body">
        <div class="ai-header">
          <span class="ai-user">@${a.tag}</span>
          <span class="ai-action">reviewed</span>
          <span class="ai-song">${a.song}</span>
          <span class="ai-artist">by ${a.artist}</span>
        </div>
        ${a.review ? `<div class="ai-review">&ldquo;${a.review}&rdquo;</div>` : ''}
        <div class="ai-meta">
          <span class="ai-rating">&#9733; ${a.rating}/10</span>
          <span class="ai-time">${a.time}</span>
        </div>
      </div>
    </div>`).join('');
}

let sbOpen = true;
function toggleSidebar() {
  sbOpen = !sbOpen;
  document.getElementById('sidebar').classList.toggle('col', !sbOpen);
  document.body.classList.toggle('sb-col', !sbOpen);
}

renderUsers(MEMBERS);
renderActivity();
</script>
</body>
</html>
