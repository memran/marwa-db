<?php

declare(strict_types=1);

namespace Marwa\DB\CLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'make:migration',
    description: 'Create a new migration file',
    help: 'This command performs a specific task. Use it carefully.'
)]

final class MakeMigrationCommand extends Command
{

    public function __construct(private string $migrationsPath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Migration name e.g. create_users_table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string)$input->getArgument('name');
        $safe = preg_replace('/[^a-z0-9_]/i', '_', $name);
        $ts = date('Y_m_d_His');
        $file = rtrim($this->migrationsPath, '/') . "/{$ts}_{$safe}.php";

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0777, true);
        }

        $stub = <<<PHP
<?php
use Marwa\\DB\\Schema\\Builder as Schema;

return new class {
    public function up(): void
    {
        Schema::create('example', function (\$table) {
            \$table->increments('id');
            \$table->string('name', 100)->nullable();
            \$table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::drop('example');
    }
};
PHP;

        file_put_contents($file, $stub);
        $output->writeln("<info>Created:</info> {$file}");
        return Command::SUCCESS;
    }
}
