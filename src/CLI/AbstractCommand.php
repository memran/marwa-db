<?php

declare(strict_types=1);

namespace Marwa\DB\CLI;

use Closure;
use Throwable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Base abstract command similar to Laravel's style.
 *
 * Usage:
 *   final class MakeUserCommand extends AbstractCommand {
 *       protected string $name = 'user:make';
 *       protected string $description = 'Create a new user';
 *
 *       protected function arguments(): array {
 *           return [
 *               ['email', InputArgument::REQUIRED, 'User email'],
 *           ];
 *       }
 *
 *       protected function options(): array {
 *           return [
 *               ['admin', 'a', InputOption::VALUE_NONE, 'Make the user admin'],
 *               ['name',  null, InputOption::VALUE_REQUIRED, 'Full name', null],
 *           ];
 *       }
 *
 *       protected function handle(): int {
 *           $email = $this->argument('email');
 *           $name  = $this->option('name') ?? $this->ask('Full name?');
 *           if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
 *               $this->error('Invalid email address.');
 *               return self::INVALID;
 *           }
 *
 *           $this->withProgressBar(100, function (ProgressBar $bar) {
 *               for ($i = 0; $i < 100; $i++) { usleep(2000); $bar->advance(); }
 *           });
 *
 *           $this->table(['Field','Value'], [['Email',$email],['Name',$name]]);
 *           $this->success('User created successfully.');
 *           return self::SUCCESS;
 *       }
 *   }
 */
abstract class AbstractCommand extends Command
{
    /** @var string Command name, e.g. "make:user" */
    protected string $name = '';

    /** @var string Command description for help output */
    protected string $description = '';

    /** @var array<string,mixed> Cached input values */
    private array $cachedArguments = [];
    private array $cachedOptions   = [];

    protected InputInterface $input;
    protected OutputInterface $output;

    /**
     * Define arguments: return an array of arrays, each:
     * [name, mode, description, default]
     * e.g. ['email', InputArgument::REQUIRED, 'User email']
     * @return array<int,array<int,mixed>>
     */
    protected function arguments(): array
    {
        return [];
    }

    /**
     * Define options: return an array of arrays, each:
     * [name, shortcut, mode, description, default]
     * e.g. ['force','f', InputOption::VALUE_NONE, 'Force operation']
     * @return array<int,array<int,mixed>>
     */
    protected function options(): array
    {
        return [];
    }

    /**
     * Main command logic. Return one of Command::* exit codes.
     */
    abstract protected function handle(): int;

    /**
     * Configure the command's name/description/definition.
     */
    protected function configure(): void
    {
        if ($this->name === '') {
            throw new \LogicException(static::class . ' must define a $name.');
        }

        $this->setName($this->name);
        $this->setDescription($this->description ?: $this->name);

        // Arguments
        foreach ($this->arguments() as $arg) {
            [$name, $mode, $desc, $default] = $arg + [null, null, '', null];
            $this->getDefinition()->addArgument(new InputArgument(
                (string)$name,
                (int)($mode ?? InputArgument::OPTIONAL),
                (string)($desc ?? ''),
                $default
            ));
        }

        // Options
        foreach ($this->options() as $opt) {
            [$name, $shortcut, $mode, $desc, $default] = $opt + [null, null, null, '', null];
            $this->getDefinition()->addOption(new InputOption(
                (string)$name,
                $shortcut ? (string)$shortcut : null,
                (int)($mode ?? InputOption::VALUE_NONE),
                (string)($desc ?? ''),
                $default
            ));
        }
    }

    /**
     * Symfony entrypoint. Wraps handle() with IO setup and safe execution.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;

        // Snapshot for performant lookups
        foreach ($this->getDefinition()->getArguments() as $a) {
            $this->cachedArguments[$a->getName()] = $input->getArgument($a->getName());
        }
        foreach ($this->getDefinition()->getOptions() as $o) {
            $this->cachedOptions[$o->getName()] = $input->getOption($o->getName());
        }

        try {
            return $this->handle();
        } catch (Throwable $e) {
            $this->error('Unhandled exception: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    /* ------------------------------------------------------------
     |  Input getters (Laravel-like API)
     |-------------------------------------------------------------*/

