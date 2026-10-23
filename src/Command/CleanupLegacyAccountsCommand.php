<?php

namespace App\Command;

use App\Service\LegacyAccountCleanup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cleanup-legacy-accounts', description: 'Remove verified demo students and merge the duplicate VPSAS account, with a private database backup.')]
class CleanupLegacyAccountsCommand extends Command
{
    public function __construct(private readonly LegacyAccountCleanup $cleanup)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Apply the displayed cleanup after saving a private backup.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $plan = $this->cleanup->plan();
        $rows = [];
        foreach ($plan['remove'] as $user) {
            $rows[] = [$user->getEmail(), 'Remove demo account and its dependent records'];
        }
        foreach ($plan['merge'] as ['from' => $from, 'into' => $into]) {
            $rows[] = [$from->getEmail(), 'Merge records into '.$into->getEmail()];
        }
        if (!$rows) {
            $io->success('No legacy demo or duplicate office accounts remain.');
            return Command::SUCCESS;
        }
        $io->table(['Account', 'Action'], $rows);
        if (!$input->getOption('apply')) {
            $io->note('Preview only. Use --apply to save a private backup and perform this cleanup.');
            return Command::SUCCESS;
        }
        $backup = $this->cleanup->apply();
        $io->success('Cleanup complete. Official accounts and independently registered users were preserved. Backup: '.$backup);

        return Command::SUCCESS;
    }
}
