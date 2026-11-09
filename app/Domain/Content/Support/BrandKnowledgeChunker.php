<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

/** Splits one human-authored Brand Knowledge document into stable retrieval units without calling AI. */
final class BrandKnowledgeChunker
{
    /** @return list<string> */
    public function chunks(string $source): array
    {
        $paragraphs = preg_split('/\n\s*\n/u', $this->retrievableSource($source)) ?: [];
        $chunks = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            while (mb_strlen($paragraph) > 1200) {
                $cut = mb_strrpos(mb_substr($paragraph, 0, 1201), ' ');
                $cut = $cut === false || $cut < 400 ? 1200 : $cut;
                $chunks[] = trim(mb_substr($paragraph, 0, $cut));
                $paragraph = trim(mb_substr($paragraph, $cut));
            }
            if ($paragraph !== '') {
                $chunks[] = $paragraph;
            }
        }

        return array_slice($chunks, 0, 100);
    }

    /** The editor keeps open questions visible, but unconfirmed material must never become AI evidence. */
    private function retrievableSource(string $source): string
    {
        $lines = preg_split('/\n/u', str_replace(["\r\n", "\r"], "\n", trim($source))) ?: [];
        $kept = [];
        $skip = false;
        foreach ($lines as $line) {
            if (preg_match('/^\s*#{1,6}\s+(.+?)\s*$/u', $line, $heading) === 1) {
                $name = mb_strtolower(trim($heading[1], " \t\n\r\0\x0B:#"));
                $skip = in_array($name, ['informasi yang perlu dikonfirmasi', 'perlu dikonfirmasi', 'data yang perlu dikonfirmasi'], true);
            } elseif (preg_match('/^\s*(?:informasi yang perlu dikonfirmasi|perlu dikonfirmasi|data yang perlu dikonfirmasi)\s*:?\s*$/iu', $line) === 1) {
                $skip = true;
            }
            if (! $skip) {
                $kept[] = $line;
            }
        }

        return trim(implode("\n", $kept));
    }
}
