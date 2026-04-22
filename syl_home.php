<?php
// ─── SYL shared bootstrap ───────────────────────────────────────────────────
session_start();
require_once 'db.php';

// h() — always use when echoing user data into HTML
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// formatDuration() — converts seconds (DB) to "m:ss" for display
function formatDuration($s) {
    if (!$s) return '&mdash;';
    return floor($s/60).':'.str_pad($s%60,2,'0',STR_PAD_LEFT);
}

// Auth state — used throughout the page
$isLoggedIn = isset($_SESSION['user_tag']);
$userTag    = $isLoggedIn ? $_SESSION['user_tag'] : '';

// Logout — handle before any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: syl_home.php');
    exit;
}
?>
<?php
// ─── Community feed — 5 most recent reviews across all users ─────────────────
// Shown in the "What people are listening to" section at the bottom of the page
$communityReviews = [];
try {
    $stmt = $pdo->query("
        SELECT r.song_name, r.artist_name, r.duration, r.rating, r.review
        FROM Reviews r ORDER BY r.review_date DESC LIMIT 5
    ");
    $communityReviews = $stmt->fetchAll();
} catch (Exception $e) {}

// Row colors cycle — no per-row color stored in DB
$rowColors = ['sr-yellow','sr-sand','sr-pink','sr-teal','sr-lavender'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SYL — Save Your Listens</title>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--yellow:#F5E642;--pink:#F06292;--teal:#4DD0C4;--lavender:#B39DDB;--green:#69D17E;--orange:#FF8A50;--blue:#5B9BF5;--purple:#9C6FE4;--sand:#E8E0D0;--dark:#1A1A2E;--darker:#0F0F1E;--card:#22223B;--text:#F0EDE6;--muted:#8B8BAA;--sidebar-w:220px;--sidebar-w-col:64px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--darker);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}
.page{display:none;}.page.active{display:block;}
#sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--dark);border-right:1px solid #ffffff0a;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:200;transition:width .3s cubic-bezier(.4,0,.2,1);overflow:hidden;}
#sidebar.col{width:var(--sidebar-w-col);}
.sb-top{display:flex;align-items:center;padding:20px 16px 16px;border-bottom:1px solid #ffffff08;min-height:64px;flex-shrink:0;}
.sb-logo{font-family:'Dela Gothic One',sans-serif;font-size:1.4rem;background:linear-gradient(90deg,var(--yellow),var(--pink));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;white-space:nowrap;opacity:1;transition:opacity .2s;}
#sidebar.col .sb-logo{opacity:0;pointer-events:none;}
/* Toggle btn lives outside sidebar — always visible */
#sb-toggle-btn{position:fixed;top:14px;z-index:300;background:var(--dark);border:1px solid #ffffff12;color:var(--muted);cursor:pointer;font-size:1.05rem;width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;transition:color .2s,background .2s,left .3s cubic-bezier(.4,0,.2,1),box-shadow .2s;}
#sb-toggle-btn:hover{color:var(--yellow);background:var(--card);box-shadow:0 0 0 3px #F5E64220;}
body:not(.sb-col) #sb-toggle-btn{left:calc(var(--sidebar-w) - 46px);}
body.sb-col #sb-toggle-btn{left:14px;}
.sb-nav{flex:1;padding:16px 8px;display:flex;flex-direction:column;gap:4px;overflow:hidden;}
.sb-link{display:flex;text-decoration:none;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;border:none;background:none;color:var(--muted);cursor:pointer;font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:500;white-space:nowrap;transition:background .18s,color .18s;text-align:left;width:100%;position:relative;}
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
.top-nav{display:flex;align-items:center;justify-content:flex-end;padding:14px 40px;background:var(--dark);border-bottom:1px solid #ffffff0a;position:sticky;top:0;z-index:100;gap:10px;}
.nbtn{padding:8px 20px;border-radius:100px;border:none;font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:600;cursor:pointer;transition:all .2s;}
.nbtn.ghost{background:transparent;color:var(--text);border:2px solid #ffffff20;}
.nbtn.ghost:hover{border-color:var(--yellow);color:var(--yellow);}
.nbtn.primary{background:var(--yellow);color:var(--dark);}
.nbtn.primary:hover{background:#ffe000;transform:translateY(-1px);}
.nbtn.out{background:transparent;color:var(--muted);border:2px solid #ffffff15;}
.nbtn.out:hover{border-color:var(--pink);color:var(--pink);}
#nav-out{display:flex;gap:10px;}
#nav-in{display:none;gap:10px;align-items:center;}
.user-chip{display:flex;align-items:center;gap:8px;background:var(--card);border-radius:100px;padding:6px 14px 6px 8px;font-size:.82rem;}
.user-av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--teal));display:flex;align-items:center;justify-content:center;font-size:.8rem;}
.hw{padding:40px;max-width:1060px;margin:0 auto;}
.hero{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;margin-bottom:60px;}
.h-eyebrow{font-family:'Space Mono',monospace;font-size:.7rem;letter-spacing:3px;color:var(--teal);text-transform:uppercase;margin-bottom:14px;}
.h-title{font-family:'Dela Gothic One',sans-serif;font-size:3rem;line-height:1.05;margin-bottom:18px;}
.h-title span{color:var(--yellow);}
.h-sub{font-size:1rem;color:var(--muted);line-height:1.7;margin-bottom:28px;}
.h-cta{display:flex;gap:12px;flex-wrap:wrap;}
.cbtn{padding:13px 28px;border-radius:100px;border:none;font-family:'DM Sans',sans-serif;font-weight:700;font-size:.95rem;cursor:pointer;transition:all .2s;}
.cbtn.big{background:var(--yellow);color:var(--dark);}
.cbtn.big:hover{transform:translateY(-2px);box-shadow:0 10px 30px #F5E64240;}
.cbtn.ol{background:transparent;color:var(--text);border:2px solid #ffffff25;}
.cbtn.ol:hover{border-color:var(--teal);color:var(--teal);}
.h-preview{background:var(--card);border-radius:18px;overflow:hidden;border:1px solid #ffffff0f;box-shadow:0 24px 64px #00000050;}
.pbar{padding:12px 16px;background:#2D2D4E;display:flex;align-items:center;gap:8px;}
.pdots{display:flex;gap:5px;}.pdots span{width:9px;height:9px;border-radius:50%;}
.dr{background:var(--pink);}.dy{background:var(--yellow);}.dg{background:var(--green);}
.purl{font-family:'Space Mono',monospace;font-size:.65rem;color:var(--muted);margin-left:4px;}
.pbody{padding:14px;}
.pu{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.pav{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--teal));display:flex;align-items:center;justify-content:center;font-size:1rem;}
.pun{font-weight:700;font-size:.88rem;}.ph{font-size:.7rem;color:var(--muted);}
.prow{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:9px;margin-bottom:6px;font-size:.78rem;font-weight:600;}
.prow .rn{font-family:'Space Mono',monospace;font-size:.6rem;opacity:.55;min-width:14px;}
.prow .rname{flex:1;}.prow .rart{opacity:.6;font-weight:400;font-size:.72rem;}
.prow .rrat{font-family:'Space Mono',monospace;font-size:.65rem;}
.r1{background:var(--yellow);color:var(--dark);}.r2{background:var(--pink);color:#fff;}
.r3{background:var(--teal);color:var(--dark);}.r4{background:var(--lavender);color:var(--dark);}.r5{background:var(--sand);color:var(--dark);}
.sbar{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:52px;}
.sc{background:var(--card);border-radius:14px;padding:22px 24px;border:1px solid #ffffff08;position:relative;overflow:hidden;}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.sc.sy::before{background:var(--yellow);}.sc.st::before{background:var(--teal);}.sc.sp::before{background:var(--pink);}
.snum{font-family:'Dela Gothic One',sans-serif;font-size:2.2rem;color:var(--yellow);margin-bottom:4px;}
.sc.st .snum{color:var(--teal);}.sc.sp .snum{color:var(--pink);}.slbl{font-size:.82rem;color:var(--muted);}
.pnote{display:flex;align-items:center;gap:14px;background:var(--card);border-radius:14px;padding:16px 22px;margin-bottom:36px;border-left:4px solid var(--yellow);}
.pnote-icon{font-size:1.3rem;}
.pnote-text{font-size:.85rem;color:var(--muted);line-height:1.5;}
.pnote-text strong{color:var(--text);}
.pnote-cta{margin-left:auto;padding:8px 18px;background:var(--yellow);color:var(--dark);border:none;border-radius:100px;font-family:'DM Sans',sans-serif;font-weight:700;font-size:.82rem;cursor:pointer;white-space:nowrap;transition:background .2s;}
.pnote-cta:hover{background:#ffe000;}
.s-eye{font-family:'Space Mono',monospace;font-size:.68rem;letter-spacing:3px;color:var(--teal);text-transform:uppercase;margin-bottom:8px;}
.s-title{font-family:'Dela Gothic One',sans-serif;font-size:1.7rem;margin-bottom:6px;}
.s-hint{font-size:.8rem;color:var(--muted);font-family:'Space Mono',monospace;margin-bottom:32px;}
.agrid{display:grid;grid-template-columns:repeat(3,1fr);gap:28px;}
.acard{position:relative;cursor:pointer;}
.awrap{position:relative;width:100%;aspect-ratio:1;border-radius:16px;overflow:visible;}
.aart{width:100%;height:100%;border-radius:16px;object-fit:cover;display:block;transition:transform .4s cubic-bezier(.34,1.56,.64,1),box-shadow .4s;box-shadow:0 8px 28px #00000060;}
.aglow{position:absolute;inset:-10px;border-radius:22px;opacity:0;filter:blur(18px);transition:opacity .4s;z-index:-1;}
.acard:hover .aart{transform:scale(1.03) translateY(-4px);box-shadow:0 18px 50px #00000080;}
.acard:hover .aglow{opacity:.3;}
.aoverlay{position:absolute;inset:0;border-radius:16px;background:linear-gradient(to top,#0F0F1Ecc 0%,#0F0F1E44 50%,transparent 100%);opacity:0;transition:opacity .3s;pointer-events:none;}
.acard:hover .aoverlay{opacity:1;}
.ahover{position:absolute;bottom:12px;left:12px;right:12px;opacity:0;transform:translateY(5px);transition:opacity .3s .05s,transform .3s .05s;pointer-events:none;z-index:10;}
.acard:hover .ahover{opacity:1;transform:translateY(0);}
.ahovname{font-family:'Dela Gothic One',sans-serif;font-size:.9rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ahovart{font-size:.72rem;color:rgba(255,255,255,.6);margin-top:2px;}
.orb{position:absolute;width:40px;height:40px;border-radius:50%;border:2.5px solid rgba(255,255,255,.12);background:var(--card);display:flex;align-items:center;justify-content:center;font-size:1rem;cursor:pointer;z-index:20;opacity:.4;transform:scale(.88);transition:opacity .25s,transform .25s cubic-bezier(.34,1.56,.64,1),border-color .25s;}
.acard:hover .orb{opacity:.9;transform:scale(1);}
.orb:hover{opacity:1!important;transform:scale(1.2)!important;border-color:rgba(255,255,255,.55)!important;z-index:30;}
.cbub{position:absolute;background:var(--card);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:12px 14px;width:210px;z-index:50;pointer-events:none;opacity:0;transform:scale(.92) translateY(5px);transition:opacity .2s,transform .2s cubic-bezier(.34,1.56,.64,1);box-shadow:0 10px 36px #00000070;--asz:7px;}
.cbub.vis{opacity:1;transform:scale(1) translateY(0);pointer-events:auto;}
.cbub::before{content:'';position:absolute;border:var(--asz) solid transparent;}
.cbub.au::before{bottom:calc(var(--asz)*-2);left:18px;border-top-color:var(--card);}
.cbub.ad::before{top:calc(var(--asz)*-2);left:18px;border-bottom-color:var(--card);}
.buser{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
.bav{width:25px;height:25px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;flex-shrink:0;}
.bun{font-family:'Space Mono',monospace;font-size:.68rem;color:var(--teal);font-weight:700;}
.bsong{font-size:.68rem;color:var(--muted);margin-top:1px;}
.bdiv{height:1px;background:rgba(255,255,255,.06);margin-bottom:8px;}
.bcom{font-size:.8rem;color:var(--text);line-height:1.5;font-style:italic;}
.brm{font-size:.72rem;color:var(--yellow);font-family:'Space Mono',monospace;cursor:pointer;margin-top:5px;display:inline-block;pointer-events:auto;}
.brm:hover{text-decoration:underline;}
.brat{margin-top:8px;font-family:'Space Mono',monospace;font-size:.68rem;color:var(--yellow);letter-spacing:1px;}
.ameta{margin-top:12px;padding:0 2px;}
.aname{font-family:'Dela Gothic One',sans-serif;font-size:.95rem;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.aartist{font-size:.78rem;color:var(--muted);margin-bottom:7px;}
.astats{display:flex;gap:10px;align-items:center;}
.astat{font-family:'Space Mono',monospace;font-size:.65rem;color:var(--muted);}
.astat span{color:var(--text);font-weight:700;}
.gpill{margin-left:auto;padding:2px 9px;border-radius:100px;font-size:.6rem;font-family:'Space Mono',monospace;font-weight:700;letter-spacing:.5px;}
.sdw{padding:40px;max-width:900px;margin:0 auto;}
.sback{display:inline-flex;align-items:center;gap:8px;background:none;border:none;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:.85rem;cursor:pointer;padding:0;margin-bottom:32px;transition:color .2s;}
.sback:hover{color:var(--yellow);}
.shero{display:flex;gap:32px;align-items:flex-start;margin-bottom:48px;}
.sart{width:180px;height:180px;border-radius:16px;object-fit:cover;box-shadow:0 16px 48px #00000070;flex-shrink:0;}
.sinfo{}
.salb{font-family:'Space Mono',monospace;font-size:.7rem;letter-spacing:2px;color:var(--teal);text-transform:uppercase;margin-bottom:8px;}
.stitle{font-family:'Dela Gothic One',sans-serif;font-size:2.2rem;line-height:1.1;margin-bottom:6px;}
.sartist{font-size:1rem;color:var(--muted);margin-bottom:20px;}
.sstats{display:flex;gap:20px;flex-wrap:wrap;}
.sstat{background:var(--card);border-radius:10px;padding:10px 16px;}
.sstat-n{font-family:'Dela Gothic One',sans-serif;font-size:1.3rem;color:var(--yellow);}
.sstat-l{font-size:.72rem;color:var(--muted);}
.ch{font-family:'Dela Gothic One',sans-serif;font-size:1.2rem;margin-bottom:20px;}
.cc{background:var(--card);border-radius:14px;padding:18px 20px;margin-bottom:12px;border:1px solid #ffffff08;transition:border-color .2s;}
.cc.hl{border-color:var(--yellow);}
.ccu{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.ccav{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.ccn{font-weight:700;font-size:.9rem;}
.cch{font-size:.72rem;color:var(--muted);font-family:'Space Mono',monospace;}
.cct{font-size:.88rem;color:var(--text);line-height:1.6;font-style:italic;margin-bottom:10px;}
.ccr{font-family:'Space Mono',monospace;font-size:.72rem;color:var(--yellow);}
.gp{background:var(--card);border-radius:14px;padding:22px 24px;border:1px solid #ffffff08;text-align:center;margin-top:24px;}
.gp p{color:var(--muted);font-size:.9rem;margin-bottom:14px;}
.gpbtn{padding:10px 24px;background:var(--yellow);color:var(--dark);border:none;border-radius:100px;font-family:'DM Sans',sans-serif;font-weight:700;font-size:.88rem;cursor:pointer;transition:background .2s;}
.gpbtn:hover{background:#ffe000;}
.stub{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:calc(100vh - 64px);color:var(--muted);text-align:center;padding:40px;}
.stub-i{font-size:3rem;margin-bottom:16px;}
.stub-t{font-family:'Dela Gothic One',sans-serif;font-size:1.6rem;color:var(--text);margin-bottom:8px;}
.stub-s{font-size:.9rem;max-width:320px;line-height:1.6;}
.stub-b{margin-top:16px;padding:6px 16px;background:var(--card);border-radius:100px;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--teal);border:1px solid #4DD0C433;}
footer{text-align:center;padding:32px 40px;color:var(--muted);font-size:.75rem;font-family:'Space Mono',monospace;border-top:1px solid #ffffff08;}
@media(max-width:900px){.agrid{grid-template-columns:1fr 1fr;}.hero{grid-template-columns:1fr;}.sbar{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){#main{margin-left:var(--sidebar-w-col)!important;}#sidebar{width:var(--sidebar-w-col)!important;}.sb-label{opacity:0!important;}.sb-logo{opacity:0!important;}.hw,.sdw{padding:20px;}.agrid{grid-template-columns:1fr;}.sbar{grid-template-columns:1fr;}}
</style>
</head>
<body>
<!-- Fixed toggle button — always visible whether sidebar is open or collapsed -->
<button id="sb-toggle-btn" onclick="toggleSidebar()" title="Toggle sidebar">&#9776;</button>

<aside id="sidebar">
  <div class="sb-top"><a class="sb-logo" href="syl_home.php" style="text-decoration:none;">SYL</a></div>
  <nav class="sb-nav">
    <a class="sb-link active" href="syl_home.php"      data-label="home">      <span class="sb-icon">&#127968;</span><span class="sb-label">Home</span></a>
    <a class="sb-link " href="syl_songs.php"     data-label="songs">     <span class="sb-icon">&#127925;</span><span class="sb-label">Songs</span></a>
    <a class="sb-link " href="syl_friends.php"   data-label="friends">   <span class="sb-icon">&#128101;</span><span class="sb-label">Friends</span></a>
    <a class="sb-link " href="syl_community.php" data-label="community"><span class="sb-icon">&#127758;</span><span class="sb-label">Community</span></a>
  </nav>
  <div class="sb-bottom">
    <a class="sb-link " href="syl_settings.php"  data-label="settings">  <span class="sb-icon">&#9881;&#65039;</span><span class="sb-label">Settings</span></a>
  </div>
</aside>
<div id="main">
  <div class="top-nav">
    <div id="nav-out">
      <button class="nbtn ghost"   onclick="window.location='syl_auth.php?tab=login'">Log In</button>
      <button class="nbtn primary" onclick="window.location='syl_auth.php?tab=register'">Sign Up Free</button>
    </div>
    <div id="nav-in">
      <div class="user-chip"><div class="user-av">&#127911;</div><span id="nav-uname">user</span></div>
      <button class="nbtn out" onclick="doLogout()">Log Out</button>
    </div>
  </div>
  <div id="page-home" class="page active">
    <div class="hw">
      <section class="hero">
        <div>
          <p class="h-eyebrow">&#127925; Save Your Listens</p>
          <h1 class="h-title">Your music,<br><span>your voice.</span></h1>
          <p class="h-sub">Log what you listen to, rate it, leave your thoughts, and share your musical taste with the world &mdash; or just keep it for yourself.</p>
          <div class="h-cta">
            <button class="cbtn big" onclick="window.location='syl_auth.php?tab=register'">Get Started &mdash; Free</button>
            <button class="cbtn ol"  onclick="window.location='syl_auth.php?tab=login'">Log In</button>
          </div>
        </div>
        <div class="h-preview">
          <div class="pbar"><div class="pdots"><span class="dr"></span><span class="dy"></span><span class="dg"></span></div><span class="purl">joshm.syl.app</span></div>
          <div class="pbody">
            <div class="pu"><div class="pav">&#127911;</div><div><div class="pun">joshm</div><div class="ph">recent listens &middot; 5 songs</div></div></div>
            <div class="prow r1"><span class="rn">01</span><span class="rname">Blinding Lights</span><span class="rart">The Weeknd</span><span class="rrat">&#9733; 5.0</span></div>
            <div class="prow r2"><span class="rn">02</span><span class="rname">As It Was</span><span class="rart">Harry Styles</span><span class="rrat">&#9733; 4.5</span></div>
            <div class="prow r3"><span class="rn">03</span><span class="rname">Espresso</span><span class="rart">Sabrina Carpenter</span><span class="rrat">&#9733; 5.0</span></div>
            <div class="prow r4"><span class="rn">04</span><span class="rname">luther</span><span class="rart">Kendrick &amp; SZA</span><span class="rrat">&#9733; 5.0</span></div>
            <div class="prow r5"><span class="rn">05</span><span class="rname">APT.</span><span class="rart">Rose &amp; Bruno Mars</span><span class="rrat">&#9733; 4.5</span></div>
          </div>
        </div>
      </section>
      <div class="sbar">
        <div class="sc sy"><div class="snum">12K+</div><div class="slbl">Songs reviewed</div></div>
        <div class="sc st"><div class="snum">3.4K</div><div class="slbl">Active listeners</div></div>
        <div class="sc sp"><div class="snum">98K</div><div class="slbl">Total ratings given</div></div>
      </div>
      <div class="pnote">
        <span class="pnote-icon">&#128064;</span>
        <div class="pnote-text"><strong>No account needed to browse.</strong> Hover an album, then hover a listener avatar to read their take.</div>
        <button class="pnote-cta" onclick="window.location='syl_auth.php?tab=register'">Join SYL Free &rarr;</button>
      </div>
      <p class="s-eye">&#128293; Trending This Week</p>
      <h2 class="s-title">What SYL members are listening to</h2>
      <p class="s-hint">Hover an album &middot; hover a listener orb &middot; click "read more" to open the full song page</p>
      <div class="agrid" id="albumsGrid"></div>
    </div>
    <footer>SYL &mdash; Save Your Listens &middot; &copy; 2026 &middot; No account needed to browse</footer>
  </div>
  <div id="page-song" class="page">
    <div class="sdw">
      <button class="sback" onclick="navTo('home')">&#8592; Back to Home</button>
      <div class="shero">
        <img class="sart" id="song-art" src="" alt="album art">
        <div class="sinfo">
          <div class="salb"   id="song-alb"></div>
          <div class="stitle" id="song-title"></div>
          <div class="sartist" id="song-artist"></div>
          <div class="sstats">
            <div class="sstat"><div class="sstat-n" id="song-revs">&mdash;</div><div class="sstat-l">Reviews</div></div>
            <div class="sstat"><div class="sstat-n" id="song-avg">&mdash;</div><div class="sstat-l">Avg Rating</div></div>
          </div>
        </div>
      </div>
      <div class="ch">&#128172; What people are saying</div>
      <div id="song-clist"></div>
      <div class="gp" id="guest-p">
        <p>Got an opinion? <strong>Sign up free</strong> to leave your own rating and review.</p>
        <button class="gpbtn" onclick="window.location='syl_auth.php?tab=register'">Add Your Review &rarr;</button>
      </div>
    </div>
  </div>
  <div id="page-songs"     class="page"><div class="stub"><div class="stub-i">&#127925;</div><div class="stub-t">Songs</div><div class="stub-s">Browse the full catalog. Filter by genre, artist, or rating.</div><div class="stub-b">Coming in Phase 2</div></div></div>
  <div id="page-friends"   class="page"><div class="stub"><div class="stub-i">&#128101;</div><div class="stub-t">Friends</div><div class="stub-s">See what your friends are listening to and compare taste.</div><div class="stub-b">Coming in Phase 2</div></div></div>
  <div id="page-community" class="page"><div class="stub"><div class="stub-i">&#127758;</div><div class="stub-t">Community</div><div class="stub-s">Global feed of recent reviews, trending songs, top listeners.</div><div class="stub-b">Coming in Phase 2</div></div></div>
  <div id="page-settings"  class="page"><div class="stub"><div class="stub-i">&#9881;&#65039;</div><div class="stub-t">Settings</div><div class="stub-s">Manage profile, password, privacy, and embed preferences.</div><div class="stub-b">Coming in Phase 2</div></div></div>
</div>
<script>
const ART = ["data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj4KICA8ZGVmcz4KICAgIDxyYWRpYWxHcmFkaWVudCBpZD0iYmcxIiBjeD0iNTAlIiBjeT0iNTAlIiByPSI2MCUiPgogICAgICA8c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjMmEyMDQwIi8+CiAgICAgIDxzdG9wIG9mZnNldD0iMTAwJSIgc3RvcC1jb2xvcj0iIzBGMEYxRSIvPgogICAgPC9yYWRpYWxHcmFkaWVudD4KICA8L2RlZnM+CiAgPHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9InVybCgjYmcxKSIvPgogIDwhLS0gR3JhaW4gdGV4dHVyZSBkb3RzIC0tPgogIDxmaWx0ZXIgaWQ9Im5vaXNlMSI+PGZlVHVyYnVsZW5jZSB0eXBlPSJmcmFjdGFsTm9pc2UiIGJhc2VGcmVxdWVuY3k9IjAuNjUiIG51bU9jdGF2ZXM9IjMiIHN0aXRjaFRpbGVzPSJzdGl0Y2giLz48ZmVDb2xvck1hdHJpeCB0eXBlPSJzYXR1cmF0ZSIgdmFsdWVzPSIwIi8+PGZlQmxlbmQgaW49IlNvdXJjZUdyYXBoaWMiIG1vZGU9Im92ZXJsYXkiIHJlc3VsdD0iYmxlbmQiLz48L2ZpbHRlcj4KICA8cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsdGVyPSJ1cmwoI25vaXNlMSkiIG9wYWNpdHk9IjAuMDQiLz4KICA8IS0tIENhc3NldHRlIGJvZHkgLS0+CiAgPHJlY3QgeD0iNzAiIHk9IjEzMCIgd2lkdGg9IjI2MCIgaGVpZ2h0PSIxNjAiIHJ4PSIxNCIgcnk9IjE0IiBmaWxsPSIjMWUxYTMwIiBzdHJva2U9IiNGNUU2NDIiIHN0cm9rZS13aWR0aD0iMi41Ii8+CiAgPCEtLSBMYWJlbCBzdHJpcCAtLT4KICA8cmVjdCB4PSI5MCIgeT0iMTUwIiB3aWR0aD0iMjIwIiBoZWlnaHQ9IjgwIiByeD0iNiIgZmlsbD0iI0Y1RTY0MiIgb3BhY2l0eT0iMC45Ii8+CiAgPHRleHQgeD0iMjAwIiB5PSIxODgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJtb25vc3BhY2UiIGZvbnQtd2VpZ2h0PSJib2xkIiBmb250LXNpemU9IjE0IiBmaWxsPSIjMWExYTJlIj5TWUw8L3RleHQ+CiAgPHRleHQgeD0iMjAwIiB5PSIyMDgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJtb25vc3BhY2UiIGZvbnQtc2l6ZT0iOSIgZmlsbD0iIzFhMWEyZSIgb3BhY2l0eT0iMC43Ij5TQVZFIFlPVVIgTElTVEVOUzwvdGV4dD4KICA8IS0tIFRhcGUgd2luZG93IGN1dG91dCAtLT4KICA8cmVjdCB4PSIxMDUiIHk9IjI0NSIgd2lkdGg9IjE5MCIgaGVpZ2h0PSIzMiIgcng9IjUiIGZpbGw9IiMwRjBGMUUiLz4KICA8IS0tIFRhcGUgcmVlbHMgLS0+CiAgPGNpcmNsZSBjeD0iMTU1IiBjeT0iMjYxIiByPSIxMiIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjRjVFNjQyIiBzdHJva2Utd2lkdGg9IjIiLz4KICA8Y2lyY2xlIGN4PSIxNTUiIGN5PSIyNjEiIHI9IjUiIGZpbGw9IiNGNUU2NDIiIG9wYWNpdHk9IjAuNiIvPgogIDxjaXJjbGUgY3g9IjI0NSIgY3k9IjI2MSIgcj0iMTIiIGZpbGw9Im5vbmUiIHN0cm9rZT0iI0Y1RTY0MiIgc3Ryb2tlLXdpZHRoPSIyIi8+CiAgPGNpcmNsZSBjeD0iMjQ1IiBjeT0iMjYxIiByPSI1IiBmaWxsPSIjRjVFNjQyIiBvcGFjaXR5PSIwLjYiLz4KICA8IS0tIFRhcGUgcGF0aCAtLT4KICA8cGF0aCBkPSJNMTY3IDI2MSBRMjAwIDI3MCAyMzMgMjYxIiBmaWxsPSJub25lIiBzdHJva2U9IiNGNUU2NDIiIHN0cm9rZS13aWR0aD0iMS41IiBvcGFjaXR5PSIwLjUiLz4KICA8IS0tIFNpZGUgbm90Y2hlcyAtLT4KICA8cmVjdCB4PSI3MCIgeT0iMTU1IiB3aWR0aD0iOCIgaGVpZ2h0PSIyMCIgcng9IjIiIGZpbGw9IiNGNUU2NDIiIG9wYWNpdHk9IjAuMyIvPgogIDxyZWN0IHg9IjMyMiIgeT0iMTU1IiB3aWR0aD0iOCIgaGVpZ2h0PSIyMCIgcng9IjIiIGZpbGw9IiNGNUU2NDIiIG9wYWNpdHk9IjAuMyIvPgogIDwhLS0gQ29ybmVyIHNjcmV3cyAtLT4KICA8Y2lyY2xlIGN4PSI5MCIgY3k9IjE0OCIgcj0iNSIgZmlsbD0iI0Y1RTY0MiIgb3BhY2l0eT0iMC4yIi8+PGxpbmUgeDE9Ijg3IiB5MT0iMTQ4IiB4Mj0iOTMiIHkyPSIxNDgiIHN0cm9rZT0iI0Y1RTY0MiIgc3Ryb2tlLXdpZHRoPSIxIiBvcGFjaXR5PSIwLjQiLz48bGluZSB4MT0iOTAiIHkxPSIxNDUiIHgyPSI5MCIgeTI9IjE1MSIgc3Ryb2tlPSIjRjVFNjQyIiBzdHJva2Utd2lkdGg9IjEiIG9wYWNpdHk9IjAuNCIvPgogIDxjaXJjbGUgY3g9IjMxMCIgY3k9IjE0OCIgcj0iNSIgZmlsbD0iI0Y1RTY0MiIgb3BhY2l0eT0iMC4yIi8+PGxpbmUgeDE9IjMwNyIgeTE9IjE0OCIgeDI9IjMxMyIgeTI9IjE0OCIgc3Ryb2tlPSIjRjVFNjQyIiBzdHJva2Utd2lkdGg9IjEiIG9wYWNpdHk9IjAuNCIvPjxsaW5lIHgxPSIzMTAiIHkxPSIxNDUiIHgyPSIzMTAiIHkyPSIxNTEiIHN0cm9rZT0iI0Y1RTY0MiIgc3Ryb2tlLXdpZHRoPSIxIiBvcGFjaXR5PSIwLjQiLz4KICA8Y2lyY2xlIGN4PSI5MCIgY3k9IjI4MiIgcj0iNSIgZmlsbD0iI0Y1RTY0MiIgb3BhY2l0eT0iMC4yIi8+PGNpcmNsZSBjeD0iMzEwIiBjeT0iMjgyIiByPSI1IiBmaWxsPSIjRjVFNjQyIiBvcGFjaXR5PSIwLjIiLz4KICA8IS0tIEdsb3cgLS0+CiAgPGVsbGlwc2UgY3g9IjIwMCIgY3k9IjIwMCIgcng9IjEzMCIgcnk9IjkwIiBmaWxsPSJub25lIiBzdHJva2U9IiNGNUU2NDIiIHN0cm9rZS13aWR0aD0iMSIgb3BhY2l0eT0iMC4wOCIvPgo8L3N2Zz4=", "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj4KICA8ZGVmcz4KICAgIDxyYWRpYWxHcmFkaWVudCBpZD0iYmcyIiBjeD0iNTAlIiBjeT0iNTAlIiByPSI2NSUiPgogICAgICA8c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjMmUxYTI2Ii8+CiAgICAgIDxzdG9wIG9mZnNldD0iMTAwJSIgc3RvcC1jb2xvcj0iIzBGMEYxRSIvPgogICAgPC9yYWRpYWxHcmFkaWVudD4KICAgIDxyYWRpYWxHcmFkaWVudCBpZD0iZGlzYyIgY3g9IjUwJSIgY3k9IjUwJSIgcj0iNTAlIj4KICAgICAgPHN0b3Agb2Zmc2V0PSIwJSIgc3RvcC1jb2xvcj0iIzJhMWYyYSIvPgogICAgICA8c3RvcCBvZmZzZXQ9IjEwMCUiIHN0b3AtY29sb3I9IiMxMTExMTgiLz4KICAgIDwvcmFkaWFsR3JhZGllbnQ+CiAgPC9kZWZzPgogIDxyZWN0IHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIiBmaWxsPSJ1cmwoI2JnMikiLz4KICA8IS0tIFJlY29yZCBkaXNjIC0tPgogIDxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iMTU1IiBmaWxsPSJ1cmwoI2Rpc2MpIiBzdHJva2U9IiNGMDYyOTIiIHN0cm9rZS13aWR0aD0iMS41IiBvcGFjaXR5PSIwLjYiLz4KICA8IS0tIEdyb292ZSByaW5ncyAtLT4KICA8Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjE0NSIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjRjA2MjkyIiBzdHJva2Utd2lkdGg9IjAuNSIgb3BhY2l0eT0iMC4xNSIvPgogIDxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iMTMwIiBmaWxsPSJub25lIiBzdHJva2U9IiNGMDYyOTIiIHN0cm9rZS13aWR0aD0iMC41IiBvcGFjaXR5PSIwLjEyIi8+CiAgPGNpcmNsZSBjeD0iMjAwIiBjeT0iMjAwIiByPSIxMTUiIGZpbGw9Im5vbmUiIHN0cm9rZT0iI0YwNjI5MiIgc3Ryb2tlLXdpZHRoPSIwLjUiIG9wYWNpdHk9IjAuMTIiLz4KICA8Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjEwMCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjRjA2MjkyIiBzdHJva2Utd2lkdGg9IjAuNSIgb3BhY2l0eT0iMC4xMiIvPgogIDxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iODUiICBmaWxsPSJub25lIiBzdHJva2U9IiNGMDYyOTIiIHN0cm9rZS13aWR0aD0iMC41IiBvcGFjaXR5PSIwLjE1Ii8+CiAgPGNpcmNsZSBjeD0iMjAwIiBjeT0iMjAwIiByPSI3MCIgIGZpbGw9Im5vbmUiIHN0cm9rZT0iI0YwNjI5MiIgc3Ryb2tlLXdpZHRoPSIwLjUiIG9wYWNpdHk9IjAuMTUiLz4KICA8IS0tIExhYmVsIGNpcmNsZSAtLT4KICA8Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjU1IiBmaWxsPSIjRjA2MjkyIiBvcGFjaXR5PSIwLjkiLz4KICA8Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjQwIiBmaWxsPSIjMmUxYTI2Ii8+CiAgPHRleHQgeD0iMjAwIiB5PSIxOTYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJtb25vc3BhY2UiIGZvbnQtd2VpZ2h0PSJib2xkIiBmb250LXNpemU9IjExIiBmaWxsPSIjRjA2MjkyIj5TWUw8L3RleHQ+CiAgPHRleHQgeD0iMjAwIiB5PSIyMTAiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJtb25vc3BhY2UiIGZvbnQtc2l6ZT0iNyIgZmlsbD0iI0YwNjI5MiIgb3BhY2l0eT0iMC43Ij5TSURFIEE8L3RleHQ+CiAgPCEtLSBDZW50ZXIgaG9sZSAtLT4KICA8Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjYiIGZpbGw9IiMwRjBGMUUiLz4KICA8IS0tIEhpZ2hsaWdodCBhcmMgLS0+CiAgPHBhdGggZD0iTSA4MCAxNjAgQSAxMzAgMTMwIDAgMCAxIDE3MCA3NSIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIiBvcGFjaXR5PSIwLjA2Ii8+CiAgPCEtLSBSZWZsZWN0aW9uIHNoZWVuIC0tPgogIDxlbGxpcHNlIGN4PSIxNjAiIGN5PSIxNDUiIHJ4PSIzMCIgcnk9IjE1IiBmaWxsPSJ3aGl0ZSIgb3BhY2l0eT0iMC4wMyIgdHJhbnNmb3JtPSJyb3RhdGUoLTM1IDE2MCAxNDUpIi8+Cjwvc3ZnPg==", "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj4KICA8ZGVmcz4KICAgIDxyYWRpYWxHcmFkaWVudCBpZD0iYmczIiBjeD0iNDAlIiBjeT0iNDAlIiByPSI2NSUiPgogICAgICA8c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjMGYyYTJhIi8+CiAgICAgIDxzdG9wIG9mZnNldD0iMTAwJSIgc3RvcC1jb2xvcj0iIzBGMEYxRSIvPgogICAgPC9yYWRpYWxHcmFkaWVudD4KICA8L2RlZnM+CiAgPHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9InVybCgjYmczKSIvPgogIDwhLS0gR2xvd2luZyByaW5ncyAtLT4KICA8Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjE2MCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjNEREMEM0IiBzdHJva2Utd2lkdGg9IjAuNSIgb3BhY2l0eT0iMC4wOCIvPgogIDxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iMTMwIiBmaWxsPSJub25lIiBzdHJva2U9IiM0REQwQzQiIHN0cm9rZS13aWR0aD0iMC41IiBvcGFjaXR5PSIwLjEiLz4KICA8IS0tIERvdWJsZSBiZWFtZWQgbm90ZSAtLT4KICA8IS0tIE5vdGUgaGVhZHMgLS0+CiAgPGVsbGlwc2UgY3g9IjE0OCIgY3k9IjI1NSIgcng9IjIyIiByeT0iMTYiIGZpbGw9IiM0REQwQzQiIHRyYW5zZm9ybT0icm90YXRlKC0xNSAxNDggMjU1KSIvPgogIDxlbGxpcHNlIGN4PSIyNDAiIGN5PSIyMjUiIHJ4PSIyMiIgcnk9IjE2IiBmaWxsPSIjNEREMEM0IiB0cmFuc2Zvcm09InJvdGF0ZSgtMTUgMjQwIDIyNSkiLz4KICA8IS0tIFN0ZW1zIC0tPgogIDxsaW5lIHgxPSIxNjgiIHkxPSIyNDgiIHgyPSIxNjgiIHkyPSIxMjgiIHN0cm9rZT0iIzRERDBDNCIgc3Ryb2tlLXdpZHRoPSI1IiBzdHJva2UtbGluZWNhcD0icm91bmQiLz4KICA8bGluZSB4MT0iMjYwIiB5MT0iMjE4IiB4Mj0iMjYwIiB5Mj0iOTgiIHN0cm9rZT0iIzRERDBDNCIgc3Ryb2tlLXdpZHRoPSI1IiBzdHJva2UtbGluZWNhcD0icm91bmQiLz4KICA8IS0tIEJlYW1zIC0tPgogIDxwYXRoIGQ9Ik0xNjggMTI4IFEyMTQgMTA4IDI2MCA5OCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjNEREMEM0IiBzdHJva2Utd2lkdGg9IjExIiBzdHJva2UtbGluZWNhcD0icm91bmQiLz4KICA8cGF0aCBkPSJNMTY4IDE0OCBRMjE0IDEyOCAyNjAgMTE4IiBmaWxsPSJub25lIiBzdHJva2U9IiM0REQwQzQiIHN0cm9rZS13aWR0aD0iNyIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIi8+CiAgPCEtLSBEb3RzIG9uIG5vdGUgaGVhZHMgLS0+CiAgPGNpcmNsZSBjeD0iMTQ4IiBjeT0iMjU1IiByPSI1IiBmaWxsPSIjMEYwRjFFIiBvcGFjaXR5PSIwLjUiLz4KICA8Y2lyY2xlIGN4PSIyNDAiIGN5PSIyMjUiIHI9IjUiIGZpbGw9IiMwRjBGMUUiIG9wYWNpdHk9IjAuNSIvPgogIDwhLS0gU3BhcmtsZSBkb3RzIC0tPgogIDxjaXJjbGUgY3g9IjMxMCIgY3k9IjMxMCIgcj0iMyIgZmlsbD0iIzRERDBDNCIgb3BhY2l0eT0iMC40Ii8+CiAgPGNpcmNsZSBjeD0iOTUiICBjeT0iMTUwIiByPSIyIiBmaWxsPSIjNEREMEM0IiBvcGFjaXR5PSIwLjMiLz4KICA8Y2lyY2xlIGN4PSIzMjAiIGN5PSIxMzAiIHI9IjIiIGZpbGw9IiM0REQwQzQiIG9wYWNpdHk9IjAuMjUiLz4KICA8Y2lyY2xlIGN4PSI4MCIgIGN5PSIyOTAiIHI9IjMiIGZpbGw9IiM0REQwQzQiIG9wYWNpdHk9IjAuMiIvPgogIDwhLS0gTGFiZWwgLS0+CiAgPHRleHQgeD0iMjAwIiB5PSIzNDUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJtb25vc3BhY2UiIGZvbnQtc2l6ZT0iMTAiIGZpbGw9IiM0REQwQzQiIG9wYWNpdHk9IjAuNSIgbGV0dGVyLXNwYWNpbmc9IjQiPlNZTDwvdGV4dD4KPC9zdmc+", "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj4KICA8ZGVmcz4KICAgIDxyYWRpYWxHcmFkaWVudCBpZD0iYmc0IiBjeD0iNTAlIiBjeT0iNTAlIiByPSI2MCUiPgogICAgICA8c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjMWUxYTJlIi8+CiAgICAgIDxzdG9wIG9mZnNldD0iMTAwJSIgc3RvcC1jb2xvcj0iIzBGMEYxRSIvPgogICAgPC9yYWRpYWxHcmFkaWVudD4KICA8L2RlZnM+CiAgPHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9InVybCgjYmc0KSIvPgogIDwhLS0gTGVmdCByZWVsIC0tPgogIDxjaXJjbGUgY3g9IjEzNSIgY3k9IjIwMCIgcj0iODAiIGZpbGw9IiMxYTFhMmUiIHN0cm9rZT0iI0IzOUREQiIgc3Ryb2tlLXdpZHRoPSIyIi8+CiAgPGNpcmNsZSBjeD0iMTM1IiBjeT0iMjAwIiByPSI2NSIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjQjM5RERCIiBzdHJva2Utd2lkdGg9IjEiIG9wYWNpdHk9IjAuMyIvPgogIDxjaXJjbGUgY3g9IjEzNSIgY3k9IjIwMCIgcj0iNTAiIGZpbGw9Im5vbmUiIHN0cm9rZT0iI0IzOUREQiIgc3Ryb2tlLXdpZHRoPSIxIiBvcGFjaXR5PSIwLjIiLz4KICA8IS0tIExlZnQgc3Bva2VzIC0tPgogIDxsaW5lIHgxPSIxMzUiIHkxPSIxMjAiIHgyPSIxMzUiIHkyPSIyMDAiIHN0cm9rZT0iI0IzOUREQiIgc3Ryb2tlLXdpZHRoPSIzIiBvcGFjaXR5PSIwLjUiLz4KICA8bGluZSB4MT0iNjYiICB5MT0iMTY1IiB4Mj0iMTM1IiB5Mj0iMjAwIiBzdHJva2U9IiNCMzlEREIiIHN0cm9rZS13aWR0aD0iMyIgb3BhY2l0eT0iMC41Ii8+CiAgPGxpbmUgeDE9IjY2IiAgeTE9IjIzNSIgeDI9IjEzNSIgeTI9IjIwMCIgc3Ryb2tlPSIjQjM5RERCIiBzdHJva2Utd2lkdGg9IjMiIG9wYWNpdHk9IjAuNSIvPgogIDxsaW5lIHgxPSIxMzUiIHkxPSIyODAiIHgyPSIxMzUiIHkyPSIyMDAiIHN0cm9rZT0iI0IzOUREQiIgc3Ryb2tlLXdpZHRoPSIzIiBvcGFjaXR5PSIwLjUiLz4KICA8bGluZSB4MT0iMjA0IiB5MT0iMjM1IiB4Mj0iMTM1IiB5Mj0iMjAwIiBzdHJva2U9IiNCMzlEREIiIHN0cm9rZS13aWR0aD0iMyIgb3BhY2l0eT0iMC41Ii8+CiAgPGxpbmUgeDE9IjIwNCIgeTE9IjE2NSIgeDI9IjEzNSIgeTI9IjIwMCIgc3Ryb2tlPSIjQjM5RERCIiBzdHJva2Utd2lkdGg9IjMiIG9wYWNpdHk9IjAuNSIvPgogIDxjaXJjbGUgY3g9IjEzNSIgY3k9IjIwMCIgcj0iMTgiIGZpbGw9IiNCMzlEREIiIG9wYWNpdHk9IjAuMTUiLz4KICA8Y2lyY2xlIGN4PSIxMzUiIGN5PSIyMDAiIHI9IjgiICBmaWxsPSIjQjM5RERCIiBvcGFjaXR5PSIwLjUiLz4KICA8IS0tIFJpZ2h0IHJlZWwgKHNtYWxsZXIg4oCUIGxlc3MgdGFwZSkgLS0+CiAgPGNpcmNsZSBjeD0iMjc1IiBjeT0iMjAwIiByPSI2NSIgZmlsbD0iIzFhMWEyZSIgc3Ryb2tlPSIjQjM5RERCIiBzdHJva2Utd2lkdGg9IjIiLz4KICA8Y2lyY2xlIGN4PSIyNzUiIGN5PSIyMDAiIHI9IjUyIiBmaWxsPSJub25lIiBzdHJva2U9IiNCMzlEREIiIHN0cm9rZS13aWR0aD0iMSIgb3BhY2l0eT0iMC4zIi8+CiAgPGNpcmNsZSBjeD0iMjc1IiBjeT0iMjAwIiByPSIzOCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjQjM5RERCIiBzdHJva2Utd2lkdGg9IjEiIG9wYWNpdHk9IjAuMiIvPgogIDxsaW5lIHgxPSIyNzUiIHkxPSIxMzUiIHgyPSIyNzUiIHkyPSIyMDAiIHN0cm9rZT0iI0IzOUREQiIgc3Ryb2tlLXdpZHRoPSIzIiBvcGFjaXR5PSIwLjUiLz4KICA8bGluZSB4MT0iMjE4IiB5MT0iMTY3IiB4Mj0iMjc1IiB5Mj0iMjAwIiBzdHJva2U9IiNCMzlEREIiIHN0cm9rZS13aWR0aD0iMyIgb3BhY2l0eT0iMC41Ii8+CiAgPGxpbmUgeDE9IjIxOCIgeTE9IjIzMyIgeDI9IjI3NSIgeTI9IjIwMCIgc3Ryb2tlPSIjQjM5RERCIiBzdHJva2Utd2lkdGg9IjMiIG9wYWNpdHk9IjAuNSIvPgogIDxsaW5lIHgxPSIyNzUiIHkxPSIyNjUiIHgyPSIyNzUiIHkyPSIyMDAiIHN0cm9rZT0iI0IzOUREQiIgc3Ryb2tlLXdpZHRoPSIzIiBvcGFjaXR5PSIwLjUiLz4KICA8bGluZSB4MT0iMzMyIiB5MT0iMjMzIiB4Mj0iMjc1IiB5Mj0iMjAwIiBzdHJva2U9IiNCMzlEREIiIHN0cm9rZS13aWR0aD0iMyIgb3BhY2l0eT0iMC41Ii8+CiAgPGxpbmUgeDE9IjMzMiIgeTE9IjE2NyIgeDI9IjI3NSIgeTI9IjIwMCIgc3Ryb2tlPSIjQjM5RERCIiBzdHJva2Utd2lkdGg9IjMiIG9wYWNpdHk9IjAuNSIvPgogIDxjaXJjbGUgY3g9IjI3NSIgY3k9IjIwMCIgcj0iMTUiIGZpbGw9IiNCMzlEREIiIG9wYWNpdHk9IjAuMTUiLz4KICA8Y2lyY2xlIGN4PSIyNzUiIGN5PSIyMDAiIHI9IjciICBmaWxsPSIjQjM5RERCIiBvcGFjaXR5PSIwLjUiLz4KICA8IS0tIFRhcGUgcGF0aCBiZXR3ZWVuIHJlZWxzIC0tPgogIDxwYXRoIGQ9Ik0yMTMgMTg1IFEyMDUgMjAwIDIxMyAyMTUiIGZpbGw9Im5vbmUiIHN0cm9rZT0iI0IzOUREQiIgc3Ryb2tlLXdpZHRoPSIzIiBvcGFjaXR5PSIwLjYiLz4KICA8IS0tIFBsYXloZWFkIC0tPgogIDxyZWN0IHg9IjE5OCIgeT0iMTc4IiB3aWR0aD0iMTQiIGhlaWdodD0iNDQiIHJ4PSIzIiBmaWxsPSIjQjM5RERCIiBvcGFjaXR5PSIwLjI1Ii8+CiAgPHJlY3QgeD0iMjAzIiB5PSIxNzUiIHdpZHRoPSI0IiBoZWlnaHQ9IjUwIiByeD0iMiIgZmlsbD0iI0IzOUREQiIgb3BhY2l0eT0iMC42Ii8+CiAgPHRleHQgeD0iMjAwIiB5PSIzNTUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJtb25vc3BhY2UiIGZvbnQtc2l6ZT0iMTAiIGZpbGw9IiNCMzlEREIiIG9wYWNpdHk9IjAuNCIgbGV0dGVyLXNwYWNpbmc9IjQiPlNZTDwvdGV4dD4KPC9zdmc+", "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj4KICA8ZGVmcz4KICAgIDxyYWRpYWxHcmFkaWVudCBpZD0iYmc1IiBjeD0iNTAlIiBjeT0iNTAlIiByPSI2MCUiPgogICAgICA8c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjMGYyYTFhIi8+CiAgICAgIDxzdG9wIG9mZnNldD0iMTAwJSIgc3RvcC1jb2xvcj0iIzBGMEYxRSIvPgogICAgPC9yYWRpYWxHcmFkaWVudD4KICA8L2RlZnM+CiAgPHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9InVybCgjYmc1KSIvPgogIDwhLS0gQXJjIGJhbmQgLS0+CiAgPHBhdGggZD0iTSAxMDUgMjE1IEEgMTAwIDEwNSAwIDAgMSAyOTUgMjE1IiBmaWxsPSJub25lIiBzdHJva2U9IiM2OUQxN0UiIHN0cm9rZS13aWR0aD0iMTAiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIvPgogIDwhLS0gTGVmdCBlYXIgY3VwIC0tPgogIDxyZWN0IHg9IjcyIiB5PSIyMDUiIHdpZHRoPSI1MiIgaGVpZ2h0PSI3NSIgcng9IjIwIiBmaWxsPSIjMWEyZTFhIiBzdHJva2U9IiM2OUQxN0UiIHN0cm9rZS13aWR0aD0iMi41Ii8+CiAgPHJlY3QgeD0iODMiIHk9IjIxOCIgd2lkdGg9IjMwIiBoZWlnaHQ9IjUwIiByeD0iMTIiIGZpbGw9IiM2OUQxN0UiIG9wYWNpdHk9IjAuMTIiLz4KICA8Y2lyY2xlIGN4PSI5OCIgY3k9IjI0MiIgcj0iMTQiIGZpbGw9IiM2OUQxN0UiIG9wYWNpdHk9IjAuMiIvPgogIDxjaXJjbGUgY3g9Ijk4IiBjeT0iMjQyIiByPSI3IiAgZmlsbD0iIzY5RDE3RSIgb3BhY2l0eT0iMC41Ii8+CiAgPCEtLSBSaWdodCBlYXIgY3VwIC0tPgogIDxyZWN0IHg9IjI3NiIgeT0iMjA1IiB3aWR0aD0iNTIiIGhlaWdodD0iNzUiIHJ4PSIyMCIgZmlsbD0iIzFhMmUxYSIgc3Ryb2tlPSIjNjlEMTdFIiBzdHJva2Utd2lkdGg9IjIuNSIvPgogIDxyZWN0IHg9IjI4NyIgeT0iMjE4IiB3aWR0aD0iMzAiIGhlaWdodD0iNTAiIHJ4PSIxMiIgZmlsbD0iIzY5RDE3RSIgb3BhY2l0eT0iMC4xMiIvPgogIDxjaXJjbGUgY3g9IjMwMiIgY3k9IjI0MiIgcj0iMTQiIGZpbGw9IiM2OUQxN0UiIG9wYWNpdHk9IjAuMiIvPgogIDxjaXJjbGUgY3g9IjMwMiIgY3k9IjI0MiIgcj0iNyIgIGZpbGw9IiM2OUQxN0UiIG9wYWNpdHk9IjAuNSIvPgogIDwhLS0gQ2FibGUgLS0+CiAgPHBhdGggZD0iTSA5OCAyODAgUSA5OCAzMjAgMjAwIDMyMCBRIDMwMiAzMjAgMzAyIDI4MCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjNjlEMTdFIiBzdHJva2Utd2lkdGg9IjIuNSIgc3Ryb2tlLWRhc2hhcnJheT0iNiA0IiBvcGFjaXR5PSIwLjQiLz4KICA8IS0tIEphY2sgcGx1ZyAtLT4KICA8cmVjdCB4PSIxOTIiIHk9IjMxNSIgd2lkdGg9IjE2IiBoZWlnaHQ9IjMwIiByeD0iNSIgZmlsbD0iIzY5RDE3RSIgb3BhY2l0eT0iMC43Ii8+CiAgPGNpcmNsZSBjeD0iMjAwIiBjeT0iMzUwIiByPSI2IiBmaWxsPSIjNjlEMTdFIiBvcGFjaXR5PSIwLjkiLz4KICA8IS0tIFNvdW5kIHdhdmVzIGZyb20gY3VwcyAtLT4KICA8cGF0aCBkPSJNIDU4IDIyNSBRIDQ4IDI0MiA1OCAyNTgiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzY5RDE3RSIgc3Ryb2tlLXdpZHRoPSIyIiBvcGFjaXR5PSIwLjMiLz4KICA8cGF0aCBkPSJNIDQ0IDIxOCBRIDMwIDI0MiA0NCAyNjUiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzY5RDE3RSIgc3Ryb2tlLXdpZHRoPSIxLjUiIG9wYWNpdHk9IjAuMTUiLz4KICA8cGF0aCBkPSJNIDM0MiAyMjUgUSAzNTIgMjQyIDM0MiAyNTgiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzY5RDE3RSIgc3Ryb2tlLXdpZHRoPSIyIiBvcGFjaXR5PSIwLjMiLz4KICA8cGF0aCBkPSJNIDM1NiAyMTggUSAzNzAgMjQyIDM1NiAyNjUiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzY5RDE3RSIgc3Ryb2tlLXdpZHRoPSIxLjUiIG9wYWNpdHk9IjAuMTUiLz4KICA8dGV4dCB4PSIyMDAiIHk9IjE3MCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9Im1vbm9zcGFjZSIgZm9udC1zaXplPSIxMCIgZmlsbD0iIzY5RDE3RSIgb3BhY2l0eT0iMC40NSIgbGV0dGVyLXNwYWNpbmc9IjQiPlNZTDwvdGV4dD4KPC9zdmc+", "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj4KICA8ZGVmcz4KICAgIDxyYWRpYWxHcmFkaWVudCBpZD0iYmc2IiBjeD0iNTAlIiBjeT0iNTAlIiByPSI2MCUiPgogICAgICA8c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjMmUxZTBhIi8+CiAgICAgIDxzdG9wIG9mZnNldD0iMTAwJSIgc3RvcC1jb2xvcj0iIzBGMEYxRSIvPgogICAgPC9yYWRpYWxHcmFkaWVudD4KICA8L2RlZnM+CiAgPHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9InVybCgjYmc2KSIvPgogIDwhLS0gQ2VudGVyIHNwZWFrZXIgY29uZSAtLT4KICA8Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9Ijg1IiBmaWxsPSIjMWUxNDA4IiBzdHJva2U9IiNGRjhBNTAiIHN0cm9rZS13aWR0aD0iMi41Ii8+CiAgPGNpcmNsZSBjeD0iMjAwIiBjeT0iMjAwIiByPSI2OCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjRkY4QTUwIiBzdHJva2Utd2lkdGg9IjEuNSIgb3BhY2l0eT0iMC40Ii8+CiAgPGNpcmNsZSBjeD0iMjAwIiBjeT0iMjAwIiByPSI1MCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjRkY4QTUwIiBzdHJva2Utd2lkdGg9IjEuNSIgb3BhY2l0eT0iMC4zNSIvPgogIDxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iMzIiIGZpbGw9IiNGRjhBNTAiIG9wYWNpdHk9IjAuMTIiLz4KICA8Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjE4IiBmaWxsPSIjRkY4QTUwIiBvcGFjaXR5PSIwLjI1Ii8+CiAgPGNpcmNsZSBjeD0iMjAwIiBjeT0iMjAwIiByPSI4IiAgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC43Ii8+CiAgPCEtLSBSYWRpYXRpbmcgd2F2ZWZvcm0gYmFycyAtLT4KICA8IS0tIFRvcCAtLT4KICA8cmVjdCB4PSIxOTAiIHk9IjkwIiAgd2lkdGg9IjIwIiBoZWlnaHQ9IjM1IiByeD0iNSIgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC43Ii8+CiAgPHJlY3QgeD0iMTgwIiB5PSI5OCIgIHdpZHRoPSI4IiAgaGVpZ2h0PSIyMCIgcng9IjMiIGZpbGw9IiNGRjhBNTAiIG9wYWNpdHk9IjAuMzUiLz4KICA8cmVjdCB4PSIyMTIiIHk9Ijk4IiAgd2lkdGg9IjgiICBoZWlnaHQ9IjIwIiByeD0iMyIgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC4zNSIvPgogIDwhLS0gQm90dG9tIC0tPgogIDxyZWN0IHg9IjE5MCIgeT0iMjc1IiB3aWR0aD0iMjAiIGhlaWdodD0iMzUiIHJ4PSI1IiBmaWxsPSIjRkY4QTUwIiBvcGFjaXR5PSIwLjciLz4KICA8cmVjdCB4PSIxODAiIHk9IjI4MiIgd2lkdGg9IjgiICBoZWlnaHQ9IjIwIiByeD0iMyIgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC4zNSIvPgogIDxyZWN0IHg9IjIxMiIgeT0iMjgyIiB3aWR0aD0iOCIgIGhlaWdodD0iMjAiIHJ4PSIzIiBmaWxsPSIjRkY4QTUwIiBvcGFjaXR5PSIwLjM1Ii8+CiAgPCEtLSBMZWZ0IC0tPgogIDxyZWN0IHg9IjkwIiAgeT0iMTkwIiB3aWR0aD0iMzUiIGhlaWdodD0iMjAiIHJ4PSI1IiBmaWxsPSIjRkY4QTUwIiBvcGFjaXR5PSIwLjciLz4KICA8cmVjdCB4PSI5OCIgIHk9IjE4MCIgd2lkdGg9IjIwIiBoZWlnaHQ9IjgiICByeD0iMyIgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC4zNSIvPgogIDxyZWN0IHg9Ijk4IiAgeT0iMjEyIiB3aWR0aD0iMjAiIGhlaWdodD0iOCIgIHJ4PSIzIiBmaWxsPSIjRkY4QTUwIiBvcGFjaXR5PSIwLjM1Ii8+CiAgPCEtLSBSaWdodCAtLT4KICA8cmVjdCB4PSIyNzUiIHk9IjE5MCIgd2lkdGg9IjM1IiBoZWlnaHQ9IjIwIiByeD0iNSIgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC43Ii8+CiAgPHJlY3QgeD0iMjgyIiB5PSIxODAiIHdpZHRoPSIyMCIgaGVpZ2h0PSI4IiAgcng9IjMiIGZpbGw9IiNGRjhBNTAiIG9wYWNpdHk9IjAuMzUiLz4KICA8cmVjdCB4PSIyODIiIHk9IjIxMiIgd2lkdGg9IjIwIiBoZWlnaHQ9IjgiICByeD0iMyIgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC4zNSIvPgogIDwhLS0gRGlhZ29uYWwgYmFycyAtLT4KICA8cmVjdCB4PSIxMjgiIHk9IjEyMCIgd2lkdGg9IjIwIiBoZWlnaHQ9IjMwIiByeD0iNSIgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC40NSIgdHJhbnNmb3JtPSJyb3RhdGUoNDUgMTM4IDEzNSkiLz4KICA8cmVjdCB4PSIyNTIiIHk9IjEyMCIgd2lkdGg9IjIwIiBoZWlnaHQ9IjMwIiByeD0iNSIgZmlsbD0iI0ZGOEE1MCIgb3BhY2l0eT0iMC40NSIgdHJhbnNmb3JtPSJyb3RhdGUoLTQ1IDI2MiAxMzUpIi8+CiAgPHJlY3QgeD0iMTI4IiB5PSIyNTAiIHdpZHRoPSIyMCIgaGVpZ2h0PSIzMCIgcng9IjUiIGZpbGw9IiNGRjhBNTAiIG9wYWNpdHk9IjAuNDUiIHRyYW5zZm9ybT0icm90YXRlKC00NSAxMzggMjY1KSIvPgogIDxyZWN0IHg9IjI1MiIgeT0iMjUwIiB3aWR0aD0iMjAiIGhlaWdodD0iMzAiIHJ4PSI1IiBmaWxsPSIjRkY4QTUwIiBvcGFjaXR5PSIwLjQ1IiB0cmFuc2Zvcm09InJvdGF0ZSg0NSAyNjIgMjY1KSIvPgogIDx0ZXh0IHg9IjIwMCIgeT0iMzgwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlIiBmb250LXNpemU9IjEwIiBmaWxsPSIjRkY4QTUwIiBvcGFjaXR5PSIwLjQiIGxldHRlci1zcGFjaW5nPSI0Ij5TWUw8L3RleHQ+Cjwvc3ZnPg=="];


/* Album data — backtick strings used for comments to safely handle apostrophes.
   In Phase 2 this comes from PHP+MySQL: SELECT a.*,s.title,u.username,r.notes,r.rating
   FROM reviews r JOIN songs s ON r.song_id=s.song_id JOIN users u ON r.user_id=u.user_id
   JOIN albums a ON s.album_id=a.album_id ORDER BY r.created_at DESC */
const ALBUMS = [
  {name:"GNX",artist:"Kendrick Lamar",art:0,genre:"Hip-Hop",gc:"#F5E642",glow:"#F5E642",rc:284,avg:4.9,
   listeners:[
    {e:"🎧",u:"joshm",  s:"luther",             c:`best collab in years, i've replayed this at least 50 times no exaggeration`,       r:"★★★★★",col:"#F5E642",pos:{t:"8%", l:"68%"}},
    {e:"🎸",u:"maya_r", s:"squabble up",         c:`this beat is genuinely unhinged and i mean that as the highest compliment`,         r:"★★★★★",col:"#4DD0C4",pos:{t:"55%",l:"-9%"}},
    {e:"🎤",u:"dre_k",  s:"peekaboo",            c:`the wordplay is on another level, took three full listens to catch everything`,      r:"★★★★★",col:"#F06292",pos:{t:"78%",l:"70%"}},
    {e:"🌟",u:"lena_v", s:"man at the garden",   c:`not a single skip, this is truly an event album not just a playlist album`,          r:"★★★★★",col:"#B39DDB",pos:{t:"15%",l:"-11%"}},
  ]},
  {name:"Short n Sweet",artist:"Sabrina Carpenter",art:1,genre:"Pop",gc:"#F06292",glow:"#F06292",rc:312,avg:4.7,
   listeners:[
    {e:"💅",u:"ash_c",  s:"Espresso",            c:`this song lives in my head rent free and i've completely stopped trying to evict it`,r:"★★★★★",col:"#F06292",pos:{t:"10%",l:"65%"}},
    {e:"🌸",u:"nina_b", s:"Please Please Please", c:`the production is so clean and her voice has never sounded better than this`,       r:"★★★★½",col:"#F5E642",pos:{t:"70%",l:"-9%"}},
    {e:"🎀",u:"cam_w",  s:"Taste",               c:`bop after bop. she did not miss once on this whole record`,                          r:"★★★★★",col:"#4DD0C4",pos:{t:"80%",l:"67%"}},
    {e:"✨",u:"riley_t",s:"Bed Chem",             c:`the bridge made me put my phone down and just sit with it for a full minute`,        r:"★★★★★",col:"#B39DDB",pos:{t:"12%",l:"-9%"}},
  ]},
  {name:"Tortured Poets Dept.",artist:"Taylor Swift",art:2,genre:"Alternative",gc:"#B39DDB",glow:"#B39DDB",rc:501,avg:4.5,
   listeners:[
    {e:"📖",u:"kira_s", s:"Fortnight",           c:`post malone on a taylor track wasn't on my bingo card but here we are and it works`, r:"★★★★★",col:"#B39DDB",pos:{t:"8%", l:"65%"}},
    {e:"🌙",u:"tommy_f",s:"But Daddy I Love Him", c:`the most unhinged lyrics she has ever written and i respect it deeply`,              r:"★★★★½",col:"#F5E642",pos:{t:"72%",l:"-9%"}},
    {e:"🕯️",u:"zoe_p", s:"The Smallest Man",    c:`this is a 10 minute therapy session wrapped in a single pop song`,                   r:"★★★★★",col:"#4DD0C4",pos:{t:"78%",l:"69%"}},
    {e:"🎻",u:"alex_m", s:"Clara Bow",           c:`connecting the album's through-line in the final track is pure genius`,               r:"★★★★★",col:"#F06292",pos:{t:"14%",l:"-11%"}},
  ]},
  {name:"Chromakopia",artist:"Tyler the Creator",art:3,genre:"Neo-Soul",gc:"#69D17E",glow:"#69D17E",rc:198,avg:4.8,
   listeners:[
    {e:"🌱",u:"dez_o",  s:"Noid",               c:`tyler made a paranoia anthem and accidentally dropped the song of the year`,           r:"★★★★★",col:"#69D17E",pos:{t:"8%", l:"67%"}},
    {e:"🎺",u:"pharr_j",s:"St. Chroma",          c:`the horns on this intro are absolutely unreal, the production is otherworldly`,        r:"★★★★★",col:"#FF8A50",pos:{t:"70%",l:"-9%"}},
    {e:"🦋",u:"mika_l", s:"Sticky",              c:`this one hits different at 2am. there's something genuinely haunting about it`,        r:"★★★★½",col:"#F5E642",pos:{t:"80%",l:"67%"}},
    {e:"🌊",u:"ben_r",  s:"I Killed You",        c:`tyler this vulnerable with production perfectly mirroring the emotion is stunning`,     r:"★★★★★",col:"#4DD0C4",pos:{t:"14%",l:"-9%"}},
  ]},
  {name:"Eternal Sunshine",artist:"Ariana Grande",art:4,genre:"R&B / Pop",gc:"#5B9BF5",glow:"#5B9BF5",rc:267,avg:4.4,
   listeners:[
    {e:"🌈",u:"viv_k",  s:"we cant be friends",  c:`the way this transitions from the last track is seamless, she planned every second`,  r:"★★★★★",col:"#5B9BF5",pos:{t:"9%", l:"65%"}},
    {e:"💙",u:"sara_t", s:"the boy is mine",      c:`mariah sample plus ari vocals is the most unstoppable combo in pop music right now`,   r:"★★★★½",col:"#F06292",pos:{t:"72%",l:"-9%"}},
    {e:"⛅",u:"noel_g", s:"eternal sunshine",     c:`felt like floating. one of those albums you just close your eyes and sink into`,        r:"★★★★★",col:"#F5E642",pos:{t:"78%",l:"69%"}},
    {e:"🎵",u:"jade_m", s:"imperfect for you",    c:`wrote this in my journal. i don't want to explain it, just need everyone to hear it`,  r:"★★★★★",col:"#B39DDB",pos:{t:"13%",l:"-11%"}},
  ]},
  {name:"Diamond Jubilee",artist:"Cindy Lee",art:5,genre:"Experimental",gc:"#FF8A50",glow:"#FF8A50",rc:89,avg:4.9,
   listeners:[
    {e:"🔮",u:"wren_c", s:"Diamond Jubilee",      c:`this 32 track google drive album changed the way i think about music entirely`,        r:"★★★★★",col:"#FF8A50",pos:{t:"8%", l:"67%"}},
    {e:"🌀",u:"oz_f",   s:"Golden Angel",          c:`sounds like a transmission from another dimension. terrifying and beautiful at once`,   r:"★★★★★",col:"#4DD0C4",pos:{t:"70%",l:"-9%"}},
    {e:"🫧",u:"rue_h",  s:"Flesh and Blood",       c:`the most important album of the year that most people will never find`,                 r:"★★★★★",col:"#B39DDB",pos:{t:"80%",l:"67%"}},
    {e:"🌌",u:"finn_s", s:"He Was a Friend",       c:`cried twice. will not be taking questions`,                                              r:"★★★★★",col:"#F5E642",pos:{t:"14%",l:"-9%"}},
  ]},
];

const MAX_C = 72;
const trunc = s => s.length <= MAX_C ? {t:s,cut:false} : {t:s.slice(0,MAX_C).trimEnd()+'...',cut:true};

function renderAlbums(){
  const grid = document.getElementById('albumsGrid');
  ALBUMS.forEach((a,ai) => {
    const card = document.createElement('div');
    card.className = 'acard';
    card.innerHTML = `
      <div class="awrap">
        <img class="aart" src="${ART[a.art]}" alt="${a.name}">
        <div class="aglow" style="background:${a.glow};"></div>
        <div class="aoverlay"></div>
        <div class="ahover"><div class="ahovname">${a.name}</div><div class="ahovart">${a.artist}</div></div>
        ${a.listeners.map((l,i)=>`
          <div class="orb" data-a="${ai}" data-l="${i}" style="top:${l.pos.t};left:${l.pos.l};border-color:${l.col}44;background:${l.col}15;">${l.e}</div>
        `).join('')}
        ${a.listeners.map((l,i)=>{
          const tp=parseFloat(l.pos.t),lp=parseFloat(l.pos.l);
          const ac=tp<40?'au':'ad';
          const bt=tp<40?`calc(${l.pos.t} + 50px)`:`calc(${l.pos.t} - 150px)`;
          const bl=lp<0?'4px':lp>60?'auto':l.pos.l;
          const br=lp>60?'4px':'auto';
          const {t,cut}=trunc(l.c);
          return `
            <div class="cbub ${ac}" id="b-${ai}-${i}" style="top:${bt};left:${bl};right:${br};">
              <div class="buser">
                <div class="bav" style="background:${l.col}22;border:1.5px solid ${l.col}55;">${l.e}</div>
                <div><div class="bun">@${l.u}</div><div class="bsong">on &ldquo;${l.s}&rdquo;</div></div>
              </div>
              <div class="bdiv"></div>
              <div class="bcom">&ldquo;${t}&rdquo;</div>
              ${cut?`<span class="brm" data-a="${ai}" data-l="${i}">read more →</span>`:''}
              <div class="brat">${l.r}</div>
            </div>`;
        }).join('')}
      </div>
      <div class="ameta">
        <div class="aname">${a.name}</div>
        <div class="aartist">${a.artist}</div>
        <div class="astats">
          <div class="astat"><span>${a.rc}</span> reviews</div>
          <div class="astat"><span>${a.avg}</span> avg</div>
          <div class="gpill" style="background:${a.gc}20;color:${a.gc};border:1px solid ${a.gc}44;">${a.genre}</div>
        </div>
      </div>`;
    grid.appendChild(card);
  });

  document.querySelectorAll('.orb').forEach(o => {
    const b = document.getElementById(`b-${o.dataset.a}-${o.dataset.l}`);
    o.addEventListener('mouseenter', () => {
      document.querySelectorAll('.cbub').forEach(x => x.classList.remove('vis'));
      b.classList.add('vis');
    });
    o.addEventListener('mouseleave', e => { if(!b.contains(e.relatedTarget)) b.classList.remove('vis'); });
  });

  document.addEventListener('click', e => {
    if(e.target.classList.contains('brm')) openSong(+e.target.dataset.a, +e.target.dataset.l);
  });
}

function openSong(ai, li){
  const a=ALBUMS[ai], l=a.listeners[li];
  document.getElementById('song-alb').textContent    = a.name+' \u2014 '+a.artist;
  document.getElementById('song-title').textContent  = l.s;
  document.getElementById('song-artist').textContent = a.artist;
  document.getElementById('song-revs').textContent   = a.rc;
  document.getElementById('song-avg').textContent    = a.avg;
  document.getElementById('song-art').src            = ART[a.art];
  document.getElementById('song-art').alt            = a.name;
  document.getElementById('song-clist').innerHTML    = a.listeners.map((c,i)=>`
    <div class="cc ${i===li?'hl':''}">
      <div class="ccu">
        <div class="ccav" style="background:${c.col}22;border:2px solid ${c.col}55;">${c.e}</div>
        <div><div class="ccn">@${c.u}</div><div class="cch">on &ldquo;${c.s}&rdquo;</div></div>
      </div>
      <div class="cct">&ldquo;${c.c}&rdquo;</div>
      <div class="ccr">${c.r}</div>
    </div>`).join('');
  document.getElementById('guest-p').style.display = isLoggedIn?'none':'block';
  navTo('song');
}

const PAGES=['home','song','songs','friends','community','settings'];
function navTo(id){
  PAGES.forEach(p=>{ const el=document.getElementById('page-'+p); if(el) el.classList.remove('active'); });
  const t=document.getElementById('page-'+id); if(t) t.classList.add('active');
  document.querySelectorAll('.sb-link').forEach(l=>l.classList.toggle('active', l.dataset.label.toLowerCase()===id));
  window.scrollTo(0,0);
}

let sbOpen=true;
function toggleSidebar(){
  sbOpen=!sbOpen;
  document.getElementById('sidebar').classList.toggle('col',!sbOpen);
  document.body.classList.toggle('sb-col',!sbOpen);
}

const isLoggedIn = <?= $isLoggedIn ? "true" : "false" ?>;
function goAuth(tab){ alert('TODO: navigate to auth.php?tab='+tab); }
function doLogout(){
  isLoggedIn=false;
  document.getElementById('nav-out').style.display='flex';
  document.getElementById('nav-in').style.display='none';
  navTo('home');
}

renderAlbums();
</script>
</body>
</html>