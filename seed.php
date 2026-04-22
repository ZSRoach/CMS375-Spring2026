<?php
require_once 'db.php';

$users = [
    ['zachary', 'Zachary', 'R', 'zachary@example.com', 'zachary1', 'big kglw fan'],
    ['josh',    'Josh',    'M', 'josh@example.com',    'josh1',    'all things bruno mars'],
    ['aaron',   'Aaron',   'B', 'aaron@example.com',   'aaron1',   '80s rock forever'],
    ['ella',    'Ella',    'S', 'ella@example.com',    'ella1',    'swiftie for life'],
    ['sasha',   'Sasha',   'K', 'sasha@example.com',   'sasha1',   'vibes only'],
];

// [user_tag, song_name, artist_name, album_name, duration_secs, rating, review, days_ago]
$reviews = [
    // Zachary — King Gizzard & the Lizard Wizard, Nonagon Infinity
    ['zachary', 'Robot Stop',             'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 206, 9,  'Perfect opener, locks you in immediately.',            13],
    ['zachary', 'Big Fig Wasp',           'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 265, 8,  'Hypnotic riff, impossible to stop listening.',         13],
    ['zachary', 'People-Vultures',        'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 260, 9,  'Hits different every single listen.',                  12],
    ['zachary', 'Mr. Beat',               'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 195, 7,  'Solid but the shortest highlight on the album.',       12],
    ['zachary', 'Evil Death Roll',        'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 310, 10, 'Peak Gizzard. Absolutely relentless.',                 11],
    ['zachary', 'Invisible Face',         'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 223, 8,  'Great transition track, criminally underrated.',       11],
    ['zachary', 'Wah Wah',                'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 243, 8,  'The wah pedal work here is insane.',                   10],
    ['zachary', 'Road Train',             'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 256, 9,  'Feels like being chased. Love it.',                   10],
    ['zachary', "I'm in Your Mind Fuzz",  'King Gizzard & the Lizard Wizard', 'Nonagon Infinity', 369, 10, 'The loop closing back to Robot Stop is pure genius.',   9],

    // Josh — Bruno Mars, 24K Magic
    ['josh', '24K Magic',               'Bruno Mars', '24K Magic', 226, 9,  'Instant classic. Best intro track in years.',           8],
    ['josh', 'Chunky',                  'Bruno Mars', '24K Magic', 217, 8,  'This groove is absolutely ridiculous.',                 8],
    ['josh', 'Perm',                    'Bruno Mars', '24K Magic', 241, 9,  'Makes you want to get up immediately.',                 7],
    ['josh', "That's What I Like",      'Bruno Mars', '24K Magic', 206, 10, 'Perfect pop song. No notes.',                          7],
    ['josh', 'Versace on the Floor',    'Bruno Mars', '24K Magic', 254, 9,  'Smooth, romantic, timeless.',                          6],
    ['josh', 'Straight Up & Down',      'Bruno Mars', '24K Magic', 229, 7,  'Good filler but not a standout.',                      6],
    ['josh', 'Calling All My Lovelies', 'Bruno Mars', '24K Magic', 253, 8,  'Underrated cut from this album.',                      5],
    ['josh', 'Finesse',                 'Bruno Mars', '24K Magic', 206, 10, 'The Cardi B remix pushed this over the top.',           5],
    ['josh', 'Too Good to Say Goodbye', 'Bruno Mars', '24K Magic', 245, 9,  'Beautiful closer that shows his full vocal range.',     4],

    // Aaron — 80s rock
    ['aaron', 'Jump',                "Van Halen",       'Van Halen',                243, 10, 'The synth riff that defined a decade.',                      9],
    ['aaron', "Sweet Child O' Mine", "Guns N' Roses",   'Appetite for Destruction', 356, 10, "Slash's intro is the greatest guitar moment ever recorded.", 8],
    ['aaron', "Don't Stop Believin'","Journey",         'Escape',                   249, 9,  'Still gets the whole room singing every time.',             7],
    ['aaron', "Livin' on a Prayer",  'Bon Jovi',        'Slippery When Wet',        249, 9,  'The key change alone earns this a 10.',                     6],
    ['aaron', 'Eye of the Tiger',    'Survivor',        'Eye of the Tiger',         245, 8,  'Rocky III gave us this forever banger.',                    5],

    // Ella — Taylor Swift, 1989
    ['ella', 'Welcome to New York',       'Taylor Swift', '1989', 212, 7,  'Fun opener but not her strongest work.',              7],
    ['ella', 'Blank Space',               'Taylor Swift', '1989', 231, 10, 'Self-aware era Taylor at her absolute best.',         7],
    ['ella', 'Style',                     'Taylor Swift', '1989', 231, 10, 'Cinematic and completely timeless.',                  6],
    ['ella', 'Out of the Woods',          'Taylor Swift', '1989', 235, 9,  'The anxiety baked into the production is so good.',   6],
    ['ella', 'All You Had to Do Was Stay','Taylor Swift', '1989', 195, 8,  'Underrated. The hook is massive.',                    5],
    ['ella', 'Shake It Off',              'Taylor Swift', '1989', 219, 8,  'Pure fun. Impossible to hate.',                       5],
    ['ella', 'I Wish You Would',          'Taylor Swift', '1989', 207, 7,  'Good album track, nothing more.',                     4],
    ['ella', 'Bad Blood',                 'Taylor Swift', '1989', 212, 7,  'The music video outshined the song.',                 4],
    ['ella', 'Wildest Dreams',            'Taylor Swift', '1989', 220, 9,  'Most underrated song on this whole album.',           3],
    ['ella', 'How You Get the Girl',      'Taylor Swift', '1989', 247, 8,  'Criminally underrated, should have been a single.',   3],
    ['ella', 'Clean',                     'Taylor Swift', '1989', 271, 10, 'Emotionally destroyed me. Perfect album closer.',     2],
    ['ella', 'Wonderland',                'Taylor Swift', '1989', 241, 8,  'Bonus track that deserved to be on the main album.',  1],

    // Sasha — random picks
    ['sasha', 'Levitating',       'Dua Lipa',       'Future Nostalgia',                     203, 9,  'Never gets old. Perfect pop production.',             6],
    ['sasha', 'Blinding Lights',  'The Weeknd',     'After Hours',                          200, 10, 'Song of the 2020s. Not up for debate.',               5],
    ['sasha', 'As It Was',        'Harry Styles',   "Harry's House",                        157, 9,  'Bittersweet and beautiful.',                          4],
    ['sasha', 'Bad Guy',          'Billie Eilish',  'When We All Fall Asleep, Where Do We Go?', 194, 9, 'Changed what pop music could sound like.',         3],
    ['sasha', 'Watermelon Sugar', 'Harry Styles',   'Fine Line',                            174, 8,  'Summer in a song.',                                   2],
    ['sasha', 'drivers license',  'Olivia Rodrigo', 'SOUR',                                 242, 10, 'Cried on the first listen. No shame.',                1],
];

$insertedUsers   = 0;
$insertedReviews = 0;
$skipped         = 0;

foreach ($users as [$tag, $first, $last, $email, $pw, $bio]) {
    $check = $pdo->prepare("SELECT tag FROM Users WHERE tag=?");
    $check->execute([$tag]);
    if ($check->fetch()) { $skipped++; continue; }
    $hash = password_hash($pw, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO Users (tag,first_name,last_name,email,pw_hash,bio) VALUES (?,?,?,?,?,?)")
        ->execute([$tag, $first, $last, $email, $hash, $bio]);
    $insertedUsers++;
}

foreach ($reviews as [$tag, $song, $artist, $album, $dur, $rating, $review, $daysAgo]) {
    $check = $pdo->prepare("SELECT tag FROM Users WHERE tag=?");
    $check->execute([$tag]);
    if (!$check->fetch()) continue;

    $dup = $pdo->prepare("
        SELECT r.id FROM Reviews r JOIN UserReviews ur ON r.id=ur.review_id
        WHERE ur.user_tag=? AND r.song_name=? AND r.artist_name=? LIMIT 1
    ");
    $dup->execute([$tag, $song, $artist]);
    if ($dup->fetch()) { $skipped++; continue; }

    $date = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
    $pdo->prepare("INSERT INTO Reviews (song_name,artist_name,album_name,duration,rating,review,review_date) VALUES (?,?,?,?,?,?,?)")
        ->execute([$song, $artist, $album, $dur, $rating, $review, $date]);
    $id = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO UserReviews (user_tag,review_id) VALUES (?,?)")->execute([$tag, $id]);
    $insertedReviews++;
}

echo "<pre>";
echo "Users inserted:   $insertedUsers\n";
echo "Reviews inserted: $insertedReviews\n";
echo "Skipped (already exist): $skipped\n\n";
echo "Passwords:\n";
foreach ($users as [$tag, , , , $pw]) {
    echo "  @$tag → $pw\n";
}
echo "</pre>";
?>
