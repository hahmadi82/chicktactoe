<?php
require_once('Board.php');
require_once('Players.php');
require_once('Redis.php');

/**
 * Class Game
 *
 * Processes the user commands for the Tic Tac Toe game and checks all the game conditions.
 * Also returns messaging to communicate the game and move status to the users formatted as a Slack response.
 */
class Game
{
    public $board;
    public $players;
    private $status;

    const GAME_TITLE = 'title';
    const INSTRUCTIONS = 'instructions';

    //Various game states after a move is taken
    const START_REQUIRED = 'start';
    const PLAYER_WINS = 'win';
    const WRONG_TURN = 'wrong';
    const INVALID_MOVE = 'invalid';
    const PLAYERS_TURN = 'next';
    const DRAW_GAME = 'draw';
    const NEW_GAME = 'new';

    //Commands made by the player
    const COMMAND_CHALLENGE = 'challenge';
    const COMMAND_STATUS = 'status';

    /**
     * The Redis class instance and channel id are passed into the board and players.
     * This allows the board and player states to be saved in Redis for a given channel.
     *
     * @param $channelId
     * @param RedisState $redis
     */
    public function __construct($channelId, RedisState $redis)
    {
        $this->board = new Board($channelId, $redis);
        $this->players = new Players($channelId, $redis);
        $this->status = self::PLAYERS_TURN;
    }

    /**
     * Takes the user's command and decides on the action.
     * The arguments following /ctt come in as a command string delimited by spaces.
     * If empty show the instructions (IE /ctt).
     * If 'challenge userName' the current user is stored as player 1 and challenger stored as player 2.
     * If the argument is a number, it's assumed to be a move from a player onto the Tic Tac Toe grid.
     * In the case of a win or draw status, the game ends and requires another challenge to restart.
     *
     * Command Logic:
     * For command '/ctt' we want to display the instructions and available commands.
     * For command '/ctt challenge @userName' parse out the challenged userName and start
     * a new game against the current user.
     * If no players are in a game, tell players to start a game before allowing status/move commands
     * For command '/ctt status' we want to print out the current board and display the current game turn
     * For command '/ctt 4' we attempt to mark square 4 for the current userName
     *
     * @param $userName
     * @param null $commandString
     * @return array
     */
    public function processCommand($userName, $commandString = null)
    {
        $commandArray = explode(' ', $commandString);

        if (empty($commandString)) {
            $this->status = self::INSTRUCTIONS;

        } else if ($commandArray[0] == self::COMMAND_CHALLENGE && !empty($commandArray[1])) {
            $challengedUserName = $commandArray[1];
            $this->startNewGame($userName, $challengedUserName);

        } else if (!$this->players->hasPlayers()){
            $this->status = self::START_REQUIRED;

        } else if ($commandArray[0] == self::COMMAND_STATUS) {
            $this->status = self::PLAYERS_TURN;

        } else {
            $moveSquareNum = $commandArray[0];
            $this->playerMove($userName, $moveSquareNum);
        }

        $response = $this->getResponse();
        $this->checkGameOverReset();

        return $response;
    }

    /**
     * Based on the game status, a message is generated to communicate with the users
     * along with the current board state.
     * We only display the 'vs' title if two players exist and the game instructions are not displaying.
     * We only display messages to the whole channel during legitimate game situations.
     * Any incorrect/out of turn moves or tutorials are shown to the user only.
     * The Slack response is returned.
     *
     * @return array
     */
    private function getResponse()
    {
        $response = [];
        $title = "";
        $displayInstructions = $this->status == self::INSTRUCTIONS;

        if ($this->players->hasPlayers() && !$displayInstructions) {
            $title = $this->getMessage(self::GAME_TITLE);
        }

        if ($this->status == self::NEW_GAME || $this->status == self::PLAYERS_TURN ||
            $this->status == self::PLAYER_WINS || $this->status == self::DRAW_GAME) {
            $response['response_type'] = 'in_channel';
        }

        //Always print the current board state and get the game status messaging
        $board = $this->board->getBoardState($displayInstructions);
        $message = $this->getMessage($this->status);
        $response['text'] = "{$title}\n{$board}\n{$message}";

        return $response;
    }