    /**
     * Get a command argument by name.
     * @param string $name
     * @return mixed
     */
    protected function argument(string $name): mixed
    {
        if (!array_key_exists($name, $this->cachedArguments)) {
            throw new \InvalidArgumentException("Unknown argument '{$name}'.");
        }
        return $this->cachedArguments[$name];
    }

    /**
     * Get a command option by name.
     * @param string $name
     * @return mixed
     */
    protected function option(string $name): mixed
    {
        if (!array_key_exists($name, $this->cachedOptions)) {
            throw new \InvalidArgumentException("Unknown option '{$name}'.");
        }
        return $this->cachedOptions[$name];
    }

    /* ------------------------------------------------------------
     |  Output helpers
     |-------------------------------------------------------------*/

    /** Write a raw line. */
    protected function line(string $message = ''): void
    {
        $this->output->writeln($message);
    }

    /** Info message (green). */
    protected function info(string $message): void
    {
        $this->output->writeln("<info>{$this->escape($message)}</info>");
    }

    /** Warning message (yellow). */
    protected function warn(string $message): void
    {
        $this->output->writeln("<comment>{$this->escape($message)}</comment>");
    }

    /** Error message (red). */
    protected function error(string $message): void
    {
        $this->output->writeln("<error>{$this->escape($message)}</error>");
    }

    /** Success (bold/green). */
    protected function success(string $message): void
    {
        $this->output->writeln("<fg=green;options=bold>{$this->escape($message)}</>");
    }

    /** Extra newline(s). */
    protected function newLine(int $count = 1): void
    {
        $this->output->writeln(str_repeat(PHP_EOL, max(1, $count)));
    }

    /** Render a table. */
    protected function table(array $headers, array $rows): void
    {
        (new Table($this->output))->setHeaders($headers)->setRows($rows)->render();
    }

    /* ------------------------------------------------------------
     |  Questions / prompts
     |-------------------------------------------------------------*/

    protected function ask(string $question, ?string $default = null, ?Closure $validator = null): string
    {
        $q = new Question("<question>{$this->escape($question)}</question> ", $default);
        if ($validator) {
            $q->setValidator(static function ($answer) use ($validator) {
                return $validator($answer);
            });
            $q->setMaxAttempts(3);
        }
        return (string)$this->getQuestionHelper()->ask($this->input, $this->output, $q);
    }

    protected function secret(string $question, ?Closure $validator = null): string
    {
        $q = new Question("<question>{$this->escape($question)}</question> ");
        $q->setHidden(true)->setHiddenFallback(false);
        if ($validator) {
            $q->setValidator(static function ($answer) use ($validator) {
                return $validator($answer);
            });
            $q->setMaxAttempts(3);
        }
        return (string)$this->getQuestionHelper()->ask($this->input, $this->output, $q);
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        $q = new ConfirmationQuestion("<question>{$this->escape($question)}</question> ", $default);
        return (bool)$this->getQuestionHelper()->ask($this->input, $this->output, $q);
    }

    /**
     * Present a list of choices.
     * @param array<int,string> $choices
     */
    protected function choice(string $question, array $choices, int|string|null $default = null, bool $multiselect = false): string|array
    {
        $q = new ChoiceQuestion("<question>{$this->escape($question)}</question> ", $choices, $default);
        $q->setMultiselect($multiselect);
        return $this->getQuestionHelper()->ask($this->input, $this->output, $q);
    }

    /* ------------------------------------------------------------
     |  Progress Helpers
     |-------------------------------------------------------------*/

    /**
     * Wrap a callback with a progress bar for N steps.
     * @param int $max
     * @param Closure(ProgressBar):void $callback
     */
    protected function withProgressBar(int $max, Closure $callback): void
    {
        $bar = new ProgressBar($this->output, max(0, $max));
        $bar->start();
        $callback($bar);
        $bar->finish();
        $this->newLine();
    }

    /* ------------------------------------------------------------
     |  Utilities
     |-------------------------------------------------------------*/

    protected function escape(string $message): string
    {
        // Basic safety for console formatting tags
        return str_replace(['<', '>'], ['&lt;', '&gt;'], $message);
    }

    protected function getQuestionHelper(): QuestionHelper
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        return $helper;
    }
}
