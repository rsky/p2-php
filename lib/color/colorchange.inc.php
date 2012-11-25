<?php
/**
 * 色変換サブルーチン
 *
 * 変換式参考資料 http: *image-d.isp.jp/commentary/color_cformula/index.html
 * Version.20081215 初版
 * Version.20081216 L*C*h表色系の変換関数を追加
 * Version.20081216.1 バグフィックス。
 *   HSV2RGB,HSL2RGB,LCh2RGB,RGB2LChの戻り値に変換前および変換途中のパラメータを追加。
 * Version.20081224 Lab2RGB,RGB2Lab追加
 * Version.20081226 16進でカラーコードを生成
 * Version.20120322 ColorChangeクラス作成
 */

function RGB2ColorCode($r, $g, $b)
{
    return ColorChange::rgb2ColorCode($r, $g, $b);
}

function HLS2RGB($hls)
{
    list($h, $l, $s) = colorchange_getargs($hls, 'H', 'L', 'S');
    return ColorChange::hls2rgb($h, $l, $s);
}

function HSV2RGB($hsv)
{
    list($h, $s, $v) = colorchange_getargs($hsv, 'H', 'S', 'V');
    return ColorChange::hsv2rgb($h, $s, $v);
}

function Lab2RGB($Lab)
{
    list($L, $a, $b) = colorchange_getargs($Lab, 'L*', 'a*', 'b*');
    return ColorChange::lab2rgb($L, $a, $b);
}

function LCh2RGB($LCh)
{
    list($L, $C, $h) = colorchange_getargs($LCh, 'L*', 'C*', 'h');
    return ColorChange::lch2rgb($L, $C, $h);
}

function RGB2Lab($rgb)
{
    list($r, $g, $b) = colorchange_getargs($rgb, 'R', 'G', 'B');
    return ColorChange::rgb2lab($r, $g, $b);
}

function RGB2LCh($rgb)
{
    list($r, $g, $b) = colorchange_getargs($rgb, 'R', 'G', 'B');
    return ColorChange::rgb2lch($r, $g, $b);
}

function RGB2XYZ($rgb)
{
    list($r, $g, $b) = colorchange_getargs($rgb, 'R', 'G', 'B');
    return ColorChange::rgb2xyz($r, $g, $b);
}

function XYZ2Lab($xyz)
{
    list($x, $y, $z) = colorchange_getargs($xyz, 'X', 'Y', 'Z');
    return ColorChange::xyz2lab($x, $y, $z);
}

function Lab2XYZ($Lab)
{
    list($L, $a, $b) = colorchange_getargs($Lab, 'L*', 'a*', 'b*');
    return ColorChange::lab2xyz($L, $a, $b);
}

function XYZ2RGB($xyz)
{
    list($x, $y, $z) = colorchange_getargs($xyz, 'X', 'Y', 'Z');
    return ColorChange::xyz2rgb($x, $y, $z);
}

function Lab2LCh($Lab)
{
    list($L, $a, $b) = colorchange_getargs($Lab, 'L*', 'a*', 'b*');
    return ColorChange::lab2lch($L, $a, $b);
}

function LCh2Lab($LCh)
{
    list($L, $C, $h) = colorchange_getargs($LCh, 'L*', 'C*', 'h');
    return ColorChange::lch2lab($L, $C, $h);
}

function colorchange_getargs(array $args, $k1, $k2, $k3)
{
    if (array_key_exists($k1, $args)) {
        return array($args[$k1], $args[$k2], $args[$k3]);
    }
    $values = array_values($args);
    return array($values[0], $values[1], $values[2]);
}

class ColorChange
{
    const COLORSPACE_RGB = 'RGB';
    const COLORSPACE_HLS = 'HLS';
    const COLORSPACE_HSV = 'HSV';
    const COLORSPACE_LAB = 'L*a*b*';
    const COLORSPACE_LCH = 'L*C*h';
    const COLORSPACE_XYZ = 'XYZ';

    public static function rgb2ColorCode($r, $g, $b)
    {
        return sprintf('#%02X%02X%02X',
                       max(0, min(255, (int)$r)),
                       max(0, min(255, (int)$g)),
                       max(0, min(255, (int)$b)));
    }

    /**
     *HLS→RGB変換
     */
    public static function hls2rgb($hls)
    {
        $args = func_get_args();
        list($h, $l, $s) = self::getArgs($args, 'H', 'L', 'S');

        $h %= 360;
        if ($h < 0) {
            $h += 360;
        }
        $l = max(0.0, min(1.0, $l));
        $s = max(0.0, min(1.0, $s));

        if ($s == 0.0) {
            $r = (int)floor($l * 255.0);
            $g = $r;
            $b = $r;
        } else {
            if ($l <= 0.5) {
                $max = $l * (1.0 + $s);
            } else {
                $max = $l * (1.0 - $s) + $s;
            }
            $min = 2.0 * $l - $max;
            $h_ary = array($h + 120, $h, $h - 120);
            $rgb = array();
            foreach ($h_ary as $hue) {
                if ($hue >= 360) {
                    $hue -= 360;
                } elseif ($hue<0) {
                    $hue += 360;
                }
                if ($hue <  60) {
                    $c  =  $min + ($max - $min) * $hue / 60;
                } elseif ($hue < 180) {
                    $c = $max;
                } elseif ($hue < 240) {
                    $c = $min + ($max - $min) * (240 - $hue) / 60;
                } else {
                    $c = $min;
                }
                $rgb[] = (int)floor($c * 255.0);
            }
            list($r, $g, $b) = $rgb;
        }

        return self::_packRgbChannels($r, $g, $b, $h, $l, $s, self::COLORSPACE_HLS);
    }

