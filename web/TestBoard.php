<?php

require_once("TestCaseCTT.php");
require_once("Board.php");

/**
 * Class BoardTest
 *
 * PHPUnit tests for the Board class.
 */
class BoardTest extends TestCaseCTT
{
    protected $board;

    /**
     * Mock the Redis class object and initialize the board.
     * We store the board as a member variable to be reused by tests later if needed.
     */
    protected function setUp()
    {
        $redisMock = $this->getRedisMock('getRedis', 1);
        $this->board = new Board(self::TEST_CHANNEL_ID, $redisMock);
    }

    /**
     * Free the class member between tests.
     */
    protected function tearDown()
    {
        $this->board = null;
    }

    /**
     * Check that the Emoji number values return as expected (with the 4 marked as X).
     */
    public function testGetNumberEmoji()
    {
        $expectedNumberEmojiMap = [1 => ":one:", 2 => ":two:", 3 => ":three:",
                                   4 => ":x:", 5 => ":five:", 6 => ":six:", //4 is marked x for tutorial
                                   7 => ":seven:", 8 => ":eight:", 9 => ":nine:"];

        for ($i = 1; $i <= count($expectedNumberEmojiMap); $i++) {
            $result = $this->invokeMethod($this->board, 'getNumberEmoji', [$i]);
            $this->assertEquals($expectedNumberEmojiMap[$i], $result);
        }
    }

    /**
     * Test that 1-9 is accepted.
     * Test that 10-19 is not accepted.
     * Test that a string is not accepted.
     */
    public function testIsValidSquareCoordinate()
    {
        for ($i = 1; $i <= 9; $i++) {
            $result = $this->invokeMethod($this->board, 'isValidSquareCoordinate', [$i]);
            $this->assertEquals(1, $result);

            $result = $this->invokeMethod($this->board, 'isValidSquareCoordinate', [$i+10]); //some other number
            $this->assertEquals(0, $result);
        }

        $result = $this->invokeMethod($this->board, 'isValidSquareCoordinate', ["notNumber"]); //some string
        $this->assertEquals(0, $result);
    }

    /**
     * Check that for an empty square #1 we return a blank square rooster Emoji.
     * If the board state has an X Emoji in square #1, we check that the X Emoji is returned.
     */
    public function testGetMarkedSquare()
    {
        $result = $this->invokeMethod($this->board, 'getMarkedSquare', [1]);
        $this->assertEquals(':rooster:', $result);

        $this->setProtectedValue($this->board, 'boardHash', [1 => ':x:']);
        $result = $this->invokeMethod($this->board, 'getMarkedSquare', [1]);
        $this->assertEquals(':x:', $result);
    }

    /**
     * Check that player number 1 and 2 are valid, but 3 is not.
     */
    public function testIsValidPlayer()
    {
        $result = $this->invokeMethod($this->board, 'isValidPlayer', [1]);
        $this->assertTrue($result);

        $result = $this->invokeMethod($this->board, 'isValidPlayer', [2]);
        $this->assertTrue($result);

        $result = $this->invokeMethod($this->board, 'isValidPlayer', [3]);
        $this->assertFalse($result);
    }

    /**
     * We check that for a board state, where square #1 is an X,
     * the Slack text string returns as such (where [] is a rooster Emoji):
     *  X [] []
     * [] [] []
     * [] [] []
     *
     * We also check that if the displayInstructions value is true, we return a text string with:
     * 1 2 3
     * X 5 6
     * 7 8 9
     */
    public function testGetBoardState()
    {
        $this->setProtectedValue($this->board, 'boardHash', [1 => ':x:']);
        $result = $this->invokeMethod($this->board, 'getBoardState', [false]);
        $this->assertEquals(self::TEST_GAME_BOARD, $result);

        $this->setProtectedValue($this->board, 'boardHash', [1 => ':x:']);
        $result = $this->invokeMethod($this->board, 'getBoardState', [true]);
        $this->assertEquals(self::TEST_INSTRUCTION_BOARD, $result);
    }

    /**
     * Create a new board object where we expect 'setRedis' to be called once.
     * Test that the board state has an empty value (rooster Emoji) for square #2.
     * Test that if we mark square #2 for player 1, the marked square returns as an X,
     * since player 1 is always designated as an X Emoji.
     */
    public function testMarkSquareForPlayer()
    {
        $redisMock = $this->getRedisMock('setRedis', 1);

        $board = new Board(self::TEST_CHANNEL_ID, $redisMock);
        $result = $this->invokeMethod($board, 'getMarkedSquare', [2]);
        $this->assertEquals(':rooster:', $result);

        $board->markSquareForPlayer(1, 2);
        $result = $this->invokeMethod($board, 'getMarkedSquare', [2]);
        $this->assertEquals(':x:', $result);

    }

    /**
     * We set the board state to have square #1 marked as an X Emoji.
     * We then test that square #1 returns as marked = true and square #2 returns as marked = false.
     */
    public function testIsSquareMarked()
    {
        $this->setProtectedValue($this->board, 'boardHash', [1 => ':x:']);
        $result = $this->invokeMethod($this->board, 'isSquareMarked', [1]);
        $this->assertTrue($result);

        $result = $this->invokeMethod($this->board, 'isSquareMarked', [2]);
        $this->assertFalse($result);
    }

