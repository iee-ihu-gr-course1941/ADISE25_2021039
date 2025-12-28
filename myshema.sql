DROP TABLE IF EXISTS deck;
CREATE TABLE deck (
  card_id INT AUTO_INCREMENT PRIMARY KEY,
  suit ENUM('H','D','C','S') NOT NULL,   -- ♥ ♦ ♣ ♠
  value ENUM('A','2','3','4','5','6','7','8','9','10','J','Q','K') NOT NULL,
  is_drawn BOOLEAN DEFAULT FALSE,
  drawn_by ENUM('P1','P2') DEFAULT NULL,
  drawn_at TIMESTAMP NULL
) ENGINE=InnoDB;


INSERT INTO deck (suit, value)
SELECT s.suit, v.value
FROM
 (SELECT 'H' suit UNION SELECT 'D' UNION SELECT 'C' UNION SELECT 'S') s,
 (SELECT 'A' value UNION SELECT '2' UNION SELECT '3' UNION SELECT '4'
  UNION SELECT '5' UNION SELECT '6' UNION SELECT '7' UNION SELECT '8'
  UNION SELECT '9' UNION SELECT '10' UNION SELECT 'J'
  UNION SELECT 'Q' UNION SELECT 'K') v;


DROP TABLE IF EXISTS hands;
CREATE TABLE hands (
  card_id INT PRIMARY KEY,
  player ENUM('P1','P2') NOT NULL,
  FOREIGN KEY (card_id) REFERENCES deck(card_id)
);


DROP TABLE IF EXISTS table_cards;
CREATE TABLE table_cards (
  card_id INT PRIMARY KEY,
  placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (card_id) REFERENCES deck(card_id)
);


DROP TABLE IF EXISTS game_status;
CREATE TABLE game_status (
  status ENUM(
    'not_active',
    'waiting_player',
    'dealing',
    'playing',
    'round_end',
    'game_end',
    'aborted'
  ) NOT NULL DEFAULT 'not_active',
  turn ENUM('P1','P2'),
  last_change TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


DROP TABLE IF EXISTS players;
CREATE TABLE players (
  player ENUM('P1','P2') PRIMARY KEY,
  username VARCHAR(30),
  token VARCHAR(100),
  last_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
