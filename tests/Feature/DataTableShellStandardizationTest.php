<?php

namespace Tests\Feature;

use Tests\TestCase;

class DataTableShellStandardizationTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function managementTablePages(): array
    {
        return [
            'students/index.tsx',
            'teachers/index.tsx',
            'users/index.tsx',
            'roles/index.tsx',
        ];
    }

    public function test_management_pages_use_the_generic_data_table_shell_without_manual_tables(): void
    {
        foreach ($this->managementTablePages() as $page) {
            $source = file_get_contents(resource_path("js/pages/{$page}"));

            $this->assertIsString($source);
            $this->assertStringContainsString(
                "import DataTableShell from '@/components/data-table/data-table-shell';",
                $source,
                $page,
            );
            $this->assertStringContainsString('<DataTableShell', $source, $page);
            $this->assertStringContainsString('columns={columns}', $source, $page);
            $this->assertStringContainsString('selection={{', $source, $page);
            $this->assertStringContainsString('pagination={{', $source, $page);
            $this->assertStringNotContainsString(
                "from '@/components/ui/table'",
                $source,
                $page,
            );
            $this->assertStringNotContainsString(
                "from '@/components/ui/pagination'",
                $source,
                $page,
            );
        }
    }
}
