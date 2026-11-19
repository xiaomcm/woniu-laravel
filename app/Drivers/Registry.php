<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

use App\Drivers\Cloud\TemplateUrl;
use App\Drivers\Mail\HttpApi;
use App\Drivers\Mail\Smtp;
use App\Drivers\Payment\Alipay;
use App\Drivers\Payment\Epay;
use App\Drivers\Payment\Paypal;
use App\Drivers\Payment\Stripe;
use App\Drivers\Payment\Unionpay;
use App\Drivers\Payment\WechatPay;
use App\Drivers\Security\AliyunGreen;
use App\Drivers\Security\BaiduCloudAudit;
use App\Drivers\Security\HttpModeration;
use App\Drivers\Security\LocalAudit;
use App\Drivers\Security\NsfwJs;
use App\Drivers\Security\TencentCloudAudit;
use App\Drivers\Sms\AliyunSms;
use App\Drivers\Sms\HuaweiCloudSms;
use App\Drivers\Sms\HttpSms;
use App\Drivers\Sms\JdCloudSms;
use App\Drivers\Sms\RonglianSms;
use App\Drivers\Sms\SubmailSms;
use App\Drivers\Sms\TencentSms;
use App\Drivers\Sms\TwilioSms;
use App\Drivers\Social\GenericOauth;
use App\Drivers\Social\Github;
use App\Drivers\Social\Qq;
use App\Drivers\Social\Wechat;
use App\Drivers\Social\Yaohuo;

class Registry
{
    public static function types(): array
    {
        return [
            'security' => ['label' => '图片安全', 'icon' => 'shield', 'desc' => '上传后自动审核，命中策略时进入待审或直接拒绝'],
            'social'   => ['label' => '社会化登录', 'icon' => 'user', 'desc' => '第三方账号一键登录，可自动建号'],
            'cloud'    => ['label' => '云处理', 'icon' => 'cloud', 'desc' => '图片样式化输出，缩放、裁剪、压缩、锐化'],
            'payment'  => ['label' => '支付', 'icon' => 'card', 'desc' => '套餐、扩容订单的收款通道'],
            'mail'     => ['label' => '邮件', 'icon' => 'mail', 'desc' => '验证码、工单回复、订单通知'],
            'sms'      => ['label' => '短信', 'icon' => 'phone', 'desc' => '手机号注册与登录验证码'],
        ];
    }

