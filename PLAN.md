src/
│
├── Core/                          # Bootstrap i wiring
│   ├── Plugin.php                 # Główna klasa - TYLKO rejestracja, zero logiki
│   ├── Container.php              # Prosty DI container (wstrzykiwanie zależności)
│   └── Bootstrap.php             # Rejestruje managery, listenery, taski
│
│
├── Domain/                        # 🧠 SERCE PROJEKTU
│   │                              # Czyste PHP - ZERO importów z PocketMine!
│   │                              # Testowalny, niezależny od frameworka
│   │
│   ├── Player/
│   │   ├── PlayerProfile.php      # Encja: dane gracza w RAM (ELO, ranga, stats)
│   │   ├── PlayerState.php        # Enum: LOBBY|IN_QUEUE|IN_MATCH|SPECTATING|IN_TOURNAMENT
│   │   └── Division.php           # Enum: IRON|BRONZE|SILVER|GOLD|DIAMOND|ELITE
│   │
│   ├── Match/
│   │   ├── Match.php              # Encja meczu
│   │   ├── MatchResult.php        # Value object: wynik, statsy, czas
│   │   └── MatchState.php         # Enum: WAITING|STARTING|ACTIVE|ENDING
│   │
│   ├── Ranking/
│   │   ├── EloCalculator.php      # Matematyka ELO (czyste PHP, łatwo wymienić na Glicko2)
│   │   └── SeasonSnapshot.php     # Value object: stan rankingu na koniec sezonu
│   │
│   ├── Tournament/
│   │   ├── Tournament.php         # Encja turnieju
│   │   ├── Bracket.php            # Logika drabinki (Single Elimination na start)
│   │   └── TournamentState.php    # Enum: SCHEDULED|REGISTRATION|ACTIVE|FINISHED
│   │
│   └── GameMode/
│       ├── GameModeInterface.php  # Kontrakt: każdy tryb MUSI implementować
│       └── GameModeConfig.php     # Value object: ustawienia trybu (czas, HP, efekty)
│
│
├── Application/                   # Orkiestracja - łączy Domain z Infrastrukturą
│   │
│   ├── Player/
│   │   ├── SessionManager.php     # Ładuje profil przy join, zapisuje przy quit
│   │   └── StatsCollector.php     # Zbiera mikro-zdarzenia w meczu (perły, golden apple itd.)
│   │
│   ├── Matchmaking/
│   │   ├── QueueManager.php       # Kto czeka, w jakiej kolejce
│   │   └── Matchmaker.php         # Co tick: łączy graczy o podobnym MMR
│   │
│   ├── Match/
│   │   ├── MatchManager.php       # Tworzy i niszczy instancje meczów
│   │   └── MatchLifecycle.php     # Przejścia stanów: WAITING → STARTING → ACTIVE → ENDING
│   │
│   ├── Tournament/
│   │   ├── TournamentManager.php  # Zarządza aktywnym turniejem
│   │   ├── BracketGenerator.php   # Generuje drabinkę ze zgłoszonych graczy
│   │   └── TournamentScheduler.php # ⏰ Automatyczny start (cron-like, bez akcji admina)
│   │
│   └── Arena/
│       ├── ArenaPool.php          # Pula wolnych aren (which are free/busy)
│       └── ArenaLifecycle.php     # Reset areny po meczu
│
│
├── GameMode/                      # 🎮 Plugowalny system trybów
│   ├── GameModeRegistry.php       # Rejestracja i lookup trybów (dodajesz nowy = jeden plik)
│   ├── AbstractGameMode.php       # Baza z domyślnymi zachowaniami
│   └── Nodebuff/                  # Przykładowy tryb
│       ├── NodebuffMode.php       # Implementacja GameModeInterface
│       ├── NodebuffListener.php   # Eventy specyficzne dla tego trybu
│       └── NodebuffConfig.php     # Ustawienia (efekty po śmierci, czas respawnu itd.)
│
│
├── Infrastructure/                # 🔌 Zewnętrzne systemy - łatwo wymienne
│   │
│   ├── Database/
│   │   ├── DatabaseManager.php    # Wrapper na libasynql
│   │   ├── PlayerRepository.php   # Wszystkie zapytania SQL dot. graczy
│   │   ├── MatchRepository.php    # Historia meczów, statsy
│   │   └── TournamentRepository.php
│   │
│   ├── Cache/
│   │   ├── RedisClient.php        # Połączenie z Redisem
│   │   └── LeaderboardCache.php   # Live ranking w Redisie (posortowane sety)
│   │
│   ├── Sync/                      # 🚀 KLUCZ DO MULTI-INSTANCE W PRZYSZŁOŚCI
│   │   ├── ServerSyncInterface.php # Abstrakcja: "powiadom inne serwery o zdarzeniu"
│   │   ├── LocalSync.php          # Implementacja mono: no-op (nic nie robi)
│   │   └── RedisSync.php          # Implementacja multi: Pub/Sub przez Redis
│   │                              # Żeby przejść na multi - zmieniasz JEDEN wpis w Container.php
│   │
│   └── Http/
│       ├── WebhookDispatcher.php  # Wysyła eventy do Next.js (nowy mecz, wynik turnieju)
│       └── DiscordNotifier.php    # Opcjonalne powiadomienia Discord
│
│
├── Combat/                        # ⚔️ Silnik PvP
│   ├── KnockbackEngine.php        # Własny algorytm knockbacku (W-tap, sprint reset)
│   ├── DamageCalculator.php       # I-frames, armor, efekty
│   └── HitValidator.php           # Reach check, kąt uderzenia (podstawy bez AC)
│
│
├── Arena/                         # Instancje map
│   ├── ArenaInstance.php          # Aktywna arena: kto gra, wynik, czas
│   ├── SchematicLoader.php        # Async wklejanie mapy (nie laguje serwera)
│   └── SpawnPoint.php             # Value object: pozycja spawnu
│
│
├── Listener/                      # 👂 TYLKO cienkie przekaźniki
│   ├── PlayerListener.php         # → SessionManager
│   ├── CombatListener.php         # → HitValidator, DamageCalculator
│   ├── PacketListener.php         # → KnockbackEngine (InventoryTransactionPacket)
│   └── WorldListener.php          # → ArenaLifecycle
│
│
├── Task/                          # Taski PMMP
│   ├── MatchTickTask.php          # Zegar meczowy, warunki zwycięstwa
│   ├── QueueTickTask.php          # Co sekundę odpala Matchmaker
│   ├── TournamentTickTask.php     # Stan turnieju, timeouty
│   └── StatsFlushTask.php         # Batch zapis statów do DB (nie per-event!)
│
│
└── Util/
    ├── MathUtil.php
    ├── TimeUtil.php
    └── FormatterUtil.php