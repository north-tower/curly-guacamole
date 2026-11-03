-- Mordin seed tables: class matrix, northern course prefixes, trainer yards.
-- Re-runnable: REPLACE / INSERT IGNORE preserve manually added rows elsewhere.
-- Trainer names must match historic_runners_beta.trainer_name exactly (see mordin_class_NOTES.md).

CREATE TABLE IF NOT EXISTS mordin_class_matrix (
    tier_code VARCHAR(12) NOT NULL,
    comp_level TINYINT NOT NULL,
    mcr DECIMAL(5,1) NOT NULL,
    PRIMARY KEY (tier_code, comp_level)
);

REPLACE INTO mordin_class_matrix (tier_code, comp_level, mcr) VALUES
('G1',1,87),('G1',2,87),('G1',3,88),('G1',4,89),('G1',5,90),
('G2',1,85),('G2',2,86),('G2',3,87),('G2',4,87),('G2',5,88),
('G3',1,81),('G3',2,84),('G3',3,86),('G3',4,86),('G3',5,86),
('LISTED',1,80),('LISTED',2,82),('LISTED',3,85),('LISTED',4,86),('LISTED',5,86),
('C2_OPEN',1,80),('C2_OPEN',2,82),('C2_OPEN',3,85),('C2_OPEN',4,86),('C2_OPEN',5,86),
('C2_HIGH',1,78),('C2_HIGH',2,80),('C2_HIGH',3,82),('C2_HIGH',4,84),('C2_HIGH',5,85),
('C2_MID',1,77),('C2_MID',2,79),('C2_MID',3,81),('C2_MID',4,82),('C2_MID',5,84),
('C3_MIDHIGH',1,75),('C3_MIDHIGH',2,77),('C3_MIDHIGH',3,80),('C3_MIDHIGH',4,81),('C3_MIDHIGH',5,83),
('C34_MID',1,73),('C34_MID',2,74),('C34_MID',3,76),('C34_MID',4,79),('C34_MID',5,81),
('C4_LOWMID',1,70),('C4_LOWMID',2,72),('C4_LOWMID',3,74),('C4_LOWMID',4,77),('C4_LOWMID',5,79),
('C5_LOW',1,68),('C5_LOW',2,70),('C5_LOW',3,71),('C5_LOW',4,75),('C5_LOW',5,78),
('C67_BOTTOM',1,59),('C67_BOTTOM',2,63),('C67_BOTTOM',3,67),('C67_BOTTOM',4,71),('C67_BOTTOM',5,73);

CREATE TABLE IF NOT EXISTS mordin_northern_courses (
    course_key VARCHAR(50) NOT NULL PRIMARY KEY
);

INSERT IGNORE INTO mordin_northern_courses (course_key) VALUES
('newcastle'),('catterick'),('redcar'),('musselburgh'),('hamilton'),
('ayr'),('beverley'),('carlisle'),('pontefract'),('ripon'),('thirsk');

CREATE TABLE IF NOT EXISTS trainer_locations (
    trainer_name VARCHAR(100) NOT NULL PRIMARY KEY,
    location VARCHAR(30) NOT NULL
);

INSERT IGNORE INTO trainer_locations (trainer_name, location) VALUES
('Charlie Appleby','Newmarket'),('John & Thady Gosden','Newmarket'),('William Haggas','Newmarket'),
('Roger Varian','Newmarket'),('Sir Michael Stoute','Newmarket'),('James Fanshawe','Newmarket'),
('George Boughey','Newmarket'),('David Simcock','Newmarket'),('Simon & Ed Crisford','Newmarket'),
('Charlie Fellowes','Newmarket'),('Michael Bell','Newmarket'),('Marco Botti','Newmarket'),
('Sir Mark Prescott Bt','Newmarket'),('Kevin Philippart De Foy','Newmarket'),('Jane Chapple-Hyam','Newmarket'),
('Harry Eustace','Newmarket'),
('Charles Hills','Lambourn'),('Clive Cox','Lambourn'),('Archie Watson','Lambourn'),
('Jamie Osborne','Lambourn'),('Ed Walker','Lambourn'),('Joseph Tuite','Lambourn');
