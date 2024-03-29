<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Paiko implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * states.inc.php
 *
 * Paiko game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

 
$machinestates = [
    // The initial state. Please do not modify.
    State::GAME_START => [
        Fsm::NAME => "gameSetup",
        Fsm::DESCRIPTION => "",
        Fsm::TYPE => FsmType::MANAGER,
        Fsm::ACTION => 'stGameSetup',
        Fsm::TRANSITIONS => ['' => State::DRAFT_RESOLUTION]
    ],

    State::DRAFT_RESOLUTION => [
        Fsm::NAME => 'draftResolution',
        Fsm::DESCRIPTION => '',
        Fsm::TYPE => FsmType::GAME,
        Fsm::ACTION => 'stDraftResolution',
        Fsm::TRANSITIONS => ['draft' => State::DRAFT, 'action' => State::ACTION]
    ],

    State::DRAFT => [
        Fsm::NAME => 'draft',
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must draft ${count} pieces'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must draft ${count} pieces'),
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::ARGUMENTS => 'argDraft',
        Fsm::POSSIBLE_ACTIONS => ['draft'],
        Fsm::TRANSITIONS => ['draft' => State::DRAFT_RESOLUTION],
    ],

    State::ACTION => [
        Fsm::NAME => 'playerTurn',
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must select an action'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must select a action'),
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::POSSIBLE_ACTIONS => ['deploy', 'draw', 'shift'],
        Fsm::TRANSITIONS => ['action' => State::CAPTURE_RESOLUTION, 'end' => STATE::GAME_END]
    ],

    State::CAPTURE_RESOLUTION => [
        Fsm::NAME => 'captureResolution',
        Fsm::DESCRIPTION => '',
        Fsm::TYPE => FsmType::GAME,
        Fsm::ACTION => 'stCaptureResolution',
        Fsm::TRANSITIONS => ['capture' => State::CAPTURE, 'reserve' => State::RESERVE, 'next' => State::ACTION]
    ],

    State::CAPTURE => [
        Fsm::NAME => 'capture',
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must select a tile to capture'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must select a tile to capture'),
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::POSSIBLE_ACTIONS => ['capture'],
        Fsm::TRANSITIONS => ['capture' => State::CAPTURE_RESOLUTION]
    ],

    State::RESERVE => [
        Fsm::NAME => 'reserve',
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must choose a tile from your reserve'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must choose a tile for the opponent'),
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::POSSIBLE_ACTIONS => ['selectTile'],
        Fsm::TRANSITIONS => ['select' => State::DRAFT_RESOLUTION]
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    State::GAME_END => [
        Fsm::NAME => 'gameEnd',
        Fsm::DESCRIPTION => clienttranslate('End of game'),
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::ACTION => 'stGameEnd',
        Fsm::ARGUMENTS => 'argGameEnd'
    ]
];



