DELIMITER $$
CREATE PROCEDURE `daily_race_comment_history_UPDATE`()
BEGIN
DROP TABLE IF EXISTS daily_race_comment_history;

-- Create target table with keys to avoid DISTINCT/ORDER BY and speed up writes
CREATE TABLE daily_race_comment_history (
    runner_id INT NOT NULL,
    race_id INT NOT NULL,
    meeting_date DATE,
    race_type VARCHAR(50),
    going VARCHAR(50),
    class VARCHAR(20),
    name VARCHAR(100),
    form_figures VARCHAR(50),
    finish_position INT,
    distance_beaten DECIMAL(8,2),
    in_race_comment TEXT,
    official_rating INT,
    speed_rating INT,
    wt_speed_rating DECIMAL(8,2),
    legacy_speed_rating INT,
    PRIMARY KEY (runner_id, race_id),
    INDEX idx_meeting_date (meeting_date)
);

-- Insert with exact join keys to prevent row explosion; no DISTINCT/ORDER BY needed
INSERT IGNORE INTO daily_race_comment_history (
    runner_id, race_id, meeting_date, race_type, going, class, name, form_figures,
    finish_position, distance_beaten, in_race_comment, official_rating, speed_rating,
    wt_speed_rating, legacy_speed_rating
)
SELECT STRAIGHT_JOIN
    hrunb.runner_id,
    dch.race_id,
    hracb.meeting_date,
    hracb.race_type,
    hracb.going,
    hracb.class,
    hrunb.name,
    hrunb.form_figures,
    hrunb.finish_position,
    hrunb.distance_beaten,
    hrunb.in_race_comment,
    hrunb.official_rating,
    sr.speed_rating,
    ROUND(sr.wt_speed_rating, 2) AS wt_speed_rating,
    hrunb.legacy_speed_rating
FROM daily_comment_history dch
JOIN historic_runners_beta hrunb
  ON dch.runner_id = hrunb.runner_id AND dch.race_id = hrunb.race_id
LEFT JOIN historic_races_beta hracb
  ON dch.race_id = hracb.race_id
LEFT JOIN sr_results sr
  ON dch.race_id = sr.race_id AND dch.runner_id = sr.runner_id;
END $$
DELIMITER ;