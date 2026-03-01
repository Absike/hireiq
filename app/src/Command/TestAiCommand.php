<?php

namespace App\Command;

use App\Service\AiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-ai', description: 'Test AI service integration')]
class TestAiCommand extends Command
{
    public function __construct(
        private AiService $aiService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🤖 Testing AI Service...');

        try {
            $response = $this->aiService->generateSummary('This is a test CV text for a Senior PHP Developer with 10 years of experience in Symfony and Vue.js.');
            $output->writeln('✅ Response: ' . $response);
        } catch (\Throwable $e) {
            $output->writeln('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
