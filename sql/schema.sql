CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    join_code VARCHAR(8) NOT NULL UNIQUE,
    status ENUM('waiting','active','finished') NOT NULL DEFAULT 'waiting',
    photo_interval_seconds INT NOT NULL DEFAULT 120,
    started_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('hunted','hunter') NOT NULL,
    join_code VARCHAR(8) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id)
);

CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    device_id VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    is_captain BOOLEAN NOT NULL DEFAULT FALSE,
    last_seen TIMESTAMP NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id)
);

CREATE TABLE photo_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    slot_number INT NOT NULL,
    slot_code VARCHAR(8) NOT NULL,
    deadline DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id)
);

CREATE TABLE photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_id INT NOT NULL,
    player_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (slot_id) REFERENCES photo_slots(id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

CREATE TABLE location_pings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id)
);

CREATE TABLE game_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    team_id INT NULL,
    player_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);
