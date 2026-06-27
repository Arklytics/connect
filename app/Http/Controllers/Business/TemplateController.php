<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = DB::table('gd_whatsapp_templates')
            ->where('biz_id', $request->session()->get('biz_id'))
            ->orderByDesc('id')
            ->get();

        return view('business.templates.index', compact('templates'));
    }

    public function create()
    {
        return view('business.templates.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'template_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:MARKETING,UTILITY,AUTHENTICATION'],
            'language' => ['nullable', 'string', 'max:20'],
            'header_type' => ['nullable', 'in:NONE,TEXT,IMAGE,VIDEO,DOCUMENT'],
            'header_text' => ['nullable', 'string', 'max:255'],
            'header_sample' => ['nullable', 'string', 'max:255'],
            'header_media_handle' => ['nullable', 'string', 'max:255'],
            'header_media_url' => ['nullable', 'url'],
            'header_media_file' => ['nullable', 'file', 'max:20480'],
            'body_text' => ['required', 'string'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'buttons' => ['nullable', 'array'],
            'body_samples' => ['nullable', 'array'],
        ]);

        $bizId = (int) $request->session()->get('biz_id');
        $business = DB::table('gd_orders')
            ->where('id', $bizId)
            ->select('whatsapp_id', 'auth_token')
            ->first();
        $business = $business ?: (object) [];

        $accessToken = trim((string) ($business->auth_token ?? ''));
        if ($accessToken === '') {
            $accessToken = trim((string) (DB::table('gd_app_settings')
                ->where('admin_id', 0)
                ->where('setting_key', 'META_ACCESS_TOKEN')
                ->value('setting_value') ?: ''));
        }

        $whatsappBusinessId = trim((string) ($business->whatsapp_id ?? ''));
        $appId = trim((string) (DB::table('gd_app_settings')
            ->where('admin_id', 0)
            ->where('setting_key', 'META_APP_ID')
            ->value('setting_value') ?: \Config::get('META_APP_ID', '')));

        $headerType = strtoupper(trim((string) ($data['header_type'] ?? 'NONE')));
        $headerText = trim((string) ($data['header_text'] ?? ''));
        $headerSample = trim((string) ($data['header_sample'] ?? ''));
        $headerMediaHandle = trim((string) ($data['header_media_handle'] ?? ''));
        $headerMediaUrl = trim((string) ($data['header_media_url'] ?? ''));
        $bodyText = trim((string) ($data['body_text'] ?? ''));
        $footerText = trim((string) ($data['footer_text'] ?? ''));
        $bodySamples = is_array($data['body_samples'] ?? null) ? $data['body_samples'] : [];
        $buttonsInput = is_array($data['buttons'] ?? null) ? $data['buttons'] : [];
        $components = [];
        $buttons = [];
        $mediaUrl = $headerMediaUrl;

        if ($request->hasFile('header_media_file')) {
            $mediaFile = $request->file('header_media_file');
            $mediaType = (string) ($mediaFile?->getMimeType() ?? '');
            $allowedTypes = [
                'image/jpeg',
                'image/png',
                'video/mp4',
                'video/3gpp',
                'application/pdf',
            ];

            if ($appId === '' || $accessToken === '') {
                return back()->withInput()->with('error', 'Meta App ID or access token is missing. Add API credentials first.');
            }

            if (!in_array($mediaType, $allowedTypes, true)) {
                return back()->withInput()->with('error', 'Unsupported file type. Use JPG, PNG, MP4, 3GP, or PDF.');
            }

            $mediaDir = public_path('business-template-media');
            if (!is_dir($mediaDir)) {
                mkdir($mediaDir, 0775, true);
            }

            $fileName = Str::uuid() . '.' . $mediaFile->getClientOriginalExtension();
            $mediaFile->move($mediaDir, $fileName);
            $localPath = $mediaDir . DIRECTORY_SEPARATOR . $fileName;
            $mediaUrl = asset('business-template-media/' . $fileName);

            $uploadResult = \ApiSupport::metaUploadMediaHandle(
                $appId,
                $accessToken,
                $localPath,
                (string) $mediaFile->getClientOriginalName(),
                $mediaType,
                (int) $mediaFile->getSize()
            );

            if (!($uploadResult['ok'] ?? false)) {
                return back()->withInput()->with('error', 'Media saved locally, but handle generation failed: ' . (string) ($uploadResult['error'] ?? 'Unknown error.'));
            }

            $headerMediaHandle = (string) ($uploadResult['handle'] ?? '');
        }

        if ($headerType === 'TEXT' && $headerText !== '') {
            $header = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $headerText,
            ];

            if ($this->templatePlaceholderNumbers($headerText) !== []) {
                if ($headerSample === '') {
                    return back()->withInput()->with('error', 'Header variable example is required.');
                }

                $header['example'] = ['header_text' => [$headerSample]];
            }

            $components[] = $header;
        } elseif (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            if ($headerMediaHandle === '') {
                return back()->withInput()->with('error', ucfirst(strtolower($headerType)) . ' header requires a WhatsApp media handle for template review.');
            }

            $components[] = [
                'type' => 'HEADER',
                'format' => $headerType,
                'example' => [
                    'header_handle' => [$headerMediaHandle],
                ],
            ];
        }

        $body = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];

        $bodyVariableNumbers = $this->templatePlaceholderNumbers($bodyText);
        if (!empty($bodyVariableNumbers)) {
            $sampleRow = [];
            foreach ($bodyVariableNumbers as $number) {
                $sampleRow[] = trim((string) ($bodySamples[$number] ?? $bodySamples[(string) $number] ?? ''));
            }

            if (in_array('', $sampleRow, true)) {
                return back()->withInput()->with('error', 'Every body variable needs an example value.');
            }

            $body['example'] = ['body_text' => [$sampleRow]];
        }

        $components[] = $body;

        if ($footerText !== '') {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $footerText,
            ];
        }

        foreach ($buttonsInput as $button) {
            if (!is_array($button)) {
                continue;
            }

            $buttonType = strtoupper(trim((string) ($button['type'] ?? '')));
            $buttonText = trim((string) ($button['text'] ?? ''));
            $buttonValue = trim((string) ($button['value'] ?? ''));

            if ($buttonType === '' || $buttonText === '') {
                continue;
            }

            if ($buttonType === 'URL' && $buttonValue !== '') {
                $buttons[] = [
                    'type' => 'URL',
                    'text' => $buttonText,
                    'url' => $buttonValue,
                ];
            } elseif ($buttonType === 'PHONE_NUMBER' && $buttonValue !== '') {
                $buttons[] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => $buttonText,
                    'phone_number' => $buttonValue,
                ];
            } elseif ($buttonType === 'QUICK_REPLY') {
                $buttons[] = [
                    'type' => 'QUICK_REPLY',
                    'text' => $buttonText,
                ];
            }
        }

        if (!empty($buttons)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }

        if ($whatsappBusinessId === '' || $accessToken === '') {
            return back()->withInput()->with('error', 'WhatsApp Business ID or access token is missing. Add API credentials first.');
        }

        $payload = [
            'name' => $this->normalizeTemplateName((string) $data['template_name']),
            'category' => strtoupper(trim((string) ($data['category'] ?? 'MARKETING'))),
            'language' => trim((string) ($data['language'] ?? 'en_US')) ?: 'en_US',
            'components' => $components,
        ];

        $ch = curl_init("https://graph.facebook.com/v18.0/{$whatsappBusinessId}/message_templates");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                'Content-Type: application/json',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $apiResponse = json_decode((string) $response, true);
        if ($curlError !== '') {
            return back()->withInput()->with('error', 'WhatsApp API request failed: ' . $curlError);
        }

        if ($httpStatus < 200 || $httpStatus >= 300 || isset($apiResponse['error'])) {
            $apiError = $apiResponse['error']['message'] ?? 'Unexpected WhatsApp API error.';
            return back()->withInput()->with('error', 'WhatsApp API rejected the template: ' . $apiError);
        }

        $templateId = (string) ($apiResponse['id'] ?? '');
        $status = (string) ($apiResponse['status'] ?? 'PENDING');
        $apiCategory = (string) ($apiResponse['category'] ?? ($payload['category'] ?? 'MARKETING'));
        $buttonsJson = json_encode($buttons, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $placeholdersJson = json_encode([
            'header_type' => $headerType,
            'header_text' => $headerText,
            'header_sample' => $headerSample,
            'header_media_handle' => $headerMediaHandle,
            'header_media_url' => $mediaUrl,
            'body_samples' => $bodySamples,
            'body_placeholder_numbers' => $bodyVariableNumbers,
            'buttons' => $buttons,
            'payload' => $payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        DB::table('gd_whatsapp_templates')->insert([
            'biz_id' => $bizId,
            'template_id' => $templateId,
            'template_name' => $this->normalizeTemplateName((string) $data['template_name']),
            'message_title' => $headerType === 'TEXT' ? $headerText : ($headerType !== 'NONE' ? ucfirst(strtolower($headerType)) . ' Header' : 'Template'),
            'message_body' => $bodyText,
            'placeholders' => $placeholdersJson,
            'subtitle' => $footerText,
            'media_url' => $mediaUrl,
            'status' => $status,
            'category' => $apiCategory,
            'buttons' => $buttonsJson,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('business.templates.index')->with('success', 'Template created on WhatsApp Cloud API and saved locally.');
    }

    public function fetch(Request $request, int $template)
    {
        $template = DB::table('gd_whatsapp_templates')
            ->where('id', $template)
            ->where('biz_id', $request->session()->get('biz_id'))
            ->first();

        abort_if(!$template, 404);

        return response()->json([
            'message_title' => $template->message_title,
            'message_body' => $template->message_body,
            'subtitle' => $template->subtitle,
            'media_url' => $template->media_url,
            'buttons' => json_decode($template->buttons ?: '[]', true),
        ]);
    }

    private function normalizeTemplateName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';

        return trim($name, '_');
    }

    private function templatePlaceholderNumbers(string $text): array
    {
        preg_match_all('/{{\s*(\d+)\s*}}/', $text, $matches);
        $numbers = array_map('intval', $matches[1] ?? []);
        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        return $numbers;
    }
}
