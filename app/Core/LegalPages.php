<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

/**
 * 全站底部的三个说明页正文：关于我们 / 服务条款 / 隐私政策。
 *
 * 集中放这里，新安装的种子（SchemaExtra）和老站点升级（Migrator）共用同一份，
 * 改一次两边都生效。正文是给用户看的，所以用白话，不堆法律腔。
 */
final class LegalPages
{
    /** 老版本留下的占位正文，用来判断要不要升级成完整版 */
    private const PLACEHOLDERS = [
        'about'   => '<p>蜗牛图床提供稳定、快速、可扩展的图片托管服务。</p>',
        'terms'   => '<p>上传内容需遵守所在地法律法规，禁止上传违法与侵权内容。</p>',
        'privacy' => '<p>我们仅收集运行服务所必需的信息，不会向第三方出售用户数据。</p>',
    ];

    /** @return array{title:string,slug:string,content:string,sortable:int} */
    public static function definitions(): array
    {
        return [
            'about' => [
                'title' => '关于我们',
                'slug' => 'about',
                'sortable' => 10,
                'content' => self::about(),
            ],
            'terms' => [
                'title' => '服务条款',
                'slug' => 'terms',
                'sortable' => 20,
                'content' => self::terms(),
            ],
            'privacy' => [
                'title' => '隐私政策',
                'slug' => 'privacy',
                'sortable' => 30,
                'content' => self::privacy(),
            ],
        ];
    }

    public static function isPlaceholder(string $slug, string $content): bool
    {
        return trim($content) === trim(self::PLACEHOLDERS[$slug] ?? "\0");
    }

    private static function about(): string
    {
        return <<<'HTML'
<p>蜗牛图床是一个自己搭建的图片托管程序，装在你自己的服务器上，图片、数据库和账号都归你管。</p>
<h3>能做什么</h3>
<p>上传图片、按相册整理、生成外链、设置过期和访问密码，还能开图片广场、套餐商城和工单。后台能调用户组权限、存储策略和审核规则。</p>
<h3>关于数据</h3>
<p>程序不往作者服务器回传任何东西。你传的每张图、每个账号都只在你自己的站点里。作者没有副本，也帮不上忙恢复——所以请自己定期备份。</p>
<h3>开源与授权</h3>
<p>代码公开可查，允许自用、修改和商用。转载或修改请保留作者署名与官网地址。代码没有经过第三方安全审计，对外提供服务前请自行做好安全加固。</p>
HTML;
    }

    private static function terms(): string
    {
        return <<<'HTML'
<p>使用本站即表示你已阅读并同意以下条款。假如你不同意，请不要注册或上传。</p>
<h3>账号</h3>
<p>请保管好自己的账号密码。账号下的所有操作都算你本人的行为。发现账号被盗用，请及时联系站主。</p>
<h3>上传内容</h3>
<p>你对自己上传的内容负责。禁止上传违法、侵权、诈骗、色情、暴恐以及侵犯他人隐私的内容。站主有权对违规内容做下线、删除处理，并视情况停用相关账号。</p>
<h3>合理使用</h3>
<p>不要把本站当成无限制的存储盘或分发盗版资源的渠道。站主会根据服务器情况设定容量、单文件大小、每日和单次上传数量等限制，也可能因为滥用而调整配额。</p>
<h3>服务变更</h3>
<p>本站由站主自行维护，不承诺 7×24 小时不间断。站主有权因维护、升级或不可抗力暂时关闭部分功能，无需事先逐一通知。</p>
HTML;
    }

    private static function privacy(): string
    {
        return <<<'HTML'
<p>说明一下本站会用到哪些信息，以及这些信息放在哪。</p>
<h3>收集什么</h3>
<p>注册时收集用户名和密码；如果你填了邮箱，用于找回密码和接收通知。上传图片时会记录文件名、大小、时间、IP 和所属用户。使用第三方登录时，会从对方平台拿到你的昵称和唯一标识。</p>
<h3>存在哪里</h3>
<p>以上信息都存在本站自己的数据库和服务器磁盘上，不会卖给第三方，也不会主动传到作者或其它外部服务器。开启第三方登录、邮件或对象存储时，对应的数据会发送给这些服务的提供方，具体以它们各自的政策为准。</p>
<h3>公开范围</h3>
<p>你把图片设成公开后，拿到链接的人就能访问。设成私有或加访问密码的图片，只有你和被授权的分享链接能看。请谨慎设置公开范围。</p>
<h3>你的控制</h3>
<p>你可以在个人设置里改邮箱、密码、关闭 API，也可以删除自己的图片。要彻底注销账号，请联系站主。删除后数据无法恢复，请提前备份。</p>
HTML;
    }
}
