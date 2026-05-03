<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Solicitud aceptada</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#1e40af,#2563eb);padding:32px 36px;text-align:center;">
            <p style="margin:0 0 8px;font-size:13px;color:rgba(255,255,255,.8);font-weight:600;letter-spacing:.5px;text-transform:uppercase;">e-Service</p>
            <h1 style="margin:0;font-size:26px;font-weight:900;color:#ffffff;letter-spacing:-.5px;">¡Tu solicitud fue aceptada!</h1>
            <p style="margin:10px 0 0;font-size:14px;color:rgba(255,255,255,.85);">Un profesional tomará tu caso</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px 36px;">

            <p style="margin:0 0 24px;font-size:15px;color:#334155;">
              Hola <strong>{{ $serviceRequest->client->name ?? 'Cliente' }}</strong>, 👋
            </p>

            <p style="margin:0 0 24px;font-size:14px;color:#475569;line-height:1.6;">
              Buenas noticias. El profesional <strong>{{ $serviceRequest->professional->user->name ?? 'Profesional' }}</strong>
              ha aceptado tu solicitud y pronto se pondrá en contacto contigo para coordinar los detalles.
            </p>

            <!-- Detalle del servicio -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 22px;">
                  <p style="margin:0 0 14px;font-size:11px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;">Detalle del servicio</p>

                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#64748b;font-weight:600;">Servicio</td>
                      <td style="padding:6px 0;font-size:13px;color:#0f172a;font-weight:700;text-align:right;">{{ $serviceRequest->service->name ?? '—' }}</td>
                    </tr>
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#64748b;font-weight:600;">Profesional</td>
                      <td style="padding:6px 0;font-size:13px;color:#0f172a;font-weight:700;text-align:right;">{{ $serviceRequest->professional->user->name ?? '—' }}</td>
                    </tr>
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#64748b;font-weight:600;">Fecha</td>
                      <td style="padding:6px 0;font-size:13px;color:#0f172a;font-weight:700;text-align:right;">{{ $serviceRequest->service_date }} {{ $serviceRequest->service_time }}</td>
                    </tr>
                    @if($serviceRequest->address)
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#64748b;font-weight:600;">Dirección</td>
                      <td style="padding:6px 0;font-size:13px;color:#0f172a;font-weight:700;text-align:right;">{{ $serviceRequest->address }}</td>
                    </tr>
                    @endif
                    <tr>
                      <td colspan="2" style="padding-top:12px;border-top:1px solid #e2e8f0;"></td>
                    </tr>
                    <tr>
                      <td style="padding:6px 0;font-size:14px;color:#0f172a;font-weight:700;">Total</td>
                      <td style="padding:6px 0;font-size:18px;color:#2563eb;font-weight:900;text-align:right;">${{ number_format($serviceRequest->budget, 0, ',', '.') }}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <p style="margin:0;font-size:13px;color:#94a3b8;line-height:1.6;">
              Puedes hacer seguimiento de tu solicitud en la plataforma en cualquier momento.<br>
              Si tienes preguntas, contáctanos respondiendo este correo.
            </p>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 36px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#94a3b8;">© {{ date('Y') }} e-Service · Este correo fue enviado automáticamente, por favor no respondas directamente.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
