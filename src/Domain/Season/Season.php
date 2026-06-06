<?php

declare(strict_types=1);

namespace Domain\Season;

/**
 * TODO: Encja reprezentująca jeden sezon rankingowy z datami startu i końca.
 * Przechowuje numer sezonu, aktualny stan oraz daty graniczne.
 * Używana przez SeasonManager do zarządzania cyklem życia sezonu.
 */
final class Season {

    private int $id;
    private int $seasonNumber;
    private bool $isActive;
    private \DateTimeImmutable $startDate;
    private \DateTimeImmutable $endDate;

    /**
     * Konstruktor encji sezonu rankingowego.
     */
    public function __construct(
        int $id,
        int $seasonNumber,
        bool $isActive,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ) {
        $this->id = $id;
        $this->seasonNumber = $seasonNumber;
        $this->isActive = $isActive;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Unikalny indentyfikator bazy danych (ID wpisu).
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * Numer sezonu wyświetlany graczom (np . Sezon 1, Sezon 2).
     */
    public function getSeasonNumber(): int {
        return $this->seasonNumber;
    }

    /**
     * Zwraca informacje, czy ten sezon jest obecnie otwarty dla rozgrywek rankingowych.
     */
    public function isActive(): bool {
        // Sezon musi mieć flagę aktywności oraz mieścić się w ramach.
        $now = new \DateTimeImmutable();
        return $this->isActive && $now >= $this->startDate && $now <= $this->endDate;
    }

    /**
     * Zwraca dokładną datę i godzinę rozpoczęcia sezonu.
     */
    public function getStartDate(): \DateTimeImmutable {
        return $this->startDate;
    }

    /**
     * Zwraca dokładną datę i godzinę planowanego zakończenia sezonu.
     */
    public function getEndDate(): \DateTimeImmutable {
        return $this->endDate;
    }

    /**
     * Sprawdza, czy sezon już wygasł i powinien zostać zarchiwizowany.
     */
    public function isExpired(): bool {
        return new \DateTimeImmutable() > $this->endDate;
    }

    /**
     * Pozwala na ręczne zamknięcie sezonu przez Administratora.
     */
    public function close(): void {
        $this->isActive = false;
    }
}
