<?php
//this provides this file access to the database
require_once 'db.php';

// --- GET PUBLIC USER INFO ---
//call this function to actually access the information of a user using their tag (for example, @elonmusk)
function getUserInfo($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT tag, first_name, last_name, bio, picture,
               fav_song_1, fav_song_2, fav_song_3, fav_song_4
        FROM Users
        WHERE tag = ?
    ");
    $stmt->execute([$tag]);
    return $stmt->fetch(); // returns one row, or false if not found
}

// --- GET ALL REVIEWS FOR A USER ---
//call this function to get an array of all of the users reviews. used for showing each review one after the other
//returns most recent reviews first
function getUserReviews($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.song_name, r.artist_name, r.album_name,
               r.duration, r.rating, r.review, r.review_date
        FROM Reviews r
        JOIN UserReviews ur ON r.id = ur.review_id
        WHERE ur.user_tag = ?
        ORDER BY r.review_date DESC
    ");
    $stmt->execute([$tag]);
    return $stmt->fetchAll();
}

// --- GET REVIEW COUNT THIS MONTH ---
// returns a number of the amount of reviews in this current month by this user
function getUserReviewCountThisMonth($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM Reviews r
        JOIN UserReviews ur ON r.id = ur.review_id
        WHERE ur.user_tag = ?
        AND MONTH(r.review_date) = MONTH(CURDATE())
        AND YEAR(r.review_date)  = YEAR(CURDATE())
    ");
    $stmt->execute([$tag]);
    return $stmt->fetch()['total'];
}

// --- GET REVIEWS SORTED BY HIGHEST RATING ---
// returns the reviews sorted by highest rating instead of recency
function getUserReviewsByRating($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.song_name, r.artist_name, r.album_name,
               r.duration, r.rating, r.review, r.review_date
        FROM Reviews r
        JOIN UserReviews ur ON r.id = ur.review_id
        WHERE ur.user_tag = ?
        ORDER BY r.rating DESC
    ");
    $stmt->execute([$tag]);
    return $stmt->fetchAll();
}

// --- GET REVIEWS FROM THIS WEEK ---
// returns only the reviews that were made in the last 7 days
function getUserReviewsThisWeek($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.song_name, r.artist_name, r.album_name,
               r.duration, r.rating, r.review, r.review_date
        FROM Reviews r
        JOIN UserReviews ur ON r.id = ur.review_id
        WHERE ur.user_tag = ?
        AND r.review_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY r.review_date DESC
    ");
    $stmt->execute([$tag]);
    return $stmt->fetchAll();
}

// --- GET TOTAL REVIEW COUNT ---
//returns the total number of reviews a user has
function getUserReviewCount($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM UserReviews
        WHERE user_tag = ?
    ");
    $stmt->execute([$tag]);
    return $stmt->fetch()['total'];
}

// --- GET AVERAGE RATING ---
//gets the average rating of all of the users reviews
function getUserAverageRating($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT ROUND(AVG(r.rating), 2) AS average
        FROM Reviews r
        JOIN UserReviews ur ON r.id = ur.review_id
        WHERE ur.user_tag = ?
    ");
    $stmt->execute([$tag]);
    return $stmt->fetch()['average'];
}

// --- GET FAVORITE SONGS INFO ---
// gets the info corresponding to the 4 favorite songs the user has picked, in favorite order
function getUserFavorites($pdo, $tag) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.song_name, r.artist_name, r.album_name,
               r.duration, r.rating, r.review, r.review_date,
               CASE u.fav_song_1 WHEN r.id THEN 1
                    WHEN u.fav_song_2 THEN 2
                    WHEN u.fav_song_3 THEN 3
                    WHEN u.fav_song_4 THEN 4
               END AS fav_order
        FROM Users u
        JOIN Reviews r ON r.id IN (u.fav_song_1, u.fav_song_2, u.fav_song_3, u.fav_song_4)
        WHERE u.tag = ?
        ORDER BY fav_order ASC
    ");
    $stmt->execute([$tag]);
    return $stmt->fetchAll();
}

// --- CREATE A REVIEW ---
// pass all the necessary information from the user to the database to add a review linked to that user
function createReview($pdo, $tag, $song, $artist, $album, $duration, $rating, $review) {
    // Insert into Reviews first to get the new ID
    $stmt = $pdo->prepare("
        INSERT INTO Reviews (song_name, artist_name, album_name, duration, rating, review)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$song, $artist, $album, $duration, $rating, $review]);
    $reviewId = $pdo->lastInsertId();

    // Then link it to the user in UserReviews
    $stmt = $pdo->prepare("
        INSERT INTO UserReviews (user_tag, review_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$tag, $reviewId]);

    return $reviewId;
}

// --- EXAMPLE USAGE ---
// Replace the hardcoded values with the actual user input from an html form
$user    = getUserInfo($pdo, 'someuser');
$reviews = getUserReviews($pdo, 'someuser');
$newId   = createReview($pdo, 'someuser', 'Bohemian Rhapsody', 'Queen', 'A Night at the Opera', 354, 10, 'A masterpiece.');
?>