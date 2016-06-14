<?php

require_once("TestCaseCTT.php");
require_once("Game.php");

class GameTest extends TestCaseCTT
{
    protected $game;
    const TEST_CHANNEL_ID = '12345';

    /**
     * Mock the Redis class object and initialize the board.
     * We store the game as a member variable to be reused by tests later if needed.
     * The game object contains a mocked class member variables of both the board and players.
     */
    protected function setUp()
    {
        $redisMock = $this->getMockBuilder('RedisState')
            ->setMethods(['setRedis', 'getRedis', 'deleteRedis'])
            ->getMock();

        $this->game = new Game(self::TEST_CHANNEL_ID, $redisMock);

        $playersMock = $this->getMockBuilder('Players')
            ->setConstructorArgs([self::TEST_CHANNEL_ID, $redisMock])
            ->setMethods(['challenge', 'getPlayerUserName', 'isPlayerAllowedToMakeMove', 'hasPlayers',
                'toggleTurn', 'resetPlayerTurn', 'getCurrentTurn', 'getCurrentTurnUserName', 'clearPlayers'])
            ->getMock();
        $this->setProtectedValue($this->game, 'players', $playersMock);

        $boardMock = $this->getMockBuilder('Board')
            ->setConstructorArgs([self::TEST_CHANNEL_ID, $redisMock])
            ->setMethods(['getBoardState', 'markSquareForPlayer', 'isSquareMarked', 'clearBoard',
                'isBoardFull', 'playerSequenceFound'])
            ->getMock();
        $this->setProtectedValue($this->game, 'board', $boardMock);
    }

    /**
     * Free the class member between tests.
     */
    protected function tearDown()
    {
        $this->game = null;
    }

    /**
     * If a help command is submitted, we check that the instruction board is returned without the 'vs' title
     * in the Slack response text as expected.
     * The instruction messaging should be communicated.
     */
    public function testProcessCommand_help()
    {
        $expectedTitle = "";
        $expectedBoard = $this->getBoardStateExpecations(true);

        $this->game->players->expects($this->once())
            ->method('hasPlayers')
            ->willReturn(true);

        $this->getResetExpectations(0);
        $expectedMessage = $this->invokeMethod($this->game, 'getMessage', [Game::INSTRUCTIONS]);
        $result = $this->invokeMethod($this->game, 'processCommand', [self::TEST_USER_NAME1, '']);
        $this->assertEquals(["text" => "{$expectedTitle}\n{$expectedBoard}\n{$expectedMessage}"], $result);
    }

    /**
     * If a challenge command is submitted, we check that the title and board are returned for the Slack response text as expected.
     * We also ensure that a challenge between the two players was made and the game was reset by clearing the board and
     * resetting the player turn. The new game message should be communicated.
     */
    public function testProcessCommand_challenge()
    {
        $expectedTitle = $this->titleExpectations();
        $expectedBoard = $this->getBoardStateExpecations();

        $this->game->players->expects($this->once())
            ->method('hasPlayers')
            ->willReturn(true);

        $this->game->players->expects($this->once())
            ->method('challenge')
            ->with(self::TEST_USER_NAME1, self::TEST_USER_NAME2);

        $this->getResetExpectations();
        $expectedMessage = $this->invokeMethod($this->game, 'getMessage', [Game::NEW_GAME]);
        $result = $this->invokeMethod($this->game, 'processCommand', [self::TEST_USER_NAME1, 'challenge ' . self::TEST_USER_NAME2]);
        $this->assertEquals(['response_type' => 'in_channel', "text" => "{$expectedTitle}\n{$expectedBoard}\n{$expectedMessage}"], $result);
    }

    /**
     * If a status command is submitted, we check that the title and board are returned for the Slack response text as expected
     * where it displays the current board state and the player turn message.
     * The player turn should be communicated.
     */
    public function testProcessCommand_status()
    {
        $this->game->players->expects($this->exactly(2))
            ->method('hasPlayers')
            ->willReturn(true);

        $expectedTitle = $this->titleExpectations();
        $expectedBoard = $this->getBoardStateExpecations();

        $this->getResetExpectations(0);
        $expectedMessage = $this->invokeMethod($this->game, 'getMessage', [Game::PLAYERS_TURN]);
        $result = $this->invokeMethod($this->game, 'processCommand', [self::TEST_USER_NAME1, 'status']);
        $this->assertEquals(['response_type' => 'in_channel', 'text' => "{$expectedTitle}\n{$expectedBoard}\n{$expectedMessage}"], $result);
    }

