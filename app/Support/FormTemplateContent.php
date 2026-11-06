<?php

namespace App\Support;

use App\Services\InsuranceForms;
use Illuminate\Validation\ValidationException;

class FormTemplateContent
{
    private const PREFIXES = ['{"editor":"document-v1","ops":', '{"editor":"document-v2","ops":'];
    public const MAX_BYTES = 3000000;
    private const FORMATS = [
        'bold'=>[true], 'italic'=>[true], 'underline'=>[true], 'strike'=>[true],
        'font'=>['serif','monospace'], 'size'=>['small','large','huge','8pt','9pt','10pt','11pt','12pt','14pt','16pt','18pt','20pt','24pt','28pt','32pt','36pt'],
        'header'=>[1,2,3], 'list'=>['ordered','bullet'], 'align'=>['left','right','center','justify'],
    ];

    public static function isFormatted(string $body): bool
    {
        foreach (self::PREFIXES as $prefix) if (str_starts_with($body,$prefix)) return true;
        return false;
    }

    public static function isDocument(string $body): bool { return str_starts_with($body,self::PREFIXES[1]); }

    private static function invalid(string $message = 'Il documento contiene una formattazione non supportata.'): never
    {
        throw ValidationException::withMessages(['body'=>$message]);
    }

    public static function defaultLayout(): array
    {
        return ['paper'=>'a4','orientation'=>'portrait','margin_top'=>12,'margin_bottom'=>15,'margin_left'=>15,'margin_right'=>15,
            'repeat_header'=>false,'repeat_footer'=>true,'header_space'=>25,'footer_space'=>12];
    }

    public static function encode(string $delta): string
    {
        $document = self::decode($delta);
        if (!self::hasContent($document['ops'])) self::invalid('Scrivi il testo del modello o inserisci un’immagine.');
        $data = ['editor'=>isset($document['layout']) ? 'document-v2' : 'document-v1'] + $document;
        return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    private static function decode(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) self::invalid('Il modello è troppo grande. Riduci il testo o le immagini.');
        $data = json_decode($json,true,16);
        if (!is_array($data) || array_diff(array_keys($data),['editor','ops','header','footer','layout'])
            || (isset($data['editor']) && !in_array($data['editor'],['document-v1','document-v2'],true))) self::invalid();
        $document = ['ops'=>self::validateOps($data['ops'] ?? null)];
        if (array_key_exists('header',$data) || array_key_exists('footer',$data) || array_key_exists('layout',$data) || ($data['editor'] ?? null)==='document-v2') {
            $document['header'] = self::validateOps($data['header'] ?? [],false);
            $document['footer'] = self::validateOps($data['footer'] ?? [],false);
            $document['layout'] = self::validateLayout($data['layout'] ?? []);
        }
        $length = 0;
        foreach (['ops','header','footer'] as $section) $length += mb_strlen(self::text($document[$section] ?? []));
        if ($length > 50001) self::invalid('Riduci il testo complessivo del modello entro 50.000 caratteri.');
        return $document;
    }

    private static function validateLayout(mixed $layout): array
    {
        $defaults = self::defaultLayout();
        if (!is_array($layout) || array_diff(array_keys($layout),array_keys($defaults))) self::invalid();
        $layout += $defaults;
        if (!in_array($layout['paper'],['a4','letter'],true) || !in_array($layout['orientation'],['portrait','landscape'],true)) self::invalid();
        foreach (['margin_top','margin_bottom','margin_left','margin_right','header_space','footer_space'] as $key) {
            $maximum = str_ends_with($key,'space') ? 60 : 40;
            if (!is_int($layout[$key]) || $layout[$key] < 5 || $layout[$key] > $maximum) self::invalid('Usa margini tra 5 e 40 mm e spazi di intestazione e piè di pagina tra 5 e 60 mm.');
        }
        if (!is_bool($layout['repeat_header']) || !is_bool($layout['repeat_footer'])) self::invalid();
        $height = $layout['orientation']==='landscape' ? 210 : 279;
        $occupied = $layout['margin_top'] + $layout['margin_bottom'] + ($layout['repeat_header'] ? $layout['header_space'] : 0) + ($layout['repeat_footer'] ? $layout['footer_space'] : 0);
        if ($height-$occupied < 70) self::invalid('Lascia almeno 70 mm per il testo della pagina.');
        return $layout;
    }

