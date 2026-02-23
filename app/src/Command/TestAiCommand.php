<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:test-ai', description: 'Test Groq via OpenAI-compatible API')]
class TestAiCommand extends Command
{
    public function __construct(
        private string $groqKey,
        private HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🤖 Testing Groq...');

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'auth_bearer' => $this->groqKey,
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => 'Say hello and confirm you are running on Groq. One sentence only.'],
                ],
            ],
        ]);

        $data = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? 'No response';

        $output->writeln('✅ Response: ' . $content);

        return Command::SUCCESS;
    }
}
