<?php

return [
    'alipay' => [
        'protocol' => 'https',
        // 生产环境: openapi.alipay.com
        // 沙盒环境: openapi-sandbox.dl.alipaydev.com
        'gateway_host' => 'openapi.alipay.com',
        // 必填-支付宝分配的 app_id
        'app_id' => env('alipay.app_id', ''),
        // 必填-应用私钥 字符串或路径
        // 在 https://open.alipay.com/develop/manage 《应用详情->开发设置->接口加签方式》中设置
        'app_secret_cert' => file_get_contents(root_path() . 'cert/alipay/appSecretCert.txt'),
        // 必填-应用公钥证书 路径
        // 设置应用私钥后，即可下载得到以下3个证书
        'app_public_cert_path' => root_path() . 'cert/alipay/appPublicCert.crt',
        // 必填-支付宝公钥证书 路径
        'alipay_public_cert_path' => root_path() . 'cert/alipay/alipayPublicCert.crt',
        // 必填-支付宝根证书 路径
        'alipay_root_cert_path' => root_path() . 'cert/alipay/alipayRootCert.crt',
        'return_url' => '',
        'notify_url' => '',
    ],
    'wechat' => [
        // 应用appid
        'appid' => env('wechatpay.appid', ''),
        // 商户号
        'merchant_id' => env('wechatpay.mch_id', ''),
        // 「商户API证书」的「证书序列号」
        'merchant_certificate_serial' => env('wechatpay.mch_cert_serial', ''),
        // 微信支付api密钥
        'api_v3_key' => env('wechatpay.api_v3_key', ''),
        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        'merchant_private_key_file_path' => root_path() . 'cert/wechat/apiclient_key.pem',
        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名(不可修改)
        'platform_certificate_file_dir' => root_path() . 'cert/wechat/',
    ],
];