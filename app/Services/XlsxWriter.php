<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class XlsxWriter
{
    /**
     * @param  list<list<string|int>>  $rows
     * @param  array{columnWidths?: array<int, float>, titleRow?: int, headerRow?: int, borderFromRow?: int, noteRow?: int, wrapColumns?: list<int>, centerColumns?: list<int>, headerImage?: string}  $options
     */
    public function write(array $rows, string $sheetName, array $options = []): string
    {
        $letterhead = null;
        if (isset($options['headerImage'])) {
            $letterhead = $this->letterhead($options['headerImage'], $options['columnWidths'] ?? []);
            array_unshift($rows, []);
            foreach (['titleRow', 'noteRow', 'headerRow', 'borderFromRow'] as $key) {
                if (isset($options[$key])) {
                    $options[$key]++;
                }
            }
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'school-xlsx-');

        if ($temporaryPath === false) {
            throw new RuntimeException('File Excel tidak dapat dibuat.');
        }

        try {
            $archive = new ZipArchive;

            if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('File Excel tidak dapat dibuat.');
            }

            $styled = $options !== [];
            $parts = [
                '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.($styled ? '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' : '').($letterhead !== null ? '<Default Extension="png" ContentType="image/png"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '').'</Types>',
                '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->escape($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.($styled ? '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' : '').'</Relationships>',
                'xl/worksheets/sheet1.xml' => $this->sheet($rows, $options, $letterhead),
            ];
            if ($styled) {
                $parts['xl/styles.xml'] = $this->styles();
            }

            if ($letterhead !== null) {
                $parts['xl/media/image1.png'] = $letterhead['content'];
                $parts['xl/worksheets/_rels/sheet1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
                $parts['xl/drawings/_rels/drawing1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/></Relationships>';
                $parts['xl/drawings/drawing1.xml'] = $this->drawing($letterhead);
            }

            foreach ($parts as $name => $xml) {
                if (! $archive->addFromString($name, $xml)) {
                    $archive->close();
                    throw new RuntimeException('File Excel tidak dapat dibuat.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('File Excel tidak dapat diselesaikan.');
            }

            $content = file_get_contents($temporaryPath);

            if ($content === false) {
                throw new RuntimeException('File Excel tidak dapat dibaca.');
            }

            return $content;
        } finally {
            @unlink($temporaryPath);
        }
    }

    /**
     * @param  list<list<string|int>>  $rows
     * @param  array{columnWidths?: array<int, float>, titleRow?: int, headerRow?: int, borderFromRow?: int, noteRow?: int, wrapColumns?: list<int>, centerColumns?: list<int>, headerImage?: string}  $options
     * @param  array{content: string, width: int, height: int, offsetX: int, column: int}|null  $letterhead
     */
    private function sheet(array $rows, array $options, ?array $letterhead = null): string
    {
        $columnCount = max([1, ...array_map('count', $rows)]);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($options !== []) {
            $xml .= '<sheetFormatPr defaultRowHeight="20"/>';
        }
        if (isset($options['columnWidths'])) {
            $xml .= '<cols>';
            foreach ($options['columnWidths'] as $column => $width) {
                $xml .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $height = 20;
            if ($letterhead !== null && $number === 1) {
                $height = min(409, (int) ceil($letterhead['height'] * 0.75) + 12);
            } elseif (isset($options['titleRow']) && $options['titleRow'] === $number) {
                $height = 30;
            } elseif (isset($options['borderFromRow']) && $number >= $options['borderFromRow']) {
                foreach ($options['wrapColumns'] ?? [] as $column) {
                    $width = max(1, (int) ($options['columnWidths'][$column] ?? 8) - 2);
                    $lines = 0;
                    foreach (explode("\n", (string) ($row[$column - 1] ?? '')) as $line) {
                        $lines += max(1, (int) ceil(mb_strwidth($line) / $width));
                    }
                    $height = min(409, max($height, $lines * 15 + 6));
                }
            }
            $xml .= '<row r="'.$number.'"'.($options !== [] ? ' ht="'.$height.'" customHeight="1"' : '').'>';

            foreach ($row as $columnIndex => $value) {
                $column = $columnIndex + 1;
                $reference = $this->columnName($column).$number;
                $style = 0;
                if (isset($options['titleRow']) && $options['titleRow'] === $number) {
                    $style = 1;
                } elseif (isset($options['headerRow']) && $options['headerRow'] === $number) {
                    $style = 2;
                } elseif (isset($options['borderFromRow']) && $number >= $options['borderFromRow']) {
                    $style = in_array($column, $options['centerColumns'] ?? [], true) ? 5 : (in_array($column, $options['wrapColumns'] ?? [], true) ? 4 : 3);
                }
                $styleAttribute = $style > 0 ? ' s="'.$style.'"' : '';
                $xml .= is_int($value)
                    ? '<c r="'.$reference.'"'.$styleAttribute.'><v>'.$value.'</v></c>'
                    : '<c r="'.$reference.'"'.$styleAttribute.' t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
            }

            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        $mergeRows = array_filter([$options['titleRow'] ?? null, $options['noteRow'] ?? null], fn (?int $row): bool => $row !== null && count($rows[$row - 1] ?? []) === 1);
        if ($mergeRows !== []) {
            $lastColumn = $this->columnName($columnCount);
            $xml .= '<mergeCells count="'.count($mergeRows).'">';
            foreach ($mergeRows as $row) {
                $xml .= '<mergeCell ref="A'.$row.':'.$lastColumn.$row.'"/>';
            }
            $xml .= '</mergeCells>';
        }

        if ($letterhead !== null) {
            $xml .= '<drawing xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId1"/>';
        }

        return $xml.'</worksheet>';
    }

    /**
     * @param  array<int, float>  $columnWidths
     * @return array{content: string, width: int, height: int, offsetX: int, column: int}
     */
    private function letterhead(string $path, array $columnWidths): array
    {
        if (! is_file($path) || filesize($path) > 5_000_000) {
            throw new RuntimeException('Gambar kop Excel tidak tersedia atau terlalu besar.');
        }

        $image = @getimagesize($path);
        if ($image === false || $image['mime'] !== 'image/png') {
            throw new RuntimeException('Gambar kop Excel harus berupa PNG yang valid.');
        }

        $totalWidth = array_sum($columnWidths);
        $availableWidth = $totalWidth > 0 ? (int) floor($totalWidth * 7 + count($columnWidths) * 5) : $image[0];
        $width = max(1, (int) round($image[0] * 0.6));
        $height = (int) round($image[1] * $width / $image[0]);
        $offsetX = (int) round(max(0, $availableWidth - $width) / 2);
        $column = 0;
        ksort($columnWidths);
        foreach ($columnWidths as $columnWidth) {
            $pixels = (int) floor($columnWidth * 7 + 5);
            if ($offsetX < $pixels) {
                break;
            }
            $offsetX -= $pixels;
            $column++;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Gambar kop Excel tidak dapat dibaca.');
        }

        return ['content' => $content, 'width' => $width, 'height' => $height, 'offsetX' => $offsetX, 'column' => $column];
    }

    /** @param array{content: string, width: int, height: int, offsetX: int, column: int} $letterhead */
    private function drawing(array $letterhead): string
    {
        $widthEmu = $letterhead['width'] * 9525;
        $heightEmu = $letterhead['height'] * 9525;
        $offsetEmu = $letterhead['offsetX'] * 9525;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><xdr:oneCellAnchor><xdr:from><xdr:col>'.$letterhead['column'].'</xdr:col><xdr:colOff>'.$offsetEmu.'</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:ext cx="'.$widthEmu.'" cy="'.$heightEmu.'"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="kop-sekolah.png"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$widthEmu.'" cy="'.$heightEmu.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="16"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
