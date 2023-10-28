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
  * paiko.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );
require_once('modules/constants.inc.php');

class Paiko extends Table
{
	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels([]);
	}
	
    protected function getGameName(): string
    {
		// Used for translations and stuff. Please do not modify.
        return 'paiko';
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = [])
    {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = 'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
        $values = [];
        foreach($players as $player_id => $player ) {
            $color = array_shift($default_colors);
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( ',', $values );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        self::createBoard();
        self::createTiles(array_keys($players));

        //$this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);
        $result['tiles'] = self::getObjectListFromDb('SELECT * FROM tiles');

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }

    static function getBoard(): array {
        return self::getObjectListFromDb('SELECT x, y FROM board');;
    }

    static function createBoard(): void {
        $coords = [];
        for ($y = 0; $y < 7; ++$y) {
            for ($x = 6 - $y; $x < 7; ++$x) {
                $flipX = 13 - $x;
                $flipY = 13 - $y;
                if ($x !== 5 || $y !== 5) {
                    $coords[] = "($x,$y)";
                    $coords[] = "($flipX,$flipY)";
                }
                if ($x !== 6 || $y !== 6) {
                    $coords[] = "($flipX,$y)";
                    $coords[] = "($x,$flipY)";
                }
            }
        }
        $args = implode(',', $coords);
        self::DbQuery("INSERT INTO board(x, y) VALUES $args");
    }

    static function createTiles(array $playerIds): void {
        $tiles = [];
        $reserve = TileState::RESERVE;
        foreach ($playerIds as $playerId) {
            foreach (Tile::ALL as $tile) {
                for ($i = 0; $i < 3; ++$i) {
                    $tiles[] = "($tile, $reserve, $playerId)";
                }
            }
        }
        $args = implode(',', $tiles);
        $query = "INSERT INTO tiles (type, state, player_id) VALUES $args";
        self::DbQuery($query);
    }

    static function getTilesToDraft(): int {
        $reserve = TileState::RESERVE;
        $draftedTiles = (int)self::getUniqueValueFromDb(
            "SELECT COUNT(*) FROM tiles WHERE state <> $reserve");

        $toDraft = 0;
        $count = 0;
        foreach (DRAFT_COUNTS as $draftCount) {
            $toDraft += $draftCount;
            if ($draftedTiles < $toDraft) {
                $count = $draftCount;
                break;
            }
        }
        return $count;
    }

    function stDraftResolution(): void {
        if (self::getTilesToDraft() === 0) {
            $this->gamestate->nextState('action');
        } else {
            self::activeNextPlayer();
            $this->gamestate->nextState('draft');
        }
    }

    function stActionResolution(): void {
        //TODO
    }

    function argDraft(): array {
        return ['count' => self::getTilesToDraft()];
    }

    function draft(array $tiles): void {
        self::checkAction('draft');
        if (count($tiles) <> self::getTilesToDraft()) {
            new BgaUserException('Invalid tile count');
        }
        $activePlayer = self::getActivePlayerId();
        $reserve = TileState::RESERVE;
        $hand = TileState::HAND;
        $ids = implode(',', $tiles);
        self::DbQuery(<<<EOF
            UPDATE tiles
            SET state = $hand
            WHERE state = $reserve 
                AND tile_id IN ($ids)
                AND player_id = $activePlayer
            EOF);

        if (self::DbAffectedRow() <> count($tiles)) {
            throw new BgaUserException('Invalid tiles list');
        }

        $this->gamestate->nextState('draft');
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
