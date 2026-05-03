<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\WorkEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkEvidenceController extends Controller
{
    // Listar evidencias de una solicitud
    public function index($requestId)
    {
        $evidences = WorkEvidence::where('service_request_id', $requestId)->get();

        return response()->json($evidences->map(function ($e) {
            return [
                'id'        => $e->id,
                'file_url'  => Storage::url($e->file_path),
                'file_type' => $e->file_type,
                'note'      => $e->note,
                'created_at'=> $e->created_at->format('d/m/Y H:i'),
            ];
        }));
    }

    // Subir evidencia
    public function store(Request $request, $requestId)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,mp4,pdf|max:20480',
            'note' => 'nullable|string|max:500',
        ]);

        $professional = $request->user()->professional;

        // Verificar que la solicitud le pertenece
        $serviceRequest = ServiceRequest::where('id', $requestId)
            ->where('professional_id', $professional->id)
            ->where('status', 'accepted')
            ->firstOrFail();

        $file     = $request->file('file');
        $mime     = $file->getMimeType();
        $fileType = str_starts_with($mime, 'image') ? 'image'
                  : (str_starts_with($mime, 'video') ? 'video' : 'pdf');

        $path = $file->store("evidences/{$professional->id}", 'public');

        $evidence = WorkEvidence::create([
            'service_request_id' => $serviceRequest->id,
            'professional_id'    => $professional->id,
            'file_path'          => $path,
            'file_type'          => $fileType,
            'note'               => $request->note,
        ]);

        return response()->json([
            'message'  => 'Evidencia subida correctamente',
            'file_url' => Storage::url($evidence->file_path),
            'id'       => $evidence->id,
        ], 201);
    }

    // Marcar trabajo como completado
    public function complete(Request $request, $requestId)
    {
        $professional   = $request->user()->professional;
        $serviceRequest = ServiceRequest::where('id', $requestId)
            ->where('professional_id', $professional->id)
            ->where('status', 'accepted')
            ->firstOrFail();

        $serviceRequest->update(['status' => 'completed']);

        return response()->json(['message' => 'Trabajo marcado como completado']);
    }
}
