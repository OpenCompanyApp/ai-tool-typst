<?php

namespace OpenCompany\AiToolTypst\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolTypst\TypstService;

class RenderTypst implements Tool
{
    public function __construct(
        private TypstService $typstService,
    ) {}

    public function description(): string
    {
        return <<<'DESC'
Render a Typst document to PDF. Pass valid Typst markup and get back a downloadable PDF link.

Use this tool to generate formatted documents: reports, invoices, proposals, summaries, letters, tables, and more.

Example markup:
```
#set page(margin: 2cm)
#set text(size: 11pt)

= Quarterly Report

== Summary

Revenue increased by *15%* compared to last quarter.

#table(
  columns: (1fr, 1fr, 1fr),
  [*Metric*], [*Q1*], [*Q2*],
  [Revenue], [$1.2M], [$1.38M],
  [Users], [12,000], [15,400],
)
```

Key Typst syntax:
- `= Heading` for headings (`==` for h2, `===` for h3)
- `*bold*` for bold, `_italic_` for italic
- `#table(columns: ..., [...], [...])` for tables
- `#set page(margin: 2cm)` for page settings
- `#set text(size: 12pt, font: "...")` for text settings
- `- item` for bullet lists, `+ item` for numbered lists
- `#line(length: 100%)` for horizontal rules
- `#align(center)[...]` for alignment
- `#v(1em)` for vertical spacing
DESC;
    }

    public function handle(Request $request): string
    {
        $markup = trim($request['markup'] ?? '');
        if (empty($markup)) {
            return 'Error: Typst markup is required.';
        }

        $title = $request['title'] ?? 'Document';

        try {
            $url = $this->typstService->render($markup);

            return "![{$title}]({$url})";
        } catch (\Throwable $e) {
            return 'Error rendering Typst document: ' . $e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'markup' => $schema
                ->string()
                ->description('Typst markup content to compile into a PDF document.')
                ->required(),
            'title' => $schema
                ->string()
                ->description('Document title used as link text (default: "Document").'),
        ];
    }
}
