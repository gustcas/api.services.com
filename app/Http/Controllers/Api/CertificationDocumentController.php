<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;

class CertificationDocumentController extends Controller
{
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
     * Descarga el acta de capacitación para el cliente
     */
    public function downloadForClient(Request $request, $requestId)
    {
        $serviceRequest = ServiceRequest::with(['client', 'service', 'city', 'professional.user'])
            ->where('id', $requestId)
            ->whereIn('status', ['accepted', 'completed'])
            ->firstOrFail();

        $cycle    = $serviceRequest->cycle ?? 1;
        $category = $this->resolveCategoryFolder($serviceRequest);

        $planPath = storage_path("app/certifications/{$category}/ciclo{$cycle}/plan.docx");
        $planFile = $this->fillPlanTemplate($planPath, $serviceRequest);
        $evalFile = $this->fillEvalTemplate($serviceRequest);
        $videoDocPath = storage_path("app/certifications/{$category}/ciclo{$cycle}/video.docx");

        $zipName = "Acta-{$serviceRequest->id}.zip";
        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($planFile && file_exists($planFile)) $zip->addFile($planFile, "Plan_Capacitacion_Ciclo{$cycle}.docx");
        if ($evalFile && file_exists($evalFile)) $zip->addFile($evalFile, "Evaluacion.docx");
        if (file_exists($videoDocPath))          $zip->addFile($videoDocPath, "Video_Ciclo{$cycle}.docx");

        $zip->close();

        if ($planFile && file_exists($planFile)) @unlink($planFile);
        if ($evalFile && file_exists($evalFile)) @unlink($evalFile);

        return response()->download($zipPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Plan de capacitación (admin/cliente).
     */
    public function downloadPlan(Request $request, $requestId)
    {
        $serviceRequest = ServiceRequest::with(['client', 'service', 'city', 'professional.user'])
            ->where('id', $requestId)
            ->whereIn('status', ['accepted', 'completed'])
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
        return response()->download($planFile, "Plan_Capacitacion_Ciclo{$cycle}.docx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Evaluación (admin/cliente).
     * - Presencial: evaluación en blanco (una sola).
     * - Virtual: redirige al frontend para descargar por índice de participante.
     *   Este endpoint devuelve info JSON con la cantidad de participantes.
     */
    public function downloadEval(Request $request, $requestId)
    {
        $serviceRequest = ServiceRequest::with(['client', 'service', 'city', 'professional.user'])
            ->where('id', $requestId)
            ->whereIn('status', ['accepted', 'completed'])
            ->firstOrFail();

        $formType     = $serviceRequest->service ? $serviceRequest->service->form_type : null;
        $isPresencial = in_array($formType, ['certificacion_presencial', 'presencial_participantes']);

        // PRESENCIAL — evaluación en blanco
        if ($isPresencial) {
            $blankDir  = storage_path('app/certifications/evaluaciones_blanco');
            $blankFile = $this->randomFile($blankDir, ['docx']);
            if (!$blankFile) {
                return response()->json(['error' => 'Plantilla no disponible'], 404);
            }
            $pdfFile = $this->convertToPdf($blankFile);
            if ($pdfFile) {
                return response()->download($pdfFile, "Evaluacion_Blanco_{$requestId}.pdf", [
                    'Content-Type' => 'application/pdf',
                ])->deleteFileAfterSend(true);
            }
            return response()->download($blankFile, "Evaluacion_Blanco_{$requestId}.docx")->deleteFileAfterSend(true);
        }

        // VIRTUAL / OTROS — evaluación con nombre del primer participante (genérica)
        $evalFile = $this->fillEvalTemplate($serviceRequest);
        if (!$evalFile || !file_exists($evalFile)) {
            return response()->json(['error' => 'Documento no disponible'], 404);
        }
        $pdfFile = $this->convertToPdf($evalFile);
        if ($pdfFile) {
            @unlink($evalFile);
            return response()->download($pdfFile, "Evaluacion_{$requestId}.pdf", [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        }
        return response()->download($evalFile, "Evaluacion_{$requestId}.docx")->deleteFileAfterSend(true);
    }

    /**
     * Evaluación individual por índice de participante (virtual).
     * El frontend llama N veces, una por participante.
     */
    public function downloadEvalByIndex(Request $request, $requestId, $idx)
    {
        $serviceRequest = ServiceRequest::with(['service'])
            ->where('id', $requestId)
            ->firstOrFail();

        $names  = $serviceRequest->people_names ?? [];
        $ids    = $serviceRequest->people_identifications ?? [];
        $name   = $names[$idx] ?? "Participante_" . ($idx + 1);
        $cedula = $ids[$idx] ?? '';

        $evalPath = $this->randomFile(storage_path('app/certifications/evaluaciones'), ['docx']);
        if (!$evalPath) {
            return response()->json(['error' => 'Plantilla no disponible'], 404);
        }

        $template = new TemplateProcessor($evalPath);
        $template->setValue('fecha',                 $serviceRequest->service_date ?? '');
        $template->setValue('participante_nombre_1', $name);
        $template->setValue('participante_cedula_1', $cedula);
        $template->setValue('empresa',               $serviceRequest->company_name ?? '');

        $tmpDocx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "eval_{$requestId}_{$idx}.docx";
        $template->saveAs($tmpDocx);

        $pdfFile = $this->convertToPdf($tmpDocx);
        @unlink($tmpDocx);

        if ($pdfFile) {
            return response()->download($pdfFile, "Evaluacion_{$requestId}_p" . ($idx + 1) . ".pdf", [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        }

        return response()->json(['error' => 'Error al generar PDF'], 500);
    }

    /**
     * Video del ciclo.
     */
    public function downloadVideo(Request $request, $requestId)
    {
        $serviceRequest = ServiceRequest::with(['service'])
            ->where('id', $requestId)
            ->whereIn('status', ['accepted', 'completed'])
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

    /**
     * Plan de saneamiento (cliente).
     */
    public function downloadSaneamiento(Request $request, $requestId)
    {
        $serviceRequest = ServiceRequest::with(['client', 'service', 'city', 'professional.user'])
            ->where('id', $requestId)
            ->firstOrFail();

        $category     = $this->resolveCategoryFolder($serviceRequest);
        $templatePath = storage_path("app/saneamiento/{$category}/planSaneamiento.docx");

        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Documento no disponible'], 404);
        }

        $professional = $serviceRequest->professional;
        $profUser     = $professional ? $professional->user : null;

        $template = new TemplateProcessor($templatePath);
        $template->setValue('empresa',     $serviceRequest->company_name  ?? '');
        $template->setValue('propietario', $serviceRequest->company_owners ?? '');
        $template->setValue('celular',     $serviceRequest->company_phone  ?? '');
        $template->setValue('fecha',       $serviceRequest->service_date   ?? '');
        $template->setValue('capacitador', $profUser ? $profUser->name : '');

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'saneamiento_' . $serviceRequest->id . '.docx';
        $template->saveAs($tmpPath);

        $pdfFile = $this->convertToPdf($tmpPath);
        if ($pdfFile) {
            @unlink($tmpPath);
            return response()->download($pdfFile, "PlanSaneamiento_{$serviceRequest->id}.pdf", [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        }
        return response()->download($tmpPath, "PlanSaneamiento_{$serviceRequest->id}.docx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Plan de saneamiento (admin).
     */
    public function downloadSaneamientoAdmin(Request $request, $requestId)
    {
        $serviceRequest = ServiceRequest::with(['client', 'service', 'city', 'professional.user'])
            ->where('id', $requestId)
            ->whereIn('status', ['accepted', 'completed'])
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
        $template->setValue('fecha',       $serviceRequest->service_date   ?? '');
        $template->setValue('capacitador', $profUser ? $profUser->name : '');

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'saneamiento_admin_' . $serviceRequest->id . '.docx';
        $template->saveAs($tmpPath);

        $pdfFile = $this->convertToPdf($tmpPath);
        if ($pdfFile) {
            @unlink($tmpPath);
            return response()->download($pdfFile, "PlanSaneamiento_{$serviceRequest->id}.pdf", [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        }
        return response()->download($tmpPath, "PlanSaneamiento_{$serviceRequest->id}.docx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Evaluación en blanco (admin).
     */
    public function downloadEvalBlankAdmin(Request $request, $id)
    {
        $serviceRequest = \App\Models\ServiceRequest::findOrFail($id);
        $blankDir  = storage_path('app/certifications/evaluaciones_blanco');
        $blankFile = $this->randomFile($blankDir, ['docx']);
        if (!$blankFile) {
            return response()->json(['message' => 'No hay plantillas de evaluación disponibles'], 404);
        }
        $pdfFile = $this->convertToPdf($blankFile);
        if ($pdfFile) {
            return response()->download($pdfFile, "Evaluacion_Blanco_{$id}.pdf", [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        }
        return response()->download($blankFile, "Evaluacion_Blanco_{$id}.docx");
    }

    /**
     * Documentos para el profesional (plan sin firmas + evaluación en blanco).
     */
    public function downloadForProfessional(Request $request, $id, $type)
    {
        $professional   = $request->user()->professional;
        $serviceRequest = \App\Models\ServiceRequest::with(['service', 'professional.user', 'city'])
            ->where('id', $id)
            ->where('professional_id', $professional->id)
            ->where('status', 'accepted')
            ->firstOrFail();

        if ($type === 'plan') {
            $cycle    = $serviceRequest->cycle ?? 1;
            $category = $this->resolveCategoryFolder($serviceRequest);
            $planPath = storage_path("app/certifications/{$category}/ciclo{$cycle}/plan.docx");
            $planFile = $this->fillPlanTemplate($planPath, $serviceRequest, false);

            if (!$planFile || !file_exists($planFile)) {
                return response()->json(['message' => 'Plantilla no encontrada'], 404);
            }

            $pdfFile = $this->convertToPdf($planFile);
            if ($pdfFile) {
                @unlink($planFile);
                return response()->download($pdfFile, "Plan_Capacitacion_{$id}.pdf", [
                    'Content-Type' => 'application/pdf',
                ])->deleteFileAfterSend(true);
            }
            return response()->download($planFile, "Plan_Capacitacion_{$id}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])->deleteFileAfterSend(true);
        }

        if ($type === 'eval-blank') {
            $blankDir  = storage_path('app/certifications/evaluaciones_blanco');
            $blankFile = $this->randomFile($blankDir, ['docx']);
            if (!$blankFile) {
                return response()->json(['message' => 'No hay plantillas de evaluación disponibles'], 404);
            }
            $pdfFile = $this->convertToPdf($blankFile);
            if ($pdfFile) {
                return response()->download($pdfFile, "Evaluacion_{$id}.pdf", [
                    'Content-Type' => 'application/pdf',
                ])->deleteFileAfterSend(true);
            }
            return response()->download($blankFile, "Evaluacion_{$id}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
        }

        return response()->json(['message' => 'Tipo no válido'], 400);
    }

    // ── Helpers privados ────────────────────────────────────────────────────

    private function resolveCategoryFolder($serviceRequest): string
    {
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

    private function fillPlanTemplate($templatePath, $serviceRequest, bool $conFirmas = true): ?string
    {
        if (!file_exists($templatePath)) return null;

        $professional = $serviceRequest->professional;
        $profUser     = $professional ? $professional->user : null;
        $names        = $serviceRequest->people_names ?? [];
        $ids          = $serviceRequest->people_identifications ?? [];

        $template = new TemplateProcessor($templatePath);

        $template->setValue('empresa',             $serviceRequest->company_name    ?? '');
        $template->setValue('direccion',           $serviceRequest->company_address ?? '');
        $template->setValue('propietario',         $serviceRequest->company_owners  ?? '');
        $template->setValue('nit',                 $serviceRequest->company_nit     ?? '');
        $template->setValue('celular',             $serviceRequest->company_phone   ?? '');
        $template->setValue('ciudad',              $serviceRequest->city ? $serviceRequest->city->name : '');
        $template->setValue('fecha',               $serviceRequest->service_date    ?? '');
        $template->setValue('mes', $serviceRequest->service_date
            ? ucfirst(\Carbon\Carbon::parse($serviceRequest->service_date)->locale('es')->isoFormat('MMM YYYY'))
            : '');
        $template->setValue('lugar',               $serviceRequest->company_locality ?? '');
        $template->setValue('capacitador',         $profUser ? $profUser->name : '');
        $template->setValue('cargo',               'Capacitador');
        $template->setValue('celular_capacitador', $professional ? ($professional->phone ?? '') : '');

        for ($n = 1; $n <= 12; $n++) {
            $idx            = $n - 1;
            $hasParticipant = isset($names[$idx]) && $names[$idx] !== '';
            $template->setValue("participante_nombre_{$n}",  $hasParticipant ? ($names[$idx] ?? '')                  : '');
            $template->setValue("participante_cedula_{$n}",  $hasParticipant ? ($ids[$idx]   ?? '')                  : '');
            $template->setValue("participante_sesion_{$n}",  $hasParticipant ? ($serviceRequest->service_date ?? '') : '');
            $template->setValue("participante_empresa_{$n}", $hasParticipant ? ($serviceRequest->company_name  ?? '') : '');
            $template->setValue("fecha_{$n}",   $hasParticipant ? ($serviceRequest->service_date ?? '') : '');
            $template->setValue("empresa_{$n}", $hasParticipant ? ($serviceRequest->company_name  ?? '') : '');
        }

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plan_' . $serviceRequest->id . '_' . uniqid() . '.docx';
        $template->saveAs($tmpPath);

        $firmasDir         = storage_path('app/certifications/firmas');
        $firmasDisponibles = $this->listFiles($firmasDir, ['jpg', 'jpeg', 'png']);
        shuffle($firmasDisponibles);

        $imageReplacements = [];
        $firmaIndex = 0;
        for ($n = 1; $n <= 12; $n++) {
            $idx            = $n - 1;
            $hasParticipant = isset($names[$idx]) && $names[$idx] !== '';
            if ($conFirmas && $hasParticipant && isset($firmasDisponibles[$firmaIndex])) {
                $imageReplacements["\${participante_firma_{$n}}"] = $firmasDisponibles[$firmaIndex];
                $firmaIndex++;
            } else {
                $imageReplacements["\${participante_firma_{$n}}"] = null;
            }
        }

        if ($professional && $professional->professional_card) {
            $credencialPath = storage_path('app/public/' . ltrim($professional->professional_card, '/'));
            if (file_exists($credencialPath)) {
                $ext = strtolower(pathinfo($credencialPath, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg']) && function_exists('imagecreatefromjpeg')) {
                    $pngPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cred_' . $professional->id . '.png';
                    $img = @imagecreatefromjpeg($credencialPath);
                    if ($img) {
                        imagepng($img, $pngPath);
                        imagedestroy($img);
                        $credencialPath = $pngPath;
                    }
                }
                $imageReplacements['${credencial_profesional}'] = $credencialPath;
            }
        }

        $this->replaceImagesByDescr($tmpPath, $imageReplacements);

        if (isset($pngPath) && file_exists($pngPath)) @unlink($pngPath);

        return $tmpPath;
    }

    private function fillEvalTemplate($serviceRequest): ?string
    {
        $evalPath = $this->randomFile(storage_path('app/certifications/evaluaciones'), ['docx']);
        if (!$evalPath) return null;

        $names = $serviceRequest->people_names ?? [];

        $template = new TemplateProcessor($evalPath);
        $template->setValue('fecha',                  $serviceRequest->service_date ?? '');
        $template->setValue('participante_nombre_1',  $names[0] ?? '');

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eval_' . $serviceRequest->id . '.docx';
        $template->saveAs($tmpPath);

        return $tmpPath;
    }

    private function randomFile(string $dir, array $extensions): ?string
    {
        if (!is_dir($dir)) return null;

        $files = array_filter(scandir($dir), function ($f) use ($dir, $extensions) {
            if (in_array($f, ['.', '..'])) return false;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            return in_array($ext, $extensions) && is_file($dir . DIRECTORY_SEPARATOR . $f);
        });

        if (empty($files)) return null;

        $files = array_values($files);
        return $dir . DIRECTORY_SEPARATOR . $files[array_rand($files)];
    }

    private function listFiles(string $dir, array $extensions): array
    {
        if (!is_dir($dir)) return [];

        $files = array_filter(scandir($dir), function ($f) use ($dir, $extensions) {
            if (in_array($f, ['.', '..'])) return false;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            return in_array($ext, $extensions) && is_file($dir . DIRECTORY_SEPARATOR . $f);
        });

        return array_map(fn($f) => $dir . DIRECTORY_SEPARATOR . $f, array_values($files));
    }

    private function replaceImagesByDescr(string $docxPath, array $replacements): void
    {
        // Trabajar sobre una copia para evitar corrupción del ZIP original
        $tmpCopy = $docxPath . '.tmp';
        if (!copy($docxPath, $tmpCopy)) return;

        $zip = new \ZipArchive();
        if ($zip->open($tmpCopy) !== true) { @unlink($tmpCopy); return; }

        $documentXml = $zip->getFromName('word/document.xml');
        $relsXml     = $zip->getFromName('word/_rels/document.xml.rels');
        if (!$documentXml || !$relsXml) { $zip->close(); return; }

        $mediaFiles = [];
        preg_match_all('/Id="([^"]+)"[^>]+Target="(media\/[^"]+)"/', $relsXml, $m);
        foreach ($m[1] as $i => $rId) $mediaFiles[$rId] = $m[2][$i];

        $placeholderToRId = [];
        preg_match_all('/<wp:(?:inline|anchor)[^>]*>.*?<\/wp:(?:inline|anchor)>/s', $documentXml, $blockMatches);
        foreach ($blockMatches[0] as $block) {
            if (!preg_match('/(?:name|descr)="(\$\{[^"]+\})"/', $block, $nm)) continue;
            $placeholder = $nm[1];
            if (!preg_match('/r:embed="([^"]+)"/', $block, $blipMatch)) continue;
            $placeholderToRId[$placeholder] = $blipMatch[1];
        }

        $newDocumentXml = $documentXml;
        $newRelsXml     = $relsXml;
        $rIdCounter     = 200;

        foreach ($replacements as $placeholder => $newImagePath) {
            if (!isset($placeholderToRId[$placeholder])) continue;
            $oldRId = $placeholderToRId[$placeholder];
            if (!isset($mediaFiles[$oldRId])) continue;
            if (!$newImagePath || !file_exists($newImagePath)) continue;

            $ext          = strtolower(pathinfo($newImagePath, PATHINFO_EXTENSION));
            $newRId       = 'rId' . $rIdCounter++;
            $newMediaFile = 'media/img_cert_' . $rIdCounter . '.' . $ext;

            $escapedPlaceholder = preg_quote($placeholder, '/');
            $newDocumentXml = preg_replace_callback(
                '/<wp:(?:inline|anchor)[^>]*>.*?<\/wp:(?:inline|anchor)>/s',
                function ($match) use ($escapedPlaceholder, $oldRId, $newRId) {
                    $block = $match[0];
                    if (!preg_match('/(?:name|descr)="' . $escapedPlaceholder . '"/', $block)) return $block;
                    return preg_replace('/r:embed="' . preg_quote($oldRId, '/') . '"/', 'r:embed="' . $newRId . '"', $block, 1);
                },
                $newDocumentXml
            );

            $newRelsXml = str_replace(
                '</Relationships>',
                '<Relationship Id="' . $newRId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' . $newMediaFile . '"/>
</Relationships>',
                $newRelsXml
            );

            $zip->addFile($newImagePath, 'word/' . $newMediaFile);
        }

        $zip->addFromString('word/document.xml', $newDocumentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $newRelsXml);
        $zip->close();

        rename($tmpCopy, $docxPath);
        if (file_exists($tmpCopy)) @unlink($tmpCopy);
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

        $outDir = sys_get_temp_dir();

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

        $waited = 0;
        while (!file_exists($pdfPath) && $waited < 8) { sleep(1); $waited++; }
        if (!file_exists($pdfPath)) return null;

        $safePath = storage_path('app/temp_pdfs/' . $pdfName);
        if (!is_dir(storage_path('app/temp_pdfs'))) mkdir(storage_path('app/temp_pdfs'), 0755, true);
        copy($pdfPath, $safePath);

        return file_exists($safePath) ? $safePath : null;
    }
}