    /**
     * Starts a new game by resetting the board/player turn and
     * storing the two challenging users into the player object (via Redis).
     * A message indicating the new game and player turn is returned.
     *
     * @param $userName
     * @param $challengedUserName
     * @return string
     */
    private function startNewGame($userName, $challengedUserName)
    {
        $this->resetGame();
        $this->players->challenge($userName, $challengedUserName);
        $this->status = self::NEW_GAME;
    }

    /**
     * Returns the player turn to player 1 and clears all the moves from the board,
     * both stored in Redis.
     */
    private function resetGame()
    {
        $this->players->clearPlayers();
        $this->players->resetPlayerTurn();
        $this->board->clearBoard();
    }

    /**
     * Checks if the current game status at the end of a players command is a win/draw
     * and resets the game.
     */
    private function checkGameOverReset()
    {
        if ($this->status == self::PLAYER_WINS || $this->status == self::DRAW_GAME) {
            $this->resetGame();
        }
    }

    /**
     * First checks if the userName making the move is allowed and designated to take the turn.
     * If not, a 'wrong turn' message is set as the game status.
     * If the userName is allowed to make the move, we attempt to mark the square with their marker (X or O)
     * and change the turn to the next player, unless the current move won the game.
     * If marking the square fails for the appropriate player, we set the game status to an invalid move.
     *
     * @param $userName
     * @param $num
     */
    private function playerMove($userName, $num)
    {
        if (!$this->players->isPlayerAllowedToMakeMove($userName)) {
            $this->status = self::WRONG_TURN;

        } else if ($this->board->markSquareForPlayer($this->players->getCurrentTurn(), $num)) {
            $this->checkEndGame();

        } else {
            $this->status = self::INVALID_MOVE;
        }
    }

    /**
     * For the current player, we check if we found 3 markers (X or O) in a row.
     * If the player wins we set the game status to a win.
     * If the player doesn't win, but the board is full, we set the game status to a draw.
     * If neither scenario happened, we flip the player turn and set the game status to next player's turn.
     */
    private function checkEndGame()
    {
        $isWinner = $this->board->playerSequenceFound($this->players->getCurrentTurn());

        if ($isWinner) {
            $this->status = self::PLAYER_WINS;

        } else if ($this->board->isBoardFull()) {
            $this->status = self::DRAW_GAME;

        } else {
            $this->players->toggleTurn();
            $this->status = self::PLAYERS_TURN;
        }
    }

    /**
     * This method returns any messaging used to communicate to the players in the Slack channel.
     * This includes the title above the game board, the instructions, or any messaging related to the game status
     * after a player attempts to take a turn.
     *
     * @param $sType
     * @return string
     */
    private function getMessage($sType)
    {
        $playerTurnUserName = $this->players->getCurrentTurnUserName();
        $message = "";
        switch($sType) {
            case self::GAME_TITLE:
                $message .= "*{$this->players->getPlayerUserName(1)} vs {$this->players->getPlayerUserName(2)}*";
                break;
            case self::START_REQUIRED:
                $message .= "*Challenge someone to begin a game of Chick Tac Toe:*\n_/ctt challenge @userName_";
                break;
            case self::PLAYERS_TURN:
                $message .= "_{$playerTurnUserName}: It's your turn._";
                break;
            case self::INVALID_MOVE:
                $message .= "_{$playerTurnUserName}: Invalid Move! Please try again._";
                break;
            case self::PLAYER_WINS:
                $message .= "*{$playerTurnUserName} wins a chicken dinner!!* :poultry_leg:";
                break;
            case self::DRAW_GAME:
                $message .= "*This game is a draw! :chicken:*";
                break;
            case self::INSTRUCTIONS:
                $message .= "*Help:* _/ctt_\n*Game Status:* _/ctt status_\n*New Game:* _/ctt challenge @userName_\n*Example Move:* _/ctt 4 (Places an :x: in the :four: position)_\n*Win Condition*: _3 in a row wins!_";
                break;
            case self::NEW_GAME:
                $message .= "*Starting a new game... 3 in a row wins.*\n_{$playerTurnUserName}: It's your turn._";
                break;
            case self::WRONG_TURN:
                $message .= "_You are not allowed to take this turn._";
                break;
        }
        $message .= "\n";
        return $message;
    }
}
