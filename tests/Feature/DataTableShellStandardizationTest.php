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

    public function test_agenda_history_uses_the_data_table_action_column_without_inline_print_form(): void
    {
        $source = file_get_contents(resource_path('js/pages/teacher/agenda/index.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('<DataTableShell', $source);
        $this->assertStringContainsString('rowActions={{', $source);
        $this->assertStringContainsString('selection={{', $source);
        $this->assertStringContainsString('selectedKeys: selectedAgendas', $source);
        $this->assertStringContainsString('onSelectionChange: setSelectedAgendas', $source);
        $this->assertStringContainsString('Pilih semua agenda di halaman ini', $source);
        $this->assertStringNotContainsString("header: 'Agenda'", $source);
        $this->assertStringNotContainsString('Cetak laporan Agenda', $source);
        $this->assertStringNotContainsString('agendaReport', $source);
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
