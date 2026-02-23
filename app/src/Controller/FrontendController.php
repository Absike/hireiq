<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FrontendController extends AbstractController
{
    #[Route('/', name: 'frontend_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('base.html.twig', $this->getViteAssets());
    }

    #[Route('/{reactRouting}', requirements: ['reactRouting' => '.+'], methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('base.html.twig', $this->getViteAssets());
    }

    private function getViteAssets(): array
    {
        $manifestPath = $this->getParameter('kernel.project_dir').'/public/build/.vite/manifest.json';

        if (!is_file($manifestPath)) {
            return [
                'viteJs' => null,
                'viteCss' => [],
            ];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $entry = is_array($manifest) ? ($manifest['index.html'] ?? null) : null;

        if (!is_array($entry) || !isset($entry['file'])) {
            return [
                'viteJs' => null,
                'viteCss' => [],
            ];
        }

        $css = [];
        foreach (($entry['css'] ?? []) as $cssFile) {
            $css[] = '/build/'.$cssFile;
        }

        return [
            'viteJs' => '/build/'.$entry['file'],
            'viteCss' => $css,
        ];
    }
}
