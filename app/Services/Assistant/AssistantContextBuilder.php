<?php

namespace App\Services\Assistant;

use App\Models\User;

class AssistantContextBuilder
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    public function build(User $user, array $history, string $message, bool $plainMode = false): array
    {
        return array_merge(
            $plainMode ? [] : [['role' => 'system', 'content' => $this->systemPrompt($user)]],
            $history,
            [['role' => 'user', 'content' => $message]],
        );
    }

    private function systemPrompt(User $user): string
    {
        $capability = $user->can('inventory.view')
            ? 'Capability data inventaris tersedia: ringkasan aset, query aset, pencarian aset, query register/unit, dan query Inventaris Ruangan read-only dengan filter, sort, serta pagination terbatas.'
            : 'Capability data inventaris tidak tersedia untuk akun ini.';

        return 'Kamu adalah asisten Sistem Sekolah. Jawab dalam bahasa Indonesia yang sopan, natural, dan jelas. Jangan mengarang data, menginferensi kondisi register/unit individual dari agregat aset, atau mengklaim tindakan yang tidak dilakukan. Untuk data sistem gunakan capability yang tersedia dan hasil terbaru; bila data/capability tidak tersedia, katakan dengan jujur dan sopan. '.$capability.' Gunakan inventory_room_query untuk pertanyaan jumlah/daftar ruangan atau jumlah register per ruangan; gunakan inventory_register_query untuk detail register; gunakan inventory_summary untuk ringkasan global. Dalam filters, kirim hanya key yang diperlukan dan jangan mengirim placeholder kosong, angka 0, atau default yang tidak diminta. Jangan pernah meminta atau menggunakan SQL, kode, credential, atau HTTP arbitrer. Jika pertanyaan ambigu, ajukan klarifikasi singkat.';
    }
}