    private static function validateOps(mixed $ops, bool $pageBreaks = true): array
    {
        if (!is_array($ops) || !array_is_list($ops) || count($ops)>5000) self::invalid();
        $images = $breaks = 0;
        foreach ($ops as $op) {
            if (!is_array($op) || !array_key_exists('insert',$op) || array_diff(array_keys($op),['insert','attributes'])) self::invalid();
            $insert = $op['insert'];
            if (!is_string($insert)) {
                if (!is_array($insert) || count($insert)!==1) self::invalid();
                if (array_key_exists('field',$insert)) {
                    if (!in_array($insert['field'],InsuranceForms::TOKENS,true)) self::invalid();
                } elseif (array_key_exists('image',$insert)) {
                    if (++$images>20) self::invalid('Usa al massimo 20 immagini per ciascuna parte del documento.');
                    self::validateImage($insert['image']);
                } elseif (!$pageBreaks || !isset($insert['page_break']) || $insert['page_break']!==true) self::invalid();
                elseif (++$breaks>40) self::invalid('Riduci le interruzioni di pagina entro 40.');
            }
            $attributes = $op['attributes'] ?? [];
            if (!is_array($attributes)) self::invalid();
            foreach ($attributes as $key=>$value) {
                $valid = match ($key) {
                    'color','background'=>is_string($value) && preg_match('/^#[0-9a-f]{6}$/iD',$value),
                    'table'=>is_string($value) && preg_match('/^[a-z0-9_-]{1,40}$/iD',$value),
                    default=>in_array($value,self::FORMATS[$key] ?? [],true),
                };
                if (!$valid) self::invalid();
            }
            if (is_array($insert) && isset($insert['page_break']) && $attributes) self::invalid();
        }
        $text = self::text($ops);
        if (substr_count($text,"\n")>2000) self::invalid('Riduci il modello entro 2.000 righe.');
        preg_match_all('/\{\{[^{}]*\}\}/u',$text,$whole);
        $inline = [];
        foreach ($ops as $op) {
            preg_match_all('/\{\{[^{}]*\}\}/u',self::text([$op]),$part);
            array_push($inline,...$part[0]);
        }
        if ($whole[0]!==$inline) self::invalid('Un campo automatico è diviso tra formati diversi. Reinseriscilo con «Inserisci campo».');
        return $ops;
    }

    private static function validateImage(mixed $image): void
    {
        if (!is_array($image) || array_diff(array_keys($image),['data','alt','width','align'])
            || !is_string($image['data'] ?? null) || strlen($image['data'])>700000
            || !preg_match('~^data:image/(png|jpeg);base64,([A-Za-z0-9+/]*={0,2})$~D',$image['data'],$parts)) self::invalid('Usa un’immagine PNG o JPEG caricata dal computer.');
        $bytes = base64_decode($parts[2],true);
        if ($bytes===false || strlen($bytes)>512000) self::invalid('Riduci l’immagine entro 500 KB.');
        $size = @getimagesizefromstring($bytes);
        /** Sono ammessi soltanto raster verificati e incorporati nel modello, senza URL o letture di file esterni. */
        if (!$size || $size[0]<1 || $size[1]<1 || $size[0]>1600 || $size[1]>1600 || ($size['mime'] ?? null)!=='image/'.$parts[1]) self::invalid('L’immagine non è valida o supera 1600 × 1600 pixel.');
        if (!is_string($image['alt'] ?? null) || trim($image['alt'])==='' || mb_strlen($image['alt'])>160
            || !in_array($image['width'] ?? null,[25,50,75,100],true) || !in_array($image['align'] ?? null,['left','center','right'],true)) self::invalid('Indica descrizione, dimensione e allineamento dell’immagine.');
    }

    private static function text(array $ops): string
    {
        $text = '';
        foreach ($ops as $op) {
            $insert = $op['insert'];
            if (is_string($insert)) $text .= $insert;
            elseif (isset($insert['field'])) $text .= '{{'.$insert['field'].'}}';
            elseif (isset($insert['image'])) $text .= '[Immagine: '.$insert['image']['alt'].']';
            else $text .= "\n";
        }
        return $text;
    }

    private static function hasContent(array $ops): bool { return trim(self::text($ops))!==''; }

    public static function plain(string $body): string
    {
        return self::isFormatted($body) ? preg_replace('/\n\z/','',self::text(self::decode($body)['ops'])) : $body;
    }

    public static function allText(string $body): string
    {
        if (!self::isDocument($body)) return self::plain($body);
        $document = self::decode($body);
        return implode("\n",array_map(fn($section)=>self::text($document[$section]),['header','ops','footer']));
    }

    public static function editorOps(string $body): array
    {
        if (self::isFormatted($body)) return self::decode($body)['ops'];
        $ops = [];
        foreach (preg_split('/(\{\{[a-z_]+\}\})/u',str_replace(["\r\n","\r"],"\n",$body),-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY) as $part) {
            $field = str_starts_with($part,'{{') ? substr($part,2,-2) : null;
            $ops[] = ['insert'=>in_array($field,InsuranceForms::TOKENS,true) ? ['field'=>$field] : $part];
        }
        $ops[] = ['insert'=>"\n"];
        return $ops;
    }

