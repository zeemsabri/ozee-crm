<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class XeroContactSyncService
{
    private const CONTACTS_URL = 'https://api.xero.com/api.xro/2.0/Contacts';

    public function __construct(private readonly XeroTokenService $xeroTokenService) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getContactCandidatesForClient(Client $client): array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $byEmail = $this->searchByEmail($credentials, (string) $client->email);
        $byName = $this->searchByName($credentials, (string) $client->name);

        return collect([...$byEmail, ...$byName])
            ->filter(fn (array $contact): bool => ! empty($contact['contact_id']))
            ->unique('contact_id')
            ->values()
            ->all();
    }

    /**
     * @param  array{access_token:string, tenant_id:string, tenant_name:?string}  $credentials
     * @return array<int, array<string, mixed>>
     */
    private function searchByEmail(array $credentials, string $email): array
    {
        if ($email === '') {
            return [];
        }

        $where = sprintf('EmailAddress != null && EmailAddress == "%s"', $this->escapeXeroString($email));

        return $this->fetchContacts($credentials, [
            'where' => $where,
        ]);
    }

    /**
     * @param  array{access_token:string, tenant_id:string, tenant_name:?string}  $credentials
     * @return array<int, array<string, mixed>>
     */
    private function searchByName(array $credentials, string $name): array
    {
        if (trim($name) === '') {
            return [];
        }

        return $this->fetchContacts($credentials, [
            'searchTerm' => trim($name),
        ]);
    }

    /**
     * @param  array{access_token:string, tenant_id:string, tenant_name:?string}  $credentials
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function fetchContacts(array $credentials, array $query): array
    {
        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get(self::CONTACTS_URL, $query)
            ->throw()
            ->json();

        $contacts = collect(data_get($response, 'Contacts', []));

        return $this->mapContacts($contacts)->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $contacts
     * @return Collection<int, array<string, mixed>>
     */
    private function mapContacts(Collection $contacts): Collection
    {
        return $contacts->map(function (array $contact): array {
            return [
                'contact_id' => data_get($contact, 'ContactID'),
                'name' => data_get($contact, 'Name'),
                'email' => data_get($contact, 'EmailAddress'),
                'contact_status' => data_get($contact, 'ContactStatus'),
                'is_supplier' => (bool) data_get($contact, 'IsSupplier', false),
                'is_customer' => (bool) data_get($contact, 'IsCustomer', false),
            ];
        });
    }

    /**
     * Create a new contact in Xero for the given client.
     *
     * @return array<string, mixed>
     */
    public function createContactForClient(Client $client): array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $payload = [
            'Name' => $client->name,
            'EmailAddress' => $client->email,
        ];

        if ($client->phone) {
            $payload['Phones'] = [
                [
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $client->phone,
                ],
            ];
        }

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->post(self::CONTACTS_URL, $payload)
            ->throw()
            ->json();

        $contact = collect(data_get($response, 'Contacts', []))->first();

        if (! $contact) {
            throw new \RuntimeException('Failed to create contact in Xero.');
        }

        return $this->mapContacts(collect([$contact]))->first();
    }

    private function escapeXeroString(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }
}