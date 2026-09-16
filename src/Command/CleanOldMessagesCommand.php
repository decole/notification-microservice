<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NotificationRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:messages:cleanup', description: 'Delete messages older than specified retention days')]
final class CleanOldMessagesCommand extends Command
{
    public function __construct(private readonly NotificationRepositoryInterface $notificationRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Number of retention days to keep',
            '30',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        if ($days <= 0) {
            $io->error('Days option must be a positive integer.');

            return Command::FAILURE;
        }

        $deletedCount = $this->notificationRepository->deleteMessagesOlderThanDays($days);
        $io->success(sprintf('Cleanup completed. Deleted %d message(s) older than %d day(s).', $deletedCount, $days));

        return Command::SUCCESS;
    }
}
