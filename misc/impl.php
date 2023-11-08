<?php

class_alias('Paiko', 'GameImplName');

class GameState {
    function state_id(): string { return 0; }
    function nextState(string $transition): void {}
    function prevState(string $transition): void {}
    function jumpToState(int $state): void {}
    function checkPossibleAction(string $action): void {}
    function setAllPlayersMultiactive(): void {}
    function setPlayersMultiactive(array $players, string $stateTransition, bool $overwrite = false): void {}
    function setPlayerNonMultiactive(string $player, string $stateTransition): void {}
    function isPlayerActive(string $playerId): bool { return false; }
}

class Table {
    protected $gamestate;

    function __construct()
    {
        $this->gamestate = new GameState();
    }
    static function reloadPlayersBasicInfos(): void {}
    static function loadPlayersBasicInfos(): array { return []; }
    static function getGameinfos(): array {return [];}
    static function getPlayersNumber(): int { return 0; }
    static function getActivePlayerId(): string { return ''; }
    static function getActivePlayerName(): string { return ''; }
    static function getCurrentPlayerId(): string { return ''; }
    static function getPlayerNameById(string $playerId): string { return ''; }
    static function getPlayerNoById(string $playerId): int { return 0; }
    static function isSpectator(): bool { return false; }
    static function isCurrentPlayerZombie(): bool { return false; }
    static function activeNextPlayer(): void {}
    static function DbQuery(string $query): void {}
    static function DbAffectedRow(): int { return 0; }
    static function getCollectionFromDb(string $query, bool $singleColumn = false): array { return []; }
    static function getObjectFromDb(string $query): ?object { return null; }
    static function getNonEmptyObjectFromDb(string $query): object { return new GameImplName(); }
    static function getObjectListFromDb(string $query, bool $singleColumn = false): array { return []; }
    static function getUniqueValueFromDb(string $query): ?string { return null; }

    static function initGameStateLabels(array $array): void {}
    static function setGameStateInitialValue(string $name, int $value): void {}
    static function incGameStateValue(string $name, int $value): void {}
    static function setGameStateValue(string $name, int $value): void {}
    static function getGameStateValue(string $name): string { return ''; }
    static function checkAction(string $action): void {}
    static function notifyAllPlayers(string $name, string $text, array $args): void {}
    static function notifyPlayers(string $playerId, string $name, string $text, array $args): void {}
}

class game_view_page {
    function begin_block(string $game, string $name): void {}
    function insert_block(string $name, array $args): void {}
    function reset_subblocks(string $name): void {}
}

class game_view {
    protected $game;
    protected $tpl;
    protected $page;

    function __construct()
    {
        $this->game = new GameImplName();
        $this->page = new game_view_page();
        $this->tpl = [];
    }
}

const AT_int = 0;
const AT_posint = 0;
const AT_float = 0;
const AT_bool = 0;
const AT_enum = 0;
const AT_alphanum = 0;
const AT_alphanum_dash = 0;
const AT_numberlist = 0;
const AT_base64 = 0;
const AT_json = 0;

class APP_GameAction {
    protected $game;

    function __construct()
    {
        $this->game = new GameImplName();
    }

    static function setAjaxMode(): void {}
    static function ajaxResponse(): void {}
    static function getArg(string $name, int $type, bool $required = false): string { return ''; }
}

function totranslate(string $text): string { return $text; }
function clienttranslate(string $text): string { return $text; }