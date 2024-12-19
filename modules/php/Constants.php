<?php

enum Fsm {
    final const NAME = 'name';
    final const DESCRIPTION = 'description';
    final const OWN_DESCRIPTION = 'descriptionmyturn';
    final const TYPE = 'type';
    final const ACTION = 'action';
    final const TRANSITIONS = 'transitions';
    final const PROGRESSION = 'updateGameProgression';
    final const POSSIBLE_ACTIONS = 'possibleactions';
    final const ARGUMENTS = 'args';
}

enum FsmType {
    final const MANAGER = 'manager';
    final const GAME = 'game';
    final const SINGLE_PLAYER = 'activeplayer';
    final const MULTIPLE_PLAYERS = 'multipleactiveplayer';
}

enum State {
    final const GAME_START = 1;

    final const NEXT_TURN = 2;
    final const ACTION = 3;
    final const CAPTURE = 5;
    final const RESERVE = 6;
    final const SAI_MOVE = 4;
    final const DRAFT_DISPATCH = 7;
    final const DRAFT = 8;
    final const GAME_END = 99;
}

enum GameGlobal: string {
    case SaiCoords = 'sai_coords';
    case Captures = 'captures';
    case FireCaptures = 'fire_captures';
    case LastPiece = 'last_piece';
    case Score = 'score';
    case LastPlayer = 'last_player';

    final const IDS = [
        self::SaiCoords->value => 10,
        self::Captures->value => 11,
        self::FireCaptures->value => 12,
        self::LastPiece->value => 13,
        self::Score->value => 14,
        self::LastPlayer->value => 15
    ];
}

enum GameOption: string {
    case Option = 'option';
    final const IDS = [
        self::Option->value => 100
    ];
}

enum Stats {
    final const TABLE_STATS_LIST = [];
    final const PLAYER_STATS_LIST = [];
}

enum PieceStatus: int {
    case Reserve = 0;
    case Hand = 1;
    case Board = 2;
}

enum PieceType: int {
    case Sai = 0;
    case Sword = 1;
    case Bow = 2;
    case Lotus = 3;
    case Air = 4;
    case Fire = 5;
    case Earth = 6;
    case Water = 7;

    public function getName(): string {
        return match($this) {
            self::Air => 'Air',
            self::Fire => 'Fire',
            self::Bow => 'Bow',
            self::Sai => 'Sai',
            self::Lotus => 'Lotus',
            self::Earth => 'Earth',
            self::Water => 'Water',
            self::Sword => 'Sword',
        };
    }

    function getMoves(): int
    {
        return match($this) {
            self::Lotus => 0,
            self::Air => 0,
            self::Earth => 1,
            default => 2
        };
    }

    const THREAT = [
        self::Sai->value => [[0, -1]],
        self::Sword->value => [
            [-1, -1], [0, -1], [1, -1],
            [-1, 0], [1, 0],
            [-1, 1], [0, 1], [1, 1]],
        self::Bow->value => [[0, -2], [0, -3], [0, -4]],
        self::Lotus->value => [],
        self::Air->value => [
            [-1, -2], [1, -2],
            [-2, -1], [2, -1],
            [-2, 1], [2, 1],
            [-1, 2], [1, 2]],
        self::Fire->value => [
            [-2, -2], [-1, -2], [0, -2], [1, -2], [2, -2],
            [0, 0],
            [-1, -1], [0, -1], [1, -1]],
        self::Earth->value => [
            [0, -2],
            [0, -1],
            [-2, 0], [-1, 0], [1, 0], [2, 0],
            [0, 1],
            [0, 2]],
        self::Water->value => [
            [-1, -1], [1, -1],
            [-1, 1], [1, 1]]
    ];

    const COVER = [
        self::Sai->value => [[-1, 0], [1, 0]],
        self::Sword->value => [],
        self::Bow->value => [],
        self::Lotus->value => [
            [-1, -1], [0, -1], [1, -1],
            [-1, 0], [0, 0], [1, 0],
            [-1, 1], [0, 1], [1, 1]],
        self::Air->value => [],
        self::Fire->value => [],
        self::Earth->value => [],
        self::Water->value => [
            [0, -1],
            [-1, 0], [1, 0],
            [0, 1]]
    ];
}

const HOLES = [
    [5, 5], [8, 8], [7, 6], [6, 7]
];

const DIRECTIONS = [[0, -1], [1, 0], [0, 1], [-1, 0]];
