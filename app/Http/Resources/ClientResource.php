<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $user = auth()->user();

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($user && $user->hasPermission('edit_clients')) {
            $data['email'] = $this->email;
            $data['xero_contact_id'] = $this->xero_contact_id;
            $data['xero_contact_name'] = $this->xero_contact_name;
            $data['xero_contact_email'] = $this->xero_contact_email;
            $data['xero_sync_mode'] = $this->xero_sync_mode;
            $data['xero_synced_at'] = $this->xero_synced_at;
            $data['xero_synced_by_user_id'] = $this->xero_synced_by_user_id;
            $data['xero_synced_by'] = $this->xeroSyncedBy ? [
                'id' => $this->xeroSyncedBy->id,
                'name' => $this->xeroSyncedBy->name,
            ] : null;
            $data['telegram_link_code'] = $this->telegram_link_code;
            $data['telegram_account'] = $this->telegramAccount ? [
                'id' => $this->telegramAccount->id,
                'username' => $this->telegramAccount->username,
                'first_name' => $this->telegramAccount->first_name,
                'last_name' => $this->telegramAccount->last_name,
            ] : null;
        }

        return $data;
    }
}
