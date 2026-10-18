<?php

namespace Billboard\Http\Controllers;

use Billboard\Ads\AdRepository;
use Billboard\Tracking\Tracker;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TrackingController extends Controller
{
    private const int MAX_BODY_BYTES = 8192;

    /**
     * Receives batched, viewable impressions sent by billboard.js.
     *
     * The body is JSON sent as text/plain (navigator.sendBeacon), shaped like
     * {"e": [["<ad id>", "<placement handle>"], ...]}.
     */
    public function impressions(Request $request, Tracker $tracker)
    {
        $content = (string) $request->getContent();

        if ($content !== '' && strlen($content) <= self::MAX_BODY_BYTES) {
            $payload = json_decode($content, true);

            if (is_array($payload) && isset($payload['e']) && is_array($payload['e'])) {
                $tracker->recordImpressions($request, $payload['e']);
            }
        }

        return response()->noContent()->withHeaders([
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Counts a click and sends the visitor to the ad's destination.
     *
     * The destination always comes from the saved ad, never from the request,
     * so this route can't be used as an open redirect.
     */
    public function click(Request $request, string $ad, string $placement, AdRepository $ads, Tracker $tracker)
    {
        $found = $ads->find($ad);

        abort_unless($found && $found->url(), 404);

        $tracker->recordClick($request, $found, $placement);

        return redirect()->away($found->url())->withHeaders([
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }
}
