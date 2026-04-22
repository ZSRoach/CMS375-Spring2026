-- Database Initialization
CREATE DATABASE IF NOT EXISTS music_reviews;
USE music_reviews;

-- Users Table
CREATE TABLE Users (
    tag         VARCHAR(32)     NOT NULL,
    first_name  VARCHAR(50)     NOT NULL,
    last_name   VARCHAR(50)     NOT NULL,
    email       VARCHAR(255)    NOT NULL UNIQUE,
    pw_hash     CHAR(60)        NOT NULL,
    bio         VARCHAR(200)    DEFAULT NULL,
    picture     MEDIUMBLOB      DEFAULT NULL,

    PRIMARY KEY (tag)
);

-- Reviews Table
CREATE TABLE Reviews (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    song_name   VARCHAR(255)    NOT NULL,
    artist_name VARCHAR(255)    NOT NULL,
    album_name  VARCHAR(255)    DEFAULT NULL,
    duration    SMALLINT        NOT NULL COMMENT 'time stored in seconds',
    rating      TINYINT         NOT NULL CHECK (rating BETWEEN 1 AND 10),
    review      VARCHAR(500)    DEFAULT NULL,
    review_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
);

-- UserReviews Table
CREATE TABLE UserReviews (
    user_tag    VARCHAR(32)     NOT NULL,
    review_id   INT UNSIGNED    NOT NULL,

    PRIMARY KEY (user_tag, review_id),
    CONSTRAINT forkey_ur_user   FOREIGN KEY (user_tag)  REFERENCES Users(tag)
                                ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT forkey_ur_review FOREIGN KEY (review_id) REFERENCES Reviews(id)
                                ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE UserFavorites (
    user_tag    VARCHAR(32)     NOT NULL,
    review_id   INT UNSIGNED    NOT NULL,
    rank        TINYINT         NOT NULL CHECK (rank BETWEEN 1 AND 4),

    PRIMARY KEY (user_tag, rank),
    CONSTRAINT forkey_uf_user   FOREIGN KEY (user_tag)  REFERENCES  Users(tag)
                                ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT forkey_uf_review FOREIGN KEY (review_id) REFERENCES Reviews(id)
                                ON UPDATE CASCADE ON DELETE CASCADE
);