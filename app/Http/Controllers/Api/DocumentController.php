<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentController extends Controller
{
    /**
     * Genera un documento Word de capacitación.
     * doc = 1 | 2 | 3 (variante)
     */
    public function generate(Request $request, $requestId, $doc = 1)
    {
        $professional = $request->user()->professional;

        $serviceRequest = ServiceRequest::with('client', 'service', 'city')
            ->where('id', $requestId)
            ->where('professional_id', $professional->id)
            ->firstOrFail();

        $templatePath = storage_path("app/templates/Capacitacionbar1686-{$doc}.docx");
        $filename     = "Capacitacionbar1686-{$doc}.docx";
        $tmpPath      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        // Si existe la plantilla Word, usar TemplateProcessor para llenar placeholders
        if (file_exists($templatePath)) {
            $template = new TemplateProcessor($templatePath);

            $names = $serviceRequest->people_names ?? [];
            $ids   = $serviceRequest->people_identifications ?? [];

            $template->setValue('empresa',       $serviceRequest->company_name    ?? '');
            $template->setValue('direccion',     $serviceRequest->company_address ?? '');
            $template->setValue('propietario',   $serviceRequest->company_owners  ?? '');
            $template->setValue('nit',           $serviceRequest->company_nit     ?? '');
            $template->setValue('celular',       $serviceRequest->company_phone   ?? '');
            $template->setValue('ciudad',        $serviceRequest->city ? $serviceRequest->city->name : '');
            $template->setValue('fecha',         $serviceRequest->service_date    ?? '');
            $template->setValue('hora',          $serviceRequest->service_time    ?? '');
            $template->setValue('servicio',      $serviceRequest->service ? $serviceRequest->service->name : '');
            $template->setValue('num_personas',  $serviceRequest->people_count    ?? '');
            $template->setValue('lugar',         $serviceRequest->company_address ?? ($serviceRequest->address ?? ''));

            // Clonar filas de participantes si el template lo soporta
            if (!empty($names)) {
                try {
                    $template->cloneRow('participante_nombre', count($names));
                    foreach ($names as $i => $name) {
                        $n = $i + 1;
                        $template->setValue("participante_nombre#{$n}", $name ?: '—');
                        $template->setValue("participante_cedula#{$n}",  $ids[$i] ?? '—');
                        $template->setValue("participante_num#{$n}",     (string)$n);
                    }
                } catch (\Exception $e) {
                    // El template no tiene fila clonable, continuar sin error
                }
            }

            $template->saveAs($tmpPath);

        } else {
            // Generar documento desde cero si no existe la plantilla
            $tmpPath = $this->generateFromScratch($serviceRequest, $doc);
            $tmpPath = $tmpPath ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        }

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function generateForClient(Request $request, $requestId, $doc = 1)
    {
        $serviceRequest = ServiceRequest::with('client', 'service', 'city')
            ->where('id', $requestId)
            ->where('client_id', $request->user()->id)
            ->where('status', 'completed')
            ->firstOrFail();

        $templatePath = storage_path("app/templates/Capacitacionbar1686-{$doc}.docx");
        $filename     = "Capacitacionbar1686-{$doc}.docx";
        $tmpPath      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($templatePath)) {
            $template = new TemplateProcessor($templatePath);

            $names = $serviceRequest->people_names ?? [];
            $ids   = $serviceRequest->people_identifications ?? [];

            $template->setValue('empresa',       $serviceRequest->company_name    ?? '');
            $template->setValue('direccion',     $serviceRequest->company_address ?? '');
            $template->setValue('propietario',   $serviceRequest->company_owners  ?? '');
            $template->setValue('nit',           $serviceRequest->company_nit     ?? '');
            $template->setValue('celular',       $serviceRequest->company_phone   ?? '');
            $template->setValue('ciudad',        $serviceRequest->city ? $serviceRequest->city->name : '');
            $template->setValue('fecha',         $serviceRequest->service_date    ?? '');
            $template->setValue('hora',          $serviceRequest->service_time    ?? '');
            $template->setValue('servicio',      $serviceRequest->service ? $serviceRequest->service->name : '');
            $template->setValue('num_personas',  $serviceRequest->people_count    ?? '');
            $template->setValue('lugar',         $serviceRequest->company_address ?? ($serviceRequest->address ?? ''));

            if (!empty($names)) {
                try {
                    $template->cloneRow('participante_nombre', count($names));
                    foreach ($names as $i => $name) {
                        $n = $i + 1;
                        $template->setValue("participante_nombre#{$n}", $name ?: '—');
                        $template->setValue("participante_cedula#{$n}",  $ids[$i] ?? '—');
                        $template->setValue("participante_num#{$n}",     (string)$n);
                    }
                } catch (\Exception $e) { }
            }

            $template->saveAs($tmpPath);
        } else {
            $tmpPath = $this->generateFromScratch($serviceRequest, $doc);
            $tmpPath = $tmpPath ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        }

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    private function generateFromScratch($serviceRequest, $doc): string
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop'    => Converter::cmToTwip(2),
            'marginBottom' => Converter::cmToTwip(2),
            'marginLeft'   => Converter::cmToTwip(3),
            'marginRight'  => Converter::cmToTwip(2),
        ]);

        $titleStyle = ['bold' => true, 'size' => 14, 'allCaps' => true];
        $labelStyle = ['bold' => true, 'size' => 11];
        $valueStyle = ['size' => 11];
        $parStyle   = ['spaceAfter' => Converter::pointToTwip(6)];

        $section->addText('ACTA DE CAPACITACIÓN', $titleStyle, ['alignment' => 'center', 'spaceAfter' => Converter::pointToTwip(12)]);
        $section->addText(
            'Formulario No. ' . str_pad($serviceRequest->id, 4, '0', STR_PAD_LEFT) . '-' . $doc,
            ['size' => 10, 'color' => '888888'],
            ['alignment' => 'center', 'spaceAfter' => Converter::pointToTwip(10)]
        );
        $section->addTextBreak(1);

        $section->addText('DATOS DEL ESTABLECIMIENTO', ['bold' => true, 'size' => 12, 'color' => '1a56db'], $parStyle);
        $this->addRow($section, 'Nombre del establecimiento:', $serviceRequest->company_name ?? '—', $labelStyle, $valueStyle, $parStyle);
        $this->addRow($section, 'Dirección:', $serviceRequest->company_address ?? '—', $labelStyle, $valueStyle, $parStyle);
        $this->addRow($section, 'Propietario/a:', $serviceRequest->company_owners ?? '—', $labelStyle, $valueStyle, $parStyle);
        $this->addRow($section, 'NIT / Cédula:', $serviceRequest->company_nit ?? '—', $labelStyle, $valueStyle, $parStyle);
        $this->addRow($section, 'Celular:', $serviceRequest->company_phone ?? '—', $labelStyle, $valueStyle, $parStyle);
        $this->addRow($section, 'Ciudad:', $serviceRequest->city ? $serviceRequest->city->name : '—', $labelStyle, $valueStyle, $parStyle);
        $section->addTextBreak(1);

        $section->addText('DATOS DEL SERVICIO', ['bold' => true, 'size' => 12, 'color' => '1a56db'], $parStyle);
        $this->addRow($section, 'Servicio:', $serviceRequest->service ? $serviceRequest->service->name : '—', $labelStyle, $valueStyle, $parStyle);
        $this->addRow($section, 'Fecha:', $serviceRequest->service_date ?? '—', $labelStyle, $valueStyle, $parStyle);
        $this->addRow($section, 'Hora:', $serviceRequest->service_time ?? '—', $labelStyle, $valueStyle, $parStyle);
        $this->addRow($section, 'No. de participantes:', $serviceRequest->people_count ?? '—', $labelStyle, $valueStyle, $parStyle);
        $section->addTextBreak(1);

        $names = $serviceRequest->people_names ?? [];
        $ids   = $serviceRequest->people_identifications ?? [];

        if (!empty($names)) {
            $section->addText('LISTA DE PARTICIPANTES', ['bold' => true, 'size' => 12, 'color' => '1a56db'], $parStyle);

            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => 'cccccc',
                'cellMargin'  => 80,
                'width'       => 100 * 50,
                'unit'        => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT,
            ]);

            $table->addRow();
            $this->addCell($table, '#',               ['bold' => true], 8);
            $this->addCell($table, 'Nombre y Apellido', ['bold' => true], 55);
            $this->addCell($table, 'No. de Cédula',   ['bold' => true], 22);
            $this->addCell($table, 'Firma',           ['bold' => true], 15);

            foreach ($names as $i => $name) {
                $table->addRow();
                $this->addCell($table, (string)($i + 1), [], 8);
                $this->addCell($table, $name ?: '—',  [], 55);
                $this->addCell($table, $ids[$i] ?? '—', [], 22);
                $this->addCell($table, '',             [], 15);
            }
        }

        $section->addTextBreak(2);
        $section->addText('FIRMAS', ['bold' => true, 'size' => 12, 'color' => '1a56db'], $parStyle);

        $sigTable = $section->addTable(['borderSize' => 0, 'width' => 100 * 50, 'unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT]);
        $sigTable->addRow(['exactHeight' => true, 'tHeight' => Converter::cmToTwip(2.5)]);
        $c1 = $sigTable->addCell(4500);
        $c1->addText('___________________________', $valueStyle);
        $c1->addText('Capacitador / Instructor',   $labelStyle);
        $c2 = $sigTable->addCell(4500);
        $c2->addText('___________________________', $valueStyle);
        $c2->addText('Propietario / Representante', $labelStyle);

        $filename = "Capacitacionbar1686-{$doc}.docx";
        $tmpPath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tmpPath);

        return $tmpPath;
    }

    private function addRow($section, $label, $value, $labelStyle, $valueStyle, $parStyle)
    {
        $run = $section->addTextRun($parStyle);
        $run->addText($label . ' ', $labelStyle);
        $run->addText((string)$value, $valueStyle);
    }

    private function addCell($table, $text, $fontStyle, $widthPct)
    {
        $cell = $table->addCell($widthPct * 50, ['bgColor' => empty($fontStyle['bold']) ? 'ffffff' : 'f0f4ff']);
        $cell->addText($text, $fontStyle);
        return $cell;
    }
}
