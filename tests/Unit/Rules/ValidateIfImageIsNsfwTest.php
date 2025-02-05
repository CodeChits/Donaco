<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidateIfImageIsNsfw;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ValidateIfImageIsNsfwTest extends TestCase
{
    public function test_valid_image_passes_validation()
    {
        config()->set('app.manual_payment_mode_validation', true);

        Http::fake([
            config('app.sight_engine.api.url') => Http::response([
                'nudity' => [
                    'sexual_activity' => 0.0,
                    'sexual_display' => 0.0,
                    'erotica' => 0.0,
                    'very_suggestive' => 0.0,
                    'suggestive' => 0.0,
                    'mildly_suggestive' => 0.0,
                ],
                'gore' => ['prob' => 0.0]
            ], Response::HTTP_OK)
        ]);

        $rule = new ValidateIfImageIsNsfw();
        $failCallback = function ($message) {
            $this->fail("Validation failed with message: $message");
        };

        $image = UploadedFile::fake()->image('image.jpg');

        $rule->validate('image', $image, $failCallback);
        $this->assertTrue(true);
    }

    public function test_nsfw_image_fails_validation()
    {
        config()->set('app.manual_payment_mode_validation', true);

        Http::fake([
            config('app.sight_engine.api.url') => Http::response([
                'nudity' => [
                    'sexual_activity' => 0.8,
                ]
            ], Response::HTTP_OK),
        ]);

        $rule = new ValidateIfImageIsNsfw();

        $this->expectExceptionMessage('The image contains inappropriate content.');

        $image = UploadedFile::fake()->image('image.jpg');

        $rule->validate('image', $image, function ($message) {
            throw new \Exception($message);
        });
    }

    public function test_gore_image_fails_validation()
    {
        config()->set('app.manual_payment_mode_validation', true);

        Http::fake([
            config('app.sight_engine.api.url') => Http::response([
                'gore' => ['prob' => 0.8]
            ], Response::HTTP_OK)
        ]);

        $rule = new ValidateIfImageIsNsfw();

        $this->expectExceptionMessage('The image contains gore content.');

        $image = UploadedFile::fake()->image('image.jpg');

        $rule->validate('image', $image, function ($message) {
            throw new \Exception($message);
        });
    }

    public function test_api_failure_logs_warning()
    {
        config()->set('app.manual_payment_mode_validation', true);

        Http::fake([
            config('app.sight_engine.api.url') => Http::response(
                [],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            )
        ]);

        Log::shouldReceive('warning')->once();

        $rule = new ValidateIfImageIsNsfw();

        $image = UploadedFile::fake()->image('image.jpg');

        $rule->validate('image', $image, function ($message) {
            $this->fail("Validation should not fail, just log a warning.");
        });
    }

    public function test_image_passes_validation_when_nsfw_validation_is_disabled()
    {
        config()->set('app.manual_payment_mode_validation', false);

        Http::fake([
            config('app.sight_engine.api.url') => Http::response([
                'nudity' => [
                    'sexual_activity' => 0.8,
                ]
            ], Response::HTTP_OK),
        ]);

        $rule = new ValidateIfImageIsNsfw();
        $failCallback = function ($message) {
            $this->fail("Validation failed with message: $message");
        };

        $image = UploadedFile::fake()->image('image.jpg');

        $rule->validate('image', $image, $failCallback);
        $this->assertTrue(true);
    }
}
