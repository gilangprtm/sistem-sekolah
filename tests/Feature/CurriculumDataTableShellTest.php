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
            $this->assertStringNotContainsString(
                "from '@/components/ui/checkbox'",
                $source,
                $page,
            );
        }
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