    /**
     * HSV→RGB変換
     */
    public static function hsv2rgb($h, $s, $v)
    {
        $h %= 360;
        if ($h < 0) {
            $h += 360;
        }
        $s = max(0.0, min(1.0, $s));
        $v = max(0.0, min(1.0, $v));

        $hi = intval(floor($h / 60.0)) % 6;
        $f = $h / 60 - $hi;
        $p = $v * (1.0 - $s);
        $q = $v * (1.0 - $f * $s);
        $t = $v * (1.0 - (1.0 - $f) * $s);

        switch ($hi) {
            case 0: $R = $v; $G = $t; $B = $p; break;
            case 1: $R = $q; $G = $v; $B = $p; break;
            case 2: $R = $p; $G = $v; $B = $t; break;
            case 3: $R = $p; $G = $q; $B = $v; break;
            case 4: $R = $t; $G = $p; $B = $v; break;
            case 5: $R = $v; $G = $p; $B = $q; break;
        }

        $r = (int)floor($R * 255.0);
        $g = (int)floor($G * 255.0);
        $b = (int)floor($B * 255.0);

        return self::_packRgbChannels($r, $g, $b, $h, $s, $v, self::COLORSPACE_HSV);
    }

    public static function lab2rgb($L, $a, $b)
    {
        list($x, $y, $z) = self::lab2xyz($L, $a, $b);
        list($R, $G, $B) = self::xyz2rgb($x, $y, $z);

        return self::_packRgbChannels($R, $G, $B, $L, $a, $b, self::COLORSPACE_LAB);
    }

    public static function lch2rgb($L, $C, $h)
    {
        list($l, $a, $b) = self::lch2lab($L, $C, $h);
        list($R, $G, $B) = self::lab2rgb($l, $a, $b);

        return self::_packRgbChannels($R, $G, $B, $L, $C, $h, self::COLORSPACE_LCH);
    }

    public static function rgb2lab($r, $g, $b)
    {
        list($x, $y, $z) = self::rgb2xyz($r, $g, $b);

        return self::xyz2lab($x, $y, $z);
    }

    public static function rgb2lch($r, $g, $b)
    {
        list($L, $A, $B) = self::rgb2lab($r, $g, $b);

        return self::lab2lch($L, $A, $B);
    }

    public static function rgb2xyz($r, $g, $b)
    {
        $linearRGB = array();
        foreach (array($r, $g, $b) as $c) {
            $c = max(0.0, min(1.0, $c / 255.0));
            if ($c <= 0.04045) {
                $c /= 12.92;
            } else {
                $c = pow(($c + 0.055) / (1.0 + 0.055), 2.4);
            }
            $linearRGB[] = $c;
        }
        list($r, $g, $b) = $linearRGB;

        $x = (0.412453 * $r) + (0.35758  * $g) + (0.180423 * $b);
        $y = (0.212671 * $r) + (0.71516  * $g) + (0.072169 * $b);
        $z = (0.019334 * $r) + (0.119193 * $g) + (0.950227 * $b);

        return self::_packChannels($x, $y, $z, self::COLORSPACE_XYZ);
    }

    public static function xyz2lab($x, $y, $z)
    {
        // D65光源補正
        $x /= 0.95045;
        $z /= 1.08892;

        $f = array();
        foreach (array($x, $y, $z) as $c) {
            $c = max(0.0, min(1.0, $c));
            if ($c > 0.008856) {
                $c = pow($c, 1.0 / 3.0);
            } else {
                $c = (903.3 * $c + 16.0) / 116.0;
            }
            $f[] = $c;
        }

        // L:[0..100],a:[-134..220],b:[-140..122]
        $L = 116.0 * $f[1] - 16.0;
        $a = 500.0 * (($f[0] / 0.95045) - $f[1]);
        $b = 200.0 * ($f[1] - ($f[2] / 1.08892));

        return self::_packChannels($L, $a, $b, self::COLORSPACE_LAB);
    }

