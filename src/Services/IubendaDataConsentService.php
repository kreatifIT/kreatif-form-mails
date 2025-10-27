<?php

namespace Kreatif\StatamicForms\Services;


use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Kreatif\StatamicForms\IubendaConsentException;


class IubendaDataConsentService
{
    protected PendingRequest $httpClient;
    protected array $preferences = [];

    protected array $legalNotices = [
        ['identifier' => 'privacy_policy'],
        ['identifier' => 'cookie_policy'],
    ];

    protected array $subjectData = [
        'email'      => '',
        'first_name' => '',
        'last_name'  => '',
        'full_name'  => '',
        'verified'   => false,
    ];
    protected bool $debug = false;

    public function __construct()
    {
        // Point to our addon's config file for the API details.
        $this->httpClient = Http::baseUrl(config('kreatif-statamic-forms.iubenda.base_uri'))
                                ->withHeaders([
                                    'ApiKey'       => config('kreatif-statamic-forms.iubenda.public_key'),
                                    'Content-Type' => 'application/json; charset=utf-8',
                                ])
                                ->acceptJson();
    }

    public function setEmail(string $email): self
    {
        $this->subjectData['email'] = strtolower(trim($email));
        return $this;
    }

    public function setFirstname(string $firstname): self
    {
        $this->subjectData['first_name'] = trim($firstname);
        return $this;
    }

    public function setLastname(?string $lastname): self
    {
        if ($lastname == null) {
            $this->subjectData['last_name'] = '';
            return $this;
        }
        $this->subjectData['last_name'] = trim($lastname);
        return $this;
    }

    public function setFullname(string $fullname): self
    {
        $this->subjectData['full_name'] = trim($fullname);
        return $this;
    }

    public function markAsVerified(): self
    {
        $this->subjectData['verified'] = true;
        return $this;
    }

    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    public function setLegalNotices(array $legalNotices): self
    {
        $this->legalNotices = $legalNotices;
        return $this;
    }

    public function getLegalNotices(): ?array
    {
        return $this->legalNotices;
    }

    public function withDebugging(): self
    {
        $this->debug = true;
        return $this;
    }

    public function createConsent(array $additionalData = []): array
    {
        // Validate required data
        if (empty($this->subjectData['email'])) {
            throw new InvalidArgumentException('Email is required for data consent.');
        }

        if (!filter_var($this->subjectData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address provided for data consent.');
        }

        // Check if API key is configured
        if (empty(config('kreatif-statamic-forms.iubenda.public_key'))) {
            throw new InvalidArgumentException('Iubenda API key is not configured.');
        }

        Log::info("Sending Iubenda consent", [
            'email' => $this->subjectData['email'],
            'preferences' => $this->preferences,
        ]);

        // Build full name if not set
        if (empty($this->subjectData['full_name'])) {
            $this->subjectData['full_name'] = trim($this->subjectData['first_name'] . ' ' . $this->subjectData['last_name']);
        }

        // Prepare payload
        $payload = [
            'subject'       => array_merge($this->subjectData, $additionalData),
            'preferences'   => $this->preferences,
            'legal_notices' => $this->legalNotices,
        ];

        if ($this->debug) {
            Log::debug('Iubenda Consent Request:', ['payload' => $payload]);
        }

        try {
            $response = $this->httpClient->post('consent', $payload);

            // Check for HTTP errors
            if ($response->failed()) {
                $errorMessage = $response->json('error') ?? $response->body();
                Log::error('Iubenda API request failed', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'email' => $this->subjectData['email'],
                ]);

                throw new IubendaConsentException(
                    "Iubenda API returned error (Status {$response->status()}): {$errorMessage}"
                );
            }

            $responseData = $response->json();

            if ($this->debug) {
                Log::debug('Iubenda Consent Response:', [
                    'status' => $response->status(),
                    'body' => $responseData
                ]);
            }

            // Validate response
            if (empty($responseData['id'])) {
                Log::error('Iubenda consent ID missing in response', [
                    'response' => $responseData,
                    'email' => $this->subjectData['email'],
                ]);
                throw new IubendaConsentException('No consent ID was returned by Iubenda.');
            }

            Log::info("Successfully created Iubenda consent", [
                'consent_id' => $responseData['id'],
                'email' => $this->subjectData['email'],
            ]);

            return $responseData;

        } catch (RequestException $e) {
            Log::error('Iubenda API request exception', [
                'error' => $e->getMessage(),
                'email' => $this->subjectData['email'],
            ]);

            throw new IubendaConsentException(
                'Failed to send consent to Iubenda: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}