<?php
// src/Command/SeedDemoDataCommand.php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\TimeEntry;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed-demo',
    description: 'Purge and seed demo data: Clients, Orders, TimeEntries (no Users, Invitations, or OrderAssets)',
)]
class SeedDemoDataCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->addOption('keep', null, InputOption::VALUE_NONE, 'Keep existing data (append instead of purge)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $now  = new \DateTimeImmutable();

        if (!$input->getOption('yes')) {
            $io->warning('This will '.($input->getOption('keep') ? 'append demo data' : 'DELETE then re-create demo data').' for Clients, Orders, TimeEntries.');
            if (!$io->confirm('Proceed?', false)) {
                return Command::SUCCESS;
            }
        }

        // $conn = $this->em->getConnection();
        // $platform = $conn->getDatabasePlatform();

        // if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
        //     $conn->executeStatement('PRAGMA foreign_keys = ON');
        // }
        
        if (!$input->getOption('keep')) {
            // Purge in FK-safe order
            $this->em->createQuery('DELETE FROM App\Entity\TimeEntry te')->execute();
            // Optional: purge assets if they exist in your schema (but we won’t create new ones)
            if (class_exists(\App\Entity\OrderAsset::class)) {
                $this->em->createQuery('DELETE FROM App\Entity\OrderAsset oa')->execute();
            }
            $this->em->createQuery('DELETE FROM App\Entity\Order o')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Client c')->execute();
            $this->em->flush();
        }

        // Demo dataset blueprint
        $clientsData = [
            [
                'name' => 'Alpha Studio',
                'channel' => 'https://youtube.com/@alpha-studio',
                'orders' => [
                    [
                        'title' => 'React Hooks Deep Dive',
                        'brief' => 'Clean, bold typography, blue/purple gradient, code editor vibe.',
                        'price' => 1200, // €12.00
                        'status' => OrderStatus::DELIVERED,
                        'paid' => true,
                        'due_offset_days' => -20,
                        'entries' => [45, 30, 25],
                    ],
                    [
                        'title' => 'AI vs Human Creativity',
                        'brief' => 'Split-face concept, neon accents, dramatic contrast.',
                        'price' => 2500,
                        'status' => OrderStatus::DELIVERED,
                        'paid' => false,
                        'due_offset_days' => -7,
                        'entries' => [60, 40],
                    ],
                    [
                        'title' => 'How to Color Grade in 10 min',
                        'brief' => 'Warm teal-orange, film strip overlay.',
                        'price' => 1500,
                        'status' => OrderStatus::DOING,
                        'paid' => false,
                        'due_offset_days' => +3,
                        'entries' => [35],
                    ],
                ],
            ],
            [
                'name' => 'Beta Bytes',
                'channel' => null,
                'orders' => [
                    [
                        'title' => 'Docker in Production',
                        'brief' => 'Minimal, container icon, dark background.',
                        'price' => 1800,
                        'status' => OrderStatus::ACCEPTED,
                        'paid' => false,
                        'due_offset_days' => +5,
                        'entries' => [],
                    ],
                    [
                        'title' => 'Top 5 Mac Shortcuts',
                        'brief' => 'Playful emojis, clean layout.',
                        'price' => 900,
                        'status' => OrderStatus::CREATED,
                        'paid' => false,
                        'due_offset_days' => +10,
                        'entries' => [],
                    ],
                    [
                        'title' => 'Why You Need TypeScript',
                        'brief' => 'Sharp serif headline, purple accent.',
                        'price' => 2000,
                        'status' => OrderStatus::REFUSED,
                        'paid' => false,
                        'due_offset_days' => -2,
                        'entries' => [15],
                    ],
                ],
            ],
            [
                'name' => 'Gamma Films',
                'channel' => 'https://youtube.com/@gamma-films',
                'orders' => [
                    [
                        'title' => 'Cinematic B-Roll Secrets',
                        'brief' => 'Lens flare, soft glow, 3D depth.',
                        'price' => 3000,
                        'status' => OrderStatus::DELIVERED                    ,
                        'paid' => false,
                        'due_offset_days' => -1,
                        'entries' => [55, 50],
                    ],
                    [
                        'title' => 'Beat the Algorithm',
                        'brief' => 'Red alert bar, bold CTA, chart motif.',
                        'price' => 2200,
                        'status' => OrderStatus::DOING,
                        'paid' => false,
                        'due_offset_days' => +1,
                        'entries' => [20, 20],
                    ],
                    [
                        'title' => 'Minimal Setup Tour',
                        'brief' => 'Monochrome desk, soft shadow.',
                        'price' => 1300,
                        'status' => OrderStatus::CANCELED,
                        'paid' => false,
                        'due_offset_days' => -5,
                        'entries' => [],
                    ],
                ],
            ],
        ];

        $createdCounts = ['clients' => 0, 'orders' => 0, 'entries' => 0];

        foreach ($clientsData as $cData) {
            $client = (new Client())
                ->setName($cData['name'])
                ->setChannelUrl($cData['channel']);

            $this->em->persist($client);
            $createdCounts['clients']++;

            foreach ($cData['orders'] as $oData) {
                $createdAt  = $now->modify('-'.random_int(5, 30).' days');
                $updatedAt  = $createdAt->modify('+'.random_int(0, 4).' days');
                $dueAt      = $now->modify(($oData['due_offset_days'] >= 0 ? '+' : '').$oData['due_offset_days'].' days');

                $order = (new Order())
                    ->setClient($client)
                    ->setTitle($oData['title'])
                    ->setBrief($oData['brief'])
                    ->setPrice(max(500, (int)$oData['price'])) // >= €5
                    ->setStatus($oData['status'])
                    ->setPaid((bool)$oData['paid'])
                    ->setDueAt($dueAt)
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt);

                $this->em->persist($order);
                $createdCounts['orders']++;

                foreach ($oData['entries'] as $minutes) {
                    $teCreated = $createdAt->modify('+'.random_int(0, 10).' hours');
                    $entry = (new TimeEntry())
                        ->setRelatedOrder($order)
                        ->setMinutes($minutes)
                        ->setNote(null)
                        ->setCreatedAt($teCreated);

                    $this->em->persist($entry);
                    $createdCounts['entries']++;
                }
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            'Seed complete: %d clients, %d orders, %d time entries.',
            $createdCounts['clients'],
            $createdCounts['orders'],
            $createdCounts['entries'],
        ));

        return Command::SUCCESS;
    }
}
