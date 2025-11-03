<?php

namespace Phaseolies\Error;

class Highlighter
{
    public static function make($code)
    {
        $needsWrapping = !str_contains($code, '<?php');

        $codeToHighlight = $needsWrapping ? "<?php\n" . $code : $code;

        try {
            $tokens = @token_get_all($codeToHighlight);
        } catch (\Throwable $e) {
            return htmlspecialchars($code);
        }

        $output = '';
        $skipFirst = $needsWrapping;

        foreach ($tokens as $token) {

            if (is_array($token)) {
                [$id, $text] = $token;

                if ($skipFirst && $id === T_OPEN_TAG) {
                    $skipFirst = false;
                    continue;
                }

                $class = match ($id) {
                    T_OPEN_TAG, T_CLOSE_TAG => 'text-hl-tag',
                    T_VARIABLE => 'text-hl-variable',
                    T_STRING => 'text-hl-string',
                    T_FUNCTION, T_CLASS, T_TRAIT, T_INTERFACE => 'text-hl-definition',
                    T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC, T_FINAL, T_ABSTRACT => 'text-hl-modifier',
                    T_RETURN, T_IF, T_ELSE, T_FOREACH, T_FOR, T_WHILE, T_SWITCH, T_CASE => 'text-hl-keyword',
                    T_NEW, T_INSTANCEOF => 'text-hl-keyword',
                    T_CONSTANT_ENCAPSED_STRING => 'text-hl-literal',
                    T_COMMENT, T_DOC_COMMENT => 'text-hl-comment',
                    T_LNUMBER, T_DNUMBER => 'text-hl-number',
                    default => 'text-hl-default',
                };

                $output .= "<span class='{$class}'>" . htmlspecialchars($text) . "</span>";
            } else {
                $output .= htmlspecialchars($token);
            }
        }

        return $output;
    }
}