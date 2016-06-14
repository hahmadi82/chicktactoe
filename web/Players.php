<?php

require_once('Redis.php');

/**
 * Class Players
 *
 * Keeps track of the players playing the Tic Tac Toe game.
 * The players in the challenge are stored in Redis and this class
 * makes sure they are the only Slack channel members able to play the game.
 * The player turn is managed here and saved/retrieved from Redis.
 */
class Players {
    private $player1;
    private $player2;
    private $playerTurn;

    private $redis;
    private $redisKeyP1;
    private $redisKeyP2;
    private $redisKeyTurn;

    /**
     * For a given channel, we save the Redis key used to reference player 1 and 2.
     * We initialize the class with player userNames retrieved from Redis along
     * with the current player's turn (either 1 or 2).
     * The default value is null so we can check whether players are playing or not.
     * The default turn is always Player 1, for cases where the game just started and nothing is stored in Redis.
     *
     * @param $channelId
     * @param RedisState $redis
     */
    public function __construct($channelId, RedisState $redis) {
        $this->redisKeyP1 = "$channelId:p1";
        $this->redisKeyP2 = "$channelId:p2";
        $this->redisKeyTurn = "$channelId:playerTurn";

        $this->redis = $redis;
        $this->player1 = $this->redis->getRedis($this->redisKeyP1, null); //default null for hasPlayers check
        $this->player2 = $this->redis->getRedis($this->redisKeyP2, null);
        $this->playerTurn = $this->redis->getRedis($this->redisKeyTurn, 1);
    }

    /**
     * When a player challenges another player, we store the first userName as player 1
     * and the player they challenged as player 2.
     * Since players may or may not user the @ symbol preceding the userName, we strip it out before storing.
     *
     * @param $userName1
     * @param $userName2
     */
    public function challenge($userName1, $userName2)
    {
        $this->player1 = ltrim($userName1, '@');
        $this->player2 = ltrim($userName2, '@');
        $this->redis->setRedis($this->redisKeyP1, $this->player1);
        $this->redis->setRedis($this->redisKeyP2, $this->player2);
    }

    /**
     * Given a player number (1 or 2) we return the corresponding userName that was retrieved
     * earlier in the constructor.
     *
     * @param $playerNumber
     * @return mixed|null
     */
    public function getPlayerUserName($playerNumber)
    {
        $memberVarString = "player" . $playerNumber;
        return $this->$memberVarString;
    }

    /**
     * To check if a player is allowed to make a move, we try to retrieve the player number
     * associated wth the userName (1 or 2) and check if that player number matches the player
     * number that should be making the current turn.
     * The current turn number was retrieved from Redis in the constructor earlier.
     *
     * @param $userName
     * @return bool
     */
    public function isPlayerAllowedToMakeMove($userName)
    {
        $playerNumber = $this->getPlayerNumber($userName);
        return $playerNumber == $this->playerTurn;
    }

    /**
     * If the current turn is 1 we flip it to 2.
     * If it's 2 we flip it to 1.
     * We then proceed to store the new turn number in Redis.
     */
    public function toggleTurn()
    {
        $this->playerTurn = $this->playerTurn == 1 ? 2 : 1;
        $this->redis->setRedis($this->redisKeyTurn, $this->playerTurn);
    }

    /**
     * We reset the turn to 1 and store in Redis.
     * This is for cases where we reset the game after a game has ended.
     */
    public function resetPlayerTurn()
    {
        $this->playerTurn = 1;
        $this->redis->setRedis($this->redisKeyTurn, $this->playerTurn);
    }

    /**
     * Returns the current player turn, which was retrieved from Redis in the constructor earlier.
     *
     * @return mixed|null
     */
    public function getCurrentTurn()
    {
        return $this->playerTurn;
    }

    /**
     * For the current player turn (1 or 2), we return the userName associated with it.
     *
     * @return mixed|null
     */
    public function getCurrentTurnUserName()
    {
        return $this->getPlayerUserName($this->playerTurn);
    }

    /**
     * Check to see that two players are engaged in a challenge of Tic Tac Toe.
     */
    public function hasPlayers()
    {
        return isset($this->player1) && isset($this->player2);
    }

    /**
     * Sets the players as null and deletes them from Redis for the given channel.
     */
    public function clearPlayers()
    {
        $this->player1 = null;
        $this->redis->deleteRedis($this->redisKeyP1);

        $this->player2 = null;
        $this->redis->deleteRedis($this->redisKeyP2);
    }

    /**
     * For a given userName, we check the player1 and player2 class members to see if they match.
     * If they do, we return the number associated with them. If not, we return 0 which will cause
     * failures in player turn validation (checking whether a player is allowed to make a move).
     *
     * @param $userName
     * @return int
     */
    private function getPlayerNumber($userName)
    {
        if ($this->player1 == $userName) {
            return 1;
        }

        if ($this->player2 == $userName) {
            return 2;
        }

        return 0;
    }

}