    /**
     * If a move command is submitted, we check that the title and board are returned for the Slack response text as expected.
     * We also want to ensure that the move was able to be made.
     * The player turn should be communicated.
     */
    public function testProcessCommand_move()
    {
        $this->game->players->expects($this->exactly(2))
            ->method('hasPlayers')
            ->willReturn(true);

        $expectedTitle = $this->titleExpectations();
        $expectedBoard = $this->getBoardStateExpecations();

        $this->playerMoveExpectations(true, 1);
        $this->endGameExpectations(false, false, 2, 1, 1);
        $this->getResetExpectations(0);
        $expectedMessage = $this->invokeMethod($this->game, 'getMessage', [Game::PLAYERS_TURN]);
        $result = $this->invokeMethod($this->game, 'processCommand', [self::TEST_USER_NAME1, '1']);
        $this->assertEquals(['response_type' => 'in_channel', 'text' => "{$expectedTitle}\n{$expectedBoard}\n{$expectedMessage}"], $result);
    }

    /**
     * Sets the expectation to clear the board and reset the player turn.
     * We also expect that userName1 challenges userName2 and that a new game is started with both players.
     */
    public function testStartNewGame()
    {
        $this->getResetExpectations();
        $this->game->players->expects($this->once())
            ->method('challenge')
            ->with(self::TEST_USER_NAME1, self::TEST_USER_NAME2);

        $this->invokeMethod($this->game, 'startNewGame', [self::TEST_USER_NAME1, self::TEST_USER_NAME2]);
        $result = $this->getProtectedValue($this->game, 'status');
        $this->assertEquals(Game::NEW_GAME, $result);
    }

    /**
     * Tests that the board was cleared and the player turn reset.
     */
    public function testResetGame()
    {
        $this->getResetExpectations();
        $this->invokeMethod($this->game, 'resetGame');
    }

    /**
     * Tests that the game is reset for both a draw and a win.
     */
    public function testCheckGameOverReset()
    {
        $this->getResetExpectations(2);
        $this->setProtectedValue($this->game, 'status', Game::DRAW_GAME);
        $this->invokeMethod($this->game, 'checkGameOverReset');

        $this->setProtectedValue($this->game, 'status', Game::PLAYER_WINS);
        $this->invokeMethod($this->game, 'checkGameOverReset');
    }

    /**
     * Test that the game state is returned as a wrong player move if the player was not allowed
     * to make the move.
     */
    public function testPlayerMove_wrong()
    {
        $this->playerMoveExpectations(false, 0);
        $this->invokeMethod($this->game, 'playerMove', [self::TEST_USER_NAME1, 1]);
        $result = $this->getProtectedValue($this->game, 'status');
        $this->assertEquals(Game::WRONG_TURN, $result);
    }

    /**
     * Sets up the expectations to where the player is allowed to make the move and marks a square.
     * Also ensures that a winner was not found and that the turn is toggled to the next player.
     */
    public function testPlayerMove_move()
    {
        $this->playerMoveExpectations(true, 1);
        $this->endGameExpectations(false, false, 2, 1, 1);
        $this->invokeMethod($this->game, 'playerMove', [self::TEST_USER_NAME1, 1]);
        $result = $this->getProtectedValue($this->game, 'status');
        $this->assertEquals(Game::PLAYERS_TURN, $result);
    }

    /**
     * Sets up the expectations to where a winning sequence was found in the current move
     * and sets the game state to a winner and communicates the winning player.
     */
    public function testCheckEndGame_win()
    {
        $this->endGameExpectations(true, false, 1, 0, 0);
        $this->invokeMethod($this->game, 'checkEndGame');
        $result = $this->getProtectedValue($this->game, 'status');
        $this->assertEquals(Game::PLAYER_WINS, $result);
    }

    /**
     * Sets up the expectations to where a winner is not found and the board is full
     * This sets the game state to a draw.
     */
    public function testCheckEndGame_draw()
    {
        $this->endGameExpectations(false, true, 1, 1, 0);
        $this->invokeMethod($this->game, 'checkEndGame');
        $result = $this->getProtectedValue($this->game, 'status');
        $this->assertEquals(Game::DRAW_GAME, $result);
    }

    /**
     * Sets up expectations to where a winner is not found and the board is not full.
     * This results in switching the player turn and setting the game state to communicate
     * that it's this current player's turn.
     */
    public function testCheckEndGame_turn()
    {
        $this->endGameExpectations(false, false, 1, 1, 1);
        $this->invokeMethod($this->game, 'checkEndGame');
        $result = $this->getProtectedValue($this->game, 'status');
        $this->assertEquals(Game::PLAYERS_TURN, $result);
    }

