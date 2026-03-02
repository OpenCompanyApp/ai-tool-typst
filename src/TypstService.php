<?php

namespace OpenCompany\AiToolTypst;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TypstService
{
    /**
     * Render Typst markup to raw PDF bytes.
     */
    public function renderToBytes(string $markup): string
    {
        $uuid = Str::uuid()->toString();
        $tmpInput = sys_get_temp_dir() . '/' . $uuid . '.typ';
        $tmpOutput = sys_get_temp_dir() . '/' . $uuid . '.pdf';

        file_put_contents($tmpInput, $markup);

        try {
            $typst = $this->findTypst();

            $command = [
                $typst,
                'compile',
                $tmpInput,
                $tmpOutput,
            ];

            $process = new Process($command);
            $process->setTimeout(30);
            $process->setEnv($this->buildEnv($process));
            $process->run();

            if (!$process->isSuccessful()) {
                $error = $process->getErrorOutput() ?: $process->getOutput();
                throw new \RuntimeException('Typst rendering failed: ' . trim($error));
            }

            if (!file_exists($tmpOutput) || filesize($tmpOutput) === 0) {
                throw new \RuntimeException('Typst produced no output.');
            }

            return file_get_contents($tmpOutput);
        } finally {
            @unlink($tmpInput);
            @unlink($tmpOutput);
        }
    }

    /**
     * Render Typst markup to a PDF on public disk.
     *
     * @return string Public URL path to the generated PDF
     */
    public function render(string $markup): string
    {
        $bytes = $this->renderToBytes($markup);

        Storage::disk('public')->makeDirectory('typst');

        $relativePath = 'typst/' . Str::uuid()->toString() . '.pdf';
        Storage::disk('public')->put($relativePath, $bytes);

        return '/storage/' . $relativePath;
    }

    private function buildEnv(Process $process): array
    {
        $env = $process->getEnv();
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        foreach (['/opt/homebrew/bin', '/usr/local/bin', dirname(PHP_BINARY)] as $dir) {
            if (is_dir($dir) && !str_contains($path, $dir)) {
                $path = $dir . ':' . $path;
            }
        }
        $env['PATH'] = $path;

        return $env;
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
