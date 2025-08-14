<?php

declare(strict_types=1);

namespace Marwa\DB\CLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'make:seeder',
    description: 'Create a new database seeder class',
    help: 'This command performs a specific task. Use it carefully.'
)]
final class MakeSeederCommand extends Command
{

    public function __construct(
        private string $seedPath = '',                  // e.g. project_root()/database/seeders
        private string $seedNamespace = 'Database\\Seeders'
    ) {
        parent::__construct();
        $this->seedPath = $seedPath ?: getcwd() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders';
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new database seeder class')
            ->addArgument('name', InputArgument::REQUIRED, 'Seeder class name (e.g., UsersTableSeeder)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string)$input->getArgument('name');
        $class = preg_replace('/[^A-Za-z0-9_]/', '', $name);

        if (!$class || $class[0] === strtolower($class[0])) {
            $output->writeln('<error>Class name must be StudlyCase, e.g., UsersTableSeeder</error>');
            return Command::FAILURE;
        }

        if (!is_dir($this->seedPath) && !mkdir($concurrentDirectory = $this->seedPath, 0775, true) && !is_dir($concurrentDirectory)) {
            $output->writeln('<error>Failed to create seeders directory: ' . $this->seedPath . '</error>');
            return Command::FAILURE;
        }

        $file = $this->seedPath . DIRECTORY_SEPARATOR . $class . '.php';
        if (file_exists($file)) {
            $output->writeln('<comment>Seeder already exists:</comment> ' . $file);
            return Command::SUCCESS;
        }

        $stub = $this->stub($this->seedNamespace, $class);

        if (false === file_put_contents($file, $stub)) {
            $output->writeln('<error>Could not write file:</error> ' . $file);
            return Command::FAILURE;
        }

        $output->writeln('<info>Seeder created:</info> ' . $file);
        return Command::SUCCESS;
    }

    private function stub(string $namespace, string $class): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Marwa\DB\Seeder\Seeder;
use Faker\Factory as FakerFactory;

final class {$class} implements Seeder
{
    public function run(): void
    {
        // TODO: add your seed logic here, e.g.:
        // \\App\\Models\\User::create([
        //     'name' => \$faker->name(),
        //     'email' => \$faker->unique()->safeEmail(),
        // ]);
    }
}

PHP;
    }
}
