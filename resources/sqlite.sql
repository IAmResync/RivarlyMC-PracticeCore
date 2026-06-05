-- #!sqlite

-- # { rivarly
-- #   { practice
-- #     { players
-- #       { init
CREATE TABLE IF NOT EXISTS practice_players (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid         TEXT    NOT NULL UNIQUE,
    xuid         TEXT    NOT NULL DEFAULT '',
    username     TEXT    NOT NULL,
    global_elo   INTEGER NOT NULL DEFAULT 1000,
    global_kills INTEGER NOT NULL DEFAULT 0,
    global_deaths INTEGER NOT NULL DEFAULT 0
);
-- #       }
-- #       { load
-- #         :username string
SELECT uuid, xuid, username, global_elo, global_kills, global_deaths FROM practice_players WHERE username = :username;
-- #       }
-- #       { save
-- #         :uuid string
-- #         :xuid string
-- #         :username string
-- #         :global_elo int
-- #         :global_kills int
-- #         :global_deaths int
INSERT INTO practice_players (uuid, xuid, username, global_elo, global_kills, global_deaths)
VALUES (:uuid, :xuid, :username, :global_elo, :global_kills, :global_deaths)
ON CONFLICT(uuid) DO UPDATE SET
    xuid           = excluded.xuid,
    username       = excluded.username,
    global_elo     = excluded.global_elo,
    global_kills   = excluded.global_kills,
    global_deaths  = excluded.global_deaths;
-- #       }
-- #     }
-- #     { matches
-- #       { init
CREATE TABLE IF NOT EXISTS practice_matches (
    id               TEXT    PRIMARY KEY,
    game_mode        TEXT    NOT NULL,
    winner           TEXT    NOT NULL,
    loser            TEXT    NOT NULL,
    duration_seconds INTEGER NOT NULL,
    played_at        DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- #       }
-- #       { log
-- #         :id string
-- #         :game_mode string
-- #         :winner string
-- #         :loser string
-- #         :duration_seconds int
INSERT INTO practice_matches (id, game_mode, winner, loser, duration_seconds)
VALUES (:id, :game_mode, :winner, :loser, :duration_seconds);
-- #       }
-- #     }
-- #     { tournaments
-- #       { init
CREATE TABLE IF NOT EXISTS practice_tournaments (
    id                 TEXT PRIMARY KEY,
    name               TEXT NOT NULL,
    game_mode          TEXT NOT NULL,
    winner             TEXT,
    participants_count INTEGER  NOT NULL,
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- #       }
-- #       { log_start
-- #         :id string
-- #         :name string
-- #         :game_mode string
-- #         :participants_count int
INSERT INTO practice_tournaments (id, name, game_mode, participants_count)
VALUES (:id, :name, :game_mode, :participants_count);
-- #       }
-- #       { set_winner
-- #         :id string
-- #         :winner string
UPDATE practice_tournaments SET winner = :winner WHERE id = :id;
-- #       }
-- #     }
-- #   }
-- # }
