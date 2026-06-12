<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;

class CertificationDocumentController extends Controller
{
    // Mapa de categoría (nombre BD) → carpeta en storage
    private $categoryFolderMap = [
        'bar'           => 'bar',
        'carnes'        => 'carnes',
        'carniceria'    => 'carnes',
        'cigarreria'    => 'cigarreria',
        'fruver'        => 'fruver',
        'panaderia'     => 'panaderia',
        'panadería'     => 'panaderia',
        'restaurante'   => 'restaurante',
        'supermercado'  => 'supermercado',
    ];

    /**
     * Descarga el acta de capacitación para el cliente.
     * Genera: plan de capacitación 
     *  + evaluación aleatoria
     */
    public function downloadForClient(Request $request, $requestId)
    {
        $serviceRequest = ServiceRequest::with([
            'client',
            'service',
            'city',
            'professional.user',
        ])
            ->where('id', $requestId)
            // ->where('client_id', $request->user()->id)
            ->where('status', 'completed')
            ->firstOrFail();

        $cycle    = $serviceRequest->cycle ?? 1;
        $category = $this->resolveCategoryFolder($serviceRequest);

        // ── 1. Plan de capacitación
        $planPath = storage_path("app/certifications/{$category}/ciclo{$cycle}/plan.docx");
        $planFile = $this->fillPlanTemplate($planPath, $serviceRequest);

        // ── 2. Evaluación aleatoria
        $evalFile = $this->fillEvalTemplate($serviceRequest);

        // ── 3. Video del ciclo
        $videoDocPath = storage_path("app/certifications/{$category}/ciclo{$cycle}/video.docx");

        // ── 4. Devolver todos los archivos como ZIP
        $zipName = "Acta-{$serviceRequest->id}.zip";
        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($planFile && file_exists($planFile)) {
            $zip->addFile($planFile, "Plan_Capacitacion_Ciclo{$cycle}.docx");
        }
        if ($evalFile && file_exists($evalFile)) {
            $zip->addFile($evalFile, "Evaluacion.docx");
        }
        if (file_exists($videoDocPath)) {
            $zip->addFile($videoDocPath, "Video_Ciclo{$cycle}.docx");
        }

        $zip->close();

        // Limpiar temporales
        if ($planFile && file_exists($planFile)) @unlink($planFile);
        if ($evalFile && file_exists($evalFile)) @unlink($evalFile);

        return response()->download($zipPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
public function downloadPlan(Request $request, $requestId)
{
    $serviceRequest = ServiceRequest::with(['client', 'service', 'city', 'professional.user'])
        ->where('id', $requestId)
        // ->where('client_id', $request->user()->id)
        ->where('status', 'completed')
        ->firstOrFail();

    $cycle    = $serviceRequest->cycle ?? 1;
    $category = $this->resolveCategoryFolder($serviceRequest);
    $planPath = storage_path("app/certifications/{$category}/ciclo{$cycle}/plan.docx");
    $planFile = $this->fillPlanTemplate($planPath, $serviceRequest);

    if (!$planFile || !file_exists($planFile)) {
        return response()->json(['error' => 'Documento no disponible'], 404);
    }

    $pdfFile = $this->convertToPdf($planFile);
    \Log::info('pdfFile result: ' . ($pdfFile ?? 'NULL'));
    if ($pdfFile) {
        @unlink($planFile);
        return response()->download($pdfFile, "Plan_Capacitacion_Ciclo{$cycle}.pdf", [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
    \Log::info('Fallback to docx');
    return response()->download($planFile, "Plan_Capacitacion_Ciclo{$cycle}.docx", [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ])->deleteFileAfterSend(true);
}

public function downloadEval(Request $request, $requestId)
{
    $serviceRequest = ServiceRequest::with(['client', 'service', 'city', 'professional.user'])
        ->where('id', $requestId)
        // ->where('client_id', $request->user()->id)
        ->where('status', 'completed')
        ->firstOrFail();

    $evalFile = $this->fillEvalTemplate($serviceRequest);

    if (!$evalFile || !file_exists($evalFile)) {
        return response()->json(['error' => 'Documento no disponible'], 404);
    }

    $pdfFile = $this->convertToPdf($evalFile);
    if ($pdfFile) {
        @unlink($evalFile);
        return response()->download($pdfFile, 'Evaluacion.pdf', [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
    return response()->download($evalFile, 'Evaluacion.docx', [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ])->deleteFileAfterSend(true);
}

public function downloadVideo(Request $request, $requestId)
{
    $serviceRequest = ServiceRequest::with(['service'])
        ->where('id', $requestId)
        // ->where('client_id', $request->user()->id)
        ->where('status', 'completed')
        ->firstOrFail();

    $cycle        = $serviceRequest->cycle ?? 1;
    $category     = $this->resolveCategoryFolder($serviceRequest);
    $videoDocPath = storage_path("app/certifications/{$category}/ciclo{$cycle}/video.docx");

    if (!file_exists($videoDocPath)) {
        return response()->json(['error' => 'Documento no disponible'], 404);
    }

    $pdfFile = $this->convertToPdf($videoDocPath);
    if ($pdfFile) {
        return response()->download($pdfFile, "Video_Ciclo{$cycle}.pdf", [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
    return response()->download($videoDocPath, "Video_Ciclo{$cycle}.docx", [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
}

// Plan saneamiento
public function downloadSaneamiento(Request $request, $requestId)
{
    $serviceRequest = ServiceRequest::with([
        'client',
        'service',
        'city',
        'professional.user',
    ])
        ->where('id', $requestId)
        // // ->where('client_id', $request->user()->id)
        ->where('status', 'completed')
        ->firstOrFail();

    $category     = $this->resolveCategoryFolder($serviceRequest);
    $templatePath = storage_path("app/saneamiento/{$category}/planSaneamiento.docx");

    if (!file_exists($templatePath)) {
        return response()->json(['error' => 'Documento no disponible'], 404);
    }

    $professional = $serviceRequest->professional;
    $profUser     = $professional ? $professional->user : null;

    $template = new TemplateProcessor($templatePath);
    $template->setValue('empresa',    $serviceRequest->company_name  ?? '');
    $template->setValue('propietario', $serviceRequest->company_owners ?? '');
    $template->setValue('celular',    $serviceRequest->company_phone  ?? '');
    $template->setValue('fecha',        $serviceRequest->service_date ?? '');
    $template->setValue('capacitador', $profUser ? $profUser->name : '');

    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'saneamiento_' . $serviceRequest->id . '.docx';
    $template->saveAs($tmpPath);

    return response()->download($tmpPath, "PlanSaneamiento_{$serviceRequest->id}.docx", [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ])->deleteFileAfterSend(true);
}

public function downloadSaneamientoAdmin(Request $request, $requestId)
{
    $serviceRequest = ServiceRequest::with([
        'client',
        'service',
        'city',
        'professional.user',
    ])
        ->where('id', $requestId)
        ->where('status', 'completed')
        ->firstOrFail();

    $category     = $this->resolveCategoryFolder($serviceRequest);
    $templatePath = storage_path("app/saneamiento/{$category}/planSaneamiento.docx");

    if (!file_exists($templatePath)) {
        return response()->json(['error' => 'Documento no disponible'], 404);
    }

    $professional = $serviceRequest->professional;
    $profUser     = $professional ? $professional->user : null;

    $template = new TemplateProcessor($templatePath);
    $template->setValue('empresa',     $serviceRequest->company_name   ?? '');
    $template->setValue('propietario', $serviceRequest->company_owners ?? '');
    $template->setValue('celular',     $serviceRequest->company_phone  ?? '');
    $template->setValue('fecha',        $serviceRequest->service_date ?? '');
    $template->setValue('capacitador', $profUser ? $profUser->name : '');

    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'saneamiento_admin_' . $serviceRequest->id . '.docx';
    $template->saveAs($tmpPath);

    return response()->download($tmpPath, "PlanSaneamiento_{$serviceRequest->id}.docx", [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ])->deleteFileAfterSend(true);
}

    // ── Helpers privados
    private function resolveCategoryFolder($serviceRequest): string
    {
        // Primero usar la subcategoría de certificación guardada directamente
        if ($serviceRequest->cert_subcategory) {
            $key = strtolower(trim($serviceRequest->cert_subcategory));
            return $this->categoryFolderMap[$key] ?? 'restaurante';
        }
        $categoryName = '';
        if ($serviceRequest->service && $serviceRequest->service->category) {
            $categoryName = strtolower($serviceRequest->service->category->name ?? '');
        }
        return $this->categoryFolderMap[$categoryName] ?? 'restaurante';
    }

   private function fillPlanTemplate($templatePath, $serviceRequest): ?string
{
    if (!file_exists($templatePath)) {
        return null;
    }

    $professional = $serviceRequest->professional;
    $profUser     = $professional ? $professional->user : null;
    $names        = $serviceRequest->people_names ?? [];
    $ids          = $serviceRequest->people_identifications ?? [];

    $template = new TemplateProcessor($templatePath);

    // Datos del cliente / empresa
    $template->setValue('empresa',             $serviceRequest->company_name    ?? '');
    $template->setValue('direccion',           $serviceRequest->company_address ?? '');
    $template->setValue('propietario',         $serviceRequest->company_owners  ?? '');
    $template->setValue('nit',                 $serviceRequest->company_nit     ?? '');
    $template->setValue('celular',             $serviceRequest->company_phone   ?? '');
    $template->setValue('ciudad',              $serviceRequest->city ? $serviceRequest->city->name : '');
    $template->setValue('fecha',               $serviceRequest->service_date    ?? '');
    $template->setValue('mes', $serviceRequest->service_date
    ? ucfirst(\Carbon\Carbon::parse($serviceRequest->service_date)
        ->locale('es')
        ->isoFormat('MMM YYYY'))
    : '');
    $template->setValue('lugar',               $serviceRequest->company_locality ?? '');

    // Datos del profesional / capacitador
    $template->setValue('capacitador',         $profUser ? $profUser->name : '');
    $template->setValue('cargo',               'Capacitador');
    $template->setValue('celular_capacitador', $professional ? ($professional->phone ?? '') : '');

    // Participantes — texto
    for ($n = 1; $n <= 12; $n++) {
        $idx            = $n - 1;
        $hasParticipant = isset($names[$idx]) && $names[$idx] !== '';
        $template->setValue("participante_nombre_{$n}",  $hasParticipant ? ($names[$idx] ?? '')                   : '');
        $template->setValue("participante_cedula_{$n}",  $hasParticipant ? ($ids[$idx]   ?? '')                   : '');
        $template->setValue("participante_sesion_{$n}",  $hasParticipant ? ($serviceRequest->service_date ?? '')  : '');
        $template->setValue("participante_empresa_{$n}", $hasParticipant ? ($serviceRequest->company_name  ?? '')  : '');
        $template->setValue("fecha_{$n}", $hasParticipant ? ($serviceRequest->service_date ?? '') : '');
        $template->setValue("empresa_{$n}", $hasParticipant ? ($serviceRequest->company_name ?? '') : '');
    }

    // Guardar temporal
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plan_' . $serviceRequest->id . '.docx';
    $template->saveAs($tmpPath);

    // Procesar imágenes por descr directamente en el ZIP
    $firmasDir         = storage_path('app/certifications/firmas');
    $firmasDisponibles = $this->listFiles($firmasDir, ['jpg', 'jpeg', 'png']);
    shuffle($firmasDisponibles);

    $imageReplacements = [];

    // Firmas de participantes
    $firmaIndex = 0;
    for ($n = 1; $n <= 12; $n++) {
        $idx            = $n - 1;
        $hasParticipant = isset($names[$idx]) && $names[$idx] !== '';
        if ($hasParticipant && isset($firmasDisponibles[$firmaIndex])) {
            $imageReplacements["\${participante_firma_{$n}}"] = $firmasDisponibles[$firmaIndex];
            $firmaIndex++;
        } else {
            $imageReplacements["\${participante_firma_{$n}}"] = null;
        }
    }

    // Credencial del profesional
    if ($professional && $professional->professional_card) {
        $credencialPath = storage_path('app/public/' . ltrim($professional->professional_card, '/'));
        if (file_exists($credencialPath)) {
            $imageReplacements['${credencial_profesional}'] = $credencialPath;
        }
    }

    $this->replaceImagesByDescr($tmpPath, $imageReplacements);

    return $tmpPath;
}

    private function fillEvalTemplate($serviceRequest): ?string
    {
        $evalPath = $this->randomFile(storage_path('app/certifications/evaluaciones'), ['docx']);
        if (!$evalPath) {
            return null;
        }

        $names = $serviceRequest->people_names ?? [];

        $template = new TemplateProcessor($evalPath);
        $template->setValue('fecha',  $serviceRequest->service_date ?? '');
        $template->setValue('participante_nombre_1',   $names[0] ?? '');

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eval_' . $serviceRequest->id . '.docx';
        $template->saveAs($tmpPath);

        return $tmpPath;
    }

    private function randomFile(string $dir, array $extensions): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        $files = array_filter(scandir($dir), function ($f) use ($dir, $extensions) {
            if (in_array($f, ['.', '..'])) return false;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            return in_array($ext, $extensions) && is_file($dir . DIRECTORY_SEPARATOR . $f);
        });

        if (empty($files)) {
            return null;
        }

        $files = array_values($files);
        $pick  = $files[array_rand($files)];

        return $dir . DIRECTORY_SEPARATOR . $pick;
    }

    private function listFiles(string $dir, array $extensions): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = array_filter(scandir($dir), function ($f) use ($dir, $extensions) {
            if (in_array($f, ['.', '..'])) return false;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            return in_array($ext, $extensions) && is_file($dir . DIRECTORY_SEPARATOR . $f);
        });

        return array_map(
            fn($f) => $dir . DIRECTORY_SEPARATOR . $f,
            array_values($files)
        );
    }

private function replaceImagesByDescr(string $docxPath, array $replacements): void
{
    $zip = new \ZipArchive();
    if ($zip->open($docxPath) !== true) return;

    $documentXml = $zip->getFromName('word/document.xml');
    $relsXml     = $zip->getFromName('word/_rels/document.xml.rels');
    if (!$documentXml || !$relsXml) {
        $zip->close();
        return;
    }

    // ── 1. Parsear relaciones existentes
    $mediaFiles = [];
    preg_match_all('/Id="([^"]+)"[^>]+Target="(media\/[^"]+)"/', $relsXml, $m);
    foreach ($m[1] as $i => $rId) {
        $mediaFiles[$rId] = $m[2][$i];
    }

    // ── 2. Mapear placeholder → rId buscando dentro de cada bloque
    $placeholderToRId = [];
    preg_match_all(
        '/<wp:(?:inline|anchor)[^>]*>.*?<\/wp:(?:inline|anchor)>/s',
        $documentXml,
        $blockMatches
    );
    foreach ($blockMatches[0] as $block) {
        $nameMatch = preg_match('/(?:name|descr)="(\$\{[^"]+\})"/', $block, $nm);
        if (!$nameMatch) continue;
        $placeholder = $nm[1];
        if (!preg_match('/r:embed="([^"]+)"/', $block, $blipMatch)) continue;
        $placeholderToRId[$placeholder] = $blipMatch[1];
    }

    // ── 3. Para cada reemplazo, crear nuevo rId y nueva imagen
    $newDocumentXml = $documentXml;
    $newRelsXml     = $relsXml;
    $rIdCounter     = 200;

    foreach ($replacements as $placeholder => $newImagePath) {
        if (!isset($placeholderToRId[$placeholder])) continue;

        $oldRId = $placeholderToRId[$placeholder];
        if (!isset($mediaFiles[$oldRId])) continue;

        // Solo procesar si hay imagen nueva — sin participante no tocar el XML
        if (!$newImagePath || !file_exists($newImagePath)) {
            continue;
        }

        $ext          = strtolower(pathinfo($newImagePath, PATHINFO_EXTENSION));
        $newRId       = 'rId' . $rIdCounter++;
        $newMediaFile = 'media/img_cert_' . $rIdCounter . '.' . $ext;

        // Reemplazar rId solo en el bloque que contiene este placeholder
        $escapedPlaceholder = preg_quote($placeholder, '/');
        $newDocumentXml = preg_replace_callback(
            '/<wp:(?:inline|anchor)[^>]*>.*?<\/wp:(?:inline|anchor)>/s',
            function ($match) use ($escapedPlaceholder, $oldRId, $newRId) {
                $block = $match[0];
                if (!preg_match('/(?:name|descr)="' . $escapedPlaceholder . '"/', $block)) {
                    return $block;
                }
                return preg_replace(
                    '/r:embed="' . preg_quote($oldRId, '/') . '"/',
                    'r:embed="' . $newRId . '"',
                    $block,
                    1
                );
            },
            $newDocumentXml
        );

        // Agregar nueva relación
        $newRelsXml = str_replace(
            '</Relationships>',
            '<Relationship Id="' . $newRId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' . $newMediaFile . '"/>
</Relationships>',
            $newRelsXml
        );

        // Agregar imagen al ZIP
        $zip->addFile($newImagePath, 'word/' . $newMediaFile);
    }

    // ── 4. Guardar XML modificado
    $zip->addFromString('word/document.xml', $newDocumentXml);
    $zip->addFromString('word/_rels/document.xml.rels', $newRelsXml);
    $zip->close();
}

private function convertToPdf(string $docxPath): ?string
{
    $candidates = [
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        '/usr/bin/libreoffice',
        '/usr/bin/soffice',
    ];

    $soffice = null;
    foreach ($candidates as $c) {
        if (file_exists($c)) { $soffice = $c; break; }
    }

    if (!$soffice) return null;

    $outDir  = sys_get_temp_dir();

    // En Windows usar comillas dobles y reemplazar barras
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = '"' . $soffice . '" --headless --convert-to pdf --outdir "' . $outDir . '" "' . $docxPath . '" 2>&1';
    } else {
        $cmd = escapeshellarg($soffice) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($outDir) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
    }

    exec($cmd, $output, $code);

    \Log::info('LibreOffice cmd: ' . $cmd);
    \Log::info('LibreOffice output: ' . implode("\n", $output));
    \Log::info('LibreOffice code: ' . $code);

    if ($code !== 0) {
        \Log::error('LibreOffice convert failed: ' . implode("\n", $output));
        return null;
    }

    $pdfName = pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
    $pdfPath = $outDir . DIRECTORY_SEPARATOR . $pdfName;

    // Esperar hasta 8 segundos
    $waited = 0;
    while (!file_exists($pdfPath) && $waited < 8) {
        sleep(1);
        $waited++;
    }

    if (!file_exists($pdfPath)) return null;

    // Copiar a carpeta storage para evitar que se borre
    $safePath = storage_path('app/temp_pdfs/' . $pdfName);
    if (!is_dir(storage_path('app/temp_pdfs'))) {
        mkdir(storage_path('app/temp_pdfs'), 0755, true);
    }
    copy($pdfPath, $safePath);

    return file_exists($safePath) ? $safePath : null;
}

}