    public static function editorDocument(string $body): array
    {
        if (self::isDocument($body)) return self::decode($body);
        return ['ops'=>self::editorOps($body),'header'=>[
            ['insert'=>"Soluzioni Assicurative SAS\n"],['insert'=>['field'=>'titolo_modello']],['insert'=>"\n",'attributes'=>['header'=>1]],
        ],'footer'=>[['insert'=>['field'=>'riferimento_documento']],['insert'=>"\n"]],'layout'=>self::defaultLayout()];
    }

    public static function layout(string $body): array { return self::isDocument($body) ? self::decode($body)['layout'] : self::defaultLayout(); }

    private static function inline(string $text, array $formats): string
    {
        $style = [];
        if (isset($formats['color'])) $style[] = 'color:'.$formats['color'];
        if (isset($formats['size'])) $style[] = 'font-size:'.(['small'=>'8pt','large'=>'14pt','huge'=>'20pt'][$formats['size']] ?? $formats['size']);
        if (isset($formats['background'])) $style[] = 'background-color:'.$formats['background'];
        if (isset($formats['font'])) $style[] = 'font-family:'.['serif'=>'DejaVu Serif,serif','monospace'=>'DejaVu Sans Mono,monospace'][$formats['font']];
        if ($style) $text = '<span style="'.implode(';',$style).'">'.$text.'</span>';
        foreach (['bold'=>'strong','italic'=>'em','underline'=>'u','strike'=>'s'] as $format=>$tag) {
            if (!empty($formats[$format])) $text = '<'.$tag.'>'.$text.'</'.$tag.'>';
        }
        return $text;
    }

    /** Il PDF e le anteprime condividono solo markup costruito da formati ammessi. Testo e dati anagrafici vengono sempre escapati. */
    public static function html(string $body, array $tokens = [], string $section = 'ops'): string
    {
        if (!self::isFormatted($body)) {
            if ($section !== 'ops') return '';
            $paragraphs = preg_split('/\n\s*\n/u', strtr($body,$tokens));
            return implode('',array_map(fn ($p) => '<p>'.e($p).'</p>', $paragraphs));
        }
        $ops = self::decode($body)[$section] ?? [];
        if ($section !== 'ops' && !self::hasContent($ops)) return '';
        $html = $line = $group = $row = '';
        $finishGroup = function () use (&$html,&$group,&$row) {
            if ($group === 'table') $html .= '</tr></tbody></table>';
            elseif ($group !== '') $html .= '</'.$group.'>';
            $group = $row = '';
        };
        $finishLine = function (array $attrs) use (&$html,&$line,&$group,&$row,$finishGroup) {
            $tableRow = $attrs['table'] ?? null;
            $next = $tableRow !== null ? 'table' : (isset($attrs['list']) ? ($attrs['list'] === 'ordered' ? 'ol' : 'ul') : '');
            if ($next !== $group) {
                $finishGroup();
                $group = $next;
                if ($group === 'table') { $html .= '<table><tbody><tr>'; $row = $tableRow; }
                elseif ($group !== '') $html .= '<'.$group.'>';
            }
            if ($group === 'table' && $row !== $tableRow) { $html .= '</tr><tr>'; $row = $tableRow; }
            $tag = $group === 'table' ? 'td' : ($group !== '' ? 'li' : (isset($attrs['header']) ? 'h'.$attrs['header'] : 'p'));
            $align = isset($attrs['align']) ? ' style="text-align:'.$attrs['align'].'"' : '';
            $html .= '<'.$tag.$align.'>'.($line === '' ? '<br>' : $line).'</'.$tag.'>';
            $line = '';
        };
        foreach ($ops as $op) {
            $attrs = $op['attributes'] ?? [];
            $insert = $op['insert'];
            if (is_array($insert) && isset($insert['image'])) {
                if ($line !== '') $finishLine([]);
                $finishGroup();
                $image = $insert['image'];
                $margin = ['left'=>'0 auto 0 0','center'=>'0 auto','right'=>'0 0 0 auto'][$image['align']];
                $html .= '<div class="form-image" style="width:'.$image['width'].'%;margin:'.$margin.'"><img src="'.e($image['data']).'" alt="'.e($image['alt']).'" style="display:block;max-width:100%;width:100%;margin:'.$margin.'"></div>';
                continue;
            }
            if (is_array($insert) && isset($insert['page_break'])) {
                if ($line !== '') $finishLine([]);
                $finishGroup();
                $html .= '<div class="form-page-break"></div>';
                continue;
            }
            if (is_array($insert)) $insert = '{{'.$insert['field'].'}}';
            $pieces = explode("\n",$insert);
            foreach ($pieces as $index=>$piece) {
                if ($index > 0) $finishLine($attrs);
                if ($piece !== '') $line .= self::inline(e(strtr($piece,$tokens)), $attrs);
            }
        }
        if ($line !== '') $finishLine([]);
        $finishGroup();
        return $html;
    }
}
