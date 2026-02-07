<?php

namespace OpenCompany\AiToolTypst;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TypstService
{
    /**
     * Render Typst markup to a PDF document.
     *
     * @return string Public URL path to the generated PDF
     */
    public function render(string $markup): string
    {
        Storage::disk('public')->makeDirectory('typst');

        $uuid = Str::uuid()->toString();
        $relativePath = 'typst/' . $uuid . '.pdf';
        $outputPath = Storage::disk('public')->path($relativePath);

        // Write markup to a temp .typ file (typst reads from file)
        $tmpInput = sys_get_temp_dir() . '/' . $uuid . '.typ';
        file_put_contents($tmpInput, $markup);

        $typst = $this->findTypst();

        $command = [
            $typst,
            'compile',
            $tmpInput,
            $outputPath,
        ];

        $process = new Process($command);
        $process->setTimeout(30);

        // Ensure typst is in PATH for queue workers with minimal environments.
        $env = $process->getEnv();
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        foreach (['/opt/homebrew/bin', '/usr/local/bin', dirname(PHP_BINARY)] as $dir) {
            if (is_dir($dir) && !str_contains($path, $dir)) {
                $path = $dir . ':' . $path;
            }
        }
        $env['PATH'] = $path;
        $process->setEnv($env);

        $process->run();

        // Clean up temp input file
        @unlink($tmpInput);

        if (!$process->isSuccessful()) {
            $error = $process->getErrorOutput() ?: $process->getOutput();

            throw new \RuntimeException('Typst rendering failed: ' . trim($error));
        }

        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Typst produced no output.');
        }

        return '/storage/' . $relativePath;
    }

    /**
     * Find the typst binary.
     */
    private function findTypst(): string
    {
        $candidates = [
            '/opt/homebrew/bin/typst',
            '/usr/local/bin/typst',
            '/usr/bin/typst',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return 'typst';
    }
}
