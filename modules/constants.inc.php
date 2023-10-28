<?php

interface Fsm {
    const NAME = 'name';
    const DESCRIPTION = 'description';
    const OWN_DESCRIPTION = 'descriptionmyturn';
    const TYPE = 'type';
    const ACTION = 'action';
    const TRANSITIONS = 'transitions';
    const PROGRESSION = 'updateGameProgression';
    const POSSIBLE_ACTIONS = 'possibleactions';
    const ARGUMENTS = 'args';
}

interface FsmType {
    const MANAGER = 'manager';
    const GAME = 'game';
    const SINGLE_PLAYER = 'activeplayer';
    const MULTIPLE_PLAYERS = 'multipleactiveplayer';
}

interface State {
    const GAME_START = 1;

    const DRAFT_RESOLUTION = 2;
    const DRAFT = 3;
    const ACTION = 5;

    const CAPTURE_RESOLUTION = 6;
    const CAPTURE = 7;
    const RESERVE = 8;

    const GAME_END = 99;
}

interface Globals {
    const ACTIONS_TAKEN = 'actionsTaken';
    const ACTIONS_TAKEN_ID = 10;
    const CAPTAIN = 'captain';
    const CAPTAIN_ID = 11;
}

interface Tile {
    const SAI = 0;
    const SWORD = 1;
    const BOW = 2;
    const LOTUS = 3;
    const AIR = 4;
    const FIRE = 5;
    const EARTH = 6;
    const WATER = 7;

    const ALL= [
        self::SAI,
        self::SWORD,
        self::BOW,
        self::LOTUS,
        self::AIR,
        self::FIRE,
        self::EARTH,
        self::WATER];
}

interface TileState {
    const RESERVE = 0;
    const HAND = 1;
    const BOARD = 2;
    const DISCARD = 3;
}

const DRAFT_COUNTS = [7, 9, 1];