    /**
     * Create a new board object where we expect 'deleteRedis' to be called once
     * We also set the board state to have a marked square and check that clearing the board
     * sets the board state to empty.
     */
    public function testClearBoard()
    {
        $redisMock = $this->getRedisMock('deleteRedis', 1);

        $board = new Board(self::TEST_CHANNEL_ID, $redisMock);
        $this->setProtectedValue($board, 'boardHash', [1 => ':x:']);
        $board->clearBoard();

        $result = $this->getProtectedValue($board, 'boardHash');
        $this->assertEquals([], $result);
    }

    /**
     * We first check that a board state with one marked square does not return as full.
     * Then we fill the board and check that it returns as full.
     */
    public function testIsBoardFull()
    {
        $this->setProtectedValue($this->board, 'boardHash', [1 => ':x:']);
        $result = $this->invokeMethod($this->board, 'isBoardFull');
        $this->assertFalse($result);

        $fullBoard = [1 => ":x:", 2 => ":x:", 3 => ":o:",
                      4 => ":o:", 5 => ":o:", 6 => ":x:",
                      7 => ":x:", 8 => ":o:", 9 => ":x:"];
        $this->setProtectedValue($this->board, 'boardHash', $fullBoard);
        $result = $this->invokeMethod($this->board, 'isBoardFull');
        $this->assertTrue($result);
    }

    /**
     * We want to check that all the different consecutive player sequences trigger a win.
     * The boards are set up to have player 1 win and player 2 not win across all rows/columns/diagonals.
     * We iterate through each board to check that player 1 wins and player 2 loses.
     * Afterwards, the board values are flipped so X's become O's and vice versa.
     */
    public function testPlayerSequenceFound()
    {
        //horizontal win
        $row1win = [1 => ":x:", 2 => ":x:", 3 => ":x:",  //<-- this row wins
                    4 => ":x:", 5 => ":o:", 6 => ":o:",
                    7 => ":o:", 8 => ":o:", 9 => ":x:"];

        $row2win = [1 => ":o:", 2 => ":x:", 3 => ":o:",
                    4 => ":x:", 5 => ":x:", 6 => ":x:",  //<-- this row wins
                    7 => ":x:", 8 => ":o:", 9 => ":x:"];

        $row3win = [1 => ":x:", 2 => ":o:", 3 => ":o:",
                    4 => ":o:", 5 => ":o:", 6 => ":x:",
                    7 => ":x:", 8 => ":x:", 9 => ":x:"]; //<-- this row wins

        //vertical win
        $col1win = [1 => ":x:", 2 => ":x:", 3 => ":o:",
                    4 => ":x:", 5 => ":o:", 6 => ":o:",
                    7 => ":x:", 8 => ":o:", 9 => ":x:"];
                         //^ this row wins

        $col2win = [1 => ":o:", 2 => ":x:", 3 => ":o:",
                    4 => ":x:", 5 => ":x:", 6 => ":x:",
                    7 => ":o:", 8 => ":x:", 9 => ":x:"];
                                     //^ this row wins

        $col3win = [1 => ":o:", 2 => ":o:", 3 => ":x:",
                    4 => ":o:", 5 => ":o:", 6 => ":x:",
                    7 => ":x:", 8 => ":x:", 9 => ":x:"];
                                                 //^ this row wins
        //diagonal win
        $diag1win = [1 => ":x:", 2 => ":o:", 3 => ":x:", //159 wins
                     4 => ":o:", 5 => ":x:", 6 => ":o:",
                     7 => ":x:", 8 => ":o:", 9 => ":x:"];

        $diag2win = [1 => ":x:", 2 => ":o:", 3 => ":x:", //357 wins
                     4 => ":o:", 5 => ":x:", 6 => ":x:",
                     7 => ":x:", 8 => ":x:", 9 => ":o:"];

        $variousBoardWinsArray = [$row1win, $row2win, $row3win, $col1win, $col2win, $col3win, $diag1win, $diag2win];
        $this->checkThatWinnersWinAndLosersLose($variousBoardWinsArray, 1, 2);
        $this->checkThatWinnersWinAndLosersLose($this->boardFlipXO($variousBoardWinsArray), 2, 1);
    }

    /**
     * For an array of board states we want to iterate through each board
     * and check that the player that's supposed to win wins
     * and the player that's supposed to not win, loses.
     *
     * @param $variousBoardWinsArray
     * @param $winPlayerNumber
     * @param $losePlayerNumber
     */
    protected function checkThatWinnersWinAndLosersLose($variousBoardWinsArray, $winPlayerNumber, $losePlayerNumber)
    {
        foreach($variousBoardWinsArray as $boardHash) {
            $this->setProtectedValue($this->board, 'boardHash', $boardHash);
            $result = $this->invokeMethod($this->board, 'playerSequenceFound', [$winPlayerNumber]);
            $this->assertTrue($result);

            $result = $this->invokeMethod($this->board, 'playerSequenceFound', [$losePlayerNumber]);
            $this->assertFalse($result);
        }
    }

    /**
     * For an array of board states we want to switch all the X values with O values
     *
     * @param $variousBoardWinsArray
     * @return array
     */
    protected function boardFlipXO($variousBoardWinsArray)
    {
        $flippedVariousBoardWinsArray = [];
        foreach ($variousBoardWinsArray as $boardHash)
        {
            $newBoardHash = [];
            for($i = 1; $i <= 9; $i++) {
                if ($boardHash[$i] == ':x:') {
                    $newBoardHash[$i] = ':o:';
                }
                if ($boardHash[$i] == ':o:') {
                    $newBoardHash[$i] = ':x:';
                }
            }
            $flippedVariousBoardWinsArray[] = $newBoardHash;
        }
        return $flippedVariousBoardWinsArray;
    }

}
