<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Paiko implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
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
                      method on both client side (Javacript: this.checkAction) and server side (PHP: $this->checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

$machinestates = [
    State::GAME_START => [
        Fsm::NAME => 'gameSetup',
        Fsm::TYPE => FsmType::MANAGER,
        Fsm::DESCRIPTION => '',
        Fsm::ACTION => 'stGameSetup',
        Fsm::TRANSITIONS => [
            State::DRAFT => State::DRAFT
        ]
    ],

    State::DRAFT => [
        Fsm::NAME => 'draft',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must draw tiles from reserve'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must draw ${count} tile(s) from reserve'),
        Fsm::ARGUMENTS => 'argDraft',
        Fsm::POSSIBLE_ACTIONS => ['actDraft'],
        Fsm::TRANSITIONS => [
            State::DRAFT_DISPATCH => State::DRAFT_DISPATCH
        ]
    ],

    State::DRAFT_DISPATCH => [
        Fsm::NAME => 'draftDispatch',
        Fsm::TYPE => FsmType::GAME,
        Fsm::DESCRIPTION => '',
        Fsm::ACTION => 'stDraftDispatch',
        Fsm::TRANSITIONS => [
            State::DRAFT => State::DRAFT,
            State::ACTION => State::ACTION
        ]
    ],

    State::NEXT_TURN => [
        Fsm::NAME => 'nextTurn',
        Fsm::TYPE => FsmType::GAME,
        Fsm::DESCRIPTION => '',
        Fsm::PROGRESSION => true,
        Fsm::ACTION => 'stNextTurn',
        Fsm::TRANSITIONS => [
            State::ACTION => State::ACTION,
            State::GAME_END => State::GAME_END
        ]
    ],

    State::ACTION => [
        Fsm::NAME => 'action',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must take an action'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must shift or deploy a tile'),
        Fsm::POSSIBLE_ACTIONS => [
            'actDeploy',
            'actMove',
            'actDraft'
        ],
        Fsm::TRANSITIONS => [
            State::NEXT_TURN => State::NEXT_TURN,
            State::SAI_MOVE => State::SAI_MOVE,
            State::CAPTURE => State::CAPTURE
        ]
    ],

    State::SAI_MOVE => [
        Fsm::NAME => 'saiMove',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must move ${pieceIcon}'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must move ${pieceIcon}'),
        Fsm::ARGUMENTS => 'argSaiMove',
        Fsm::POSSIBLE_ACTIONS => [
            'actMove',
            'actSkip'
        ],
        Fsm::TRANSITIONS => [
            State::NEXT_TURN => State::NEXT_TURN,
            State::CAPTURE => State::CAPTURE
        ]
    ],

    State::CAPTURE => [
        Fsm::NAME => 'capture',
        Fsm::TYPE => FsmType::GAME,
        Fsm::ACTION => 'stCapture',
        Fsm::TRANSITIONS => [
            State::CAPTURE => State::CAPTURE,
            State::RESERVE => State::RESERVE,
            State::NEXT_TURN => State::NEXT_TURN
        ]
    ],

    State::RESERVE => [
        Fsm::NAME => 'reserve',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must choose a tile from the opponents reserve'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must choose a tile from reserve for the opponent to draw'),
        Fsm::POSSIBLE_ACTIONS => [
            'actDraft'
        ],
        Fsm::TRANSITIONS => [
            State::CAPTURE => State::CAPTURE
        ]
    ],

    State::GAME_END => [
        Fsm::NAME => 'gameEnd',
        Fsm::TYPE => FsmType::MANAGER,
        Fsm::DESCRIPTION => clienttranslate("End of game"),
        Fsm::ACTION => 'stGameEnd',
        Fsm::ARGUMENTS => 'argGameEnd'
    ]
];



