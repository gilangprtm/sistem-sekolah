<?php

namespace Tests\Feature;

use Tests\TestCase;

class CurriculumDataTableShellTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function curriculumTablePages(): array
    {
        return [
            'academic-period-rombels/index.tsx',
            'academic-years/index.tsx',
            'management-class/index.tsx',
            'management-class/manage.tsx',
            'rombels/index.tsx',
            'subjects/index.tsx',
            'teacher-subjects/index.tsx',
            'uas/index.tsx',
        ];
    }

    public function test_curriculum_pages_use_the_generic_data_table_shell_without_manual_tables(): void
    {
        foreach ($this->curriculumTablePages() as $page) {
            $source = file_get_contents(resource_path("js/pages/{$page}"));

            $this->assertIsString($source);
            $this->assertStringContainsString(
                "import DataTableShell from '@/components/data-table/data-table-shell';",
                $source,
                $page,
            );
            $this->assertStringNotContainsString(
                "from '@/components/ui/table'",
                $source,
                $page,
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<Checkbox\b[^>]*\bchecked=\{selected\b/s',
                $source,
                $page,
            );
        }
    }

    public function test_subject_combined_option_uses_the_shared_checkbox_component(): void
    {
        $source = file_get_contents(resource_path('js/pages/subjects/index.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("import { Checkbox } from '@/components/ui/checkbox';", $source);
        $this->assertMatchesRegularExpression('/<Checkbox\s+id="subject-allow-combined"\s+checked=\{form\.allow_combined\}/s', $source);
        $this->assertStringNotContainsString('type="checkbox"', $source);
    }

    public function test_generic_data_table_shell_uses_sixteen_pixel_horizontal_cell_padding(): void
    {
        $source = file_get_contents(
            resource_path('js/components/data-table/data-table-shell.tsx'),
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "'**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4'",
            $source,
        );
    }

    public function test_academic_year_and_semester_forms_share_one_dialog(): void
    {
        $source = file_get_contents(
            resource_path('js/pages/academic-years/index.tsx'),
        );

        $this->assertIsString($source);
        $this->assertSame(1, substr_count($source, '<Dialog '));
        $this->assertStringContainsString('<Dialog open={form !== null}', $source);
        $this->assertStringContainsString("form.kind === 'year'", $source);
        $this->assertSame(1, substr_count($source, '<ModalShell '));
        $this->assertStringContainsString('Edit Tahun Ajaran', $source);
        $this->assertMatchesRegularExpression('/onClick=\\{\\(\\) => openYearEdit\\(item\\)\\}\\s*>\\s*Edit\\s*<\\/DropdownMenuItem>/s', $source);
    }

    public function test_academic_year_selection_count_includes_rows_from_other_pages(): void
    {
        $source = file_get_contents(
            resource_path('js/pages/academic-years/index.tsx'),
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'selectedCount: selected.length,',
            $source,
        );
    }
}
