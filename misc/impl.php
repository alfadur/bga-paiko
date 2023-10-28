<?php

class GameState {
    function nextState(string $transition): void {}
    function prevState(string $transition): void {}
    function checkPossibleAction(string $action): void {}
    function setAllPlayersMultiactive(): void {}
    function setPlayersMultiactive(array $players, string $stateTransition, bool $overwrite = false): void {}
    function setPlayerNonMultiactive(string $player, string $stateTransition): void {}
    function isPlayerActive(string $playerId): bool { return false; }
}

class Table {
    protected $gamestate;

    function __construct(){
        $this->gamestate = new GameState();
    }
    static function getGameinfos(): array {return [];}
    static function getPlayersNumber(): int { return 0; }
    static function getActivePlayerId(): string { return ''; }
    static function getCurrentPlayerId(): string { return ''; }
    static function isSpectator(): bool { return false; }
    static function isCurrentPlayerZombie(): bool { return false; }
    static function activeNextPlayer(): void {}
    static function DbQuery(string $query): void {}
    static function DbAffectedRow(): int { return 0; }
    static function getCollectionFromDb(string $query): array { return []; }
    static function getObjectListFromDb(string $query): array { return []; }
    static function getUniqueValueFromDb(string $query): ?string { return null; }

    static function initGameStateLabels(array $array): void {}
    static function setInitialGameStateValue(string $name, int $value): void {}
    static function incGamestateValue(string $name, int $value): void {}

    static function checkAction(string $action): void {}
}