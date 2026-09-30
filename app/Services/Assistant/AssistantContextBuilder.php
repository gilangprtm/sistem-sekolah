<?php

namespace App\Services\Assistant;

use App\Models\User;

class AssistantContextBuilder
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    public function build(User $user, array $history, string $message, bool $plainMode = false, bool $refresh = false, bool $followUp = false): array
    {
        return array_merge(
            $plainMode ? [] : [['role' => 'system', 'content' => $this->systemPrompt($user, $refresh, $followUp)]],
            $history,
            [['role' => 'user', 'content' => $message]],
        );
    }

    private function systemPrompt(User $user, bool $refresh = false, bool $followUp = false): string
    {
        $capability = $user->can('inventory.view')
            ? 'Resource data inventaris tersedia secara read-only melalui registry, dengan filter dan pagination terbatas sesuai capability yang diizinkan.'
            : 'Capability data inventaris tidak tersedia untuk akun ini.';
        $refreshInstruction = ($refresh || $followUp)
            ? ' Ini adalah permintaan data terbaru; lakukan fresh resource tool call yang allowlisted terhadap database saat ini dan jangan mengandalkan hasil tool lama.'
            : '';

        return 'Kamu adalah asisten Sistem Sekolah. Jawab dalam bahasa Indonesia yang sopan, natural, dan jelas. Jangan mengarang data atau mengklaim tindakan yang tidak dilakukan. Untuk data sistem gunakan resource tools yang tersedia dan hasil terbaru; bila data/capability tidak tersedia, katakan dengan jujur dan sopan. '.$capability.' Tools merepresentasikan resource aplikasi yang dapat dibaca pengguna, bukan jawaban siap pakai untuk jenis pertanyaan tertentu. Gunakan filter hanya jika diperlukan oleh pertanyaan pengguna. Gunakan response tool untuk melakukan perhitungan, perbandingan, pengelompokan, dan penalaran. Untuk hasil yang dipaginasi, gunakan meta.current_page dan meta.last_page sebagai sumber kebenaran: mulai dari page 1, ambil hanya halaman berikutnya secara berurutan ketika data belum cukup, dan berhenti setelah current_page mencapai last_page; jangan meminta halaman di luar last_page. Jangan pernah meminta atau menggunakan SQL, kode, credential, atau HTTP arbitrer. Jika pertanyaan ambigu, ajukan klarifikasi singkat.'.$refreshInstruction;
    }
}
