<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ValidateIfImageIsNsfw implements ValidationRule
{
    private const NSFW_THRESHOLD = 0.5;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('app.manual_payment_mode_validation')) {
            return;
        }

        try {
            $response = Http::acceptJson()
                ->attach('media', fopen($value->getRealPath(), 'r'))
                ->post(config('app.sight_engine.api.url'), [
                    'models' => config('app.sight_engine.models'),
                    'api_user' => config('app.sight_engine.api.user'),
                    'api_secret' => config('app.sight_engine.api.secret'),
                ])
                ->throw()
                ->json();

            $nudityChecks = [
                'sexual_activity' => 'The image contains inappropriate content.',
                'sexual_display' => 'The image contains inappropriate content.',
                'erotica' => 'The image contains inappropriate content.',
                'very_suggestive' => 'The image contains inappropriate content.',
                'suggestive' => 'The image contains inappropriate content.',
                'mildly_suggestive' => 'The image contains inappropriate content.',
            ];

            foreach ($nudityChecks as $key => $message) {
                if (isset($response['nudity'][$key]) && $response['nudity'][$key] >= self::NSFW_THRESHOLD) {
                    $fail($message);
                }
            }

            if (isset($response['gore']['prob']) && $response['gore']['prob'] >= self::NSFW_THRESHOLD) {
                $fail('The image contains gore content.');
            }
        } catch (HttpClientException $e) {
            Log::warning('Sightengine API request failed: ' . $e->getMessage());
        }
    }
}
