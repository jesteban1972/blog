<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Command/SeedLccCategoriesCommand.php

namespace App\Command;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * php bin/console app:seed-lcc-categories
 */

#[AsCommand(
    name: 'app:seed-lcc-categories',
    description: 'parses and seeds the local flat lcc alpha subclasses payload into the database.',
)]
class SeedLccCategoriesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private string $projectDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $this->projectDir . '/assets/data/lcc_subclasses.json';

        if (!file_exists($filePath)) {
            $io->error(sprintf('target data source file missing at path: %s', $filePath));
            return Command::FAILURE;
        }

        $jsonRaw = file_get_contents($filePath);
        $payload = json_decode($jsonRaw, true);
        $items = $payload['data'] ?? [];

        if (empty($items)) {
            $io->error('provided json dataset is empty or formatted incorrectly.');
            return Command::FAILURE;
        }

        $repo = $this->entityManager->getRepository(Category::class);
        $slugger = new AsciiSlugger();

        $io->title('starting library of congress classification seeding sequence...');

        // pass 1: build/upsert entities to avoid reference resolution mismatches
        $io->section('executing pass 1: processing base entity rows...');
        foreach ($items as $code => $meta) {
            $category = $repo->find($code);
            if (!$category) {
                $category = new Category();
                $category->setId($code);
            }
            $category->setName($meta['name']);

            // generate the slug from the category name in lowercase format
            $slugText = strtolower($slugger->slug($meta['name'])->toString());
            $category->setSlug($slugText);

            $this->entityManager->persist($category);
        }
        $this->entityManager->flush();
        $io->writeln('  -> base entities synced successfully.');

        // pass 2: establish hierarchical structural links
        $io->section('executing pass 2: connecting parent relations...');
        foreach ($items as $code => $meta) {
            if (!empty($meta['parent'])) {
                $child = $repo->find($code);
                $parent = $repo->find($meta['parent']);

                if ($child && $parent) {
                    $child->setParent($parent);
                }
            }
        }
        $this->entityManager->flush();
        $io->success('lcc classification tree import finalized cleanly.');

        return Command::SUCCESS;
    }
}
