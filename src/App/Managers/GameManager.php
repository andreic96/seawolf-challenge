<?php

namespace App\Managers;

use App\Entities\Action;
use App\Entities\Attack;
use App\Entities\Enemy;
use App\Entities\Game;
use App\Entities\InputData;
use App\Entities\Location;
use App\Entities\Player;
use App\Entities\Torpedo;
use App\Exceptions\InvalidActionTypeException;
use App\Exceptions\InvalidDepthCantFloatAboveWaterException;
use App\Exceptions\InvalidDepthOutOfRangeException;
use App\Exceptions\InvalidInputDepthOnlyException;
use App\Exceptions\InvalidInputException;
use App\Validators\InputValidatorInterface;
use Utils\Input\InputInterface;
use Utils\Output\OutputInterface;

class GameManager
{
    /** @var InputInterface  */
    private $input;

    /** @var OutputInterface */
    private $output;

    /** @var InputValidatorInterface */
    private $inputValidator;

    /** @var AttackManager */
    private $attackManager;

    /** @var InputData */
    private $inputData;

    /** @var Game */
    private $game;

    /** @var Player */
    private $player;

    /** @var array */
    private $enemies = [];

    /** @var int */
    private $turn = 0;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        InputValidatorInterface $inputValidator,
        AttackManager $attackManager
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->inputValidator = $inputValidator;
        $this->attackManager = $attackManager;

        $this->game = new Game();
    }

    public function run() : void
    {
        $this->displayWelcomeMessages();

        $this->initPlayer();
        $this->initEnemies();

        $this->playFirstTurn();
        $this->playGame();

        $this->output->writeInfo(sprintf('Final depth: %s', $this->player->getCurrentDepth()));
        $this->output->writeInfo(sprintf('Damage taken: %s', $this->player->getDamageTaken()));
    }

    private function initPlayer() : void
    {
        $actions = [];
        try {
            $actionFloat = new Action(Action::ACTION_FLOAT, 1, 45);
            $actionDive = new Action(Action::ACTION_DIVE, 5, 75);
            $actions = [$actionFloat, $actionDive];
        } catch (InvalidActionTypeException $e) {
            $this->output->writeError($e->getMessage());
        }

        $this->player = new Player($actions);
    }

    private function initEnemies() : void
    {
        $weaponEnemyW = new Torpedo(1, 5);
        $locationEnemyW = new Location(Location::WEST);
        $enemyW = new Enemy($weaponEnemyW, $locationEnemyW);

        $weaponEnemyE = new Torpedo(7, 14);
        $locationEnemyE = new Location(Location::EAST);
        $enemyE = new Enemy($weaponEnemyE, $locationEnemyE);

        $weaponEnemyN = new Torpedo(9, 22);
        $locationEnemyN = new Location(Location::NORTH);
        $enemyN = new Enemy($weaponEnemyN, $locationEnemyN);

        $this->enemies = [$enemyW, $enemyE, $enemyN];
    }

    private function playFirstTurn() : void
    {
        while (true) {
            $inputLine = $this->input->getLine();
            try {
                $this->inputData = $this->getInputDataDepth($inputLine);
                $this->player->dive($this->inputData->getActionDepth());
                break;
            } catch (InvalidInputDepthOnlyException $e) {
                $this->output->writeError($e->getMessage());
            }
        }
    }

    private function playGame() : void
    {
        // Game loop
        while ($this->keepGameAlive()) {
            try {
                /** @var Enemy $currentEnemy */
                $currentEnemy = $this->enemies[$this->turn];

                $this->displayTurnMessages($this->player, $currentEnemy->getLocation());
                $this->setInput();

                $attack = new Attack($this->player, $currentEnemy, $this->turn);
                $this->game->addAttack($attack);

                $this->attackManager->doAction($attack, $this->inputData);
                $this->attackManager->attack($attack, $this->inputData);
                $this->attackManager->displayDamageDealtMessage($attack, $this->output);

                $this->turn++;
            } catch (
            InvalidInputException | InvalidActionTypeException |
            InvalidDepthOutOfRangeException | InvalidDepthCantFloatAboveWaterException $e
            ) {
                $this->output->writeError($e->getMessage());
            }
        }
    }

    private function keepGameAlive() : bool
    {
        return $this->turn < count($this->enemies);
    }

    private function displayWelcomeMessages() : void
    {
        $this->output->writeSuccess('Ahoy! Welcome aboard of USS Nautilus! Currently located at 42°40\'04.4"N 175°35\'28.3”W');
        $this->output->writeInfo(sprintf('Your sonar has detected %s enemy submarines coming at you!', $this->getEnemyCount()));
        $this->output->writeInfo('Take evasive actions to save your ship! Your first action is to dive. Please input the depth in meters (any depth):');
    }

    private function displayTurnMessages(Player $player, Location $location) : void
    {
        $this->output->writeInfo(sprintf('Your current depth is %s', $player->getCurrentDepth()));
        $this->output->writeInfo(sprintf(
            'You are being hit from the %s with a missile, what is your action, Captain ?',
            $location
        ));
    }

    private function setInput() : void
    {
        $inputLine = $this->input->getLine();
        $this->inputData = $this->getInputData($inputLine);
        $this->inputValidator::validateActionInput($this->inputData->getActionType());
    }

    private function getInputDataDepth(string $inputLine) : InputData
    {
        $matchesInputFormat = $this->inputValidator::validateAndReturnOnlyDepthInput($inputLine);
        $inputParts = explode(' ', $matchesInputFormat[0]);

        return new InputData($inputParts[0], $inputParts[1]);
    }

    private function getInputData(string $inputLine) : InputData
    {
        $matchesInputFormat = $this->inputValidator::validateAndReturnInput($inputLine);
        $inputParts = explode(' ', $matchesInputFormat[0]);

        return new InputData($inputParts[0]);
    }

    private function getEnemyCount() : int
    {
        return count($this->enemies);
    }
}
