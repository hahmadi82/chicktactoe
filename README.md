# Chick Tac Toe for Slack

Uses the [Silex](http://silex.sensiolabs.org/) web framework, which can easily be deployed to Heroku.

## Deploying

Install the [Heroku Toolbelt](https://toolbelt.heroku.com/).

```sh
$ git clone git@github.com:hahmadi82/chicktactoe.git # or clone your own fork
$ cd chicktactoe
$ heroku create
$ git push heroku master
$ heroku open
```

## Requirements

Provision the [Heroku Redis add-on](https://devcenter.heroku.com/articles/heroku-redis#provisioning-the-add-on).

Setup [Slash Commands](https://api.slack.com/slash-commands) for Slack with the following...
```sh
Command: /ctt
URL: https://YOUR-HEROKU-APP.herokuapp.com
Method: POST
Customize Name: Chick Tac Toe
Customize Icon: A chicken-related icon of your choice
Autocomplete Help Text (Description): Play a game of Chick Tac Toe!
Autocomplete Help Text (Usage hint): (run to see a list of game commands)
```

Lastly, copy the Slack Command **token** into *Index.php* as such:
```sh
const SLACK_VERIFY_TOKEN = 'KyyfOqdm123123hb7RXoFCYP';
````

## Run PHPUnit Tests

```sh
$ ./vendor/bin/phpunit
```