    public static function lab2xyz($L, $a, $b)
    {
        //if ($Lab[0]>=100) { $fy=1; }
        if ($L < 7.9996) {
            $fy = $L / 903.3;
            $fx = $fy + $a / 3893.5;
            $fz = $fy - $b / 1557.4;
        } else {
            $fy = ($L + 16.0) / 116.0;
            $fx = $fy + $a / 500.0;
            $fz = $fy - $b / 200.0;
            $fx = pow($fx, 3.0);
            $fy = pow($fy, 3.0);
            $fz = pow($fz, 3.0);
        }

        // D65光源補正
        $fx *= 0.95045;
        $fz *= 1.08892;

        $xyz = array();
        foreach (array($fx, $fy, $fz) as $c) {
            $xyz[] = floor($c * 10000.0) / 10000.0;
        }
        list($x, $y, $z) = $xyz;

        return self::_packChannels($x, $y, $z, self::COLORSPACE_XYZ);
    }

    public static function xyz2rgb($x, $y, $z)
    {
        $x = max(0.0, min(1.0, $x));
        $y = max(0.0, min(1.0, $y));
        $z = max(0.0, min(1.0, $z));

        if ($y == 1.0) {
            $r = 1.0;
            $g = 1.0;
            $b = 1.0;
        } else {
            $r =   (3.240479 * $x) - (1.53715  * $y) - (0.498535 * $z);
            $g = - (0.969256 * $x) + (1.875991 * $y) + (0.041556 * $z);
            $b =   (0.055648 * $x) - (0.204043 * $y) + (1.057311 * $z);
        }

        $rgb = array();
        foreach (array($r,$g,$b) as $c) {
            if ($c <= 0.0031308) {
                $c *= 12.92;
            } else {
                $c = pow($c, 1.0 / 2.4) * (1.0 + 0.055) - 0.055;
            }
            $rgb[] = (int)floor($c * 255.0);
        }
        list($r, $g, $b) = $rgb;

        return self::_packRgbChannels($r, $g, $b, $x, $y, $z, self::COLORSPACE_XYZ);
    }

    public static function lab2lch($L, $a, $b)
    {
        $L = max(0.0, min(100.0, $L));
        $a = max(-100.0, min(100.0, $a));
        $b = max(-100.0, min(100.0, $b));

        $C = sqrt($a * $a + $b * $b);
        $h = rad2deg(atan2($b, $a));
        if ($h < 0) {
            $h += 360;
        };

        return self::_packChannels($L, $C, $h, self::COLORSPACE_LCH);
    }

    public static function lch2lab($L, $C, $h)
    {
        $h2 = deg2rad($h);
        $a = $C * cos($h2);
        $b = $C * sin($h2);

        return self::_packChannels($L, $a, $b, self::COLORSPACE_LAB);
    }

    protected static function _packChannels($c1, $c2, $c3, $type)
    {
        switch ($type) {
            case self::COLORSPACE_RGB:  $K1 = 'R';  $K2 = 'G';  $K3 = 'B';  break;
            case self::COLORSPACE_HLS:  $K1 = 'H';  $K2 = 'L';  $K3 = 'S';  break;
            case self::COLORSPACE_HSV:  $K1 = 'H';  $K2 = 'S';  $K3 = 'V';  break;
            case self::COLORSPACE_LAB:  $K1 = 'L*'; $K2 = 'a*'; $K3 = 'b*'; break;
            case self::COLORSPACE_LCH:  $K1 = 'L*'; $K2 = 'C*'; $K3 = 'h';  break;
            case self::COLORSPACE_XYZ:  $K1 = 'X';  $K2 = 'Y';  $K3 = 'Z';  break;
            default: throw new InvalidArgumentException("Unknown type: {$type}");
        }

        return array(
            $c1,
            $c2,
            $c3,
            $K1 => $c1,
            $K2 => $c2,
            $K3 => $c3,
        );
    }

    protected static function _packRgbChannels($r, $g, $b, $c1, $c2, $c3, $type)
    {
        switch ($type) {
          //case self::COLORSPACE_RGB:  $K1 = 'R';  $K2 = 'G';  $K3 = 'B';  break;
            case self::COLORSPACE_HLS:  $K1 = 'H';  $K2 = 'L';  $K3 = 'S';  break;
            case self::COLORSPACE_HSV:  $K1 = 'H';  $K2 = 'S';  $K3 = 'V';  break;
            case self::COLORSPACE_LAB:  $K1 = 'L*'; $K2 = 'a*'; $K3 = 'b*'; break;
            case self::COLORSPACE_LCH:  $K1 = 'L*'; $K2 = 'C*'; $K3 = 'h';  break;
            case self::COLORSPACE_XYZ:  $K1 = 'X';  $K2 = 'Y';  $K3 = 'Z';  break;
            default: throw new InvalidArgumentException("Unknown type: {$type}");
        }

        return array(
            $r,
            $g,
            $b,
            $c1,
            $c2,
            $c3,
            'R' => $r,
            'G' => $g,
            'B' => $b,
            $K1 => $c1,
            $K2 => $c2,
            $K3 => $c3,
            'type' => $type,
            'color' => self::rgb2ColorCode($r, $g, $b),
        );
    }
}
