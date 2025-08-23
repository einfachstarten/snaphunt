-- Snaphunt Database Migration v2
-- Adds capture mechanics and game completion features

-- Add game timing and winner columns
ALTER TABLE games 
ADD COLUMN started_at TIMESTAMP NULL AFTER status,
ADD COLUMN ended_at TIMESTAMP NULL AFTER started_at,
ADD COLUMN winner_team_id INT NULL AFTER ended_at,
ADD FOREIGN KEY (winner_team_id) REFERENCES teams(id) ON DELETE SET NULL;

-- Create captures table
CREATE TABLE captures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    hunter_player_id INT NOT NULL,
    hunted_player_id INT NOT NULL,
    distance_meters INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (hunter_player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (hunted_player_id) REFERENCES players(id) ON DELETE CASCADE,
    INDEX idx_game_captures (game_id, created_at),
    INDEX idx_player_captures (hunted_player_id, created_at)
);

-- Optimize location tracking
ALTER TABLE location_pings 
ADD UNIQUE KEY unique_player_location (player_id),
ADD INDEX idx_location_time (created_at);

-- Extend game status options
ALTER TABLE games 
MODIFY COLUMN status ENUM('waiting','active','paused','finished') 
NOT NULL DEFAULT 'waiting';

-- Create game statistics view
CREATE VIEW game_stats AS
SELECT 
    g.id as game_id,
    g.name as game_name,
    g.status,
    g.created_at,
    g.started_at,
    g.ended_at,
    TIMESTAMPDIFF(MINUTE, g.started_at, COALESCE(g.ended_at, NOW())) as duration_minutes,
    COUNT(DISTINCT t.id) as total_teams,
    COUNT(DISTINCT p.id) as total_players,
    COUNT(DISTINCT CASE WHEN t.role = 'hunter' THEN p.id END) as hunter_count,
    COUNT(DISTINCT CASE WHEN t.role = 'hunted' THEN p.id END) as hunted_count,
    COUNT(DISTINCT c.id) as total_captures,
    wt.name as winner_team_name,
    wt.role as winner_role
FROM games g
LEFT JOIN teams t ON g.id = t.game_id  
LEFT JOIN players p ON t.id = p.team_id
LEFT JOIN captures c ON g.id = c.game_id
LEFT JOIN teams wt ON g.winner_team_id = wt.id
GROUP BY g.id, g.name, g.status, g.created_at, g.started_at, g.ended_at, wt.name, wt.role;

-- Add validation trigger
DELIMITER $$
CREATE TRIGGER validate_capture_roles
BEFORE INSERT ON captures
FOR EACH ROW
BEGIN
    DECLARE hunter_role VARCHAR(10);
    DECLARE hunted_role VARCHAR(10);
    
    SELECT t.role INTO hunter_role
    FROM players p JOIN teams t ON p.team_id = t.id
    WHERE p.id = NEW.hunter_player_id;
    
    SELECT t.role INTO hunted_role  
    FROM players p JOIN teams t ON p.team_id = t.id
    WHERE p.id = NEW.hunted_player_id;
    
    IF hunter_role != 'hunter' OR hunted_role != 'hunted' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Invalid capture: hunter must have hunter role, target must have hunted role';
    END IF;
END$$
DELIMITER ;
