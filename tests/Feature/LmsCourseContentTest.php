<?php

namespace Tests\Feature;

use App\Models\LmsCourse;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LmsCourseContent;
use Database\Seeders\LmsPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LmsCourseContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(LmsPermissionSeeder::class);
    }

    public function test_saves_ordered_materials_sections_media_and_questions_and_rejects_stale_edit(): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $token = $course->latestRevision->editToken($course);
        $this->actingAs($user)->patch(route('lms.design.content.update', $course), [
            'revision_id' => $token, 'content_json' => json_encode($content),
        ])->assertSessionHasNoErrors()->assertRedirect(route('lms.design'));
        $saved = $course->fresh()->latestRevision;
        $this->assertSame($content['materials'], $saved->content['materials']);
        $this->assertSame('draft', $saved->status);
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
        $this->assertDatabaseCount('tr_lms_course_runs', 0);
        $this->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page
            ->where('course.content.materials.0.title', 'Materi kedua')
            ->where('course.content.materials.1.title', 'Materi pertama')
            ->where('course.content.materials.0.sections.0.title', 'Bagian A'));
        $this->patch(route('lms.design.content.update', $course), ['revision_id' => $token, 'content_json' => '{"materials":[]}'])
            ->assertSessionHasErrors('revision_id');
        $this->assertSame($saved->content, $course->fresh()->latestRevision->content);
    }

    public function test_sanitizes_html_and_canonicalizes_youtube_without_rendering_provider_html(): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $content['materials'][0]['body'] = '<h2 onclick="evil()">Judul</h2><script>alert(1)</script><img src=x onerror=evil()><p style="color:red">A <strong>B</strong></p><svg><script>bad()</script></svg><iframe src="https://evil.test"></iframe>';
        $content['materials'][0]['media'][0]['url'] = 'https://youtu.be/dQw4w9WgXcQ';
        $this->save($user, $course, $content)->assertSessionHasNoErrors();
        $body = $course->fresh()->latestRevision->content['materials'][0]['body'];
        $this->assertSame('<h2>Judul</h2><p>A <strong>B</strong></p>', $body);
        $this->assertSame('Baris pertama<p>Baris kedua</p>', app(LmsCourseContent::class)->sanitize('Baris pertama<div>Baris kedua</div>'));
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $course->fresh()->latestRevision->content['materials'][0]['media'][0]['url']);
        $this->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page->where('course.content.materials.0.body', $body));
    }

    public function test_media_and_text_order_is_saved_for_materials_and_sections_and_invalid_values_are_rejected(): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $content['materials'][0]['layout'] = 'text_first';
        $content['materials'][0]['sections'][0]['layout'] = 'media_first';

        $this->save($user, $course, $content)->assertSessionHasNoErrors();

        $saved = $course->fresh()->latestRevision->content['materials'];
        $this->assertSame('text_first', $saved[0]['layout']);
        $this->assertSame('media_first', $saved[0]['sections'][0]['layout']);
        $this->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page
            ->where('course.content.materials.0.layout', 'text_first')
            ->where('course.content.materials.0.sections.0.layout', 'media_first'));

        $content['materials'][0]['layout'] = 'side_by_side';
        $this->save($user, $course->fresh(), $content)->assertSessionHasErrors('materials.0.layout');
        $this->assertSame($saved, $course->fresh()->latestRevision->content['materials']);

        $content['materials'][0]['layout'] = 'media_first';
        $content['materials'][0]['sections'][0]['layout'] = null;
        $this->save($user, $course->fresh(), $content)->assertSessionHasErrors('materials.0.sections.0.layout');
        $this->assertSame($saved, $course->fresh()->latestRevision->content['materials']);
    }

    public function test_tiptap_formatting_and_safe_links_survive_save_and_reload(): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $content['materials'][0]['body'] = '<h2>Judul</h2><h3>Subjudul</h3><p><strong>Tebal</strong> <em>Miring</em> <u>Bawah</u> <s>Coret</s> <code>inline</code></p><ul><li><p>Poin</p></li></ul><ol start="3"><li><p>Nomor</p></li></ol><blockquote><p>Kutipan</p></blockquote><pre><code class="language-php">&lt;?php echo &quot;A&quot;;</code></pre><hr><p><a href="https://school.example/materi?a=1&amp;b=2" onclick="evil()" style="color:red" target="_self" rel="opener">Tautan</a></p>';
        $expected = '<h2>Judul</h2><h3>Subjudul</h3><p><strong>Tebal</strong> <em>Miring</em> <u>Bawah</u> <s>Coret</s> <code>inline</code></p><ul><li><p>Poin</p></li></ul><ol start="3"><li><p>Nomor</p></li></ol><blockquote><p>Kutipan</p></blockquote><pre><code>&lt;?php echo &quot;A&quot;;</code></pre><hr><p><a href="https://school.example/materi?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">Tautan</a></p>';
        $content['materials'][0]['sections'][0]['body'] = '<p><a href="http://school.example/referensi">Referensi</a></p>';

        $this->save($user, $course, $content)->assertSessionHasNoErrors();

        $this->assertSame($expected, $course->fresh()->latestRevision->content['materials'][0]['body']);
        $this->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page
            ->where('course.content.materials.0.body', $expected)
            ->where('course.content.materials.0.sections.0.body', '<p><a href="http://school.example/referensi" target="_blank" rel="noopener noreferrer">Referensi</a></p>'));
    }

    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['java&#x73;cript:alert(1)'])]
    #[TestWith(['java&#10;script:alert(1)'])]
    #[TestWith(['data:text/html,evil'])]
    #[TestWith(['vbscript:evil'])]
    #[TestWith(['//evil.example/path'])]
    #[TestWith(['/relative/path'])]
    #[TestWith(['mailto:person@example.com'])]
    #[TestWith(['https://'])]
    public function test_unsafe_editor_links_are_removed_without_losing_the_text(string $url): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $content['materials'][0]['body'] = '<p><a href="'.$url.'" onclick="evil()"><strong>Teks</strong></a></p>';

        $this->save($user, $course, $content)->assertSessionHasNoErrors();

        $this->assertSame('<p><strong>Teks</strong></p>', $course->fresh()->latestRevision->content['materials'][0]['body']);
    }

    public function test_uploads_privately_and_only_referenced_media_can_be_served_by_authorized_users(): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $assetId = (string) Str::uuid();
        $content['materials'][0]['media'][] = ['id' => (string) Str::uuid(), 'type' => 'pdf', 'asset_id' => $assetId, 'caption' => 'Document'];
        $pdf = UploadedFile::fake()->createWithContent('lesson.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $this->save($user, $course, $content, ['uploads' => [$assetId => $pdf]])->assertSessionHasNoErrors();
        $asset = $course->fresh()->latestRevision->content['assets'][$assetId];
        Storage::disk('local')->assertExists($asset['path']);
        $url = route('lms.design.media.view', [$course, $assetId]);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page
            ->where('course.content.assets.'.$assetId.'.url', $url)
            ->missing('course.content.assets.'.$assetId.'.path'));
        [$other] = $this->context();
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($user);
        $content['materials'][0]['media'] = [];
        $this->save($user, $course->fresh(), $content)->assertSessionHasNoErrors();
        $this->get($url)->assertNotFound();
        Storage::disk('local')->assertExists($asset['path']);
    }

    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['https://www.youtube.com.evil.test/watch?v=dQw4w9WgXcQ'])]
    #[TestWith(['https://evil.test/video'])]
    public function test_rejects_untrusted_video_urls(string $url): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $content['materials'][0]['media'][0]['url'] = $url;
        $this->save($user, $course, $content)->assertSessionHasErrors('content');
        $this->assertSame(['materials' => []], $course->fresh()->latestRevision->content);
    }

    public function test_validates_schema_duplicates_question_options_and_foreign_assets(): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $content['materials'][0]['questions'][0]['options'] = [];
        $this->save($user, $course, $content)->assertSessionHasErrors('content');
        $content = $this->content();
        $content['materials'][1]['id'] = $content['materials'][0]['id'];
        $this->save($user, $course, $content)->assertSessionHasErrors('content');
        $content = $this->content();
        $content['materials'][0]['questions'][0]['options'][0]['unexpected'] = true;
        $this->save($user, $course, $content)->assertSessionHasErrors();
        $content = $this->content();
        $content['materials'][0]['media'][] = ['id' => (string) Str::uuid(), 'type' => 'image', 'asset_id' => (string) Str::uuid()];
        $this->save($user, $course, $content)->assertSessionHasErrors('content');
        $this->assertSame(['materials' => []], $course->fresh()->latestRevision->content);
    }

    public function test_rejects_missing_token_invalid_json_and_unsupported_or_oversized_uploads(): void
    {
        [$user, $course] = $this->context();
        $this->actingAs($user)->from(route('lms.design.edit', $course))
            ->patch(route('lms.design.content.update', $course), [])
            ->assertRedirect(route('lms.design.edit', $course))
            ->assertSessionHasErrors(['revision_id', 'content_json']);
        $this->patch(route('lms.design.content.update', $course), ['revision_id' => 'x', 'content_json' => '{'])->assertSessionHasErrors('content_json');
        $this->save($user, $course, $this->content(), ['uploads' => [(string) Str::uuid() => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml')]])->assertSessionHasErrors();
        $this->save($user, $course, $this->content(), ['uploads' => [(string) Str::uuid() => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')]])->assertSessionHasErrors();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_content_endpoint_preserves_authorization_and_lifecycle_locks(): void
    {
        [$user, $course] = $this->context();
        $url = route('lms.design.content.update', $course);
        $this->patch($url, [])->assertRedirect(route('login'));
        [$other] = $this->context();
        $this->save($other, $course, $this->content())->assertForbidden();
        $course->latestRevision->update(['status' => 'approved']);
        $this->save($user, $course->fresh(), $this->content())->assertForbidden();
        $this->assertSame(['materials' => []], $course->fresh()->latestRevision->content);
    }

    public function test_curriculum_saves_identity_and_contents_atomically_and_can_clear_materials(): void
    {
        [, $course] = $this->context();
        $manager = User::factory()->create();
        $manager->assignRole('Kurikulum');
        $this->save($manager, $course, $this->content(), ['title' => 'Updated'])->assertSessionHasNoErrors()->assertRedirect(route('lms.design'));
        $this->assertSame('Updated', $course->fresh()->title);
        $this->assertSame('Updated', $course->fresh()->latestRevision->title);
        $this->save($manager, $course->fresh(), ['materials' => []])->assertSessionHasNoErrors();
        $this->assertSame([], $course->fresh()->latestRevision->content['materials']);
    }

    public function test_correct_choice_is_required_and_lifecycle_fields_are_rejected(): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $content['materials'][0]['questions'][0]['options'][0]['is_correct'] = false;
        $this->save($user, $course, $content)->assertSessionHasErrors(['content' => 'Pilih tepat satu jawaban benar untuk soal pilihan ganda.']);
        $content['materials'][0]['questions'][0]['options'][0]['is_correct'] = true;
        $content['materials'][0]['questions'][0]['options'][1]['is_correct'] = true;
        $this->save($user, $course, $content)->assertSessionHasErrors('content');
        $this->save($user, $course, $this->content(), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->assertSame('draft', $course->fresh()->latestRevision->status);
    }

    public function test_image_upload_is_private_and_failed_reference_cleans_up_new_uploads(): void
    {
        [$user, $course] = $this->context();
        $content = $this->content();
        $id = (string) Str::uuid();
        $content['materials'][0]['sections'][0]['media'][] = ['id' => (string) Str::uuid(), 'type' => 'image', 'asset_id' => $id];
        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->save($user, $course, $content, ['uploads' => [$id => UploadedFile::fake()->createWithContent('image.png', $imageBytes)]])->assertSessionHasNoErrors();
        $this->get(route('lms.design.media.view', [$course, $id]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $before = Storage::disk('local')->allFiles();
        $newId = (string) Str::uuid();
        $content['materials'][0]['media'][] = ['id' => (string) Str::uuid(), 'type' => 'image', 'asset_id' => $newId];
        $content['materials'][0]['media'][] = ['id' => (string) Str::uuid(), 'type' => 'pdf', 'asset_id' => (string) Str::uuid()];
        $this->save($user, $course->fresh(), $content, ['uploads' => [$newId => UploadedFile::fake()->createWithContent('second.png', $imageBytes)]])->assertSessionHasErrors('content');
        $this->assertSame($before, Storage::disk('local')->allFiles());
    }

    /** @return array{User, LmsCourse} */
    private function context(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $course = LmsCourse::query()->create(['title' => 'Draft', 'subject_id' => Subject::factory()->create()->id, 'grade_level' => 'VII', 'created_by' => $teacher->id]);
        $course->revisions()->create(['revision_number' => 1, 'title' => 'Draft', 'content' => ['materials' => []], 'created_by' => $teacher->id]);

        return [$user, $course];
    }

    /** @param array<string, mixed> $content
     * @param  array<string, mixed>  $extra
     */
    private function save(User $user, LmsCourse $course, array $content, array $extra = []): TestResponse
    {
        return $this->actingAs($user)->post(route('lms.design.content.update', $course), [
            '_method' => 'patch', 'revision_id' => $course->latestRevision->editToken($course), 'content_json' => json_encode($content), ...$extra,
        ]);
    }

    /** @return array<string, mixed> */
    private function content(): array
    {
        $page = fn (string $title): array => ['id' => (string) Str::uuid(), 'title' => $title, 'body' => '<p>Text <strong>bold</strong></p>', 'media' => [], 'questions' => []];
        $first = [...$page('Materi kedua'), 'sections' => [$page('Bagian A')]];
        $first['media'] = [['id' => (string) Str::uuid(), 'type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']];
        $first['questions'] = [['id' => (string) Str::uuid(), 'type' => 'multiple_choice', 'prompt' => 'Choose', 'options' => [
            ['id' => (string) Str::uuid(), 'text' => 'A', 'is_correct' => true], ['id' => (string) Str::uuid(), 'text' => 'B', 'is_correct' => false],
        ]]];
        $first['sections'][0]['questions'] = [['id' => (string) Str::uuid(), 'type' => 'essay', 'prompt' => 'Explain']];

        return ['materials' => [$first, [...$page('Materi pertama'), 'sections' => []]]];
    }
}
