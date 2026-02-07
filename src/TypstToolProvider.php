<?php

namespace OpenCompany\AiToolTypst;

use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolTypst\Tools\RenderTypst;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class TypstToolProvider implements ToolProvider
{
    public function appName(): string
    {
        return 'typst';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'PDF reports, documents, invoices',
            'description' => 'Typst document rendering to PDF',
            'icon' => 'ph:file-pdf',
            'logo' => 'ph:file-pdf',
        ];
    }

    public function tools(): array
    {
        return [
            'render_typst' => [
                'class' => RenderTypst::class,
                'type' => 'write',
                'name' => 'Render Typst',
                'description' => 'Render Typst markup to a PDF document — reports, invoices, proposals, summaries, and formatted documents.',
                'icon' => 'ph:file-pdf',
            ],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    public function createTool(string $class, array $context = []): Tool
    {
        return new $class(app(TypstService::class));
    }
}
