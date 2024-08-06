<?php

namespace jszsl\pay;

use WeChatPay\Crypto\Rsa;

class WechatPlatformCertManager
{

    public static function getPlatformCertList($serial = null, $content = null)
    {
        $config = config('pay.wechat');

        if (!is_null($serial) && !is_null($content) && $serial !== '' && $content !== '') {
            self ::writeNewCertFile($serial, $content, $config);
        }

        $cert_files = self ::fetchAndProcessCertFiles($config);

        return $cert_files;
    }

    public static function deleteOldCertFiles($config)
    {
        $cert_files = glob($config['platform_certificate_file_dir'] . 'wechatpay_*.pem');
        foreach ($cert_files as $file) {
            if (is_file($file)) { // 检查是否为文件
                unlink($file);
            }
        }
    }

    private static function writeNewCertFile($serial, $content, $config)
    {
        $file_path = $config['platform_certificate_file_dir'] . 'wechatpay_' . $serial . '.pem';
        if (file_put_contents($file_path, $content) === false) {
            // 处理写入错误
            throw new \Exception("Failed to write new certificate file for serial: $serial");
        }
    }

    private static function fetchAndProcessCertFiles($config)
    {
        $cert_files = glob($config['platform_certificate_file_dir'] . 'wechatpay_*.pem');
        if (empty($cert_files)) {
            // 处理无证书文件的情况
            return [];
        }

        return array_combine(array_map(function ($file) {
            return self ::extractSerialFromFilename($file);
        }, $cert_files), array_map(function ($file) {
            return Rsa ::from(file_get_contents($file), Rsa::KEY_TYPE_PUBLIC);
        }, $cert_files));
    }

    private static function extractSerialFromFilename($file)
    {
        return basename(str_replace(['wechatpay_', '.pem'], '', $file));
    }
}