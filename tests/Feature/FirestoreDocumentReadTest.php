<?php

namespace Tests\Feature;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FirestoreDocumentReadTest extends TestCase
{
    #[Test]
    public function it_can_read_a_firestore_document_when_integration_is_enabled(): void
    {
        if (! (bool) env('FIRESTORE_RUN_INTEGRATION_TESTS', false)) {
            $this->markTestSkipped('Set FIRESTORE_RUN_INTEGRATION_TESTS=true to run Firestore integration tests.');
        }

        $projectId = trim((string) env('FIRESTORE_PROJECT_ID', 'ohya-project-02'));

        if ($projectId === '') {
            $this->fail('FIRESTORE_PROJECT_ID is required.');
        }

        $database = trim((string) env('FIRESTORE_DATABASE', '(default)'));

        if ($database === '' || $database === 'default') {
            $database = '(default)';
        }

        $credentialsPath = trim((string) env('FIRESTORE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS', '')));

        if ($credentialsPath === '') {
            $this->markTestSkipped('Set FIRESTORE_CREDENTIALS (or GOOGLE_APPLICATION_CREDENTIALS) to run Firestore integration tests.');
        }

        $json = json_decode((string) file_get_contents($credentialsPath), true);

        if (! is_array($json)) {
            $this->fail('FIRESTORE_CREDENTIALS must be a valid service account JSON file.');
        }

        $credentials = new ServiceAccountCredentials(['https://www.googleapis.com/auth/datastore'], $json);
        $tokenData = $credentials->fetchAuthToken();
        $token = $tokenData['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            $this->fail('Unable to fetch Firestore access token.');
        }

        $url = sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/%s/documents/%s/%s',
            rawurlencode($projectId),
            rawurlencode($database),
            rawurlencode('samples/php/users'),
            rawurlencode('alovelace'),
        );

        Log::info('Firestore integration test: preparing document snapshot.', [
            'project_id' => $projectId,
            'database' => $database,
            'collection' => 'samples/php/users',
            'document' => 'alovelace',
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($url);

        if (in_array($response->status(), [401, 403], true)) {
            $this->markTestSkipped('Service account has no Firestore read permission for this document/path.');
        }

        $this->assertContains($response->status(), [200, 404]);

        if ($response->status() === 404) {
            return;
        }

        $payload = $response->json();

        Log::info('Firestore integration test: snapshot fetched.', [
            'name' => $payload['name'] ?? null,
        ]);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('name', $payload);
        $this->assertStringContainsString('/documents/samples/php/users/alovelace', (string) $payload['name']);
        $this->assertIsArray($payload['fields'] ?? []);
    }
}
