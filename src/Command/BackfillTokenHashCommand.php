<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:tokens:backfill-hash', description: 'Backfill token_hash for legacy users')]
final class BackfillTokenHashCommand extends Command
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updated = 0;

        while (true) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, username, token
                 FROM users
                 WHERE token_hash IS NULL AND token IS NOT NULL
                 ORDER BY id ASC
                 LIMIT :limit',
                ['limit' => self::BATCH_SIZE],
            );

            if ([] === $rows) {
                break;
            }

            foreach ($rows as $row) {
                $token = (string) $row['token'];
                $user = (string) $row['username'];
                $hash = hash('sha256', $token);

                $this->connection->executeStatement(
                    'UPDATE users SET token_hash = :token_hash WHERE id = :id',
                    [
                        'id' => (int) $row['id'],
                        'token_hash' => $hash,
                    ],
                );
                ++$updated;

                $io->success(sprintf(
                    'User: %s has token: %s, hash: %s',
                    $user,
                    $token,
                    $hash,
                ));
            }
        }

        $io->success(sprintf('Backfilled token_hash for %d user(s).', $updated));

        return Command::SUCCESS;
    }
}
