<?php

namespace App\Service;

use App\Entity\JobPosition;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

class AiService
{
    public function __construct(
        private PlatformInterface $platform
    ) {}

    public function extractCandidateInfo(string $text): array
    {
        $prompt = sprintf(
            'Extract from this CV the following fields in JSON format:
            - name
            - email
            - phone
            - skills (array of strings)
            - technical_skills (object with keys: languages, frameworks, tools, platforms)
            - years_experience (integer)
            - education (array of strings, full degrees and schools)
            - languages (array of strings)
            - professional_summary (string, 2-3 sentences)
            - work_experience (array of objects with title, company, period)
            - achievements (array of strings, bullet points)
            - project_complexity (string, evaluation of project scale)
            - engineering_practices (array of strings, e.g., CI/CD, TDD, Agile)
            - side_projects (array of strings)
            - career_progression (string, evaluation of growth)

            CV text: %s',
            substr($text, 0, 10000)
        );

        $response = $this->invokeText($prompt);
        return json_decode($response->asText(), true) ?? [];
    }

    public function calculateScore(string $cvText, JobPosition $jobPosition): array
    {
        $prompt = sprintf(
            'Evaluate how well the candidate matches the job position.
            Job Title: %s
            Job Description: %s
            Job Requirements: %s

            Candidate CV:
            %s

            Return JSON with "score" (0-100) and "summary" (2-3 sentences).',
            $jobPosition->getTitle(),
            $jobPosition->getDescription(),
            $jobPosition->getRequirements() ?? 'No specific requirements',
            substr($cvText, 0, 6000)
        );

        $response = $this->invokeText($prompt);
        $data = json_decode($response->asText(), true) ?? [];

        return [
            'score' => $data['score'] ?? 0,
            'summary' => $data['summary'] ?? 'No summary generated.',
        ];
    }

    public function generateSummary(string $cvText): string
    {
        $prompt = sprintf(
            'Provide a concise 3-4 sentence professional summary of this CV: %s',
            substr($cvText, 0, 6000)
        );

        $response = $this->invokeText($prompt);
        return $response->asText();
    }

    public function generateInterviewQuestions(string $cvText, ?JobPosition $jobPosition = null): array
    {
        $jobContext = $jobPosition
            ? sprintf('Job Title: %s
Requirements: %s', $jobPosition->getTitle(), $jobPosition->getRequirements() ?? 'None')
            : 'General professional evaluation.';

        $prompt = sprintf(
            'Generate 5 tailored interview questions with reasons based on:
            %s
            CV: %s
            Return JSON array of objects with "question" and "reason".',
            $jobContext,
            substr($cvText, 0, 5000)
        );

        $response = $this->invokeText($prompt);
        return json_decode($response->asText(), true) ?? [];
    }

    public function chatWithCv(string $cvText, string $userMessage): string
    {
        $prompt = sprintf(
            'CV Context: %s
User Question: %s
Answer based ONLY on the CV.',
            substr($cvText, 0, 4000),
            $userMessage
        );

        $response = $this->invokeText($prompt);
        return $response->asText();
    }

    private function invokeText(string $prompt): DeferredResult
    {
        $messages = new MessageBag(Message::ofUser($prompt));

        return $this->platform->invoke('gpt-4o-mini', $messages);
    }
}
