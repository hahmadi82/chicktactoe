<?php

require_once('Redis.php');

/**
 * Class Board
 *
 * Maintains the board state to keep track of moves players have made and returns a
 * representation of the board to be displayed in Slack.
 * The board state is saved and received from Redis.
 */
class Board
{
    private $playerMarkerMap = [1 => ':x:', 2 => ':o:'];
    private $size = 3;
    private $boardHash;

    private $redisKey;
    private $redis;

    /**
     * For a give channel, we save the Redis key used to reference the Tic Tac Toe board state.
     * We initialize the class with the board state hash map.
     * The default is always an empty board represented by [].
     *
     * @param $channelId
     * @param RedisState $redis
     */
    public function __construct($channelId, RedisState $redis)
    {
        $this->redisKey = "$channelId:board";
        $this->redis = $redis;
        $this->boardHash = $this->redis->getRedis($this->redisKey, []);
    }

    /**
     * We iterate through the 3x3 grid to get the board hash map value for each square.
     * This method will format the board as a single string that will output in Slack as such (with Emojis):
     * X O X
     * O O X
     * X O O
     *
     * If the displayInstructions flag is true, we want to display a grid that shows the number associated with
     * each square and an example marked square (mark 4 with X):
     * 1 2 3
     * X 5 6
     * 7 8 9
     *
     * @param bool $displayInstructions
     * @return string
     */
    public function getBoardState($displayInstructions = false)
    {
        $boardAsString = "";
        for ($i = 1; $i <= pow($this->size, 2); $i++) {
            if ($displayInstructions) {
                $boardAsString .= $this->getNumberEmoji($i);
            } else {
                $boardAsString .= $this->getMarkedSquare($i);
            }

            if($i % $this->size == 0) {
                $boardAsString .= "\n";
            }
        }
        return $boardAsString;
    }

    /**
     * When we attempt to mark a square for a player, we first check that the player is either
     * 1 or 2, so we can retrieve the proper marker for them (X or O).
     * We also check that the square is a valid number 1-9 (representing each square on the Tic Tac Toe board).
     * The last thing we check is that the square is empty and does not already have an X or O on it.
     * If all conditions hold true, we mark the board hash map for that square and save the state to Redis.
     *
     * @param $playerNumber
     * @param $num
     * @return bool
     */
    public function markSquareForPlayer($playerNumber, $num)
    {
        $success = false;
        if ($this->isValidPlayer($playerNumber) &&
            $this->isValidSquareCoordinate($num) &&
            !$this->isSquareMarked($num)) {

            $this->boardHash[$num] = $this->playerMarkerMap[$playerNumber];
            $this->redis->setRedis($this->redisKey, $this->boardHash);
            $success =  true;
        }

        return $success;
    }

    /**
     * Given a square number we check that the value does not exist in the board hash map.
     * If the number doesn't exist yet, we know the square is empty.
     *
     * @param $num
     * @return bool
     */
    public function isSquareMarked($num)
    {
        return isset($this->boardHash[$num]);
    }

    /**
     * Sets the current board hash as empty and deletes the state from Redis for the given channel.
     */
    public function clearBoard()
    {
        $this->boardHash = [];
        $this->redis->deleteRedis($this->redisKey);
    }

    /**
     * Since the board hash can only be written with values 1-9, we check that the number of elements
     * is not greater than NxN, in this case 9.
     * If so, we know the board is full.
     * @return bool
     */
    public function isBoardFull() {
        return count($this->boardHash) == pow($this->size,2);
    }

    /**
     * After a player makes a move, we want to check if this move wins the game.
     * We check each horizontal and vertical row for 3 consecutive markers belonging to the current player (P1:X or P2:O).
     * The checking order in the nested 'for' loops is as follows:
     * row1 -> 1-2-3, col1 -> 1-4-7, row2 -> 4-5-6, col2 -> 2-5-8, row3 -> 7-8-9, col3 -> 3-6-9.
     * Any non-sequence immediately breaks out of the nested loop and moves onto the next check to avoid redundancy.
     * We then check the squares in the two diagonals 1-5-9 and 3-5-7.
     *
     * @param $playerNumber
     * @return bool
     */
    public function playerSequenceFound($playerNumber) {
        $squareMarker = $this->playerMarkerMap[$playerNumber];

        for ($i = 0; $i < $this->size; $i++) {
            for ($j = 0; $j < $this->size; $j++) {
                $squareToCheck = $i*$this->size + $j + 1;
                if ($this->getMarkedSquare($squareToCheck) != $squareMarker) {
                    break;
                }

                if ($j == $this->size - 1) {
                    return true;
                }
            }

            for ($j = 0; $j < $this->size; $j++) {
                $squareToCheck = $j*$this->size + $i + 1;
                if ($this->getMarkedSquare($squareToCheck) != $squareMarker) {
                    break;
                }

                if ($j == $this->size - 1) {
                    return true;
                }
            }
        }

        //Check the two diagonals 1-5-9 and 7-5-3
        if ($this->getMarkedSquare(5) == $squareMarker && (
            ($this->getMarkedSquare(1) == $squareMarker && $this->getMarkedSquare(9) == $squareMarker) ||
            ($this->getMarkedSquare(7) == $squareMarker && $this->getMarkedSquare(3) == $squareMarker))) {
            return true;
        }

        return false;
    }

    /**
     * Hash map that returns the Emoji value for each square marked by a number.
     * The 4th square is an X to show an example of a move on the Tic Tac Toe grid.
     *
     * @param $num
     * @return mixed
     */
    private function getNumberEmoji($num)
    {
        $numberEmojiMap = [1 => ":one:", 2 => ":two:", 3 => ":three:",
                           4 => ":x:", 5 => ":five:", 6 => ":six:",
                           7 => ":seven:", 8 => ":eight:", 9 => ":nine:"];
        return $numberEmojiMap[$num];
    }

    /**
     * The regular expression checks that the square number submitted by the player
     * is between 1-9 where the square is a 3x3 grid.
     *
     * @param $num
     * @return int
     */
    private function isValidSquareCoordinate($num)
    {
        $regex = "/^[1-" . pow($this->size, 2) . "]$/";
        return preg_match($regex, $num);
    }

    /**
     * Check if the square is marked.
     * If so, we retrieve that square value from the board hash,
     * otherwise return a blank square Emoji (in this case a rooster Emoji).
     * @param $num
     * @return string
     */
    private function getMarkedSquare($num)
    {
        return $this->isSquareMarked($num) ? $this->boardHash[$num] : ":rooster:";
    }

    /**
     * Check if a player number is in the player marker mapping.
     *
     * @param $playerNumber
     * @return bool
     */
    private function isValidPlayer($playerNumber)
    {
        return in_array($playerNumber, array_keys($this->playerMarkerMap));
    }
}