    public static function all(): array
    {
        return [
            'security' => [
                'local_audit' => [
                    'name'   => '本地敏感词',
                    'class'  => LocalAudit::class,
                    'desc'   => '按文件名、描述命中词与图片尺寸做本地判定，不依赖外网',
                    'fields' => [
                        ['key' => 'words', 'label' => '敏感词', 'type' => 'textarea', 'hint' => '每行一个或逗号分隔，命中文件名即拦截'],
                        ['key' => 'block_ext', 'label' => '禁止扩展名', 'type' => 'text', 'default' => 'php,phtml,html,htm,js,exe,sh,bat', 'hint' => '逗号分隔'],
                        ['key' => 'min_width', 'label' => '最小宽度', 'type' => 'number', 'default' => '0', 'hint' => '0 为不限'],
                        ['key' => 'min_height', 'label' => '最小高度', 'type' => 'number', 'default' => '0'],
                        ['key' => 'max_ratio', 'label' => '最大宽高比', 'type' => 'text', 'default' => '0', 'hint' => '如 5 表示宽高比不超过 5:1，0 为不限'],
                    ],
                ],
                'aliyun_green' => [
                    'name'   => '阿里云内容安全',
                    'class'  => AliyunGreen::class,
                    'desc'   => '调用 green-cip 图片审核，返回标签与置信度',
                    'fields' => [
                        ['key' => 'access_key_id', 'label' => 'AccessKey ID', 'type' => 'text', 'required' => true],
                        ['key' => 'access_key_secret', 'label' => 'AccessKey Secret', 'type' => 'password', 'required' => true],
                        ['key' => 'endpoint', 'label' => '接入地址', 'type' => 'text', 'default' => 'green-cip.cn-shanghai.aliyuncs.com'],
                        ['key' => 'region', 'label' => '地域', 'type' => 'text', 'default' => 'cn-shanghai'],
                        ['key' => 'service', 'label' => '检测场景', 'type' => 'text', 'default' => 'baselineCheck'],
                        ['key' => 'block_labels', 'label' => '拦截标签', 'type' => 'text', 'default' => 'porn,sexy,terrorism,politics,contraband', 'hint' => '逗号分隔，命中即不通过'],
                    ],
                ],
                'http_moderation' => [
                    'name'   => '自建审核接口',
                    'class'  => HttpModeration::class,
                    'desc'   => 'POST 图片地址到你的审核服务，返回 JSON 判定',
                    'fields' => [
                        ['key' => 'endpoint', 'label' => '接口地址', 'type' => 'text', 'required' => true, 'placeholder' => 'https://mod.example.com/check'],
                        ['key' => 'token', 'label' => '鉴权 Token', 'type' => 'password', 'hint' => '放在 X-Token 请求头'],
                        ['key' => 'pass_field', 'label' => '结果字段', 'type' => 'text', 'default' => 'pass', 'hint' => '约定返回 {"pass":true,"label":""}'],
                        ['key' => 'timeout', 'label' => '超时秒数', 'type' => 'number', 'default' => '5'],
                    ],
                ],
                'tencent_ims' => [
                    'name'   => '腾讯云图片审核',
                    'class'  => TencentCloudAudit::class,
                    'desc'   => '腾讯云图片内容安全（IMS），TC3-HMAC-SHA256 签名，命中标签拦截',
                    'fields' => [
                        ['key' => 'secret_id', 'label' => 'SecretId', 'type' => 'text', 'required' => true],
                        ['key' => 'secret_key', 'label' => 'SecretKey', 'type' => 'password', 'required' => true],
                        ['key' => 'endpoint', 'label' => '接入地址', 'type' => 'text', 'default' => 'ims.tencentcloudapi.com'],
                        ['key' => 'region', 'label' => '地域', 'type' => 'text', 'default' => 'ap-guangzhou'],
                        ['key' => 'biz_type', 'label' => '策略 ID', 'type' => 'text', 'hint' => '留空使用默认策略'],
                        ['key' => 'block_labels', 'label' => '拦截标签', 'type' => 'text', 'default' => 'Porn,Sexy,Terrorism,Politics,Abuse', 'hint' => '逗号分隔，命中即不通过'],
                    ],
                ],
                'baidu_censor' => [
                    'name'   => '百度内容审核',
                    'class'  => BaiduCloudAudit::class,
                    'desc'   => '百度 AI 图像审核（图像色情/暴恐/政治敏感），OAuth 取 token',
                    'fields' => [
                        ['key' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true],
                        ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
                        ['key' => 'threshold', 'label' => '嫌疑阈值', 'type' => 'number', 'default' => '0.9', 'hint' => '置信度高于该值判为嫌疑'],
                        ['key' => 'block_labels', 'label' => '拦截类型', 'type' => 'text', 'default' => 'porn,terror,politics', 'hint' => '逗号分隔'],
                    ],
                ],
                'nsfwjs' => [
                    'name'   => 'NSFW.js 本地审核',
                    'class'  => NsfwJs::class,
                    'desc'   => '对接自建 NSFW.js 服务，图片色情/性感/变态分类，零外网依赖',
                    'fields' => [
                        ['key' => 'endpoint', 'label' => '服务地址', 'type' => 'text', 'required' => true, 'placeholder' => 'http://127.0.0.1:8080/classify'],
                        ['key' => 'threshold', 'label' => '拦截阈值', 'type' => 'number', 'default' => '0.7', 'hint' => 'Porn/Hentai/Sexy 任一类高于该值即不通过'],
                        ['key' => 'block_classes', 'label' => '拦截类别', 'type' => 'text', 'default' => 'Porn,Hentai,Sexy', 'hint' => '逗号分隔'],
                        ['key' => 'timeout', 'label' => '超时秒数', 'type' => 'number', 'default' => '8'],
                    ],
                ],
            ],
            'social' => [
                'qq' => [
                    'name'   => 'QQ 互联',
                    'class'  => Qq::class,
                    'desc'   => 'QQ 登录，需在 connect.qq.com 配置回调',
                    'fields' => [
                        ['key' => 'app_id', 'label' => 'APP ID', 'type' => 'text', 'required' => true],
                        ['key' => 'app_key', 'label' => 'APP Key', 'type' => 'password', 'required' => true],
                    ],
                ],
                'wechat' => [
                    'name'   => '微信开放平台',
                    'class'  => Wechat::class,
                    'desc'   => '微信扫码登录，需开放平台网站应用',
                    'fields' => [
                        ['key' => 'app_id', 'label' => 'AppID', 'type' => 'text', 'required' => true],
                        ['key' => 'app_secret', 'label' => 'AppSecret', 'type' => 'password', 'required' => true],
                    ],
                ],
                'github' => [
                    'name'   => 'GitHub',
                    'class'  => Github::class,
                    'desc'   => 'GitHub OAuth 登录',
                    'fields' => [
                        ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                        ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                    ],
                ],
                'yaohuo' => [
                    'name'   => '妖火账号',
                    'class'  => Yaohuo::class,
                    'desc'   => '妖火账号 OAuth2 登录，端点与字段已配好，只需填 Client ID / Secret',
                    'fields' => [
                        ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                        ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                    ],
                ],
                'oauth2' => [
                    'name'   => '通用 OAuth2',
                    'class'  => GenericOauth::class,
                    'desc'   => '自定义授权地址与字段映射，适配自建或小众平台',
                    'fields' => [
                        ['key' => 'authorize_url', 'label' => '授权地址', 'type' => 'text', 'required' => true],
                        ['key' => 'token_url', 'label' => '换取令牌地址', 'type' => 'text', 'required' => true],
                        ['key' => 'userinfo_url', 'label' => '用户信息地址', 'type' => 'text', 'required' => true],
                        ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                        ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                        ['key' => 'scope', 'label' => 'Scope', 'type' => 'text', 'default' => 'user:email'],
                        ['key' => 'id_field', 'label' => 'ID 字段', 'type' => 'text', 'default' => 'id'],
                        ['key' => 'name_field', 'label' => '昵称字段', 'type' => 'text', 'default' => 'name'],
                        ['key' => 'avatar_field', 'label' => '头像字段', 'type' => 'text', 'default' => 'avatar'],
                        ['key' => 'email_field', 'label' => '邮箱字段', 'type' => 'text', 'default' => 'email'],
                    ],
                ],
            ],
            'cloud' => [
                'aliyun_oss_style' => [
                    'name'   => '阿里云 OSS 图片样式',
                    'class'  => TemplateUrl::class,
                    'desc'   => '通过 x-oss-process 生成派生 URL，不占本地算力',
                    'fields' => [
                        ['key' => 'template', 'label' => 'URL 模板', 'type' => 'text', 'default' => '{url}?x-oss-process=image/resize,w_{width},limit_0/quality,q_{quality}', 'hint' => '可用变量 {url} {width} {height} {quality} {format} {blur}'],
                    ],
                ],
                'tencent_ci' => [
                    'name'   => '腾讯云数据万象',
                    'class'  => TemplateUrl::class,
                    'desc'   => 'imageMogr2 样式串',
                    'fields' => [
                        ['key' => 'template', 'label' => 'URL 模板', 'type' => 'text', 'default' => '{url}?imageMogr2/thumbnail/{width}x/quality/{quality}/strip', 'hint' => '变量同上'],
                    ],
                ],
                'template_url' => [
                    'name'   => '自定义 URL 模板',
                    'class'  => TemplateUrl::class,
                    'desc'   => '任意 CDN 或图片服务的裁剪协议',
                    'fields' => [
                        ['key' => 'template', 'label' => 'URL 模板', 'type' => 'text', 'default' => '{url}?w={width}&q={quality}&f={format}', 'hint' => '变量同上'],
                    ],
                ],
            ],
            'payment' => [
                'alipay' => [
                    'name'   => '支付宝',
                    'class'  => Alipay::class,
                    'desc'   => '电脑网站支付，RSA2 签名，异步通知验签',
                    'fields' => [
                        ['key' => 'app_id', 'label' => 'APPID', 'type' => 'text', 'required' => true],
                        ['key' => 'private_key', 'label' => '应用私钥', 'type' => 'textarea', 'required' => true, 'hint' => 'PKCS8 未加密私钥，不含头尾'],
                        ['key' => 'public_key', 'label' => '支付宝公钥', 'type' => 'textarea', 'required' => true],
                        ['key' => 'gateway', 'label' => '网关', 'type' => 'text', 'default' => 'https://openapi.alipay.com/gateway.do'],
                        ['key' => 'notify_url', 'label' => '异步通知地址', 'type' => 'text', 'hint' => '留空自动使用系统地址'],
                    ],
                ],
                'wechat_pay' => [
                    'name'   => '微信支付',
                    'class'  => WechatPay::class,
                    'desc'   => 'APIv3 Native 扫码，回调 AES-256-GCM 解密',
                    'fields' => [
                        ['key' => 'app_id', 'label' => 'AppID', 'type' => 'text', 'required' => true],
                        ['key' => 'mch_id', 'label' => '商户号', 'type' => 'text', 'required' => true],
                        ['key' => 'cert_serial_no', 'label' => '证书序列号', 'type' => 'text', 'required' => true],
                        ['key' => 'private_key', 'label' => '商户私钥', 'type' => 'textarea', 'required' => true, 'hint' => 'apiclient_key.pem 内容'],
                        ['key' => 'api_v3_key', 'label' => 'APIv3 密钥', 'type' => 'password', 'required' => true],
                        ['key' => 'notify_url', 'label' => '回调地址', 'type' => 'text'],
                    ],
                ],
                'epay' => [
                    'name'   => '彩虹易支付',
                    'class'  => Epay::class,
                    'desc'   => '免签约聚合支付，MD5 签名，支持支付宝与微信',
                    'fields' => [
                        ['key' => 'api_url', 'label' => '接口地址', 'type' => 'text', 'required' => true, 'placeholder' => 'https://pay.example.com'],
                        ['key' => 'pid', 'label' => '商户 ID', 'type' => 'text', 'required' => true],
                        ['key' => 'key', 'label' => '商户密钥', 'type' => 'password', 'required' => true],
                        ['key' => 'pay_type', 'label' => '支付方式', 'type' => 'select', 'default' => 'alipay', 'options' => ['alipay' => '支付宝', 'wxpay' => '微信支付']],
                    ],
                ],
                'paypal' => [
                    'name'   => 'PayPal',
                    'class'  => Paypal::class,
                    'desc'   => 'PayPal Checkout（v2/orders），国际信用卡/账户，Webhook 签名回调',
                    'fields' => [
                        ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                        ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                        ['key' => 'currency', 'label' => '结算货币', 'type' => 'text', 'default' => 'USD', 'hint' => '如 USD / EUR / JPY'],
                        ['key' => 'webhook_id', 'label' => 'Webhook ID', 'type' => 'text', 'hint' => 'PayPal 后台创建的 Webhook 标识'],
                        ['key' => 'sandbox', 'label' => '沙箱模式', 'type' => 'select', 'default' => '0', 'options' => ['0' => '正式', '1' => '沙箱']],
                    ],
                ],
                'stripe' => [
                    'name'   => 'Stripe',
                    'class'  => Stripe::class,
                    'desc'   => 'Stripe Checkout 会话支付，支持全球信用卡，Webhook HMAC 验签',
                    'fields' => [
                        ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
                        ['key' => 'currency', 'label' => '结算货币', 'type' => 'text', 'default' => 'usd', 'hint' => '小写，如 usd / eur / gbp'],
                        ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => true, 'hint' => 'whsec_ 开头，用于回调验签'],
                    ],
                ],
                'unionpay' => [
                    'name'   => '银联',
                    'class'  => Unionpay::class,
                    'desc'   => '银联全渠道网关支付（5.1.0，RSA 签名），人民币结算',
                    'fields' => [
                        ['key' => 'mer_id', 'label' => '商户代码', 'type' => 'text', 'required' => true],
                        ['key' => 'cert_id', 'label' => '证书序列号', 'type' => 'text', 'required' => true],
                        ['key' => 'private_key', 'label' => '商户私钥', 'type' => 'textarea', 'required' => true, 'hint' => 'PEM 格式，不含密码可留空密码项'],
                        ['key' => 'key_pass', 'label' => '私钥密码', 'type' => 'password', 'hint' => '导出为加密 PEM 时填写'],
                        ['key' => 'unionpay_pub', 'label' => '银联公钥', 'type' => 'textarea', 'required' => true, 'hint' => '用于回调验签'],
                        ['key' => 'front_url', 'label' => '前台通知', 'type' => 'text', 'hint' => '留空使用系统返回页'],
                        ['key' => 'test_mode', 'label' => '测试环境', 'type' => 'select', 'default' => '0', 'options' => ['0' => '生产', '1' => '测试']],
                    ],
                ],
            ],
            'mail' => [
                'smtp' => [
                    'name'   => 'SMTP 发信',
                    'class'  => Smtp::class,
                    'desc'   => '原生 socket 实现，支持 SSL 与 STARTTLS',
                    'fields' => [
                        ['key' => 'host', 'label' => '服务器', 'type' => 'text', 'required' => true, 'placeholder' => 'smtp.qq.com'],
                        ['key' => 'port', 'label' => '端口', 'type' => 'number', 'default' => '465'],
                        ['key' => 'encryption', 'label' => '加密方式', 'type' => 'select', 'default' => 'ssl', 'options' => ['ssl' => 'SSL', 'tls' => 'STARTTLS', 'none' => '不加密']],
                        ['key' => 'user', 'label' => '账号', 'type' => 'text', 'required' => true],
                        ['key' => 'pass', 'label' => '密码', 'type' => 'password', 'required' => true, 'hint' => 'QQ 邮箱需用授权码'],
                        ['key' => 'from', 'label' => '发件地址', 'type' => 'text', 'required' => true],
                        ['key' => 'from_name', 'label' => '发件人名称', 'type' => 'text', 'default' => '蜗牛图床'],
                    ],
                ],
                'http_api' => [
                    'name'   => '自建发信接口',
                    'class'  => HttpApi::class,
                    'desc'   => 'POST 到你的邮件服务，Body 为 JSON',
                    'fields' => [
                        ['key' => 'endpoint', 'label' => '接口地址', 'type' => 'text', 'required' => true],
                        ['key' => 'token', 'label' => '鉴权 Token', 'type' => 'password'],
                        ['key' => 'from', 'label' => '发件地址', 'type' => 'text'],
                    ],
                ],
            ],
            'sms' => [
                'aliyun_sms' => [
                    'name'   => '阿里云短信',
                    'class'  => AliyunSms::class,
                    'desc'   => 'SendSms，RPC 签名 HMAC-SHA1',
                    'fields' => [
                        ['key' => 'access_key_id', 'label' => 'AccessKey ID', 'type' => 'text', 'required' => true],
                        ['key' => 'access_key_secret', 'label' => 'AccessKey Secret', 'type' => 'password', 'required' => true],
                        ['key' => 'sign_name', 'label' => '短信签名', 'type' => 'text', 'required' => true],
                        ['key' => 'template_code', 'label' => '模板 CODE', 'type' => 'text', 'required' => true, 'hint' => '模板变量名需为 code'],
                        ['key' => 'region', 'label' => '地域', 'type' => 'text', 'default' => 'cn-hangzhou'],
                    ],
                ],
                'tencent_sms' => [
                    'name'   => '腾讯云短信',
                    'class'  => TencentSms::class,
                    'desc'   => 'TC3 签名，SendSms',
                    'fields' => [
                        ['key' => 'secret_id', 'label' => 'SecretId', 'type' => 'text', 'required' => true],
                        ['key' => 'secret_key', 'label' => 'SecretKey', 'type' => 'password', 'required' => true],
                        ['key' => 'sdk_app_id', 'label' => 'SDKAppID', 'type' => 'text', 'required' => true],
                        ['key' => 'sign_name', 'label' => '签名内容', 'type' => 'text', 'required' => true],
                        ['key' => 'template_id', 'label' => '模板 ID', 'type' => 'text', 'required' => true],
                        ['key' => 'region', 'label' => '地域', 'type' => 'text', 'default' => 'ap-guangzhou'],
                    ],
                ],
                'http_sms' => [
                    'name'   => '自建短信接口',
                    'class'  => HttpSms::class,
                    'desc'   => 'POST 到你的短信网关',
                    'fields' => [
                        ['key' => 'endpoint', 'label' => '接口地址', 'type' => 'text', 'required' => true],
                        ['key' => 'token', 'label' => '鉴权 Token', 'type' => 'password'],
                        ['key' => 'template', 'label' => '模板标识', 'type' => 'text'],
                    ],
                ],
                'huawei_sms' => [
                    'name'   => '华为云短信',
                    'class'  => HuaweiCloudSms::class,
                    'desc'   => '华为云批量发送，AK/SK 签名，支持海外',
                    'fields' => [
                        ['key' => 'access_key_id', 'label' => 'AccessKey ID', 'type' => 'text', 'required' => true],
                        ['key' => 'access_key_secret', 'label' => 'AccessKey Secret', 'type' => 'password', 'required' => true],
                        ['key' => 'endpoint', 'label' => '接入点', 'type' => 'text', 'default' => 'https://smsapi.cn-north-4.myhuaweicloud.com'],
                        ['key' => 'sender', 'label' => '签名/通道号', 'type' => 'text', 'required' => true],
                        ['key' => 'sign_name', 'label' => '签名名称', 'type' => 'text', 'hint' => '留空则复用签名/通道号'],
                        ['key' => 'template_id', 'label' => '模板 ID', 'type' => 'text', 'required' => true],
                        ['key' => 'phone_prefix', 'label' => '号码前缀', 'type' => 'text', 'default' => '+86'],
                    ],
                ],
                'jd_sms' => [
                    'name'   => '京东云短信',
                    'class'  => JdCloudSms::class,
                    'desc'   => '京东云 sendBatchSMS，JDCLOUD2 签名',
                    'fields' => [
                        ['key' => 'access_key_id', 'label' => 'AccessKey ID', 'type' => 'text', 'required' => true],
                        ['key' => 'access_key_secret', 'label' => 'AccessKey Secret', 'type' => 'password', 'required' => true],
                        ['key' => 'region', 'label' => '地域', 'type' => 'text', 'default' => 'cn-north-1'],
                        ['key' => 'sign_id', 'label' => '签名 ID', 'type' => 'text', 'required' => true],
                        ['key' => 'template_id', 'label' => '模板 ID', 'type' => 'text', 'required' => true],
                    ],
                ],
                'ronglian_sms' => [
                    'name'   => '容联云通讯',
                    'class'  => RonglianSms::class,
                    'desc'   => 'Cloopen 模板短信，MD5 sig 鉴权',
                    'fields' => [
                        ['key' => 'account_sid', 'label' => 'AccountSid', 'type' => 'text', 'required' => true],
                        ['key' => 'auth_token', 'label' => 'AuthToken', 'type' => 'password', 'required' => true],
                        ['key' => 'app_id', 'label' => 'AppId', 'type' => 'text', 'required' => true],
                        ['key' => 'template_id', 'label' => '模板 ID', 'type' => 'text', 'required' => true],
                        ['key' => 'endpoint', 'label' => '接口地址', 'type' => 'text', 'default' => 'https://app.cloopen.com:8883'],
                        ['key' => 'expire_minutes', 'label' => '验证码有效期', 'type' => 'number', 'default' => '5'],
                    ],
                ],
                'twilio_sms' => [
                    'name'   => 'Twilio',
                    'class'  => TwilioSms::class,
                    'desc'   => 'Twilio Messages API，国际短信首选',
                    'fields' => [
                        ['key' => 'account_sid', 'label' => 'Account SID', 'type' => 'text', 'required' => true],
                        ['key' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password', 'required' => true],
                        ['key' => 'from_number', 'label' => '发送号码', 'type' => 'text', 'required' => true, 'hint' => 'E.164，如 +1415xxxx'],
                        ['key' => 'message', 'label' => '文案模板', 'type' => 'text', 'default' => 'Your verification code is {code}'],
                    ],
                ],
                'submail_sms' => [
                    'name'   => 'Submail 赛邮',
                    'class'  => SubmailSms::class,
                    'desc'   => 'Submail message/send，MD5 签名',
                    'fields' => [
                        ['key' => 'appid', 'label' => 'APPID', 'type' => 'text', 'required' => true],
                        ['key' => 'appkey', 'label' => 'APPKEY', 'type' => 'password', 'required' => true],
                        ['key' => 'content', 'label' => '文案模板', 'type' => 'text', 'default' => '您的验证码是 {code}'],
                        ['key' => 'endpoint', 'label' => '接口地址', 'type' => 'text', 'default' => 'https://api.mysubmail.com/message/send.json'],
                    ],
                ],
            ],
        ];
    }

    public static function providers(string $type): array
    {
        return self::all()[$type] ?? [];
    }

    public static function provider(string $type, string $key): ?array
    {
        return self::all()[$type][$key] ?? null;
    }

    public static function label(string $type, string $key): string
    {
        return (string) (self::provider($type, $key)['name'] ?? $key);
    }

    public static function fields(string $type, string $key): array
    {
        return (array) (self::provider($type, $key)['fields'] ?? []);
    }
}
