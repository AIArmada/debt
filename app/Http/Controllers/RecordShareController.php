<?php

namespace App\Http\Controllers;

use App\Actions\Promises\AcknowledgeShare;
use App\Actions\Promises\Data\AcknowledgeShareData;
use App\Actions\Promises\Data\DisputeShareData;
use App\Actions\Promises\DisputeShare;
use App\Domain\Queries\RecordShareView;
use App\Models\RecordShare;
use Illuminate\Contracts\View\View as ViewResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

final class RecordShareController extends Controller
{
    public function show(string $token, RecordShareView $shareView): ViewResponse
    {
        $share = $this->shareForToken($token);
        $this->setShareLocale($share, request()->query('lang'));

        return View::make('shares.show', $shareView->forShare($share));
    }

    public function acknowledge(string $token, Request $request, AcknowledgeShare $acknowledgeShare): RedirectResponse
    {
        $share = $this->shareForToken($token);
        $locale = $this->setShareLocale($share, $request->query('lang'));
        $acknowledgeShare->handle($share, AcknowledgeShareData::fromInput($request->all()));

        return redirect()->route('shares.show', $this->showParameters($token, $locale, $request))->with('share-message', __('share.acknowledgement_recorded'));
    }

    public function dispute(string $token, Request $request, DisputeShare $disputeShare): RedirectResponse
    {
        $share = $this->shareForToken($token);
        $locale = $this->setShareLocale($share, $request->query('lang'));
        $disputeShare->handle($share, DisputeShareData::fromInput($request->all()));

        return redirect()->route('shares.show', $this->showParameters($token, $locale, $request))->with('share-message', __('share.dispute_recorded'));
    }

    private function shareForToken(string $token): RecordShare
    {
        $share = RecordShare::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->firstOrFail();

        return $share->load([
            'record.profile.owner',
            'record.attachments',
            'record.partyLinks.party',
            'record.obligations.moneySubject',
            'record.obligations.chargeTerms',
        ]);
    }

    /** @return array<string, string> */
    private function showParameters(string $token, string $locale, Request $request): array
    {
        $parameters = ['token' => $token];
        if ($locale !== 'en' || $request->query('lang') !== null) {
            $parameters['lang'] = $locale;
        }

        return $parameters;
    }

    private function setShareLocale(RecordShare $share, mixed $requestedLocale): string
    {
        $ownerLocale = $share->record->profile->owner->locale;
        $locale = is_string($requestedLocale) && in_array($requestedLocale, ['en', 'ms'], true)
            ? $requestedLocale
            : (in_array($ownerLocale, ['en', 'ms'], true) ? $ownerLocale : 'en');
        App::setLocale($locale);

        return $locale;
    }
}
