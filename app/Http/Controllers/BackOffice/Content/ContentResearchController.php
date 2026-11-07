<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\AddManualSource;
use App\Domain\Content\Actions\CiteSource;
use App\Domain\Content\Actions\RequestResearch;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentResearchSource;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Research for an item (ADR-0011, phase 4): web search, manual sources, and citations. Rules live in the actions. */
final class ContentResearchController
{
    public function search(Request $request, ContentItem $item, RequestResearch $research): RedirectResponse
    {
        $query = (string) $request->validate(['query' => ['required', 'string', 'min:3', 'max:300']])['query'];

        return $this->attempt(function () use ($research, $item, $query, $request): RedirectResponse {
            $research($item, $query, $this->actor($request));

            return back()->with('success', 'Riset web dimulai. Hasilnya muncul sebagai sumber yang bisa Anda pilih.');
        });
    }

    public function addSource(Request $request, ContentItem $item, AddManualSource $add): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:300'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $text = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

        return $this->attempt(function () use ($add, $item, $data, $text, $request): RedirectResponse {
            $source = $add($item, (string) $data['url'], $text('title'), $text('summary'), $text('note'), $this->actor($request));

            return back()->with('success', "Sumber \"{$source->title}\" ditambahkan.");
        }, 'url');
    }

    public function cite(Request $request, ContentItem $item, ContentResearchSource $source, CiteSource $cite): RedirectResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        return $this->attempt(function () use ($cite, $item, $source, $note, $request): RedirectResponse {
            $cite($item, $source, is_string($note) ? $note : null, $this->actor($request));

            return back()->with('success', 'Sumber dipakai untuk konten ini.');
        });
    }

    public function uncite(Request $request, ContentItem $item, ContentResearchSource $source, CiteSource $cite): RedirectResponse
    {
        return $this->attempt(function () use ($cite, $item, $source, $request): RedirectResponse {
            $cite->remove($item, $source, $this->actor($request));

            return back()->with('success', 'Sumber dilepas dari konten ini.');
        });
    }

    /** @param  Closure(): RedirectResponse  $action */
    private function attempt(Closure $action, ?string $field = null): RedirectResponse
    {
        try {
            return $action();
        } catch (ContentActionRefused $refused) {
            return $field === null ? back()->with('error', $refused->getMessage()) : back()->withErrors([$field => $refused->getMessage()]);
        }
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