    /**
     * Sets the expectations and for each message type checks that relevant copy is included.
     * These messages are returned to players to communicate game states and moves.
     */
    public function testGetMessage()
    {
        $this->game->players->expects($this->exactly(9))
            ->method('getCurrentTurnUserName')
            ->willReturn(self::TEST_USER_NAME1);

        $this->titleExpectations();

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::START_REQUIRED]);
        $this->assertRegExp("/Challenge someone to begin a game./", $result);

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::GAME_TITLE]);
        $this->assertRegExp("/" . self::TEST_USER_NAME1 . " vs " . self::TEST_USER_NAME1 . "/", $result);

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::PLAYERS_TURN]);
        $this->assertRegExp("/" . self::TEST_USER_NAME1 . ": It's your turn./", $result);

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::INVALID_MOVE]);
        $this->assertRegExp("/" . self::TEST_USER_NAME1 . ": Invalid Move! Please try again./", $result);

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::PLAYER_WINS]);
        $this->assertRegExp("/" . self::TEST_USER_NAME1 . " wins a chicken dinner!/", $result);

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::DRAW_GAME]);
        $this->assertRegExp("/This game is a draw!/", $result);

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::INSTRUCTIONS]);
        $this->assertRegExp("/Help/", $result);
        $this->assertRegExp("/New Game/", $result);
        $this->assertRegExp("/Game Status/", $result);
        $this->assertRegExp("/Example Move/", $result);
        $this->assertRegExp("/Win Condition/", $result);

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::NEW_GAME]);
        $this->assertRegExp("/Starting a new game.../", $result);
        $this->assertRegExp("/" . self::TEST_USER_NAME1 . ": It's your turn./", $result);

        $result = $this->invokeMethod($this->game, 'getMessage', [Game::WRONG_TURN]);
        $this->assertRegExp("/You are not allowed to take this turn./", $result);
    }

    /**
     * Expects the the board state to be called and returned.
     * The boolean flag allows choosing the instruction board output or an actual game board.
     *
     * @param bool $displayInstructions
     * @return string
     */
    protected function getBoardStateExpecations($displayInstructions = false)
    {
        $expectedBoard = self::TEST_GAME_BOARD;
        if ($displayInstructions) {
            $expectedBoard = self::TEST_INSTRUCTION_BOARD;
        }

        $this->game->board->expects($this->once())
            ->method('getBoardState')
            ->willReturn($expectedBoard);

        return $expectedBoard;
    }

    /**
     * Expectations for resetting a game by clearing the board and setting the player turn to 1.
     * $numCalls allows choosing how many times the expectations should happen.
     *
     * @param int $numCalls
     */
    protected function getResetExpectations($numCalls = 1)
    {
        $this->game->players->expects($this->exactly($numCalls))
            ->method('resetPlayerTurn');

        $this->game->board->expects($this->exactly($numCalls))
            ->method('clearBoard');

        $this->game->players->expects($this->exactly($numCalls))
            ->method('clearPlayers');
    }

    /**
     * Expectations for checking if a player can make a move and marking the square.
     * The caller can set whether the move is allowed and if the mark square function is reached.
     *
     * @param $allowed
     * @param $markSquareCalls
     */
    protected function playerMoveExpectations($allowed, $markSquareCalls)
    {
        $this->game->players->expects($this->once())
            ->method('isPlayerAllowedToMakeMove')
            ->with(self::TEST_USER_NAME1)
            ->willReturn($allowed);

        $this->game->board->expects($this->exactly($markSquareCalls))
            ->method('markSquareForPlayer')
            ->with(1, 1)
            ->willReturn(1);
    }

    /**
     * Expectations for checking if the game ends.
     * The caller can set whether the win sequence was found and if the board is full or not.
     * In the case of no sequence found, the caller can set the amount of times proceeding calls are made.
     *
     * @param $sequenceFoundReturn
     * @param $isBoardFullReturn
     * @param $getCurrentTurnCalls
     * @param $boardFullCalls
     * @param $toggleTurnCalls
     */
    protected function endGameExpectations($sequenceFoundReturn, $isBoardFullReturn, $getCurrentTurnCalls, $boardFullCalls, $toggleTurnCalls)
    {
        $this->game->board->expects($this->once())
            ->method('playerSequenceFound')
            ->with(1)
            ->willReturn($sequenceFoundReturn);

        $this->game->players->expects($this->exactly($getCurrentTurnCalls))
            ->method('getCurrentTurn')
            ->willReturn(1);

        $this->game->board->expects($this->exactly($boardFullCalls))
            ->method('isBoardFull')
            ->willReturn($isBoardFullReturn);

        $this->game->players->expects($this->exactly($toggleTurnCalls))
            ->method('toggleTurn');
    }

    /**
     * Sets the expectation for getting the 'vs' title and returns a string representing this title.
     *
     * @return string
     */
    protected function titleExpectations()
    {
        $this->game->players->expects($this->exactly(2))
            ->method('getPlayerUserName')
            ->with($this->logicalOr(
                $this->equalTo(1),
                $this->equalTo(2)))
            ->willReturn(self::TEST_USER_NAME1);

        $expectedTitle = "*" . self::TEST_USER_NAME1 . " vs " . self::TEST_USER_NAME1 . "*\n";
        return $expectedTitle;
    }
}
