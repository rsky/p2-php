<?php

class ImageCache2_Normalizer
{
    /**
     * 検索用に文字列を正規化する
     */
    public static function normalize($str, $enc, $to_lower = true)
    {
        if (!$enc) {
            $enc = mb_detect_encoding($str, 'CP932,UTF-8,CP51932,JIS');
        }
        if (strcasecmp($enc, 'UTF-8') !== 0) {
            $str = mb_convert_encoding($str, 'UTF-8', $enc);
        }

        if (extension_loaded('intl')) {
            if (!Normalizer::isNormalized($str, Normalizer::FORM_C)) {
                $str = Normalizer::normalize($str, Normalizer::FORM_C);
            }
        }

        $str = mb_convert_kana($str, 'KVas', 'UTF-8');
        if ($to_lower) {
            $str = mb_strtolower($str, 'UTF-8');
        }

        return preg_replace('/\s+/u', ' ', trim($str));
    }
}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
