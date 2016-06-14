<?php

require_once("TestCaseCTT.php");
require_once("Players.php");

class PlayersTest extends TestCaseCTT
{
    protected $players;

    /**
     * Mock the Redis class object and initialize the players.
     * We store the players object as a member variable to be reused by tests later if needed.
     * We also initialize player 1 and 2 with their respective userNames.
     */
    protected function setUp()
    {
        $redisMock = $this->getRedisMock('getRedis', 3);
        $this->players = new Players(self::TEST_CHANNEL_ID, $redisMock);
        $this->setProtectedValue($this->players, 'player1', self::TEST_USER_NAME1);
        $this->setProtectedValue($this->players, 'player2', self::TEST_USER_NAME2);
    }

    /**
     * Free the class member between tests.
     */
    protected function tearDown()
    {
        $this->players = null;
    }

    /**
     * Create a new players object where we expect 'setRedis' to be called twice, once for each player.
     * We also check that when a challenge is made, each player's userName is properly assigned to the
     * class member variables.
     */
    public function testChallenge()
    {
        $redisMock = $this->getRedisMock('setRedis', 2);

        $players = new Players(self::TEST_CHANNEL_ID, $redisMock);
        $players->challenge(self::TEST_USER_NAME1, self::TEST_USER_NAME2);

        $player1 = $this->getProtectedValue($players, 'player1');
        $player2 = $this->getProtectedValue($players, 'player2');

        $this->assertEquals(self::TEST_USER_NAME1, $player1);
        $this->assertEquals(self::TEST_USER_NAME2, $player2);
    }

    /**
     * Test that getting the player 1 userName returns the userName assigned for player 1 in the setUp.
     * Test that getting the player 2 userName returns the userName assigned for player 2 in the setUp.
     */
    public function testGetPlayerUserName()
    {
        $result = $this->invokeMethod($this->players, 'getPlayerUserName', [1]);
        $this->assertEquals(self::TEST_USER_NAME1, $result);

        $result = $this->invokeMethod($this->players, 'getPlayerUserName', [2]);
        $this->assertEquals(self::TEST_USER_NAME2, $result);
    }

    /**
     * Test that given a userName we get the respective player number stored in the player class member variable.
     */
    public function testGetPlayerNumber()
    {
        $result = $this->invokeMethod($this->players, 'getPlayerNumber', [self::TEST_USER_NAME1]);
        $this->assertEquals(1, $result);

        $result = $this->invokeMethod($this->players, 'getPlayerNumber', [self::TEST_USER_NAME2]);
        $this->assertEquals(2, $result);
    }

    /**
     * We make sure that the player turn is set to 1, then check that userName of player 1 is allowed to make the move
     * while the userName of player 2 is not allowed.
     */
    public function testIsPlayerAllowedToMakeMove()
    {
        $this->setProtectedValue($this->players, 'playerTurn', 1);
        $result = $this->invokeMethod($this->players, 'isPlayerAllowedToMakeMove', [self::TEST_USER_NAME1]);
        $this->assertTrue($result);

        $result = $this->invokeMethod($this->players, 'isPlayerAllowedToMakeMove', [self::TEST_USER_NAME2]);
        $this->assertFalse($result);
    }

    /**
     * Create a new players object where we expect 'setRedis' to be called twice, once for each toggle.
     * We set the player turn to 1 and make sure that toggling the player turn sets it to 2.
     * We then toggle again and expect the value to be 1.
     */
    public function testToggleTurn()
    {
        $redisMock = $this->getRedisMock('setRedis', 2);
        $players = new Players(self::TEST_CHANNEL_ID, $redisMock);

        $this->setProtectedValue($players, 'playerTurn', 1);
        $this->invokeMethod($players, 'toggleTurn');
        $result = $this->getProtectedValue($players, 'playerTurn');
        $this->assertEquals(2, $result);

        $this->invokeMethod($players, 'toggleTurn');
        $result = $this->getProtectedValue($players, 'playerTurn');
        $this->assertEquals(1, $result);
    }

    /**
     * Set player 1 and 2 to userNames and run the clear method which should call delete in Redis twice
     * and set the names to null.
     */
    public function testClearPlayers()
    {
        $redisMock = $this->getRedisMock('deleteRedis', 2);
        $players = new Players(self::TEST_CHANNEL_ID, $redisMock);

        $this->setProtectedValue($players, 'player1', self::TEST_USER_NAME1);
        $this->setProtectedValue($players, 'player2', self::TEST_USER_NAME2);
        $this->invokeMethod($players, 'clearPlayers');

        $result = $this->getProtectedValue($players, 'player1');
        $this->assertNull($result);

        $result = $this->getProtectedValue($players, 'player2');
        $this->assertNull($result);
    }

    /**
     * Create a new players object where we expect 'setRedis' to be called once.
     * We set the player turn to 2 then call reset and expect it to be set to 1.
     */
    public function testResetPlayerTurn()
    {
        $redisMock = $this->getRedisMock('setRedis', 1);
        $players = new Players(self::TEST_CHANNEL_ID, $redisMock);

        $this->setProtectedValue($players, 'playerTurn', 2);
        $this->invokeMethod($players, 'resetPlayerTurn');
        $result = $this->getProtectedValue($players, 'playerTurn');
        $this->assertEquals(1, $result);
    }

    /**
     * Sets the player turn to 2 and checks that getting the current turn returns 2.
     */
    public function testGetCurrentTurn()
    {
        $this->setProtectedValue($this->players, 'playerTurn', 2);
        $result = $this->invokeMethod($this->players, 'getCurrentTurn');
        $this->assertEquals(2, $result);
    }

    /**
     * Tests that getting the userName for the current player turn (set to 2) returns the userName saved for player 2.
     */
    public function testGetCurrentTurnUserName()
    {
        $this->setProtectedValue($this->players, 'playerTurn', 2);
        $result = $this->invokeMethod($this->players, 'getCurrentTurnUserName');
        $this->assertEquals(self::TEST_USER_NAME2, $result);
    }

    /**
     * Test that having one or less players returns false.
     * We only want to return true if both players are set.
     */
    public function testHasPlayers()
    {
        $this->setProtectedValue($this->players, 'player1', null);
        $this->setProtectedValue($this->players, 'player2', null);
        $result = $this->invokeMethod($this->players, 'hasPlayers');
        $this->assertFalse($result);

        $this->setProtectedValue($this->players, 'player2', self::TEST_USER_NAME2);
        $result = $this->invokeMethod($this->players, 'hasPlayers');
        $this->assertFalse($result);

        $this->setProtectedValue($this->players, 'player1', self::TEST_USER_NAME1);
        $result = $this->invokeMethod($this->players, 'hasPlayers');
        $this->assertTrue($result);
    }
}
