<?php

/** Shared, table-based layout for every SmartServe email. Values are plain text. */
final class SmartServeEmailTemplate {
    public const APP_URL = 'https://dlsua-smartserve.site';

    public static function escape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function localHost($host) {
        return in_array($host, ['localhost', '0.0.0.0', '::1', '[::1]'], true)
            || str_ends_with($host, '.localhost') || preg_match('/\A127\./', $host);
    }

    /** Preserve the destination route when upgrading an old development URL. */
    public static function actionUrl($url) {
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) return null;
        $parts = parse_url($url);
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return null;
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        if (!self::localHost($host) && !in_array($host, ['dlsua-smartserve.site', 'www.dlsua-smartserve.site'], true)) return $url;
        $path = $parts['path'] ?? '';
        if (self::localHost($host)) $path = preg_replace('~\A/smartserve(?=/|$)~i', '', $path);
        return self::APP_URL . $path
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    /** Refresh already-queued messages without resending messages marked as sent. */
    public static function deliveryBodies($html, $text) {
        // Earlier versions printed this fallback paragraph below the action button.
        $html = preg_replace('~<p\b[^>]*>\s*Or open:\s*<a\b[^>]*>.*?</a>\s*</p>~is', '', $html);
        $html = preg_replace_callback('~\bhref\s*=\s*([\'"])(.*?)\1~is', function ($match) {
            $url = self::actionUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $url === null ? $match[0] : 'href=' . $match[1] . self::escape($url) . $match[1];
        }, $html);
        $lines = preg_split('/\R/', $text);
        $lines = array_filter($lines, function ($line) {
            if (!preg_match('~^.+:\s*(https?://\S+)\s*$~i', $line, $match)) return true;
            $host = strtolower(parse_url($match[1], PHP_URL_HOST) ?? '');
            return !self::localHost($host) && !in_array($host, ['dlsua-smartserve.site', 'www.dlsua-smartserve.site'], true);
        });
        $text = trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)));
        return [$html, $text];
    }

    public static function render(array $message, $logo = 'cid:smartserve-school-logo') {
        $e = [self::class, 'escape'];
        $title = $e($message['title']);
        $preheader = $e($message['intro'] ?? $message['title']);
        $name = $e(trim($message['name'] ?? '') ?: 'there');
        $intro = nl2br($e($message['intro'] ?? ''));
        $logo = $e($logo);
        $content = '';
        if (!empty($message['highlight'])) {
            $content .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;border:2px dashed #e53950;border-radius:16px"><tr><td align="center" style="padding:26px 16px"><div style="color:#d82d44;font-size:13px;font-weight:bold;letter-spacing:2px;text-transform:uppercase">' . $e($message['highlight_label'] ?? 'Tracking number') . '</div><div style="margin-top:14px;color:#082342;font-size:30px;font-weight:bold;letter-spacing:2px;word-break:break-word">' . $e($message['highlight']) . '</div></td></tr></table>';
        }
        if (!empty($message['details'])) {
            $content .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;background:#f4f7fb;border:1px solid #e6edf5;border-radius:12px">';
            foreach ($message['details'] as $label => $value) {
                if ($value === null || $value === '') continue;
                $content .= '<tr><td width="32%" valign="top" style="padding:12px 16px;color:#536781;font-size:14px;border-bottom:1px solid #e6edf5">' . $e($label) . '</td><td valign="top" style="padding:12px 16px;color:#082342;font-size:14px;font-weight:bold;border-bottom:1px solid #e6edf5;word-break:break-word">' . nl2br($e($value)) . '</td></tr>';
            }
            $content .= '</table>';
        }
        foreach ($message['paragraphs'] ?? [] as $paragraph) {
            $content .= '<p style="margin:16px 0;color:#536781;font-size:15px;line-height:1.75">' . nl2br($e($paragraph)) . '</p>';
        }
        $url = self::actionUrl($message['action_url'] ?? '');
        if ($url !== null) {
            $content .= '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0"><tr><td bgcolor="#005141" style="border-radius:8px"><a href="' . $e($url) . '" style="display:inline-block;padding:15px 24px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none">' . $e($message['action_label'] ?? 'Open SmartServe') . '</a></td></tr></table>';
        }
        $year = date('Y');
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title></head><body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif"><div style="display:none;font-size:1px;max-height:0;overflow:hidden;color:#f3f6fb">' . $preheader . '</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f3f6fb"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="750" cellspacing="0" cellpadding="0" style="width:100%;max-width:750px;background:#ffffff;border-radius:20px;overflow:hidden">
<tr><td bgcolor="#005141" style="padding:36px 28px;border-radius:20px 20px 0 0"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td width="88" valign="middle"><img src="' . $logo . '" width="72" height="72" alt="De La Salle Araneta University seal" style="display:block;border:0;background:#ffffff;border-radius:50%"></td><td valign="middle" style="color:#ffffff"><div style="font-size:11px;letter-spacing:2px;line-height:1.5">WELCOME TO</div><div style="font-size:27px;font-weight:bold;line-height:1.4;margin:8px 0">SmartServe</div><div style="font-size:12px;line-height:1.6">De La Salle Araneta University</div></td></tr></table></td></tr>
<tr><td style="padding:36px 28px"><h1 style="margin:0 0 24px;color:#082342;font-size:24px;line-height:1.4">' . $title . '</h1><p style="margin:0 0 8px;color:#536781;font-size:15px;line-height:1.75">Hello <strong>' . $name . '</strong>,</p><p style="margin:0;color:#536781;font-size:15px;line-height:1.75">' . $intro . '</p>' . $content . '</td></tr>
<tr><td align="center" bgcolor="#f8fafc" style="padding:28px;border-top:1px solid #e6edf5;border-radius:0 0 20px 20px"><img src="' . $logo . '" width="44" height="44" alt="DLSAU seal" style="display:block;margin:0 auto 12px;border:0"><div style="color:#082342;font-size:15px;font-weight:bold">SmartServe</div><div style="margin-top:6px;color:#536781;font-size:13px;line-height:1.6">De La Salle Araneta University</div><p style="margin:16px 0 0;color:#7b8a9e;font-size:12px;line-height:1.7">This is an automated notification. Please do not reply.<br>&copy; ' . $year . ' SmartServe. All rights reserved.</p></td></tr>
</table></td></tr></table></body></html>';
    }

    public static function text(array $message) {
        $lines = ['WELCOME TO SmartServe', 'De La Salle Araneta University', '', $message['title'], '', 'Hello ' . (trim($message['name'] ?? '') ?: 'there') . ',', $message['intro'] ?? ''];
        if (!empty($message['highlight'])) $lines[] = ($message['highlight_label'] ?? 'Tracking number') . ': ' . $message['highlight'];
        foreach ($message['details'] ?? [] as $label => $value) {
            if ($value !== null && $value !== '') $lines[] = $label . ': ' . $value;
        }
        foreach ($message['paragraphs'] ?? [] as $paragraph) $lines[] = $paragraph;
        $lines[] = "\nSmartServe | De La Salle Araneta University\nThis is an automated notification. Please do not reply.";
        return implode("\n\n", $lines);
    }
}
