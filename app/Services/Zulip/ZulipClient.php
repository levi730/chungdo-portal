<?php

namespace App\Services\Zulip;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the Zulip REST API (https://zulip.com/api/), authenticated
 * as a bot with organization-owner rights. Array parameters are JSON-encoded as
 * Zulip requires. Every call throws RuntimeException on a non-success response.
 */
class ZulipClient
{
    public function __construct(
        private ?string $site = null,
        private ?string $botEmail = null,
        private ?string $botApiKey = null,
    ) {
        $this->site ??= config('services.zulip.site');
        $this->botEmail ??= config('services.zulip.bot_email');
        $this->botApiKey ??= config('services.zulip.bot_api_key');
    }

    public function isConfigured(): bool
    {
        return filled($this->site) && filled($this->botEmail) && filled($this->botApiKey);
    }

    /**
     * All users: [ ['user_id'=>, 'email'=>, 'delivery_email'=>, ...], ... ].
     * With $withProfileFields each member also carries 'profile_data' (custom
     * profile field id => ['value'=>...]), which the sync uses to skip writes
     * for values that are already correct.
     */
    public function getUsers(bool $withProfileFields = false): array
    {
        return $this->get('/users', [
            'include_custom_profile_fields' => $withProfileFields ? 'true' : 'false',
        ])['members'] ?? [];
    }

    /** Custom profile fields: [ ['id'=>, 'name'=>, ...], ... ] */
    public function getProfileFields(): array
    {
        return $this->get('/realm/profile_fields')['custom_fields'] ?? [];
    }

    public function setUserProfileField(int $userId, int $fieldId, string $value): void
    {
        $this->patch("/users/{$userId}", [
            'profile_data' => json_encode([['id' => $fieldId, 'value' => $value]]),
        ]);
    }

    /** Non-system user groups: [ ['id'=>, 'name'=>, 'members'=>[ids], ...], ... ] */
    public function getUserGroups(): array
    {
        $groups = $this->get('/user_groups')['user_groups'] ?? [];

        // Never touch Zulip's built-in system groups (role-based, etc.).
        return array_values(array_filter($groups, fn ($g) => empty($g['is_system_group'])));
    }

    /** Create a user group; returns its id. */
    public function createUserGroup(string $name, string $description, array $memberIds): int
    {
        $data = $this->post('/user_groups/create', [
            'name' => $name,
            'description' => $description,
            'members' => json_encode(array_values($memberIds)),
        ]);

        if (isset($data['group_id'])) {
            return (int) $data['group_id'];
        }

        foreach ($this->getUserGroups() as $g) {
            if ($g['name'] === $name) {
                return (int) $g['id'];
            }
        }

        throw new RuntimeException("Created Zulip group {$name} but could not resolve its id.");
    }

    public function updateUserGroupMembers(int $groupId, array $add, array $delete): void
    {
        if (empty($add) && empty($delete)) {
            return;
        }

        $this->post("/user_groups/{$groupId}/members", [
            'add' => json_encode(array_values($add)),
            'delete' => json_encode(array_values($delete)),
        ]);
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Zulip API is not configured (set ZULIP_SITE, ZULIP_BOT_EMAIL, ZULIP_BOT_API_KEY).');
        }

        return Http::baseUrl(rtrim($this->site, '/').'/api/v1')
            ->withBasicAuth($this->botEmail, $this->botApiKey)
            ->asForm()
            ->acceptJson();
    }

    private function get(string $path, array $query = []): array
    {
        return $this->handle($this->request()->get($path, $query), 'GET', $path);
    }

    private function post(string $path, array $data): array
    {
        return $this->handle($this->request()->post($path, $data), 'POST', $path);
    }

    private function patch(string $path, array $data): array
    {
        return $this->handle($this->request()->patch($path, $data), 'PATCH', $path);
    }

    private function handle($response, string $method, string $path): array
    {
        $body = $response->json() ?? [];

        if ($response->failed() || ($body['result'] ?? 'error') !== 'success') {
            $msg = $body['msg'] ?? $response->body();
            throw new RuntimeException("Zulip API {$method} {$path} failed: {$msg}");
        }

        return $body;
    }
}
