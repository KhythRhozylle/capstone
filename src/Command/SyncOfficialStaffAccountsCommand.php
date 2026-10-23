<?php

namespace App\Command;

use App\Service\OfficialStaffAccounts;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync-official-staff', description: 'Apply the official staff emails, roles and uniform password without purging records.')]
class SyncOfficialStaffAccountsCommand extends Command
{
    public function __construct(private readonly OfficialStaffAccounts $accounts, private readonly EntityManagerInterface $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accounts = $this->manager->wrapInTransaction(fn () => $this->accounts->synchronize($this->manager, true));
        (new SymfonyStyle($input, $output))->success(sprintf('Updated %d official staff accounts.', count($accounts)));

        return Command::SUCCESS;
    }
}
