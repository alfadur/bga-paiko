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
        $hand = \PieceStatus::Reserve->value;
        $id = 0;
        foreach ($players as $playerId => $player) {
            $playerIndex = $this->getPlayerNoById($playerId) - 1;
            foreach (\PieceType::cases() as $type) {
                foreach (range(0, 2) as $ignored) {
                    $pieces[] = "($id, $playerIndex, $type->value, $hand)";
                    ++$id;
                }
            }
        }

        $args = implode(',', $pieces);
        self::DbQuery(<<<EOF
            INSERT INTO piece(id, player, type, status) 
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
            "SELECT player_id AS id, player_no AS no FROM player"
        );

        $scores = $this->get(\GameGlobal::Score);
        foreach ($result['players'] as &$player) {
            $player['score'] = $player['no'] === '1' ?
                $scores & 0xFF : $scores >> 8;
        }

        $result['pieces'] = self::getObjectListFromDB(
            "SELECT * FROM piece");

        $lastPieces = $this->get(\GameGlobal::LastPiece);
        $lastMoves = [];
        foreach (range(0, 1) as $playerIndex) {
            $lastPiece = $lastPieces >> 16 * $playerIndex & 0xFFFF;
            $id = $lastPiece & 0xFF;
            if ($id !== 0) {
                $lastMoves[] = [
                    'id' => $id - 1,
                    'moves' => $lastPiece >> 8
                ];
            }
        }
        $result['lastMoves'] = $lastMoves;

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

    private function getBase(int $x, int  $y): ?int
    {
        if ($x < 7 && $y > 6) {
            return 0;
        } elseif ($x > 6 && $y < 7) {
            return 1;
        }
        return null;
    }

    private function getBasePoints(int $playerIndex, ?int $base): int
    {
        return match($base) {
            $playerIndex => 0,
            1 - $playerIndex => 2,
            default => 1
        };
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
            $playerIndex = (int)$piece['player'];
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
        $base = $this->getBase($x, $y);
        if ($base !== null) {
            $result[$base] += 1;
        }
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
                $value = max(0, $value - $cover[1 - $index]);
            }
        }
        return $result;
    }

    private function capture(int $playerIndex, $pieces): bool
    {
        $captures = 0;
        $fireCaptures = 0;

        foreach ($pieces as $piece) {
            $x = (int)$piece['x'];
            $y = (int)$piece['y'];
            $id = (int)$piece['id'];
            $type = \PieceType::from((int)$piece['type']);
            $pieceIndex = (int)$piece['player'];

            $threats = $this->getThreat($x, $y, $pieces, true);

            if ($threats[1 - $pieceIndex] >= 2) {
                if ($pieceIndex === $playerIndex) {
                    throw new \BgaVisibleSystemException('Invalid self capture');
                }
                if ($type === \PieceType::Fire) {
                    $fireCaptures = $fireCaptures << 8 | $id;
                } else {
                    $captures = $captures << 8 | $id;
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

    private function updateLastPiece(int $playerIndex, int $id, bool $reset)
    {
        $lastPieces = $this->get(\GameGlobal::LastPiece);
        $lastPiece = $lastPieces >> $playerIndex * 16 & 0xFFFF;

        if (!$reset && ($lastPiece & 0xFF) === $id + 1) {
            if ($lastPiece >> 8 >= 3) {
                throw new \BgaVisibleSystemException('Repeated piece');
            }
            $lastPieces += 1 << (8 + $playerIndex * 16);
        } else {
            $lastPieces &= ~(0xFFFF << $playerIndex * 16);
            if (!$reset) {
                $lastPieces |= (1 << 8 | $id + 1) << $playerIndex * 16;
            }
        }
        $this->set(\GameGlobal::LastPiece, $lastPieces);
    }

    private function checkGameEnd(): bool
    {
        $scores = $this->get(\GameGlobal::Score);
        $scores = [$scores & 0xFF, $scores >> 8];

        if ($scores[0] >= 10 || $scores[1] >= 10) {
            self::DbQuery(<<<EOF
                        UPDATE player 
                        SET player_score = CASE WHEN player_no = 1 THEN $scores[0] ELSE $scores[1] END
                        WHERE 1
                        EOF);
            return true;
        }
        return false;
    }

    private function checkTie(): bool
    {
        $scores = $this->get(\GameGlobal::Score);
        $scores = [$scores & 0xFF, $scores >> 8];
        $captures = $this->get(\GameGlobal::CapturedPieces);
        $captures = [$captures & 0xFF, $captures >> 8];

        if ($captures[0] >= 13 && $captures[1] >= 13
            && $scores[0] <= 5 && $scores[1] <= 5)
        {
            self::DbQuery(<<<EOF
                        UPDATE player 
                        SET player_score = 1
                        WHERE 1
                        EOF);
            return true;
        }
        return false;
    }

    private function switchPlayer(): void
    {
        $playerId = $this->getActivePlayerId();
        $playerIndex = $this->getPlayerNoById($playerId) - 1;

        if ($playerIndex === $this->get(\GameGlobal::LastPlayer)) {
            $this->activeNextPlayer();
        }
    }

    public function stDraftDispatch(): void
    {
        $hand = \PieceStatus::Hand->value;

        $drafted = (int)self::getUniqueValueFromDB(<<<EOF
            SELECT COUNT(*) AS count 
            FROM piece  
            WHERE status = $hand
            EOF);
        if ($drafted < 17) {
            $this->activeNextPlayer();
            $this->gamestate->nextState(\State::DRAFT);
        } else {
            $this->gamestate->nextState(\State::ACTION);
        }
    }

    public function stNextTurn(): void
    {
        if ($this->checkGameEnd() || $this->checkTie()) {
            $this->gamestate->nextState(\State::GAME_END);
        } else {
            $this->switchPlayer();
            $this->gamestate->nextState(\State::ACTION);
        }
    }

    public function stCapture(): void
    {
        $this->switchPlayer();

        $captures = $this->get(\GameGlobal::Captures);
        $isFire = false;

        if ($captures === 0) {
            $captures = $this->get(\GameGlobal::FireCaptures);
            $isFire = true;
        }

        if ($captures === 0) {
            $this->gamestate->nextState(\State::NEXT_TURN);
        } else {
            $id = $captures & 0xFF;

            $item = self::getObjectFromDB(<<<EOF
                SELECT type, x, y 
                FROM piece
                WHERE id = $id
                EOF);
            $x = (int)$item['x'];
            $y = (int)$item['y'];
            $type = (int)$item['type'];

            self::DbQuery(<<<EOF
                DELETE FROM piece
                WHERE id = $id
                EOF);

            $this->set($isFire ? \GameGlobal::FireCaptures : \GameGlobal::Captures, $captures >> 8);

            if (count(\PieceType::COVER[$type]) > 0) {
                $board = \PieceStatus::Board->value;
                $pieces = self::getObjectListFromDB(<<<EOF
                        SELECT * FROM piece
                        WHERE status = $board
                        EOF);
                $this->capture($this->get(\GameGlobal::LastPlayer), $pieces);
            }

            $playerId = $this->getActivePlayerId();
            $playerIndex = $this->getPlayerNoById($playerId) - 1;

            $score = $type !== \PieceType::Lotus->value ?
                - $this->getBasePoints($playerIndex, $this->getBase($x, $y)) :
                0;
            if ($score !== 0) {
                $this->postInc(\GameGlobal::Score, $score << 8 * $playerIndex);
            }

            $this->postInc(\GameGlobal::CapturedPieces, 1 << $playerIndex * 8);

            $this->notifyAllPlayers('Capture', clienttranslate('${pieceIcon} is captured'), [
                'playerIndex' => $playerIndex,
                'id' => $id,
                'score' => $score,
                'pieceIcon' => "$playerIndex,$id"
            ]);

            $reserve = \PieceStatus::Reserve->value;
            $otherPlayerIndex = 1 - $playerIndex;
            $reservePieces = (int)self::getUniqueValueFromDB(<<<EOF
                SELECT COUNT(*)
                FROM piece
                WHERE player = $otherPlayerIndex AND status = $reserve
                EOF);
            if ($reservePieces === 0) {
                $this->gamestate->nextState(\State::CAPTURE);
            } else {
                $this->gamestate->nextState(\State::RESERVE);
            }
        }
    }

    private function getDraftCount(): int
    {
        $hand = \PieceStatus::Hand->value;
        $drafted = (int)self::getUniqueValueFromDB(<<<EOF
            SELECT COUNT(*) AS count 
            FROM piece  
            WHERE status = $hand
            EOF);

        return match ($drafted) {
            0 => 7,
            7 => 9,
            16 => 1,
            default => 0
        };
    }

    public function argDraft(): array
    {
        return [
            'count' => $this->getDraftCount()
        ];
    }

    public function argSaiMove(): array
    {
        $playerId = $this->getActivePlayerId();
        $playerIndex = $this->getPlayerNoById($playerId) - 1;
        $saiId = $this->get(\GameGlobal::SaiId) - 1;
        return [
            'saiId' => $saiId,
            'pieceIcon' => "$playerIndex,$saiId"
        ];
    }

    public function argCapture(): array
    {
        return [
            'captures' => $this->get(\GameGlobal::Captures),
            'fireCaptures' => $this->get(\GameGlobal::FireCaptures)
        ];
    }

    public function actDraft(
        #[IntArrayParam] array $ids): void
    {
        $state = (int)$this->gamestate->state_id();
        $playerId = $this->getActivePlayerId();
        $playerIndex = $this->getPlayerNoById($playerId) - 1;

        $this->updateLastPiece($playerIndex, 0, true);

        $draftCount = match ($state) {
            \State::DRAFT => $this->getDraftCount(),
            \State::RESERVE => 1,
            default => 3
        };

        $hand = \PieceStatus::Hand->value;
        $reserve = \PieceStatus::Reserve->value;
        $ids_str = implode(',', $ids);

        $playerCheck = $state === \State::RESERVE ?
            "player <> $playerIndex" :
            "player = $playerIndex";

        self::DbQuery(<<<EOF
            UPDATE piece 
            SET status = $hand
            WHERE id IN ($ids_str) 
              AND status = $reserve
              AND $playerCheck
        EOF);

        $correctCount = $state === \State::ACTION ?
            self::DbAffectedRow() <= $draftCount :
            self::DbAffectedRow() === $draftCount;

        if (!$correctCount) {
            throw new \BgaVisibleSystemException('Invalid draw');
        }

        if ($state === \State::RESERVE) {
            $playerIndex = 1 - $playerIndex;
        } else {
            $this->set(\GameGlobal::LastPlayer, $playerIndex);
        }

        $message = $state === \State::RESERVE ?
            clienttranslate('${player_name} chooses  ${pieceIcons} for the opponent to draw from reserve') :
            clienttranslate('${player_name} draws ${pieceIcons} from reserve');
        $this->notifyAllPlayers('Draft', $message, [
            'player_name' => $this->getPlayerNameById($playerId),
            'playerIndex' => $playerIndex,
            'pieceIds' => $ids,
            'pieceIcons' => "$playerIndex,$ids_str"
        ]);

        $this->gamestate->nextState(match ($state) {
            \State::DRAFT => \State::DRAFT_DISPATCH,
            \State::RESERVE => \State::CAPTURE,
            default => \State::NEXT_TURN
        });

        $this->commitGlobals();
    }

    public function actDeploy(
        #[IntParam] int $id,
        #[IntParam(min: 0, max:7)] int $type,
        #[IntParam(min: 0, max:13)] int $x,
        #[IntParam(min: 0, max:13)] int $y,
        #[IntParam(min: 0, max:13)] int $fromX = 0,
        #[IntParam(min: 0, max:13)] int $fromY = 0,
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

        $this->updateLastPiece($playerIndex, $id, !$waterRedeploy);

        $range = match ($piece) {
            \PieceType::Lotus,
            \PieceType::Sai => 0,
            \PieceType::Sword,
            \PieceType::Water => 1,
            \PieceType::Bow => 4,
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

        $hand = \PieceStatus::Hand->value;
        $board = \PieceStatus::Board->value;
        $handCheck = $waterRedeploy ?
            "status = $board" :
            "status = $hand";

        $redeployCheck = $waterRedeploy ?
            "x = $fromX AND y = $fromY" : '1';
        self::DbQuery(<<<EOF
            UPDATE piece 
            SET x = $x, y = $y, angle = $angle, status = $board
            WHERE id = $id AND type = $type
              AND player = $playerIndex AND $handCheck
              AND $redeployCheck
            LIMIT 1
            EOF);

        if (self::DbAffectedRow() === 0) {
            throw new \BgaVisibleSystemException('Invalid deploy');
        }

        $score = $piece !== \PieceType::Lotus ?
            $this->getBasePoints($playerIndex, $this->getBase($x, $y)) :
            0;
        if ($waterRedeploy) {
            $score -= $this->getBasePoints($playerIndex, $this->getBase($fromX, $fromY));
        }

        if ($score !== 0) {
            $this->postInc(\GameGlobal::Score, $score << 8 * $playerIndex);
        }

        $message = $waterRedeploy ?
            clienttranslate('${player_name} redeploys ${pieceIcon}') :
            clienttranslate('${player_name} deploys ${pieceIcon}');

        $this->notifyAllPlayers('Move', $message, [
            'player_name' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
            'id' => $id,
            'x' => $x,
            'y' => $y,
            'angle' => $angle,
            'score' => $score,
            'isMove' => $waterRedeploy,
            'pieceIcon' => "$playerIndex,$id"
        ]);

        $pieces[] = [
            'id' => $id,
            'x' => $x,
            'y' => $y,
            'type' => $piece->value,
            'angle' => $angle,
            'player' => $playerIndex
        ];

        $this->set(\GameGlobal::LastPlayer, $playerIndex);

        if ($piece === \PieceType::Sai) {
            $this->set(\GameGlobal::SaiId, $id + 1);
            $this->gamestate->nextState(\State::SAI_MOVE);
        } elseif ($this->capture($playerIndex, $pieces)) {
            $this->gamestate->nextState(\State::CAPTURE);
        } else {
            $this->gamestate->nextState(\State::NEXT_TURN);
        }

        $this->commitGlobals();
    }

    public function actSkip()
    {
        $saiId = $this->get(\GameGlobal::SaiId) - 1;
        $this->set(\GameGlobal::SaiId, 0);

        $pieces = self::getObjectListFromDB(<<<EOF
            SELECT piece.*
            FROM piece AS sai INNER JOIN piece ON (
                    piece.x BETWEEN sai.x - 5 AND sai.x + 5
                AND piece.y BETWEEN sai.y - 5 AND sai.y + 5)
            WHERE sai.id = $saiId
            EOF);

        if ($this->capture($this->get(\GameGlobal::LastPlayer), $pieces)) {
            $this->gamestate->nextState(\State::CAPTURE);
        } else {
            $this->gamestate->nextState(\State::NEXT_TURN);
        }

        $this->commitGlobals();
    }

    public function actMove(
        #[IntParam] int $id,
        #[IntParam(min: 0, max:7)] int $type,
        #[IntParam(min: 0, max:13)] int $x,
        #[IntParam(min: 0, max:13)] int $y,
        #[IntArrayParam(min: 0, max:3)] array $steps,
        #[IntParam(min: 0, max:3)] int $angle = 0): void
    {
        $piece = \PieceType::from($type);

        $saiId = $this->get(\GameGlobal::SaiId);

        if ($saiId) {
            if ($saiId - 1 !== $id) {
                throw new \BgaVisibleSystemException('Invalid sai');
            }
            $this->set(\GameGlobal::SaiId, 0);
        }

        if (count($steps) > $piece->getMoves() || $piece->getMoves() === 0) {
            throw new \BgaVisibleSystemException('Invalid path');
        }

        $playerId = $this->getActivePlayerId();
        $playerIndex = $this->getPlayerNoById($playerId) - 1;

        $this->updateLastPiece($playerIndex, $id, false);

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
                $pieces = array_filter($pieces, fn($piece) => (int)$piece['x'] !== $x || (int)$piece['y'] !== $y);
            }
            $threats = $this->getThreat($toX, $toY, $pieces, true);
            $cover = $this->getCover($toX, $toY, $pieces);
            $selfThreat = $piece === \PieceType::Fire ? 1 : 0;
            if ($threats[1 - $playerIndex] + $selfThreat  - $cover[$playerIndex] > 1) {
                throw new \BgaVisibleSystemException('Invalid threat');
            }
        }

        self::DbQuery(<<<EOF
            UPDATE piece
            SET x = $toX, y = $toY, angle = $angle
            WHERE id = $id AND type = $type
             AND x = $x AND y = $y
             AND player = $playerIndex
            EOF);

        if (self::DbAffectedRow() === 0) {
            throw new \BgaVisibleSystemException('Invalid move');
        }

        $playerIndex = $this->getPlayerNoById($playerId) - 1;

        $score =
            $this->getBasePoints($playerIndex, $this->getBase($toX, $toY))
            - $this->getBasePoints($playerIndex, $this->getBase($x, $y));

        if ($score !== 0) {
            $this->postInc(\GameGlobal::Score, $score << 8 * $playerIndex);
        }

        $this->notifyAllPlayers('Move', clienttranslate('${player_name} moves ${pieceIcon}'), [
            'player_name' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
            'id' => $id,
            'x' => $toX,
            'y' => $toY,
            'angle' => [$angle],
            'score' => $score,
            'isMove' => true,
            'pieceIcon' => "$playerIndex,$id"
        ]);

        $this->set(\GameGlobal::LastPlayer, $playerIndex);

        $pieces[] = [
            'id' => $id,
            'x' => $toX,
            'y' => $toY,
            'type' => $type,
            'angle' => $angle,
            'player' => $playerIndex
        ];

        if ($this->capture($playerIndex, $pieces)) {
            $this->gamestate->nextState(\State::CAPTURE);
        } else {
            $this->gamestate->nextState(\State::NEXT_TURN);
        }

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
            $playerId = $this->getActivePlayerId();
            $playerIndex = $this->getPlayerNoById($playerId) - 1;
            $this->set(\GameGlobal::LastPlayer, $playerIndex);
            $this->gamestate->jumpToState(\State::NEXT_TURN);
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
        $reserve = \PieceStatus::Reserve->value;
        self::DbQuery(<<<EOF
            UPDATE piece 
            SET x = NULL, y = NULL, angle = 0, status = $reserve
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

    public function dt()
    {
        $threats =[];
        $pieces = self::getObjectListFromDB(
            'SELECT * FROM piece WHERE x IS NOT NULL');
        foreach (range(0, 13) as $y) {
            $width = $y < 7 ? $y : 13 - $y;
            foreach (range(6 - $width, 7 + $width) as $x) {
                [$self, $other] = $this->getThreat($x, $y, $pieces, false);
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
