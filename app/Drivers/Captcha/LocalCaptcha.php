<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Captcha;

use App\Core\Session;
use App\Drivers\CaptchaDriver;
use App\Drivers\Result;

/**
 * 本地图形验证码。
 *
 * 直接输出 SVG，不依赖 GD / imagick / 任何外部服务；
 * 答案存会话、一次性、5 分钟有效，校验不区分大小写。
 */
class LocalCaptcha extends CaptchaDriver
{
    private const SESSION_KEY = '_captcha.local';
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function name(): string
    {
        return '本地图形验证码';
    }

    public function mode(): string
    {
        return 'image';
    }

    public function frontend(): array
    {
        return [
            'kind'   => 'image',
            'length' => $this->length(),
        ];
    }

    /** 生成新答案并写入会话，返回明文供出图使用 */
    public function issue(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < $this->length(); $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        Session::put(self::SESSION_KEY, [
            'code'    => strtolower($code),
            'expires' => time() + 300,
        ]);
        return $code;
    }

    /** 生成一张带干扰线与随机旋转的 SVG 图形验证码 */
    public function svg(string $code): string
    {
        $len = max(1, strlen($code));
        $w = 34 + 27 * $len;
        $h = 48;
        $out = [];

        $out[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">';
        $out[] = '<defs><linearGradient id="lbg" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="#eef2ff"/><stop offset="1" stop-color="#dbe7ff"/></linearGradient></defs>';
        $out[] = '<rect width="' . $w . '" height="' . $h . '" rx="8" fill="url(#lbg)"/>';

        // 干扰线：颜色与位置都随机，字符仍保持可读
        for ($i = 0; $i < 7; $i++) {
            $x1 = random_int(0, $w);
            $y1 = random_int(0, $h);
            $x2 = random_int(0, $w);
            $y2 = random_int(0, $h);
            $out[] = '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2
                . '" stroke="' . $this->color() . '" stroke-width="1" opacity="0.55"/>';
        }
        for ($i = 0; $i < 22; $i++) {
            $out[] = '<circle cx="' . random_int(0, $w) . '" cy="' . random_int(0, $h)
                . '" r="1" fill="' . $this->color() . '" opacity="0.5"/>';
        }

        // 字符：逐字随机倾斜、上下浮动与配色
        $step = ($w - 24) / $len;
        for ($i = 0; $i < $len; $i++) {
            $x = 12 + $step * $i + $step / 2;
            $y = 32 + random_int(-4, 4);
            $rot = random_int(-22, 22);
            $out[] = '<text x="' . (int) $x . '" y="' . $y . '" font-family="monospace" font-size="26" font-weight="700" '
                . 'text-anchor="middle" fill="' . $this->color(true) . '" '
                . 'transform="rotate(' . $rot . ' ' . (int) $x . ' ' . $y . ')">' . htmlspecialchars($code[$i], ENT_QUOTES, 'UTF-8') . '</text>';
        }
        $out[] = '</svg>';

        return implode('', $out);
    }

    public function verify(array $input): Result
    {
        $code = strtolower(trim((string) ($input['captcha_code'] ?? '')));
        if ($code === '') {
            return Result::fail('请填写图形验证码');
        }

        $entry = Session::get(self::SESSION_KEY);
        if (!is_array($entry) || (int) ($entry['expires'] ?? 0) < time()) {
            Session::forget(self::SESSION_KEY);
            return Result::fail('图形验证码已过期，请点击图片换一张');
        }

        // 一次性：无论对错都作废，防止对同一张图反复猜
        Session::forget(self::SESSION_KEY);
        if (!hash_equals((string) ($entry['code'] ?? ''), $code)) {
            return Result::fail('图形验证码不正确');
        }
        return Result::pass('验证通过');
    }

    private function length(): int
    {
        return max(3, min(8, $this->int('length', 4)));
    }

    private function color(bool $dark = false): string
    {
        $palette = $dark
            ? ['#1f3a8a', '#3730a3', '#0f766e', '#9d174d', '#7c2d12']
            : ['#93c5fd', '#a5b4fc', '#6ee7b7', '#fbcfe8', '#fed7aa'];
        return $palette[random_int(0, count($palette) - 1)];
    }
}
