<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Servicio completado</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#15803d,#16a34a);padding:32px 36px;text-align:center;">
            <p style="margin:0 0 8px;font-size:13px;color:rgba(255,255,255,.8);font-weight:600;letter-spacing:.5px;text-transform:uppercase;">e-Service</p>
            <div style="width:64px;height:64px;background:rgba(255,255,255,.2);border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:30px;line-height:64px;">✓</div>
            <h1 style="margin:0;font-size:26px;font-weight:900;color:#ffffff;letter-spacing:-.5px;">¡Servicio completado!</h1>
            <p style="margin:10px 0 0;font-size:14px;color:rgba(255,255,255,.85);">
              @if($recipientType === 'professional')
                Gracias por tu trabajo profesional
              @else
                Esperamos que estés satisfecho con el servicio
              @endif
            </p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px 36px;">

            @if($recipientType === 'professional')
              {{-- Email para el profesional --}}
              <p style="margin:0 0 24px;font-size:15px;color:#334155;">
                Hola <strong>{{ $serviceRequest->professional->user->name ?? 'Profesional' }}</strong>, 👋
              </p>
              <p style="margin:0 0 24px;font-size:14px;color:#475569;line-height:1.6;">
                El servicio para <strong>{{ $serviceRequest->client->name ?? 'el cliente' }}</strong> ha sido
                marcado como completado exitosamente. Tu ganancia será procesada próximamente.
              </p>
            @else
              {{-- Email para el cliente --}}
              <p style="margin:0 0 24px;font-size:15px;color:#334155;">
                Hola <strong>{{ $serviceRequest->client->name ?? 'Cliente' }}</strong>, 👋
              </p>
              <p style="margin:0 0 24px;font-size:14px;color:#475569;line-height:1.6;">
                Tu servicio ha sido completado y aprobado. Gracias por confiar en
                <strong>{{ $serviceRequest->professional->user->name ?? 'nuestro profesional' }}</strong>.
                Esperamos haberte ayudado de la mejor manera.
              </p>
            @endif

            <!-- Resumen financiero -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border-radius:12px;border:1px solid #86efac;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 22px;">
                  <p style="margin:0 0 14px;font-size:11px;font-weight:800;color:#15803d;text-transform:uppercase;letter-spacing:.6px;">Resumen del servicio</p>

                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#166534;font-weight:600;">Servicio</td>
                      <td style="padding:6px 0;font-size:13px;color:#14532d;font-weight:700;text-align:right;">{{ $serviceRequest->service->name ?? '—' }}</td>
                    </tr>
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#166534;font-weight:600;">Fecha</td>
                      <td style="padding:6px 0;font-size:13px;color:#14532d;font-weight:700;text-align:right;">{{ $serviceRequest->service_date }}</td>
                    </tr>
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#166534;font-weight:600;">Completado</td>
                      <td style="padding:6px 0;font-size:13px;color:#14532d;font-weight:700;text-align:right;">{{ now()->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="padding-top:12px;border-top:1px solid #86efac;"></td>
                    </tr>
                    <tr>
                      <td style="padding:6px 0;font-size:14px;color:#14532d;font-weight:700;">Total del servicio</td>
                      <td style="padding:6px 0;font-size:20px;color:#15803d;font-weight:900;text-align:right;">${{ number_format($serviceRequest->budget, 0, ',', '.') }}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            @if($recipientType === 'client')
            <p style="margin:0 0 24px;font-size:13px;color:#475569;line-height:1.6;">
              ¿Quedaste satisfecho con el servicio? Tu opinión nos ayuda a mejorar la plataforma.
            </p>
            @endif

            <p style="margin:0;font-size:13px;color:#94a3b8;line-height:1.6;">
              Puedes ver el historial completo de tus servicios en la plataforma.<br>
              Gracias por usar e-Service.
            </p>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 36px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#94a3b8;">© {{ date('Y') }} e-Service · Este correo fue enviado automáticamente.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
