<?php

namespace jszsl\pay;

use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Config;


class AlipayClient
{
    /**
     * @支付宝扫码支付
     *
     * @param $subject :主题
     * @param $outTradeNo :订单号
     * @param $totalAmount :付款金额
     */
    public static function scanPay($param)
    {
        //1. 设置参数（全局只需设置一次）
        Factory ::setOptions(self ::getOptions());
        try {
            //2. 发起API调用（以支付能力下的统一收单交易创建接口为例）
            $result = Factory ::payment() -> FaceToFace()
                -> asyncNotify($param['notify_url'] ?? '')
                -> precreate($param['subject'], $param['outTradeNo'], $param['totalAmount']);
            //3. 处理响应或异常
            if (!empty($result -> code) && $result -> code == 10000) {
                $body = json_decode($result -> httpBody, true);
                return $body['alipay_trade_precreate_response'];
            } else {
                return false;
            }
        } catch (\Exception $e) {
            // echo "调用失败，" . $e -> getMessage() . PHP_EOL;;
            return false;
        }

    }

    public static function verifyNotify($param)
    {
        Factory ::setOptions(self ::getOptions());
        $result = Factory ::payment() -> common() -> verifyNotify($param);
        if (!$result || $param['trade_status'] != 'TRADE_SUCCESS') {
            return false;
        }
        return true;
    }

    private static function getOptions()
    {
        $config = config('pay.alipay');

        $options = new Config();
        $options -> protocol = $config['protocol'] ?? 'https';
        $options -> gatewayHost = $config['gateway_host'] ?? 'openapi.alipay.com';
        $options -> signType = 'RSA2';

        $options -> appId = $config['app_id'] ?? '';

        // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
        $options -> merchantPrivateKey = $config['app_secret_cert'] ?? '';
        $options -> merchantCertPath = $config['app_public_cert_path'] ?? '';
        $options -> alipayCertPath = $config['alipay_public_cert_path'] ?? '';
        $options -> alipayRootCertPath = $config['alipay_root_cert_path'] ?? '';

        //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
        // $options->alipayPublicKey = '<-- 请填写您的支付宝公钥，例如：MIIBIjANBg... -->';

        //可设置异步通知接收服务地址（可选）
        //如果需要使用文件上传接口，请不要设置该参数
        $options -> notifyUrl = $config['notify_url'] ?? '';

        //可设置AES密钥，调用AES加解密相关接口时需要（可选）
        $options -> encryptKey = "";

        return $options;
    }


}