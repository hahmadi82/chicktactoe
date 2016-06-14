<?php
require('../vendor/autoload.php');
require_once('Game.php');
require_once('Redis.php');
use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug'] = true;
const SLACK_VERIFY_TOKEN = 'KyyfOqdmMT4K2mhb7RXoFCYP';

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

/**
 * Accepts the post request made by a Slack user via Slack Commmands (in this case '/ctt').
 * We first validate the token to make sure we only service Slack Commands that provide the same unique token.
 * If validated, we create a game object that retrieves the game state from Redis and processes the user's command.
 * The game will always return a response containing a title, game board with user moves,
 * and message(s) for the user's to read.
 * The text is formatted to display the Tic Tac Toe game using Emoji characters.
 * We return this text string in a response that Slack recognizes and displays in the channel.
 * If validation fails, we return a 403 error stating that we do not support the Slack command.
 *
 * command string - The string proceeding the Slack command '/ctt'
 * channel id - The id associated with the Slack channel making the post via a Slack Command
 * token - This unique key is validated to make sure we service Slack commands that we trust
 * user_name - This is the userName associated with the Slack user calling the command
 */
$app->post('/', function (Request $request) use($app) {
    $commandString = $request->get('text');
    $channelId = $request->get('channel_id');
    $verifyToken = $request->get('token');
    $userName = $request->get('user_name');

    if ($verifyToken != SLACK_VERIFY_TOKEN) {
        $response = ["text" => "Slack command not supported."];
        $status = 403;

    } else {
        $redis = new RedisState();
        $tttGame = new Game($channelId, $redis);
        $response = $tttGame->processCommand($userName, $commandString);
        $status = 200;
    }

    return $app->json($response, $status);
});

/**
 * Landing page
 */
$app->get('/', function () {
    return "<a href='https://github.com/hahmadi82/chicktactoe'>Chick Tac Toe</a> for Slack!";
});

$app->run();
