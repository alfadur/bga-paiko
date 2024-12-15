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

declare(strict_types=1);

namespace Bga\Games\Paiko;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\Actions\Types\IntParam;
use Bga\GameFramework\Actions\Types\BoolParam;
use Couchbase\ValueTooBigException;
use Couchbase\ViewException;
use PieceType;


require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");
require_once('Constants.php');
require_once('GlobalUtils.php');

class Game extends \Table
{
    use \GlobalUtils;
    private array $instGlobals = [];
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            ...\GameGlobal::IDS,
            ...\GameOption::IDS
        ]);
    }

    protected function setupNewGame($players, $options = [])
    {
        $data = $this->getGameinfos();
        $defaultColors = $data['player_colors'];
        $playerValues = [];

        foreach ($players as $playerId => $player) {
            $color = array_shift($defaultColors);

            $name = addslashes($player['player_name']);
            $avatar = addslashes($player['player_avatar']);
            $playerValues[] = "('$playerId','$color','$player[player_canal]','$name','$avatar')";
        }

        $args = implode(',', $playerValues);
        $query = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES $args";
        self::DbQuery($query);

        $this->reloadPlayersBasicInfos();

        foreach (\Stats::TABLE_STATS_LIST as $stat) {
            $this->initStat('table', $stat, 0);
        }

        foreach (\Stats::PLAYER_STATS_LIST as $stat) {
            $this->initStat('player', $stat, 0);
        }

        $this->generatePieces($players);

        $this->initGlobals();
        $this->activeNextPlayer();
    }

    private function generatePieces(array $players): void
    {
        $pieces = [];
        foreach ($players as $playerId => $player) {
            foreach (\PieceType::cases() as $type) {
                foreach (range(0, 2) as $_) {
                    $pieces[] = "($playerId, $type->value)";
                }
            }
        }

        $args = implode(',', $pieces);
        self::DbQuery(<<<EOF
            INSERT INTO piece(player_id, type) 
            VALUES $args
            EOF);
    }

    protected function getGameName()
    {
        return 'paiko';
    }

    protected function getAllDatas()
    {
        $result = [];

        $result['players'] = $this->getCollectionFromDb(
            "SELECT player_id AS id, player_score AS score, player_no AS no FROM player"
        );
        $result['pieces'] = self::getObjectListFromDB(
            "SELECT * FROM piece");

        return $result;
    }

    public function getGameProgression()
    {
        return 0;
    }

    private function checkBase(int $playerIndex, int $x, int $y): bool {
        return $playerIndex === 0 ?
            $x < 7 && $y > 6 :
            $x > 6 && $y < 7;
    }

    private function checkCoords(int $x, int $y, $includeHoles = false): bool
    {
        $width = $y < 7 ? $y : 13 - $y;
        return $x >= 6 - $width && $x < 7 + $width + 1
            && ($includeHoles || !in_array([$x, $y], HOLES));
    }

    private function getNearbyPieces(int $x, int $y, int $range = 5, $includeSelf = false): array
    {
        $selfCheck = $includeSelf ?
            '1' : "(x <> $x OR y <> $y)";

        return self::getObjectListFromDB(<<<EOF
            SELECT * FROM piece
            WHERE x BETWEEN $x - $range AND $x + $range
              AND y BETWEEN $y - $range AND $y + $range
              AND $selfCheck             
            EOF);
    }

    private function getField(array $field, int $x, int $y, array $pieces): array
    {
        $threats = [0, 0];
        foreach ($pieces as $piece) {
            $px = (int)$piece['x'];
            $py = (int)$piece['y'];
            $type = (int)$piece['type'];
            $angle = (int)$piece['angle'];
            $playerId = $piece['player_id'];
            $playerIndex = $this->getPlayerNoById($playerId) -1;
            foreach ($field[$type] as [$dx, $dy]) {
                [$tx, $ty] = DIRECTIONS[$angle];
                [$dx, $dy] = [
                    -$tx * $dy - $ty * $dx,
                    $tx * $dx - $ty * $dy,
                ];
                if ($px + $dx === $x && $py + $dy === $y) {
                    if ($type === \PieceType::Fire->value) {
                        ++$threats[1 - $playerIndex];
                    }
                    ++$threats[$playerIndex];
                }
            }
        }
        return $threats;
    }

    /**
     * @param int $x
     * @param int $y
     * @param array $pieces
     * @return array{0: int, 1: int}
     */
    private function getCover(int $x, int $y, array $pieces): array
    {
        $result = $this->getField(\PieceType::COVER, $x, $y, $pieces);
        $result[0] = $result[0] > 0 ? 1 : 0;
        $result[1] = $result[1] > 0 ? 1 : 0;
        if ($this->checkBase(0, $x, $y)) {
            $result[0] = 1;
        } elseif ($this->checkBase(1, $x, $y)) {
            $result[1] = 1;
        }
        return $result;
    }

    /**
     * @param int $x
     * @param int $y
     * @param array $pieces
     * @return array{0: int, 1: int}
     */
    private function getThreat(int $x, int $y, array $pieces, bool $covered): array
    {
        $result = $this->getField(\PieceType::THREAT, $x, $y, $pieces);
        if ($covered) {
            $cover = $this->getCover($x, $y, $pieces);
            foreach ($result as $index => &$value) {
                $value = max(0, $value - $cover[$index]);
            }
        }
        return $result;
    }

    private function capture(string $playerId, $pieces): bool
    {
        $playerIndex = $this->getPlayerNoById($playerId) - 1;
        $captures = 0;
        $fireCaptures = 0;

        foreach ($pieces as $piece) {
            $x = (int)$piece['x'];
            $y = (int)$piece['y'];
            $piece = PieceType::from((int)$piece['type']);
            $pieceIndex = $piece['player_id'] === $playerId ? $playerIndex : 1 - $playerIndex;

            $threats = $this->getThreat($x, $y, $pieces, true);

            if ($threats[1 - $pieceIndex] >= 2) {
                if ($pieceIndex === $pieceIndex) {
                    throw new ValueTooBigException('Invalid self capture');
                }
                $value = $x | $y << 4;
                if ($piece === PieceType::Fire) {
                    $fireCaptures = $fireCaptures << 8 | $value;
                } else {
                    $captures = $captures << 8 | $value;
                }
            }
        }

        if ($captures) {
            $this->set(\GameGlobal::Captures, $captures);
        }

        if ($fireCaptures) {
            $this->set(\GameGlobal::FireCaptures, $fireCaptures);
        }

        return $captures || $fireCaptures;
    }

    public function stNextTurn(): void
    {
        $this->activeNextPlayer();
        $this->gamestate->nextState(\State::ACTION);
    }

    public function argSaiMove(): array
    {
        $playerId = $this->getActivePlayerId();
        $playerIndex = $this->getPlayerNoById($playerId) - 1;
        $sai = \PieceType::Sai->value;
        $saiCoords = $this->get(\GameGlobal::SaiCoords);
        return [
            'x' => $saiCoords & 0xFF,
            'y' => $saiCoords >> 8,
            'pieceIcon' => "$playerIndex,$sai"
        ];
    }

    public function actDeploy(
        #[IntParam(min: 0, max:7)] int $type,
        #[IntParam(min: 0, max:13)] int $x,
        #[IntParam(min: 0, max:13)] int $y,
        #[IntParam(min: 0, max:3)] int $angle = 0,
        #[BoolParam] bool $waterRedeploy = false): void
    {
        $piece = \PieceType::from($type);

        if ($waterRedeploy && $piece !== \PieceType::Water) {
            throw  new \BgaVisibleSystemException("Invalid redeploy");
        }

        if (!$this->checkCoords($x, $y, $piece === \PieceType::Lotus)) {
            throw  new \BgaVisibleSystemException("Invalid coordinates");
        }

        $playerId = $this->getActivePlayerId();
        $playerIndex = $this->getPlayerNoById($playerId) - 1;

        $range = match ($piece) {
            \PieceType::Lotus,
            \PieceType::Sai => 0,
            \PieceType::Sword,
            \PieceType::Water => 1,
            PieceType::Bow => 4,
            default => 2
        };

        $pieces = $this->getNearbyPieces($x, $y, 4 + $range, $waterRedeploy);
        $threats = $this->getThreat($x, $y, $pieces, false);

        if ($piece === \PieceType::Lotus) {
            if ($threats[1 - $playerIndex] > 2) {
                throw new \BgaVisibleSystemException('Invalid lotus threat');
            }
        } else {
            if ($threats[1 - $playerIndex] > 0) {
                throw new \BgaVisibleSystemException('Invalid threat');
            } elseif ($threats[$playerIndex] === 0
                && !$this->checkBase($playerIndex, $x, $y))
            {
                throw new \BgaVisibleSystemException('Invalid base');
            }
        }

        $handCheck = $waterRedeploy ?
            'x IS NOT NULL' :
            'x IS NULL';

        self::DbQuery(<<<EOF
            UPDATE piece 
            SET x = $x, y = $y
            WHERE player_id = $playerId AND $handCheck
             AND type = $type
            LIMIT 1
            EOF);

        if (self::DbAffectedRow() === 0) {
            throw new \BgaVisibleSystemException('Invalid deploy');
        }

        $this->notifyAllPlayers('Deploy', clienttranslate('${player_name} deploys ${pieceIcon}'), [
            'player_name' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
            'type' => $type,
            'x' => $x,
            'y' => $y,
            'angle' => $angle,
            'pieceIcon' => "$playerIndex,$type"
        ]);

        if ($piece === \PieceType::Sai) {
            $this->set(\GameGlobal::SaiCoords, $x | $y << 8);
            $this->gamestate->nextState(\State::SAI_MOVE);
        } elseif ($this->capture($playerId, $pieces)) {
            //TODO
        } else {
            $this->gamestate->nextState(\State::NEXT_TURN);
        }

        $this->commitGlobals();
    }

    public function actSkip()
    {
        $this->set(\GameGlobal::SaiCoords, 0);
        $this->gamestate->nextState(\State::NEXT_TURN);
        $this->commitGlobals();
    }

    public function actMove(
        #[IntParam(min: 0, max:7)] int $type,
        #[IntParam(min: 0, max:13)] int $x,
        #[IntParam(min: 0, max:13)] int $y,
        #[IntArrayParam(min: 0, max:3)] array $steps,
        #[IntParam(min: 0, max:3)] int $angle = 0): void
    {
        $piece = \PieceType::from($type);

        $saiCoords = $this->get(\GameGlobal::SaiCoords);

        if ($saiCoords) {
            $saiX = $saiCoords & 0xFF;
            $saiY = $saiCoords >> 8;
            if ($saiX !== $x ||$saiY !== $y) {
                throw new \BgaVisibleSystemException('Invalid sai');
            }
            $this->set(\GameGlobal::SaiCoords, 0);
        }

        if (count($steps) > $piece->getMoves() || $piece->getMoves() === 0) {
            throw new \BgaVisibleSystemException('Invalid path');
        }

        $playerId = $this->getActivePlayerId();
        $playerIndex = $this->getPlayerNoById($playerId) - 1;

        $pieces = null;
        [$toX, $toY] = [$x, $y];
        foreach ($steps as $step) {
            [$dx, $dy] = DIRECTIONS[$step];
            $toX += $dx;
            $toY += $dy;

            if (!$this->checkCoords($toX, $toY)) {
                throw new \BgaVisibleSystemException('Invalid coordinates');
            }

            if ($pieces === null) {
                $pieces = $this->getNearbyPieces($toX, $toY);
            }
            $threats = $this->getThreat($toX, $toY, $pieces);
            $cover = $this->getCover($toX, $toY, $pieces);
            $selfThreat = $piece === \PieceType::Fire ? 1 : 0;
            if ($threats[1 - $playerIndex] + $selfThreat  - $cover[$playerIndex] > 1) {
                throw new \BgaVisibleSystemException('Invalid threat');
            }
        }

        self::DbQuery(<<<EOF
            UPDATE piece
            SET x = $toX, y = $toY, angle = $angle
            WHERE x = $x AND y = $y AND type = $type
             AND player_id = $playerId
            EOF);

        if (self::DbAffectedRow() === 0) {
            throw new \BgaVisibleSystemException('Invalid move');
        }

        $playerIndex = $this->getPlayerNoById($playerId) - 1;

        $this->notifyAllPlayers('Move', clienttranslate('${player_name} moves ${pieceIcon}'), [
            'player_name' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
            'from' => [$x, $y],
            'to' => [$toX, $toY],
            'angle' => [$angle],
            'pieceIcon' => "$playerIndex,$type"
        ]);

        $this->gamestate->nextState(\State::NEXT_TURN);
        $this->commitGlobals();
    }

    /**
     * @param array{ type: string, name: string } $state
     * @param int $player
     * @return void
     * @throws \feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $player): void
    {
        $stateName = $state['name'];

        if ($state['type'] === \FsmType::SINGLE_PLAYER) {
            switch ($stateName) {
                default:
                {
                    $this->gamestate->nextState('');
                    break;
                }
            }
        } elseif ($state['type'] === \FsmType::MULTIPLE_PLAYERS) {
            $this->gamestate->setPlayerNonMultiactive($player, '');
        } else {
            throw new \feException("Zombie mode not supported at this game state: \"$stateName\".");
        }

        $this->commitGlobals();
    }

    public function upgradeTableDb($fromVersion)
    {

    }

    public function reset()
    {
        self::DbQuery(<<<EOF
            UPDATE piece 
            SET x = NULL, y = NULL, angle = 0
            WHERE 1
            EOF);
        $this->gamestate->jumpToState(\State::ACTION);
    }

    public function prg()
    {
        $result = [];
        foreach (\GameGlobal::IDS as $id => $ignored) {
            $value = (int)$this->getGameStateValue($id);
            $result[] = "$id = $value";
        }
        $this->notifyAllPlayers('message', implode(", ", $result), []);
    }

    public function prp()
    {
        $pieces = self::getObjectListFromDB(
            "SELECT * FROM piece");
        $args = [];
        foreach ($pieces as $piece) {
            $type = \PieceType::from((int)$piece['type'])->getName();
            $args[] = !isset($piece['x']) ?
                "$type: hand" : "$type: $piece[x],$piece[y],$piece[angle]";
        }
        $this->notifyAllPlayers('message', implode('; ', $args), []);
    }

    public function dt()
    {
        $threats =[];
        $pieces = self::getObjectListFromDB(
            'SELECT * FROM piece WHERE x IS NOT NULL');
        foreach (range(0, 13) as $y) {
            $width = $y < 7 ? $y : 13 - $y;
            foreach (range(6 - $width, 7 + $width) as $x) {
                [$self, $other] = $this->getThreat($x, $y, $pieces);
                if ($self > 0) {
                    $threats[] = [$x, $y];
                }
            }
        }

        $this->notifyAllPlayers('Threat', '', [
            'threats' => $threats
        ]);
    }
}
