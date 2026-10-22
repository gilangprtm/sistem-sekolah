<?php

namespace Tests\Unit;

use App\Services\XlsxWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class XlsxWriterTest extends TestCase
{
    public function test_letterhead_is_embedded_with_internal_relationships_and_space_above_title(): void
    {
        $image = dirname(__DIR__, 2).'/public/images/kop-sekolah.png';
        $content = (new XlsxWriter)->write([['Laporan'], ['Nama', 'Keterangan'], ['Ani', '1 Sakit']], 'Laporan', [
            'headerImage' => $image, 'titleRow' => 1, 'headerRow' => 2, 'borderFromRow' => 3,
            'columnWidths' => [1 => 52.0, 2 => 40.0],
        ]);

        $parts = $this->parts($content);
        $this->assertSame(file_get_contents($image), $parts['xl/media/image1.png']);
        $sheet = $this->xml($parts['xl/worksheets/sheet1.xml']);
        $this->assertSame('Laporan', (string) $sheet->sheetData->row[1]->c[0]->is->t);
        $this->assertSame('A2:B2', (string) $sheet->mergeCells->mergeCell[0]['ref']);
        $this->assertSame('1 Sakit', (string) $sheet->sheetData->row[3]->c[1]->is->t);
        $this->assertSame('rId1', (string) $sheet->drawing->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id);

        $drawing = $this->xml($parts['xl/drawings/drawing1.xml'])->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->oneCellAnchor;
        $width = (int) (string) $drawing->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->ext->attributes()['cx'] / 9525;
        $height = (int) (string) $drawing->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->ext->attributes()['cy'] / 9525;
        $this->assertSame('0', (string) $drawing->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->from->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->row);
        $this->assertSame('0', (string) $drawing->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->from->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->col);
        $this->assertEqualsWithDelta(564, $width, 0);
        $drawingXml = $this->xml($parts['xl/drawings/drawing1.xml']);
        $drawingXml->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
        $offset = (int) (string) $drawingXml->xpath('//xdr:from/xdr:colOff')[0] / 9525;
        $this->assertEqualsWithDelta(45, $offset, 0);
        $this->assertEqualsWithDelta(940 / 305, $width / $height, 0.02);
        $this->assertGreaterThan($height * 0.75, (float) $sheet->sheetData->row[0]['ht']);
        $this->assertLessThanOrEqual(409, (float) $sheet->sheetData->row[0]['ht']);
        $blip = $drawing->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->pic->children('http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing')->blipFill->children('http://schemas.openxmlformats.org/drawingml/2006/main')->blip;
        $this->assertSame('rId1', (string) $blip->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->embed);
        $sheetRelationship = $this->xml($parts['xl/worksheets/_rels/sheet1.xml.rels'])->Relationship;
        $this->assertSame('rId1', (string) $sheetRelationship['Id']);
        $this->assertSame('../drawings/drawing1.xml', (string) $sheetRelationship['Target']);
        $imageRelationship = $this->xml($parts['xl/drawings/_rels/drawing1.xml.rels'])->Relationship;
        $this->assertSame('../media/image1.png', (string) $imageRelationship['Target']);
        $this->assertSame('rId1', (string) $imageRelationship['Id']);
        $this->assertSame('', (string) $imageRelationship['TargetMode']);
        $types = $this->xml($parts['[Content_Types].xml']);
        $types->registerXPathNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
        $this->assertCount(1, $types->xpath('//ct:Default[@Extension="png" and @ContentType="image/png"]'));
        $this->assertCount(1, $types->xpath('//ct:Override[@PartName="/xl/drawings/drawing1.xml"]'));
    }

    public function test_wide_letterhead_is_capped_below_excel_row_height_limit(): void
    {
        $content = (new XlsxWriter)->write([['Laporan']], 'Laporan', [
            'headerImage' => dirname(__DIR__, 2).'/public/images/kop-sekolah.png',
            'columnWidths' => [1 => 500.0],
        ]);

        $parts = $this->parts($content);
        $sheet = $this->xml($parts['xl/worksheets/sheet1.xml']);
        $this->assertLessThanOrEqual(409, (float) $sheet->sheetData->row[0]['ht']);
        $this->assertSame('Laporan', (string) $sheet->sheetData->row[1]->c[0]->is->t);
    }

    public function test_letterhead_size_stays_fixed_when_report_column_widths_change(): void
    {
        foreach ([[], [1 => 52.0, 2 => 40.0], [1 => 500.0]] as $columnWidths) {
            $content = (new XlsxWriter)->write([['Laporan']], 'Laporan', [
                'headerImage' => dirname(__DIR__, 2).'/public/images/kop-sekolah.png',
                'columnWidths' => $columnWidths,
            ]);

            $parts = $this->parts($content);
            $drawing = $this->xml($parts['xl/drawings/drawing1.xml']);
            $drawing->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
            $this->assertSame((string) (564 * 9525), (string) $drawing->xpath('//xdr:oneCellAnchor/xdr:ext/@cx')[0]);
            $this->assertSame((string) (183 * 9525), (string) $drawing->xpath('//xdr:oneCellAnchor/xdr:ext/@cy')[0]);
        }
    }

    public function test_plain_workbook_stays_without_letterhead_styles_or_extra_rows(): void
    {
        $content = (new XlsxWriter)->write([['Nama'], ['Ani']], 'Siswa');

        $parts = $this->parts($content);
        $this->assertArrayNotHasKey('xl/media/image1.png', $parts);
        $this->assertArrayNotHasKey('xl/drawings/drawing1.xml', $parts);
        $this->assertArrayNotHasKey('xl/styles.xml', $parts);
        $sheet = $this->xml($parts['xl/worksheets/sheet1.xml']);
        $this->assertCount(2, $sheet->sheetData->row);
        $this->assertSame('Nama', (string) $sheet->sheetData->row[0]->c[0]->is->t);
        $this->assertCount(0, $sheet->drawing);
    }

    public function test_missing_letterhead_is_rejected_instead_of_producing_a_broken_workbook(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gambar kop Excel tidak tersedia atau terlalu besar.');

        (new XlsxWriter)->write([['Laporan']], 'Laporan', ['headerImage' => __DIR__.'/missing-kop.png']);
    }

    public function test_invalid_letterhead_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gambar kop Excel harus berupa PNG yang valid.');

        (new XlsxWriter)->write([['Laporan']], 'Laporan', ['headerImage' => __FILE__]);
    }

    /** @return array<string, string> */
    private function parts(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-letterhead-test-');
        $this->assertIsString($path);
        try {
            file_put_contents($path, $content);
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path));
            $parts = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                $this->assertIsString($name);
                $part = $archive->getFromIndex($index);
                $this->assertIsString($part);
                if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                    $this->xml($part);
                }
                $parts[$name] = $part;
            }
            $archive->close();

            return $parts;
        } finally {
            unlink($path);
        }
    }

    private function xml(string $content): SimpleXMLElement
    {
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        return $xml;
    }
}
